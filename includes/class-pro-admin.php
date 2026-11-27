<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * License sub-page (ZapPopPop > License): enter, check and deactivate the Easy Digital Downloads licence key.
 * Reuses core's admin bar, theme variables and card look.
 */
class ZapPopPop_Pro_Admin {

    const PAGE = 'zappoppop-license';

    public static function hooks() {
        add_action('zappoppop_admin_menu', array(__CLASS__, 'menu'));
        add_action('network_admin_menu', array(__CLASS__, 'network_menu'), 20);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
    }

    public static function menu($parent) {
        add_submenu_page($parent, __('License', 'zappoppop-pro'), __('License', 'zappoppop-pro'), 'manage_options', self::PAGE, array(__CLASS__, 'render'));
    }

    /** Network admin: same License sub-page under core's network page. */
    public static function network_menu() {
        if (!class_exists('ZapPopPop_Multisite') || !ZapPopPop_Multisite::network_active()) {
            return;
        }
        add_submenu_page('zappoppop-network', __('License', 'zappoppop-pro'), __('License', 'zappoppop-pro'), 'manage_network_options', self::PAGE, array(__CLASS__, 'render_network'));
    }

    public static function render_network() {
        if (!current_user_can('manage_network_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'zappoppop-pro'));
        }
        include ZAPPOPPOP_PRO_PATH . 'templates/license-page.php';
    }

    public static function assets($hook) {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($page !== self::PAGE) {
            return;
        }
        self::enqueue_shell();
    }

    /** Styles and scripts shared by the License page and the locked Pro pages. */
    public static function enqueue_shell() {
        wp_enqueue_style('dashicons');
        wp_enqueue_style('zappoppop-dashboard-css', ZAPPOPPOP_URL . 'admin/css/dashboard.css', array(), ZAPPOPPOP_VERSION);
        wp_enqueue_style('zappoppop-overview-css', ZAPPOPPOP_URL . 'admin/css/overview.css', array(), ZAPPOPPOP_VERSION);
        wp_enqueue_style('zappoppop-bar-actions-css', ZAPPOPPOP_URL . 'admin/css/bar-actions.css', array('zappoppop-dashboard-css', 'zappoppop-overview-css'), ZAPPOPPOP_VERSION);
        wp_enqueue_style('zappoppop-pro-license-css', ZAPPOPPOP_PRO_URL . 'assets/css/license.css', array('zappoppop-bar-actions-css'), ZAPPOPPOP_PRO_VERSION);
        wp_enqueue_script('zappoppop-overview-js', ZAPPOPPOP_URL . 'admin/js/overview.js', array('jquery'), ZAPPOPPOP_VERSION, true);
    }

    public static function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'zappoppop-pro'));
        }
        include ZAPPOPPOP_PRO_PATH . 'templates/license-page.php';
    }
}
