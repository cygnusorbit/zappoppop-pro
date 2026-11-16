<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Boot sequence. Everything is attached to core's extension points; this plugin edits no core file.
 */
class ZapPopPop_Pro_Plugin {

    protected static $core_ok = false;

    public static function boot() {
        add_action('plugins_loaded', array(__CLASS__, 'load_textdomain'));
        add_action('zappoppop_loaded', array(__CLASS__, 'on_core_loaded'));
        add_action('admin_notices', array(__CLASS__, 'notices'));
        add_action('after_plugin_row_' . ZAPPOPPOP_PRO_BASENAME, array(__CLASS__, 'plugin_row_notice'));
        add_action('admin_head-plugins.php', array(__CLASS__, 'plugin_row_css'));
        add_action('admin_init', array('ZapPopPop_Pro_Uninstall', 'register'));
        require_once ZAPPOPPOP_PRO_PATH . 'includes/class-pro-db.php';
        ZapPopPop_Pro_DB::hooks();
        ZapPopPop_Pro_Multisite::hooks();
        register_activation_hook(ZAPPOPPOP_PRO_FILE, array('ZapPopPop_Pro_Multisite', 'activate'));
        register_deactivation_hook(ZAPPOPPOP_PRO_FILE, array(__CLASS__, 'deactivate'));

        /* Core reads these while it boots (before zappoppop_loaded fires), so they are attached right away. */
        add_filter('zappoppop_pro_licensed', array('ZapPopPop_Pro_License', 'filter_licensed'));
        add_filter('zappoppop_module_roots', array(__CLASS__, 'module_roots'));
        add_filter('zappoppop_preset_roots', array(__CLASS__, 'preset_roots'));
    }

    public static function deactivate($network_wide = false) {
        ZapPopPop_Pro_Multisite::deactivate($network_wide);
    }

    public static function load_textdomain() {
        load_plugin_textdomain('zappoppop-pro', false, dirname(ZAPPOPPOP_PRO_BASENAME) . '/languages');
    }

    public static function core_ready() {
        return self::$core_ok;
    }

    public static function on_core_loaded() {
        if (!defined('ZAPPOPPOP_API_VERSION') || (int) ZAPPOPPOP_API_VERSION < ZAPPOPPOP_PRO_MIN_API) {
            return;
        }
        self::$core_ok = true;

        foreach (array('class-pro-gate.php', 'class-pro-settings.php', 'ab/class-admin-ab-query.php', 'ab/class-front-ab.php',
                       'ab/class-ajax-campaigns.php', 'ab/class-pro-ab.php', 'stats/class-admin-stats.php', 'stats/class-pro-stats.php', 'elements/class-pro-elements.php', 'modules/class-pro-modules.php', 'modules/class-pro-pipeline.php', 'class-pro-extensions.php', 'class-pro-protection.php') as $file) {
            require_once ZAPPOPPOP_PRO_PATH . 'includes/' . $file;
        }
        ZapPopPop_Pro_Settings::hooks();
        ZapPopPop_Pro_AB::boot();
        ZapPopPop_Pro_Stats::boot();
        ZapPopPop_Pro_Elements::boot();
        ZapPopPop_Pro_Modules::boot();
        ZapPopPop_Pro_Pipeline::boot();
        ZapPopPop_Pro_Extensions::hooks();
        ZapPopPop_Pro_Protection::hooks();

        if (is_admin()) {
            ZapPopPop_Pro_Admin::hooks();
        }
    }

    /* Pro modules stay registered even without a licence, so saved popup data is never dropped. */
    public static function module_roots($roots) {
        $roots[] = array('path' => ZAPPOPPOP_PRO_PATH . 'modules', 'url' => ZAPPOPPOP_PRO_URL . 'modules/');
        return $roots;
    }

    /* Pro templates are offered in the Library only with an active licence. */
    public static function preset_roots($roots) {
        if (ZapPopPop_Pro_License::is_unlocked()) {
            $roots[] = array('path' => ZAPPOPPOP_PRO_PATH . 'library-presets', 'url' => ZAPPOPPOP_PRO_URL . 'library-presets/');
        }
        return $roots;
    }

    /** True when the Plugins screen should flag Pro as only partly active. */
    protected static function row_flagged() {
        return !self::$core_ok
            && current_user_can('activate_plugins')
            && (is_plugin_active(ZAPPOPPOP_PRO_BASENAME) || is_plugin_active_for_network(ZAPPOPPOP_PRO_BASENAME));
    }

    /* Row under Pro on the Plugins screen while ZapPopPop is missing, inactive or too old. Same columns as the table above it. */
    public static function plugin_row_notice() {
        if (!self::row_flagged()) {
            return;
        }
        $table   = _get_list_table('WP_Plugins_List_Table');
        $columns = $table ? (int) $table->get_column_count() : 4;
        $rest    = max(1, $columns - 2);
        $min     = defined('ZAPPOPPOP_PRO_MIN_CORE') ? ZAPPOPPOP_PRO_MIN_CORE : '';
        $label   = did_action('zappoppop_loaded')
            /* translators: 1: plugin name, 2: minimum version number */
            ? __('Outdated %1$s: minimum required %2$s', 'zappoppop-pro')
            /* translators: 1: plugin name, 2: minimum version number */
            : __('Missing %1$s: minimum required %2$s', 'zappoppop-pro');
        $detail  = sprintf($label, '<strong>ZapPopPop</strong>', '<strong>' . esc_html($min) . '</strong>');
        printf(
            '<tr class="active zappoppop-pro-flag" data-slug="zappoppop-pro" data-plugin="%1$s"><th scope="row" class="check-column"><span class="dashicons dashicons-warning" aria-hidden="true"></span></th><td class="plugin-title"><span>%2$s</span></td><td class="column-description desc" colspan="%3$d"><p>%4$s</p></td></tr>',
            esc_attr(ZAPPOPPOP_PRO_BASENAME),
            esc_html__('This plugin is not fully active.', 'zappoppop-pro'),
            $rest,
            wp_kses($detail, array('strong' => array()))
        );
    }

    public static function plugin_row_css() {
        if (!self::row_flagged()) {
            return;
        }
        $r = 'tr[data-plugin="' . esc_attr(ZAPPOPPOP_PRO_BASENAME) . '"]';
        echo '<style id="zappoppop-pro-row-css">'
            . $r . '.active th,' . $r . '.active td{background-color:#fcf0f1}'
            . $r . '.active:not(.zappoppop-pro-flag) th,' . $r . '.active:not(.zappoppop-pro-flag) td{box-shadow:inset 0 -1px 0 rgba(0,0,0,.06)}'
            . $r . '.active th.check-column,' . $r . '.active td.check-column{border-left-color:#d63638}'
            . $r . '.zappoppop-pro-flag .dashicons-warning{color:#d63638;width:20px;height:20px;font-size:20px;margin-left:5px}'
            . $r . '.zappoppop-pro-flag .desc p{margin:0}'
            . '@media screen and (max-width:782px){' . $r . '.zappoppop-pro-flag td{display:block!important;width:auto;padding:4px 9px 10px 9px}}'
            . '</style>' . "\n";
    }

    public static function notices() {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (!did_action('zappoppop_loaded')) {
            echo '<div class="notice notice-error"><p>' . esc_html__('ZapPopPop Pro needs the ZapPopPop plugin. Install and activate ZapPopPop first.', 'zappoppop-pro') . '</p></div>';
            return;
        }
        if (!self::$core_ok) {
            echo '<div class="notice notice-error"><p>' . esc_html__('ZapPopPop Pro needs a newer version of ZapPopPop. Please update ZapPopPop.', 'zappoppop-pro') . '</p></div>';
            return;
        }
        if (!ZapPopPop_Pro_License::is_unlocked()) {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if ($screen && strpos((string) $screen->id, 'zappoppop-license') !== false) {
                return;
            }
            printf(
                '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
                esc_html__('ZapPopPop Pro is not activated. Pro templates and modules stay locked until you enter your licence key.', 'zappoppop-pro'),
                esc_url(ZapPopPop_Pro_License::activate_url()),
                esc_html__('Activate licence', 'zappoppop-pro')
            );
        }
    }
}
