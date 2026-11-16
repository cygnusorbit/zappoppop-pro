<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Statistics: menu, page, assets, daily impression counters, retention purge and the weekly email report.
 *
 * Policy: counters, device capture and scheduled jobs run whenever Pro is active.
 * Only the admin page and its AJAX endpoints need an active licence.
 */
class ZapPopPop_Pro_Stats {

    const PAGE = 'zappoppop-stats';

    protected static $hook = '';

    public static function table() {
        return ZapPopPop_Pro_DB::daily();
    }

    public static function boot() {
        ZapPopPop_Admin_Stats::boot();

        add_action('zappoppop_impression_recorded', array(__CLASS__, 'on_impression'));
        add_action('zappoppop_submission_saved', array(__CLASS__, 'on_submission'));
        add_filter('cron_schedules', array(__CLASS__, 'cron_schedules'));
        add_action('init', array(__CLASS__, 'sync_schedules'));
        add_action('zappoppop_tracking_daily', array(__CLASS__, 'run_daily'));
        add_action('zappoppop_weekly_report', array(__CLASS__, 'send_weekly_report'));
        add_filter('zappoppop_tools_tables', array(__CLASS__, 'tools_tables'));
        add_filter('zappoppop_bar_links', array(__CLASS__, 'bar_links'));

        if (is_admin()) {
            add_action('zappoppop_admin_menu', array(__CLASS__, 'menu'));
            add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook('zappoppop_tracking_daily');
        wp_clear_scheduled_hook('zappoppop_weekly_report');
    }

    /* ------------------------------------------------------------ admin page */
    public static function menu($parent) {
        self::$hook = add_submenu_page(
            $parent,
            __('Statistics', 'zappoppop-pro'),
            __('Statistics', 'zappoppop-pro'),
            'manage_options',
            self::PAGE,
            array(__CLASS__, 'render'),
            6
        );
    }

    public static function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'zappoppop-pro'));
        }
        if (!ZapPopPop_Pro_Gate::unlocked()) {
            ZapPopPop_Pro_Gate::locked_screen(
                __('Statistics', 'zappoppop-pro'),
                'dashicons-chart-bar',
                __('Track impressions, leads and conversion rates over time. Activate your ZapPopPop Pro licence to open the Statistics dashboard.', 'zappoppop-pro')
            );
            return;
        }
        include ZAPPOPPOP_PRO_PATH . 'templates/stats-view.php';
    }

    public static function assets($hook) {
        if ($hook !== self::$hook || self::$hook === '') {
            return;
        }
        if (!ZapPopPop_Pro_Gate::unlocked()) {
            ZapPopPop_Pro_Admin::enqueue_shell();
            return;
        }
        $cv = ZAPPOPPOP_VERSION;
        $pv = ZAPPOPPOP_PRO_VERSION;
        wp_enqueue_style('dashicons');
        wp_enqueue_style('zappoppop-overview-css', ZAPPOPPOP_URL . 'admin/css/overview.css', array(), $cv);
        wp_enqueue_script('zappoppop-overview-js', ZAPPOPPOP_URL . 'admin/js/overview.js', array('jquery'), $cv, true);
        $deps = array('zappoppop-overview-css');
        if (wp_style_is('zappoppop-theme-global-css', 'enqueued') || wp_style_is('zappoppop-theme-global-css', 'registered')) {
            $deps[] = 'zappoppop-theme-global-css';
        }
        wp_enqueue_style('zappoppop-stats-sync-css', ZAPPOPPOP_PRO_URL . 'assets/css/stats-sync.css', $deps, $pv);
        wp_enqueue_script('zappoppop-stats-js', ZAPPOPPOP_PRO_URL . 'assets/js/stats.js', array('jquery'), $pv, true);
        wp_localize_script('zappoppop-stats-js', 'zappoppop_stats_vars', array(
            'ajax_url'   => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('zappoppop_admin_nonce'),
            'max_series' => ZapPopPop_Admin_Stats::MAX_SERIES,
            /* translators: %d: maximum number of chart series. */
            'max_msg'    => sprintf(__('Maximum of %d series reached', 'zappoppop-pro'), ZapPopPop_Admin_Stats::MAX_SERIES),
        ));
    }

    public static function bar_links($links) {
        return ZapPopPop_Pro_Gate::insert_link($links, 'zappoppop-log', array(self::PAGE, __('Statistics', 'zappoppop-pro'), 'dashicons-chart-bar'));
    }

    public static function tools_tables($defs) {
        $defs['stats_daily'] = array('label' => __('Daily Impressions', 'zappoppop-pro'), 'name' => self::table());
        return $defs;
    }

    /* ------------------------------------------------------------- recording */
    public static function on_impression($uid) {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            'INSERT INTO ' . self::table() . ' (stat_date, popup_uid, impressions) VALUES (%s, %s, 1)
             ON DUPLICATE KEY UPDATE impressions = impressions + 1',
            current_time('Y-m-d'),
            $uid
        ));
    }

    public static function on_submission($submission_id) {
        ZapPopPop_Admin_Stats::record_device($submission_id);
    }

    /* ------------------------------------------------------------ scheduling */
    public static function cron_schedules($s) {
        if (!isset($s['weekly'])) {
            $s['weekly'] = array('interval' => WEEK_IN_SECONDS, 'display' => __('Once Weekly', 'zappoppop-pro'));
        }
        return $s;
    }

    public static function sync_schedules() {
        if (!wp_next_scheduled('zappoppop_tracking_daily')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'zappoppop_tracking_daily');
        }
        $on   = (bool) ZapPopPop_Tracking::get('report_enabled');
        $next = wp_next_scheduled('zappoppop_weekly_report');
        if ($on && !$next) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'weekly', 'zappoppop_weekly_report');
        } elseif (!$on && $next) {
            wp_unschedule_event($next, 'zappoppop_weekly_report');
        }
    }

    public static function run_daily() {
        global $wpdb;
        $days = (int) ZapPopPop_Tracking::get('track_retention_days');
        if ($days > 0) {
            $cut = gmdate('Y-m-d', current_time('timestamp') - $days * DAY_IN_SECONDS);
            $wpdb->query($wpdb->prepare('DELETE FROM ' . self::table() . ' WHERE stat_date < %s', $cut));
        }
    }

    public static function send_weekly_report() {
        if (!ZapPopPop_Tracking::get('report_enabled')) {
            return;
        }
        global $wpdb;
        $to = sanitize_email((string) ZapPopPop_Tracking::get('report_email'));
        if (!is_email($to)) {
            $to = get_option('admin_email');
        }
        $since_d = gmdate('Y-m-d', current_time('timestamp') - 7 * DAY_IN_SECONDS);
        $since_t = $since_d . ' 00:00:00';
        $impr    = $wpdb->get_results($wpdb->prepare('SELECT popup_uid, SUM(impressions) AS n FROM ' . self::table() . ' WHERE stat_date >= %s GROUP BY popup_uid', $since_d), OBJECT_K);
        $leads   = $wpdb->get_results($wpdb->prepare("SELECT popup_uid, COUNT(id) AS n FROM {$wpdb->prefix}zappoppop_submissions WHERE created_at >= %s GROUP BY popup_uid", $since_t), OBJECT_K);
        $items   = $wpdb->get_results("SELECT uid, title FROM {$wpdb->prefix}zappoppop_items WHERE status != 'trash'");
        $lines   = array();
        $ti      = 0;
        $tl      = 0;
        foreach ((array) $items as $it) {
            $i = isset($impr[$it->uid]) ? (int) $impr[$it->uid]->n : 0;
            $l = isset($leads[$it->uid]) ? (int) $leads[$it->uid]->n : 0;
            if ($i === 0 && $l === 0) {
                continue;
            }
            $ti     += $i;
            $tl     += $l;
            $lines[] = sprintf('%s: %d impressions, %d leads, %s%% CR', $it->title, $i, $l, $i > 0 ? round($l / $i * 100, 1) : 0);
        }
        $body = sprintf(
            "ZapPopPop weekly report (since %s)\n\nTotal impressions: %d\nTotal leads: %d\nConversion rate: %s%%\n\n%s\n",
            $since_d,
            $ti,
            $tl,
            $ti > 0 ? round($tl / $ti * 100, 1) : 0,
            $lines ? implode("\n", $lines) : __('No activity recorded.', 'zappoppop-pro')
        );
        wp_mail($to, sprintf('[%s] ZapPopPop weekly report', get_bloginfo('name')), $body);
    }
}
