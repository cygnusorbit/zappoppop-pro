<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Easy Digital Downloads Software Licensing client (the licence provider).
 *
 * Policy: an active or an expired licence keeps every Pro feature working;
 * expiry only stops updates and support. A licence that was never activated on this site, or that is
 * disabled, revoked or deactivated, locks Pro. A store that cannot be reached never locks a site: the last
 * known status is kept. Status lives in a site option, so a multisite network shares one licence.
 *
 * Needs two constants in wp-config.php: ZAPPOPPOP_PRO_EDD_URL and ZAPPOPPOP_PRO_EDD_ITEM_ID.
 */
class ZapPopPop_Pro_EDD {

    const OPTION = 'zappoppop_pro_edd_license';
    const CRON   = 'zappoppop_pro_license_check';
    const ACTION = 'zappoppop_pro_edd';

    public static function init() {
        add_action('admin_post_' . self::ACTION, array(__CLASS__, 'handle_post'));
        add_action(self::CRON, array(__CLASS__, 'cron_check'));
        add_action('init', array(__CLASS__, 'schedule'));
        ZapPopPop_Pro_EDD_Updater::hooks();
    }

    /* ------------------------------------------------------------------ configuration */

    public static function store_url() {
        $url = defined('ZAPPOPPOP_PRO_EDD_URL') ? esc_url_raw(trim((string) ZAPPOPPOP_PRO_EDD_URL)) : '';
        if ($url === '') {
            return '';
        }
        $local = function_exists('wp_get_environment_type') && wp_get_environment_type() === 'local';
        if (wp_parse_url($url, PHP_URL_SCHEME) !== 'https' && !$local) {
            return '';
        }
        return $url;
    }

    public static function item_id() {
        return defined('ZAPPOPPOP_PRO_EDD_ITEM_ID') ? max(0, (int) ZAPPOPPOP_PRO_EDD_ITEM_ID) : 0;
    }

    public static function is_configured() {
        return self::store_url() !== '' && self::item_id() > 0;
    }

    /** The URL EDD counts as "this site". One activation per network on multisite. */
    public static function site_url() {
        return is_multisite() ? network_home_url() : home_url();
    }

    public static function page_url($network = false) {
        $base = $network ? network_admin_url('admin.php') : admin_url('admin.php');
        return add_query_arg('page', ZapPopPop_Pro_Admin::PAGE, $base);
    }

    /** On a network-activated plugin only a network administrator may change the (network-wide) licence. */
    public static function can_manage() {
        if (is_multisite() && function_exists('is_plugin_active_for_network') && is_plugin_active_for_network(ZAPPOPPOP_PRO_BASENAME)) {
            return current_user_can('manage_network_options');
        }
        return current_user_can('manage_options');
    }

    /* ------------------------------------------------------------------ stored state */

    public static function defaults() {
        return array('key' => '', 'status' => '', 'activated' => 0, 'expires' => '', 'checked' => 0, 'message' => '', 'ok' => 1);
    }

    public static function data() {
        $d = get_site_option(self::OPTION, array());
        return wp_parse_args(is_array($d) ? $d : array(), self::defaults());
    }

    protected static function save($d) {
        update_site_option(self::OPTION, $d);
        delete_site_transient(ZapPopPop_Pro_EDD_Updater::CACHE);
        delete_site_transient('update_plugins');
        do_action('zappoppop_pro_edd_saved');
    }

    public static function get_key() {
        $d = self::data();
        return (string) $d['key'];
    }

    public static function clean_key($key) {
        return preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $key);
    }

    public static function masked_key($key) {
        $len = strlen($key);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }
        return substr($key, 0, 4) . str_repeat('*', min(16, $len - 8)) . substr($key, -4);
    }

    public static function is_unlocked() {
        if (!self::is_configured()) {
            return false;
        }
        $d = self::data();
        return $d['key'] !== '' && !empty($d['activated']) && in_array($d['status'], array('valid', 'expired'), true);
    }

    /* ------------------------------------------------------------------ store API */

    /** One call to the store. Returns the decoded JSON array or a WP_Error. */
    public static function request($action, $key, array $extra = array()) {
        if (!self::is_configured()) {
            return new WP_Error('zappoppop_edd_config', __('Licensing is not set up yet. Add the store URL and product ID to wp-config.php.', 'zappoppop-pro'));
        }
        $body = array_merge(array(
            'edd_action'  => $action,
            'license'     => $key,
            'item_id'     => self::item_id(),
            'url'         => self::site_url(),
            'environment' => function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production',
        ), $extra);
        $res = wp_remote_post(self::store_url(), array('timeout' => 15, 'sslverify' => true, 'body' => $body));
        if (is_wp_error($res)) {
            return $res;
        }
        if ((int) wp_remote_retrieve_response_code($res) !== 200) {
            return new WP_Error('zappoppop_edd_http', __('The licence server did not answer. Please try again later.', 'zappoppop-pro'));
        }
        $json = json_decode(wp_remote_retrieve_body($res), true);
        if (!is_array($json)) {
            return new WP_Error('zappoppop_edd_json', __('The licence server sent an unexpected answer.', 'zappoppop-pro'));
        }
        return $json;
    }

    /* ------------------------------------------------------------------ actions */

    public static function activate($key) {
        $key = self::clean_key($key);
        $d   = self::data();
        if ($key === '') {
            return self::finish($d, false, __('Enter your licence key.', 'zappoppop-pro'));
        }
        $d['key']     = $key;
        $d['checked'] = time();
        $r = self::request('activate_license', $key);
        if (is_wp_error($r)) {
            $d['activated'] = 0;
            return self::finish($d, false, $r->get_error_message());
        }
        if (!empty($r['success'])) {
            $d['status']    = isset($r['license']) ? sanitize_key($r['license']) : 'valid';
            $d['activated'] = 1;
            $d['expires']   = isset($r['expires']) ? sanitize_text_field($r['expires']) : '';
            $d = self::finish($d, true, __('Licence activated. Thank you!', 'zappoppop-pro'));
            do_action('zappoppop_pro_edd_activated');
            return $d;
        }
        $code           = sanitize_key(isset($r['error']) ? $r['error'] : (isset($r['license']) ? $r['license'] : 'invalid'));
        $d['status']    = $code;
        $d['activated'] = 0;
        $d['expires']   = isset($r['expires']) ? sanitize_text_field($r['expires']) : $d['expires'];
        return self::finish($d, false, self::error_text($code, $d['expires']));
    }

    public static function deactivate() {
        $d = self::data();
        if ($d['key'] === '') {
            return self::finish($d, false, __('There is no licence to deactivate.', 'zappoppop-pro'));
        }
        do_action('zappoppop_pro_edd_deactivating');
        $r = self::request('deactivate_license', $d['key']);
        if (is_wp_error($r)) {
            /* Keep the activation: the store slot is only freed when the store confirms. */
            return self::finish($d, false, $r->get_error_message());
        }
        $d = array_merge(self::defaults(), array('status' => 'inactive', 'checked' => time()));
        return self::finish($d, true, __('Licence deactivated for this site.', 'zappoppop-pro'));
    }

    /** Re-checks the stored key with the store. A store that cannot be reached keeps the last status. */
    public static function refresh() {
        $d = self::data();
        if ($d['key'] === '') {
            return self::finish($d, false, __('Enter your licence key.', 'zappoppop-pro'));
        }
        $d['checked'] = time();
        $r = self::request('check_license', $d['key']);
        if (is_wp_error($r)) {
            return self::finish($d, false, $r->get_error_message());
        }
        $status = sanitize_key(isset($r['license']) ? $r['license'] : 'invalid');
        if (isset($r['expires'])) {
            $d['expires'] = sanitize_text_field($r['expires']);
        }
        $d['status'] = $status;
        if ($status === 'valid') {
            $d['activated'] = 1;
            return self::finish($d, true, __('Your licence is active.', 'zappoppop-pro'));
        }
        if ($status === 'expired') {
            /* Stays unlocked only if this site was activated before the expiry. */
            return self::finish($d, !empty($d['activated']), self::error_text('expired', $d['expires']));
        }
        $d['activated'] = 0;
        return self::finish($d, false, self::error_text($status, $d['expires']));
    }

    protected static function finish($d, $ok, $message) {
        $d['ok']      = $ok ? 1 : 0;
        $d['message'] = (string) $message;
        self::save($d);
        return $d;
    }

    /* ------------------------------------------------------------------ admin-post + cron */

    public static function handle_post() {
        $nonce = isset($_POST['zappoppop_edd_nonce']) ? sanitize_text_field(wp_unslash($_POST['zappoppop_edd_nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, self::ACTION)) {
            wp_die(esc_html__('Security check failed. Please go back and try again.', 'zappoppop-pro'), '', array('response' => 403));
        }
        if (!self::can_manage()) {
            wp_die(esc_html__('You do not have sufficient permissions to manage the licence.', 'zappoppop-pro'), '', array('response' => 403));
        }
        $do  = isset($_POST['zappoppop_do']) ? sanitize_key(wp_unslash($_POST['zappoppop_do'])) : '';
        $key = isset($_POST['zappoppop_key']) ? sanitize_text_field(wp_unslash($_POST['zappoppop_key'])) : '';
        if ($do === 'activate') {
            self::activate($key !== '' ? $key : self::get_key());
        } elseif ($do === 'deactivate') {
            self::deactivate();
        } elseif ($do === 'check') {
            self::refresh();
            do_action('zappoppop_pro_edd_checked');
        } elseif ($do === 'sync') {
            do_action('zappoppop_pro_edd_checked');
            self::finish(self::data(), true, __('Add-on licences synced.', 'zappoppop-pro'));
        }
        $network = !empty($_POST['zappoppop_network']) && is_multisite() && current_user_can('manage_network_options');
        wp_safe_redirect(add_query_arg('zappoppop_edd', '1', self::page_url($network)));
        exit;
    }

    /** Twice daily, on the main site only, so a network does not call the store once per site. */
    public static function schedule() {
        if (!self::is_configured() || !is_main_site()) {
            return;
        }
        if (!wp_next_scheduled(self::CRON)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'twicedaily', self::CRON);
        }
    }

    public static function cron_check() {
        if (self::is_configured() && self::get_key() !== '') {
            self::refresh();
        }
    }

    /* ------------------------------------------------------------------ labels */

    public static function expires_label($expires) {
        $expires = (string) $expires;
        if ($expires === '') {
            return '';
        }
        if ($expires === 'lifetime') {
            return __('Lifetime', 'zappoppop-pro');
        }
        $ts = strtotime($expires);
        return $ts ? date_i18n(get_option('date_format'), $ts) : '';
    }

    public static function status_label($status) {
        $map = array(
            'valid'      => __('Active', 'zappoppop-pro'),
            'expired'    => __('Expired', 'zappoppop-pro'),
            'inactive'   => __('Not activated', 'zappoppop-pro'),
            'site_inactive' => __('Not activated on this site', 'zappoppop-pro'),
            'disabled'   => __('Disabled', 'zappoppop-pro'),
            'revoked'    => __('Revoked', 'zappoppop-pro'),
            'missing'    => __('Invalid key', 'zappoppop-pro'),
            'invalid'    => __('Invalid key', 'zappoppop-pro'),
        );
        return isset($map[$status]) ? $map[$status] : ($status === '' ? __('Not activated', 'zappoppop-pro') : ucfirst(str_replace('_', ' ', $status)));
    }

    public static function error_text($code, $expires = '') {
        switch ($code) {
            case 'expired':
                $date = self::expires_label($expires);
                /* translators: %s: expiry date */
                return $date !== '' ? sprintf(__('This licence expired on %s. Pro features keep working on sites where it was already active; renew it to receive updates and support.', 'zappoppop-pro'), $date)
                    : __('This licence has expired. Renew it to receive updates and support.', 'zappoppop-pro');
            case 'missing':
            case 'invalid':
                return __('This licence key is not valid. Please check it and try again.', 'zappoppop-pro');
            case 'no_activations_left':
                return __('This licence has no activations left. Deactivate it on another site or upgrade your plan.', 'zappoppop-pro');
            case 'site_inactive':
            case 'inactive':
                return __('This licence is not active on this site. Activate it again.', 'zappoppop-pro');
            case 'disabled':
            case 'revoked':
                return __('This licence has been disabled. Please contact support.', 'zappoppop-pro');
            case 'item_name_mismatch':
            case 'invalid_item_id':
                return __('This key belongs to a different product.', 'zappoppop-pro');
        }
        return __('The licence could not be verified. Please try again or contact support.', 'zappoppop-pro');
    }
}
