<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Multisite support for Pro. Its tables, options and schedules are per site, so activation, deactivation and
 * uninstall visit every site on a network. This class does not need ZapPopPop core to be loaded.
 */
class ZapPopPop_Pro_Multisite {

    const BATCH = 100;

    public static function hooks() {
        /* Core fires this for every site it sets up (activation, network activation, new site). */
        add_action('zappoppop_site_setup', array('ZapPopPop_Pro_DB', 'maybe_install'));
        add_filter('zappoppop_site_tables', array(__CLASS__, 'site_tables'));
    }

    /** Pro's table suffixes, dropped together with core's when a site is deleted from the network. */
    public static function site_tables($tables) {
        $tables   = (array) $tables;
        $tables[] = 'zappoppop_campaigns';
        $tables[] = 'zappoppop_stats_daily';
        $tables[] = 'zappoppop_wc_recover'; /* ZPP_WC_P4 */
        return $tables;
    }

    /** Activation callback: network activation creates the tables on every site. */
    public static function activate($network_wide = false) {
        if (is_multisite() && $network_wide) {
            self::each_site(array('ZapPopPop_Pro_DB', 'install'));
            return;
        }
        ZapPopPop_Pro_DB::install();
    }

    /** Deactivation callback: network deactivation clears the schedules of every site. */
    public static function deactivate($network_wide = false) {
        if (is_multisite() && $network_wide) {
            self::each_site(array(__CLASS__, 'clear_schedules'));
            return;
        }
        self::clear_schedules();
    }

    public static function clear_schedules() {
        wp_clear_scheduled_hook('zappoppop_tracking_daily');
        wp_clear_scheduled_hook('zappoppop_weekly_report');
        wp_clear_scheduled_hook('zappoppop_pro_license_check');
        wp_clear_scheduled_hook('zappoppop_wc_recover_send'); /* ZPP_WC_P4 */
    }

    /** Runs $callback(blog_id) inside every site, in batches. $args are passed to get_sites(). */
    public static function each_site($callback, array $args = array()) {
        if (!is_multisite()) {
            call_user_func($callback, get_current_blog_id());
            return;
        }
        $offset = 0;
        do {
            $ids = get_sites(array_merge($args, array(
                'fields'  => 'ids',
                'number'  => self::BATCH,
                'offset'  => $offset,
                'orderby' => 'id',
                'order'   => 'ASC',
            )));
            foreach ($ids as $blog_id) {
                switch_to_blog((int) $blog_id);
                try {
                    call_user_func($callback, (int) $blog_id);
                } finally {
                    restore_current_blog();
                }
            }
            $offset += self::BATCH;
        } while (count($ids) === self::BATCH);
    }
}
