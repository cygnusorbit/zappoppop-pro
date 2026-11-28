<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin updates from the EDD store. The store only returns a download
 * package for a valid or expired-but-entitled licence, so this class never decides who may update.
 */
class ZapPopPop_Pro_EDD_Updater {

    const CACHE = 'zappoppop_pro_edd_update';

    public static function hooks() {
        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'inject'));
        add_filter('plugins_api', array(__CLASS__, 'plugin_info'), 20, 3);
        add_action('in_plugin_update_message-' . ZAPPOPPOP_PRO_BASENAME, array(__CLASS__, 'row_message'), 10, 2);
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

    /**
     * Extra text inside the Plugins screen update row. WordPress prints "Automatic update is unavailable for this plugin"
     * when the store gave no package (no key, key not active on this site, or an expired key); this says why and links to the License page.
     */
    public static function row_message($plugin_data, $response) {
        if (is_object($response) && !empty($response->package)) {
            return;
        }
        $d       = ZapPopPop_Pro_EDD::data();
        $expired = ($d['key'] !== '' && $d['status'] === 'expired');
        $url     = ZapPopPop_Pro_EDD::page_url(is_network_admin());
        echo ' ';
        echo esc_html($expired ? __('This licence has expired. Renew it to receive updates and support.', 'zappoppop-pro') : __('Activate your licence to enable automatic updates for this plugin.', 'zappoppop-pro'));
        if (ZapPopPop_Pro_EDD::can_manage()) {
            echo ' <a href="' . esc_url($url) . '">' . esc_html($expired ? __('License', 'zappoppop-pro') : __('Activate licence', 'zappoppop-pro')) . '</a>';
        }
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
        $out->name          = 'ZapPopPop PRO';

        // Tabs and sidebar come from the bundled readme.txt; anything the store sends (non-empty) wins.
        $rd    = self::readme_data();
        $store = array();
        if (!empty($out->sections) && is_array($out->sections)) {
            $store = array_filter($out->sections, 'strlen');
        }
        $out->sections = array_merge($rd['sections'], $store);
        if (empty($out->sections)) {
            $out->sections = array('description' => esc_html__('ZapPopPop Pro add-on.', 'zappoppop-pro'));
        }
        foreach (array('requires', 'tested', 'requires_php', 'last_updated') as $f) {
            if (empty($out->$f) && !empty($rd['meta'][$f])) {
                $out->$f = $rd['meta'][$f];
            }
        }
        // ZPP_TESTED_BRANCH: "Tested up to: 7.1" covers every 7.1.x release, so the popup shows no false "not tested" warning.
        if (!empty($out->tested) && preg_match('/^\d+\.\d+$/', $out->tested)) {
            $wp = get_bloginfo('version');
            if (preg_match('/^(\d+\.\d+)(?:\.|$|-)/', $wp, $m) && $m[1] === $out->tested) {
                $out->tested = $wp;
            }
        }
        if (empty($out->author)) {
            $out->author = '<a href="https://zappoppop.com/">ZapPopPop</a>';
        }
        if (empty($out->homepage)) {
            $out->homepage = 'https://zappoppop.com/';
        }
        return $out;
    }

    /** Reads the bundled readme.txt into "View details" tabs plus sidebar facts. Parsed only when the popup opens. */
    protected static function readme_data() {
        $res  = array('sections' => array(), 'meta' => array());
        $file = ZAPPOPPOP_PRO_PATH . 'readme.txt';
        if (!is_readable($file)) {
            return $res;
        }
        $raw = file_get_contents($file); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
        if (!is_string($raw) || $raw === '') {
            return $res;
        }
        $raw   = str_replace(array("\r\n", "\r"), "\n", $raw);
        $parts = preg_split('/^==[ \t]*([^=\n]+?)[ \t]*==[ \t]*$/m', $raw, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return $res;
        }
        $head = array();
        foreach (explode("\n", (string) $parts[0]) as $line) {
            if (preg_match('/^([A-Za-z ]+):\s*(.+)$/', trim($line), $m)) {
                $head[strtolower(trim($m[1]))] = trim($m[2]);
            }
        }
        $map = array('requires at least' => 'requires', 'tested up to' => 'tested', 'requires php' => 'requires_php');
        foreach ($map as $from => $to) {
            if (!empty($head[$from])) {
                $res['meta'][$to] = sanitize_text_field($head[$from]);
            }
        }
        $titles = array('description' => 'description', 'installation' => 'installation', 'frequently asked questions' => 'faq', 'changelog' => 'changelog');
        for ($i = 1; $i + 1 < count($parts); $i += 2) {
            $key = strtolower(trim($parts[$i]));
            if (!isset($titles[$key])) {
                continue;
            }
            $body = trim($parts[$i + 1]);
            if ($body === '') {
                continue;
            }
            $res['sections'][$titles[$key]] = self::readme_html($body);
            if ($titles[$key] === 'changelog' && preg_match('/^=[ \t]*v?[0-9][0-9.]*[ \t]*[-\x{2013}][ \t]*(\d{4}-\d{2}-\d{2})[ \t]*=/mu', $body, $m)) {
                $res['meta']['last_updated'] = $m[1];
            }
        }
        return $res;
    }

    /** Small readme.txt markup to HTML: = Heading =, * bullets, 1. steps, **bold**, `code`, links. */
    protected static function readme_html($text) {
        $inline = function ($t) {
            $t = esc_html($t);
            $t = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $t);
            $t = preg_replace('/`([^`]+)`/', '<code>$1</code>', $t);
            return make_clickable($t);
        };
        $html = '';
        $list = '';
        $para = array();
        $flush_para = function () use (&$html, &$para, $inline) {
            if ($para) {
                $html .= '<p>' . $inline(implode(' ', $para)) . '</p>';
                $para = array();
            }
        };
        $close_list = function () use (&$html, &$list) {
            if ($list !== '') {
                $html .= '</' . $list . '>';
                $list = '';
            }
        };
        foreach (explode("\n", $text) as $line) {
            $line = rtrim($line);
            if (trim($line) === '') {
                $flush_para();
                $close_list();
                continue;
            }
            if (preg_match('/^=[ \t]*(.+?)[ \t]*=$/', $line, $m)) {
                $flush_para();
                $close_list();
                $html .= '<h4>' . $inline($m[1]) . '</h4>';
            } elseif (preg_match('/^[*-][ \t]+(.+)$/', $line, $m)) {
                $flush_para();
                if ($list !== 'ul') {
                    $close_list();
                    $html .= '<ul>';
                    $list  = 'ul';
                }
                $html .= '<li>' . $inline($m[1]) . '</li>';
            } elseif (preg_match('/^\d+\.[ \t]+(.+)$/', $line, $m)) {
                $flush_para();
                if ($list !== 'ol') {
                    $close_list();
                    $html .= '<ol>';
                    $list  = 'ol';
                }
                $html .= '<li>' . $inline($m[1]) . '</li>';
            } else {
                $close_list();
                $para[] = trim($line);
            }
        }
        $flush_para();
        $close_list();
        return wp_kses_post($html);
    }

    /** Latest version data from the store, cached 6 hours (1 hour after a failed call). */
    protected static function info() {
        $key    = ZapPopPop_Pro_EDD::get_key();
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

    public static function to_object(array $r) {
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
