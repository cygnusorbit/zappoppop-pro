<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pro Bot Protection & Geo-Targeting: email policy, Google reCAPTCHA, per-IP rate limit and country targeting.
 *
 * Values are stored in core's zappoppop_settings option under the keys core always used,
 * so settings made before this tab moved to Pro keep working.
 */
class ZapPopPop_Pro_Protection {

    /** Option key => default. */
    public static function defaults_map() {
        return array(
            'email_validation'       => 'basic',
            'enable_recaptcha'       => 0,
            'recaptcha_site_key'     => '',
            'recaptcha_secret_key'   => '',
            'recaptcha_threshold'    => 0.4,
            'max_submissions_per_ip' => 10,
            'ip_geotargeting'        => 0,
            'geoip_service'          => 'none',
            'geoip_api_service'      => 'none',
        );
    }

    public static function hooks() {
        add_filter('zappoppop_factory_defaults', array(__CLASS__, 'factory_defaults'));
        add_filter('zappoppop_sanitize_global_settings', array(__CLASS__, 'sanitize'), 10, 2);
        add_filter('zappoppop_settings_tab_keys', array(__CLASS__, 'tab_keys'));
        add_action('zappoppop_settings_tab_buttons', array(__CLASS__, 'tab_button'));
        add_action('zappoppop_settings_tab_panels', array(__CLASS__, 'tab_panel'));
        add_action('zappoppop_front_enqueue', array(__CLASS__, 'enqueue'));
        add_filter('zappoppop_front_vars', array(__CLASS__, 'front_vars'));
        add_filter('zappoppop_submission_gate', array(__CLASS__, 'gate'), 10, 3);
        add_filter('zappoppop_disposable_domains', array(__CLASS__, 'extend_domains'));
        add_filter('zappoppop_popup_eligible', array(__CLASS__, 'eligible'), 10, 3);
    }

    public static function unlocked() {
        return ZapPopPop_Pro_License::is_unlocked();
    }

    /* ------------------------------------------------------------- settings data */

    public static function factory_defaults($defaults) {
        return (array) $defaults + self::defaults_map();
    }

    /** A key posted from the settings form wins; otherwise the value core already kept is retained. */
    public static function sanitize($o, $in) {
        $in  = (array) $in;
        $def = self::defaults_map();
        $has = function ($key) use ($in) { return array_key_exists($key, $in); };

        if ($has('email_validation')) {
            $o['email_validation'] = in_array($in['email_validation'], array('basic', 'disposable_filter'), true) ? $in['email_validation'] : 'basic';
        }
        foreach (array('enable_recaptcha', 'ip_geotargeting') as $key) {
            if ($has($key)) {
                $o[$key] = empty($in[$key]) ? 0 : 1;
            }
        }
        foreach (array('recaptcha_site_key', 'recaptcha_secret_key') as $key) {
            if ($has($key)) {
                $o[$key] = sanitize_text_field((string) $in[$key]);
            }
        }
        if ($has('recaptcha_threshold')) {
            $thr = is_numeric($in['recaptcha_threshold']) ? (float) $in['recaptcha_threshold'] : 0.4;
            $o['recaptcha_threshold'] = round(min(0.9, max(0.1, $thr)), 2);
        }
        if ($has('max_submissions_per_ip')) {
            $o['max_submissions_per_ip'] = min(100, max(1, (int) $in['max_submissions_per_ip']));
        }
        if ($has('geoip_service') || $has('geoip_api_service')) {
            $geo = !empty($in['geoip_service']) ? $in['geoip_service'] : (!empty($in['geoip_api_service']) ? $in['geoip_api_service'] : 'none');
            $o['geoip_service'] = in_array($geo, array('none', 'ipapi', 'ip-api'), true) ? $geo : 'none';
            $o['geoip_api_service'] = $o['geoip_service'];
        }
        foreach ($def as $key => $value) {
            if (!isset($o[$key])) {
                $o[$key] = $value;
            }
        }
        return $o;
    }

    public static function tab_keys($map) {
        $map = (array) $map;
        $map['security'] = array_keys(self::defaults_map());
        return $map;
    }

    /* ------------------------------------------------------------------ admin UI */

    public static function tab_button() {
        echo '<button type="button" class="zappoppop-tab-btn zpp-theme-tab" data-tab="security">' . esc_html__('Security & Geolocation', 'zappoppop-pro') . '</button>' . "\n";
    }

    public static function tab_panel($settings) {
        $s = is_array($settings) ? $settings : array();
        echo '<div id="tab-security" class="zappoppop-tab-content" data-tab-content="security" style="display:none;">';
        include ZAPPOPPOP_PRO_PATH . 'templates/settings-security.php';
        echo '</div>' . "\n";
    }

    /* ------------------------------------------------------------------ frontend */

    protected static function recaptcha_ready() {
        return self::unlocked() && zappoppop_get_setting('enable_recaptcha', 0) && zappoppop_get_setting('recaptcha_site_key', '') !== '';
    }

    public static function enqueue() {
        if (!self::recaptcha_ready()) {
            return;
        }
        wp_enqueue_script(
            'zappoppop-recaptcha-api',
            'https://www.google.com/recaptcha/api.js?render=' . rawurlencode((string) zappoppop_get_setting('recaptcha_site_key', '')),
            array(),
            // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- external Google API script; a ver query string is not wanted on that URL
            null,
            true
        );
    }

    public static function front_vars($vars) {
        $vars = (array) $vars;
        $vars['enable_recaptcha']   = self::recaptcha_ready();
        $vars['recaptcha_site_key'] = esc_attr((string) zappoppop_get_setting('recaptcha_site_key', ''));
        return $vars;
    }

    /* --------------------------------------------------------- submission checks */

    /** Returns an error message to block the lead, or the incoming value to let it through. */
    public static function gate($error, $stage, $context) {
        if ((string) $error !== '' || !self::unlocked()) {
            return $error;
        }
        $context = (array) $context;
        $ip      = isset($context['ip']) ? (string) $context['ip'] : '';

        if ($stage === 'request') {
            if (self::is_rate_limited($ip, (int) zappoppop_get_setting('max_submissions_per_ip', 10))) {
                self::log('security_rate_limit', "Rate limit exceeded for IP: {$ip}", array('ip' => $ip));
                return __('Submission rate limit exceeded. Please wait a while before submitting again.', 'zappoppop-pro');
            }
            if (zappoppop_get_setting('enable_recaptcha', 0)) {
                // phpcs:disable WordPress.Security.NonceVerification.Missing -- core verified the submission nonce before this filter runs
                $token = !empty($context['recaptcha_token']) ? sanitize_text_field((string) $context['recaptcha_token']) : (isset($_POST['recaptcha_token']) ? sanitize_text_field(wp_unslash($_POST['recaptcha_token'])) : (isset($_POST['g-recaptcha-response']) ? sanitize_text_field(wp_unslash($_POST['g-recaptcha-response'])) : ''));
                // phpcs:enable WordPress.Security.NonceVerification.Missing
                if (!self::verify_recaptcha($token, $ip)) {
                    self::log('security_recaptcha_fail', "reCAPTCHA verification failed for IP: {$ip}", array('ip' => $ip));
                    return __('Bot verification failed. Please refresh the page and try again.', 'zappoppop-pro');
                }
            }
        }

        if ($stage === 'fields' && !empty($context['email']) && zappoppop_get_setting('email_validation', 'basic') === 'disposable_filter') {
            if (self::is_disposable_email($context['email'])) {
                self::log('security_disposable_email', 'Rejected disposable email: ' . $context['email'], array('ip' => $ip, 'email' => $context['email']));
                return __('Disposable or temporary email addresses are not permitted. Please use a valid personal or business email.', 'zappoppop-pro');
            }
        }
        return $error;
    }

    protected static function log($event, $message, $data) {
        if (function_exists('zappoppop_log_event')) {
            zappoppop_log_event($event, $message, $data);
        }
    }

    /** Hourly per-IP submission throttle. True = limit exceeded. */
    public static function is_rate_limited($ip, $max_per_hour) {
        $max_per_hour = (int) $max_per_hour;
        if ($max_per_hour <= 0) {
            return false;
        }
        $key   = 'zappoppop_sub_rl_' . md5((string) $ip);
        $count = (int) get_transient($key);
        if ($count >= $max_per_hour) {
            return true;
        }
        set_transient($key, $count + 1, HOUR_IN_SECONDS);
        return false;
    }

    /** Server-side reCAPTCHA verification (v2 / v3). An unconfigured secret key cannot be verified, so it passes. */
    public static function verify_recaptcha($token, $ip = '') {
        $secret = (string) zappoppop_get_setting('recaptcha_secret_key', '');
        if ($secret === '') {
            return true;
        }
        if (empty($token)) {
            return false;
        }
        $res = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
            'timeout' => 10,
            'body'    => array(
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => $ip,
            ),
        ));
        if (is_wp_error($res) || (int) wp_remote_retrieve_response_code($res) !== 200) {
            return false;
        }
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (!is_array($body) || empty($body['success'])) {
            return false;
        }
        if (isset($body['score'])) {
            return (float) $body['score'] >= (float) zappoppop_get_setting('recaptcha_threshold', 0.4);
        }
        return true;
    }

    /** Extra providers on top of core's list (core's per-field "Block Disposable Emails" and the global filter below use the same list). */
    public static function extend_domains($domains) {
        if (!self::unlocked()) {
            return $domains;
        }
        return array_values(array_unique(array_merge((array) $domains, array(
            'yopmail.com', 'getnada.com', 'throwawaymail.com', 'temp-mail.org', 'dispostable.com', 'maildrop.cc', 'fakeinbox.com',
        ))));
    }

    /** True when the email domain (or a parent domain) is a known disposable provider. */
    public static function is_disposable_email($email) {
        return ZapPopPop_Disposable::is_disposable($email);
    }

    /* ---------------------------------------------------------------- geo-targeting */

    /** Country whitelist / blacklist from the popup's targeting settings, when IP Geo-Targeting is on. */
    public static function eligible($ok, $popup, $config) {
        if (!$ok || !self::unlocked() || !zappoppop_get_setting('ip_geotargeting', 0)) {
            return $ok;
        }
        $targeting = (is_array($config) && isset($config['targeting']) && is_array($config['targeting'])) ? $config['targeting'] : array();
        $mode      = isset($targeting['geo_mode']) ? $targeting['geo_mode'] : 'all';
        $countries = (isset($targeting['geo_countries']) && is_array($targeting['geo_countries'])) ? array_map('strtoupper', $targeting['geo_countries']) : array();
        if (empty($countries) || !in_array($mode, array('whitelist', 'blacklist'), true)) {
            return $ok;
        }
        $country = strtoupper((string) ZapPopPop_Module_Bridge::visitor_country());
        if ($country === '' || in_array($country, array('XX', 'T1'), true)) {
            return $ok;
        }
        $listed = in_array($country, $countries, true);
        return $mode === 'whitelist' ? $listed : !$listed;
    }
}
