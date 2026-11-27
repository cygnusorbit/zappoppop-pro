<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pro submission pipeline: Security & Protections, Double Opt-In, Autoresponder, Webhooks, MailChimp, GetResponse,
 * Twilio SMS alerts, WooCommerce coupons, Downloads, GeoIP, the module admin endpoints and the privacy tools.
 *
 * Core owns the lead capture (REST / AJAX), the confirmation link and the Notifications module, and only calls the
 * filters and actions answered here (see ZapPopPop_Module_Bridge). Without this plugin leads are stored as confirmed
 * and the stored settings of these modules are kept untouched, so nothing is lost when the plugin is deactivated.
 */
class ZapPopPop_Pro_Pipeline {

    /** Popup-settings keys sanitised here (core lists the same keys in ZapPopPop_Module_Bridge::EXT_KEYS). */
    const KEYS = array(
        'webhooks', 'woocommerce', 'downloads', 'sms',
    );
    /* ZAPPOPPOP_INTEGRATIONS_KEYS: the 'mailchimp' and 'getresponse' keys are answered by ZapPopPop_Pro_Integrations (installed ones only). */

    protected static $booted = false;

    public static function boot() {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        add_filter('zappoppop_handled_settings_keys', array(__CLASS__, 'handled_keys'));
        add_filter('zappoppop_sanitize_popup_settings', array(__CLASS__, 'sanitize'), 10, 2);

        add_action('zappoppop_submission_dispatch', array(__CLASS__, 'dispatch'));
        add_filter('zappoppop_submission_extras', array(__CLASS__, 'extras'), 10, 2);
        add_action('zappoppop_after_confirmation', array(__CLASS__, 'after_confirmation'));

        add_filter('zappoppop_redact_config', array(__CLASS__, 'redact'));
        add_filter('zappoppop_diagnostic_sms', array(__CLASS__, 'diagnostic_sms'), 10, 3);
        add_filter('zappoppop_visitor_country', array(__CLASS__, 'visitor_country'));


        new ZapPopPop_Addon_Downloads();
        new ZapPopPop_Addon_WooCommerce();
        if (is_admin()) {
            ZapPopPop_Pro_Integrations::boot_admin();
        }
    }

    /* ---------------------------------------------------------------- save / template */

    public static function handled_keys($keys) {
        return array_merge((array) $keys, self::KEYS, ZapPopPop_Pro_Integrations::handled_keys());
    }

    /** Allow-lists every key in KEYS. $ctx = array(context => save|template, raw, prev, uid). */
    public static function sanitize($settings, $ctx) {
        if (!is_object($settings)) {
            return $settings;
        }
        $raw = isset($ctx['raw']) && is_array($ctx['raw']) ? $ctx['raw'] : array();
        $tpl = isset($ctx['context']) && $ctx['context'] === 'template';
        $get = function ($key) use ($raw) {
            return isset($raw[$key]) ? $raw[$key] : array();
        };
        $json = function ($v) {
            return json_decode(wp_json_encode($v));
        };

        foreach (array('webhooks' => 'ZapPopPop_Webhooks', 'woocommerce' => 'ZapPopPop_Woocommerce', 'downloads' => 'ZapPopPop_Downloads') as $key => $class) {
            $settings->$key = $json(call_user_func(array($class, 'sanitize'), $get($key)));
        }

        /* Defaults are never stored. */
        $sms = ZapPopPop_Sms::sanitize($get('sms'));
        if ($sms !== ZapPopPop_Sms::defaults()) {
            $settings->sms = $json($sms);
        }

        /* Integration lists (Mailchimp, GetResponse, add-ons) are stored only when posted; templates never carry account keys or list ids. */
        ZapPopPop_Pro_Integrations::sanitize($settings, $raw, $tpl);

        return $settings;
    }

    /* ---------------------------------------------------------------- submission pipeline */

    /** Runs after a lead is stored and the Notifications module has been dispatched. */
    public static function dispatch($ctx) {
        $uid    = $ctx['uid'];
        $config = $ctx['config'];
        $email  = $ctx['email'];
        $fields = $ctx['fields'];
        $sid    = $ctx['submission_id'];
        $wh = ZapPopPop_Webhooks::get_config($config);
        if (!empty($wh['url']) && ZapPopPop_Webhooks::module_active()) {
            ZapPopPop_Webhooks::send($uid, $sid, $email, $fields, $ctx['country'], $wh);
        }

        ZapPopPop_Pro_Integrations::dispatch('after_submission', $uid, $ctx['title'], $config, $email, $fields, $sid, $ctx['country']);

        $sms = ZapPopPop_Sms::get_config($config, $uid);
        if (!empty($sms['enable']) && ZapPopPop_Sms::module_active()) {
            ZapPopPop_Sms::notify($uid, $ctx['title'], $email, $sms);
        }
    }

    /** Coupon and one-time download link; never before the email is confirmed. */
    public static function extras($extras, $ctx) {
        if (!empty($ctx['double_optin'])) {
            return $extras;
        }
        $extras['coupon_code']  = (string) ZapPopPop_Woocommerce::issue($ctx['uid'], $ctx['email'], ZapPopPop_Woocommerce::get_config($ctx['config']));
        $extras['download_url'] = (string) ZapPopPop_Downloads::issue($ctx['uid'], ZapPopPop_Downloads::get_config($ctx['config']));
        return $extras;
    }

    public static function after_confirmation($ctx) {
        ZapPopPop_Pro_Integrations::dispatch('after_confirmation', $ctx['uid'], $ctx['title'], $ctx['config'], $ctx['email'], $ctx['fields'], $ctx['submission_id'], $ctx['country']);
    }

    /* ---------------------------------------------------------------- display / privacy */

    /** API keys and credentials never leave the server inside a popup config. */
    public static function redact($config) {
        foreach (array('ZapPopPop_Webhooks', 'ZapPopPop_Sms', 'ZapPopPop_Downloads') as $class) {
            $config = call_user_func(array($class, 'redact'), $config);
        }
        /* Mailchimp / GetResponse keys are removed even when that integration is not installed. */
        return ZapPopPop_Pro_Integrations::redact($config);
    }

    public static function diagnostic_sms($result, $to, $creds) {
        $sms = new ZapPopPop_Addon_Sms();
        return $sms->dispatch_twilio_sms($to, 'ZapPopPop diagnostic test SMS ping.', $creds);
    }

    /** Visitor country through the GeoIP service chosen in Settings > Security. */
    public static function visitor_country($country) {
        if (is_string($country) && $country !== '') {
            return $country;
        }
        $service = zappoppop_get_setting('geoip_service', zappoppop_get_setting('geoip_api_service', 'none'));
        if ($service === 'none') {
            return '';
        }
        return (string) ZapPopPop_GeoIP::get_country_code();
    }
}
