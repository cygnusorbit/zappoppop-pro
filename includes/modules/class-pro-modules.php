<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pro popup-settings modules: Conditional Logic & Math, Payments, Video Listeners, Quiz, Frequency and Custom Code.
 *
 * Core owns the save / template / render / eligibility pipeline and only calls the filters this class answers.
 * Saved values and the visitor-facing behaviour keep working whenever this plugin is active, so a lapsed licence
 * never breaks a live popup. Installing and configuring the modules is gated by the licence (see class-pro-plugin.php).
 */
class ZapPopPop_Pro_Modules {

    /** Popup-settings keys sanitised here (core lists the same keys in ZapPopPop_Module_Bridge::EXT_KEYS). */
    const KEYS = array(
        'logic_math',
        'payments', 'video', 'quiz', 'frequency', 'custom_code',
    );


    protected static $booted = false;

    /** Loads the moved module classes (class names unchanged) from includes/modules/. */
    public static function autoload($class) {
        if (strpos($class, 'ZapPopPop_') !== 0 || strpos($class, 'ZapPopPop_Pro_') === 0) {
            return;
        }
        $file = ZAPPOPPOP_PRO_PATH . 'includes/modules/class-zappoppop-' . strtolower(str_replace('_', '-', substr($class, 10))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }

    public static function boot() {
        if (self::$booted) {
            return;
        }
        self::$booted = true;
        spl_autoload_register(array(__CLASS__, 'autoload'));

        add_filter('zappoppop_handled_settings_keys', array(__CLASS__, 'handled_keys'));
        add_filter('zappoppop_extension_settings_keys', array(__CLASS__, 'extension_keys')); /* ZAPPOPPOP_PRO_MODREG */
        add_filter('zappoppop_sanitizer_module_keys', array(__CLASS__, 'raw_module_keys'));
        add_filter('zappoppop_sanitize_popup_settings', array(__CLASS__, 'sanitize'), 10, 2);
        add_filter('zappoppop_popup_wrapper_attrs', array(__CLASS__, 'wrapper_attrs'), 10, 3);
        add_filter('zappoppop_popup_inline_css', array(__CLASS__, 'inline_css'), 10, 3);
        add_filter('zappoppop_popup_overlay', array(__CLASS__, 'overlay'), 10, 2);
        add_filter('zappoppop_popup_position', array(__CLASS__, 'position'), 10, 2);
        add_filter('zappoppop_pay_button_attrs', array(__CLASS__, 'pay_button_attrs'), 10, 2);
        add_filter('zappoppop_popup_eligible', array(__CLASS__, 'eligible'), 10, 3);
        add_action('zappoppop_admin_builder_assets', array(__CLASS__, 'builder_assets'), 20);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'front_assets'), 20);
    }

    /* ---------------------------------------------------------------- save / template */

    public static function handled_keys($keys) {
        return array_merge((array) $keys, self::KEYS, array_keys(self::registry()));
    }

    /**
     * ZAPPOPPOP_PRO_MODREG: modules supplied by add-on plugins (key => definition).
     * Filter `zappoppop_pro_modules`. A definition may hold these callables (all optional):
     *   sanitize($raw_value, $ctx) => value to store, or null to store nothing
     *   wrapper($attrs, $settings, $args) => the wrapper attributes, changed (runs first)
     *   attrs($settings, $args)    => string appended to the popup wrapper attributes
     *   eligible($settings)        => bool, false hides the popup
     *   overlay($settings)         => array(css, layers) or null; position($settings) => same for the position
     *   front_assets()             => enqueue visitor-side files
     *   builder_assets()           => enqueue / localize builder-side files
     * A key that no add-on registers is not handled here, so core keeps the value already stored for the popup.
     */
    public static function registry() {
        $defs = apply_filters('zappoppop_pro_modules', array());
        $out  = array();
        if (!is_array($defs)) {
            return $out;
        }
        foreach ($defs as $key => $def) {
            if (!is_string($key) || !preg_match('/^[a-z0-9_]+$/', $key) || !is_array($def)) {
                continue;
            }
            foreach (array('sanitize', 'wrapper', 'attrs', 'eligible', 'overlay', 'position', 'front_assets', 'builder_assets') as $fn) {
                if (isset($def[$fn]) && !is_callable($def[$fn])) {
                    unset($def[$fn]);
                }
            }
            $out[$key] = $def;
        }
        return $out;
    }

    /** Lets core extract the registered keys from the posted settings (core's own list stays unchanged). */
    public static function extension_keys($keys) {
        $keys = is_array($keys) ? $keys : array();
        foreach (array_keys(self::registry()) as $k) {
            if (!in_array($k, $keys, true)) {
                $keys[] = $k;
            }
        }
        return $keys;
    }

    /** Core keeps only 'overlay' as a raw settings subtree; the Custom Code module keeps its own (it sanitises it itself). */
    public static function raw_module_keys($keys) {
        $keys = is_array($keys) ? $keys : array();
        if (!in_array('custom_code', $keys, true)) {
            $keys[] = 'custom_code';
        }
        return $keys;
    }

    /** Allow-lists every key in KEYS. $ctx = array(context => save|template, raw, prev, uid). */
    public static function sanitize($settings, $ctx) {
        if (!is_object($settings)) {
            return $settings;
        }
        $raw     = isset($ctx['raw']) && is_array($ctx['raw']) ? $ctx['raw'] : array();
        $prev    = isset($ctx['prev']) && is_array($ctx['prev']) ? $ctx['prev'] : array();
        $get     = function ($key) use ($raw) {
            return isset($raw[$key]) ? $raw[$key] : array();
        };
        $json = function ($v) {
            return json_decode(wp_json_encode($v));
        };


        $lm = ZapPopPop_Logic_Math::sanitize($get('logic_math'));
        if ($lm['formula'] !== '' || $lm['target'] !== '') {
            $settings->logic_math = $json($lm);
        }

        /* Defaults are never stored. */
        foreach (array('payments' => 'ZapPopPop_Logic_Payments', 'video' => 'ZapPopPop_Video_Listeners', 'quiz' => 'ZapPopPop_Quiz') as $key => $class) {
            $clean = call_user_func(array($class, 'sanitize'), $get($key));
            if ($clean !== call_user_func(array($class, 'defaults'))) {
                $settings->$key = $json($clean);
            }
        }

        if (isset($raw['frequency'])) {
            $settings->frequency = $json(ZapPopPop_Frequency::sanitize($raw['frequency']));
        }

        /* Custom JS needs unfiltered_html; without it the stored JS is kept. */
        if (isset($raw['custom_code'])) {
            $prev_cc = (isset($prev['custom_code']) && is_array($prev['custom_code'])) ? $prev['custom_code'] : array();
            $cc      = ZapPopPop_Custom_Code::sanitize($raw['custom_code'], $prev_cc, current_user_can('unfiltered_html'));
            if ($cc !== ZapPopPop_Custom_Code::defaults()) {
                $settings->custom_code = $json($cc);
            }
        }

        /* ZAPPOPPOP_PRO_MODREG: keys owned by add-on plugins. */
        foreach (self::registry() as $key => $def) {
            if (!isset($def['sanitize'])) {
                continue;
            }
            $val = call_user_func($def['sanitize'], isset($raw[$key]) ? $raw[$key] : null, $ctx);
            if ($val !== null) {
                $settings->$key = $json($val);
            }
        }

        return $settings;
    }

    /* ---------------------------------------------------------------- front end */

    public static function wrapper_attrs($attrs, $settings, $args) {
        $settings = is_array($settings) ? $settings : array();
        foreach (self::registry() as $def) { /* ZAPPOPPOP_PRO_MODREG */
            if (isset($def['wrapper'])) {
                $new = call_user_func($def['wrapper'], $attrs, $settings, is_array($args) ? $args : array());
                if (is_string($new)) {
                    $attrs = $new;
                }
            }
        }
        $attrs .= ZapPopPop_Logic_Math::frontend($settings);
        ZapPopPop_Logic_Payments::context($settings);
        $attrs .= ZapPopPop_Video_Listeners::frontend($settings);
        $attrs .= ZapPopPop_Quiz::frontend($settings);
        $attrs .= ZapPopPop_Frequency::frontend($settings);
        foreach (self::registry() as $def) { /* ZAPPOPPOP_PRO_MODREG */
            if (isset($def['attrs'])) {
                $add = call_user_func($def['attrs'], $settings, is_array($args) ? $args : array());
                if (is_string($add)) {
                    $attrs .= $add;
                }
            }
        }
        return $attrs;
    }

    public static function inline_css($css, $settings, $uid) {
        return $css . ZapPopPop_Custom_Code::frontend(is_array($settings) ? $settings : array(), $uid);
    }

    public static function overlay($default, $settings) {
        foreach (self::registry() as $def) { /* ZAPPOPPOP_PRO_MODREG */
            if (isset($def['overlay'])) {
                $res = call_user_func($def['overlay'], is_array($settings) ? $settings : array());
                if (is_array($res)) {
                    return $res;
                }
            }
        }
        return $default;
    }

    public static function position($default, $settings) {
        foreach (self::registry() as $def) { /* ZAPPOPPOP_PRO_MODREG */
            if (isset($def['position'])) {
                $res = call_user_func($def['position'], is_array($settings) ? $settings : array());
                if (is_array($res)) {
                    return $res;
                }
            }
        }
        return $default;
    }

    public static function pay_button_attrs($attrs, $el) {
        return ZapPopPop_Logic_Payments::button_attrs(is_array($el) ? $el : array());
    }

    public static function eligible($ok, $popup, $config) {
        if (!$ok) {
            return false;
        }
        $settings = (is_array($config) && isset($config['settings']) && is_array($config['settings'])) ? $config['settings'] : array();
        foreach (self::registry() as $def) { /* ZAPPOPPOP_PRO_MODREG */
            if (isset($def['eligible']) && !call_user_func($def['eligible'], $settings)) {
                return false;
            }
        }
        return true;
    }

    public static function front_assets() {
        foreach (self::registry() as $def) { /* ZAPPOPPOP_PRO_MODREG */
            if (isset($def['front_assets'])) {
                call_user_func($def['front_assets']);
            }
        }
    }

    /* ---------------------------------------------------------------- builder */

    public static function builder_assets() {
        foreach (self::registry() as $def) { /* ZAPPOPPOP_PRO_MODREG */
            if (isset($def['builder_assets'])) {
                call_user_func($def['builder_assets']);
            }
        }
    }
}
