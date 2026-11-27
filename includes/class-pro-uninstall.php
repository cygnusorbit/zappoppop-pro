<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Removes Pro's data when the plugin is deleted. This replaces uninstall.php: WordPress ignores a plugin's
 * uninstall hook when uninstall.php exists, so register() registers run() directly.
 * On a network every site is cleaned in turn. Delete Pro before ZapPopPop: ZapPopPop's own cleanup removes the
 * "Clean on uninstall" setting that Pro reads here.
 */
class ZapPopPop_Pro_Uninstall {

    public static function register() {
        register_uninstall_hook(ZAPPOPPOP_PRO_FILE, array(__CLASS__, 'run'));
    }

    public static function run() {
        delete_site_transient('zappoppop_pro_update_cache');
        delete_site_transient('zappoppop_pro_edd_update');
        delete_site_option('zappoppop_pro_edd_license');
        delete_site_option('zappoppop_pro_edd_products');
        if (class_exists('ZapPopPop_Pro_Pass')) {
            ZapPopPop_Pro_Pass::clear_cache();
        }
        ZapPopPop_Pro_Multisite::each_site(array(__CLASS__, 'run_site'));
    }

    /** Cleans the current site. Tables and data go only when "clean on uninstall" is enabled in ZapPopPop settings. */
    public static function run_site() {
        wp_clear_scheduled_hook('zappoppop_pro_license_check');
        wp_clear_scheduled_hook('zappoppop_tracking_daily');
        wp_clear_scheduled_hook('zappoppop_weekly_report');
        wp_clear_scheduled_hook('zappoppop_wc_recover_send'); /* ZPP_WC_P4 */
        delete_option('zappoppop_pro_license');
        delete_option('zappoppop_pro_seed_ver');

        $cfg = get_option('zappoppop_settings', array());
        if (is_array($cfg) && !empty($cfg['clean_on_uninstall'])) {
            global $wpdb;
            $wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . 'zappoppop_campaigns`');
            $wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . 'zappoppop_stats_daily`');
            delete_option('zappoppop_pro_db');
            delete_option('zappoppop_stats_db');
            delete_option('zappoppop_stats_labels');
            $wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . 'zappoppop_wc_recover`');
            delete_option('zappoppop_wc_recover_db');
        }
    }
}
