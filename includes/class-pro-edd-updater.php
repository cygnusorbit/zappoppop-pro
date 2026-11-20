<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin updates from the EDD store (replaces what the Freemius SDK did). The store only returns a download
 * package for a valid or expired-but-entitled licence, so this class never decides who may update.
 */
class ZapPopPop_Pro_EDD_Updater {

    const CACHE = 'zappoppop_pro_edd_update';

    public static function hooks() {
        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'inject'));
        add_filter('plugins_api', array(__CLASS__, 'plugin_info'), 20, 3);
    }

    public static function slug() {
        return dirname(ZAPPOPPOP_PRO_BASENAME);
    }

    public static function inject($transient) {
        if (!is_object($transient) || !ZapPopPop_Pro_EDD::is_configured()) {
            return $transient;
        }
        $info = self::info();
        if (!$info) {
            return $transient;
        }
        if (version_compare(ZAPPOPPOP_PRO_VERSION, $info->new_version, '<')) {
            if (!isset($transient->response) || !is_array($transient->response)) {
                $transient->response = array();
            }
            $transient->response[ZAPPOPPOP_PRO_BASENAME] = $info;
        } else {
            if (!isset($transient->no_update) || !is_array($transient->no_update)) {
                $transient->no_update = array();
            }
            $transient->no_update[ZAPPOPPOP_PRO_BASENAME] = $info;
        }
        return $transient;
    }

    /** Fills the "View details" popup on the Plugins screen. */
    public static function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || !is_object($args) || empty($args->slug) || $args->slug !== self::slug()) {
            return $result;
        }
        $info = self::info();
        if (!$info) {
            return $result;
        }
        $out                = clone $info;
        $out->version       = $info->new_version;
        $out->download_link = $info->package;
        if (empty($out->sections)) {
            $out->sections = array('description' => esc_html__('ZapPopPop Pro add-on.', 'zappoppop-pro'));
        }
        return $out;
    }

    /** Latest version data from the store, cached 6 hours (1 hour after a failed call). */
    protected static function info() {
        $key = ZapPopPop_Pro_EDD::get_key();
        if ($key === '') {
            return null;
        }
        $hash   = md5($key . '|' . ZAPPOPPOP_PRO_VERSION);
        $cached = get_site_transient(self::CACHE);
        if (is_array($cached) && isset($cached['hash']) && $cached['hash'] === $hash) {
            return $cached['data'] ? self::to_object($cached['data']) : null;
        }
        $r = ZapPopPop_Pro_EDD::request('get_version', $key, array('slug' => self::slug(), 'version' => ZAPPOPPOP_PRO_VERSION, 'beta' => 0));
        if (is_wp_error($r) || empty($r['new_version'])) {
            set_site_transient(self::CACHE, array('hash' => $hash, 'data' => false), HOUR_IN_SECONDS);
            return null;
        }
        set_site_transient(self::CACHE, array('hash' => $hash, 'data' => $r), 6 * HOUR_IN_SECONDS);
        return self::to_object($r);
    }

    protected static function to_object(array $r) {
        $o              = new stdClass();
        $o->id          = ZAPPOPPOP_PRO_BASENAME;
        $o->plugin      = ZAPPOPPOP_PRO_BASENAME;
        $o->slug        = self::slug();
        $o->name        = isset($r['name']) ? sanitize_text_field($r['name']) : 'ZapPopPop Pro';
        $o->new_version = sanitize_text_field($r['new_version']);
        $o->url         = isset($r['homepage']) ? esc_url_raw($r['homepage']) : (isset($r['url']) ? esc_url_raw($r['url']) : '');
        $o->package     = isset($r['package']) ? esc_url_raw($r['package']) : (isset($r['download_link']) ? esc_url_raw($r['download_link']) : '');
        foreach (array('tested', 'requires', 'requires_php', 'last_updated') as $f) {
            if (isset($r[$f])) {
                $o->$f = sanitize_text_field($r[$f]);
            }
        }
        $sections = array();
        foreach (self::decode(isset($r['sections']) ? $r['sections'] : array()) as $k => $v) {
            if (is_scalar($v)) {
                $sections[sanitize_key($k)] = wp_kses_post((string) $v);
            }
        }
        $o->sections = $sections;
        $o->banners  = array_map('esc_url_raw', array_filter(self::decode(isset($r['banners']) ? $r['banners'] : array()), 'is_string'));
        $o->icons    = array_map('esc_url_raw', array_filter(self::decode(isset($r['icons']) ? $r['icons'] : array()), 'is_string'));
        return $o;
    }

    /** The store sends arrays as JSON or as PHP-serialized text. Objects are never revived. */
    protected static function decode($v) {
        if (is_array($v)) {
            return $v;
        }
        if (!is_string($v) || $v === '') {
            return array();
        }
        $j = json_decode($v, true);
        if (is_array($j)) {
            return $j;
        }
        $u = @unserialize($v, array('allowed_classes' => false));
        return is_array($u) ? $u : array();
    }
}
