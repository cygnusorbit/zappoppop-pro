<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * A/B Campaigns: menu, page, assets, shortcode and the hooks that keep campaign data consistent
 * (popup uid renames, export / import, tools tables, daily auto-promote).
 *
 * Policy: data and visitor-facing behaviour keep working whenever Pro is active. Only the admin
 * page and its AJAX endpoints need an active licence.
 */
class ZapPopPop_Pro_AB {

    const PAGE = 'zappoppop-ab';

    protected static $hook = '';

    public static function boot() {
        new ZapPopPop_Front_Ab();

        add_action('zappoppop_popup_uid_renamed', array(__CLASS__, 'on_uid_renamed'), 10, 2);
        add_action('zappoppop_tracking_daily', array(__CLASS__, 'auto_promote'));
        add_filter('zappoppop_export_data', array(__CLASS__, 'export_data'));
        add_filter('zappoppop_import_has_data', array(__CLASS__, 'import_has_data'), 10, 2);
        add_filter('zappoppop_import_extra', array(__CLASS__, 'import_extra'), 10, 4);
        add_filter('zappoppop_tools_tables', array(__CLASS__, 'tools_tables'));
        add_filter('zappoppop_system_report_lines', array(__CLASS__, 'report_lines'));
        add_filter('zappoppop_bar_links', array(__CLASS__, 'bar_links'));

        if (is_admin()) {
            add_action('zappoppop_admin_menu', array(__CLASS__, 'menu'));
            add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
            if (ZapPopPop_Pro_Gate::unlocked()) {
                new ZapPopPop_Ajax_Campaigns();
            }
        }
    }

    /* ------------------------------------------------------------ admin page */
    public static function menu($parent) {
        self::$hook = add_submenu_page(
            $parent,
            __('A/B Campaigns', 'zappoppop-pro'),
            __('A/B Campaigns', 'zappoppop-pro'),
            'manage_options',
            self::PAGE,
            array(__CLASS__, 'render'),
            3
        );
    }

    public static function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'zappoppop-pro'));
        }
        if (!ZapPopPop_Pro_Gate::unlocked()) {
            ZapPopPop_Pro_Gate::locked_screen(
                __('A/B Campaigns', 'zappoppop-pro'),
                'dashicons-randomize',
                __('Split-test popup variations and keep the winner. Activate your ZapPopPop Pro licence to manage A/B campaigns.', 'zappoppop-pro')
            );
            return;
        }
        global $wpdb;
        $ab_state  = ZapPopPop_Admin_AB_Query::run();
        $campaigns = $ab_state['items'];
        include ZAPPOPPOP_PRO_PATH . 'templates/ab-view.php';
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
        wp_enqueue_style('zappoppop-dashboard-css', ZAPPOPPOP_URL . 'admin/css/dashboard.css', array(), $cv);
        wp_enqueue_style('zappoppop-overview-css', ZAPPOPPOP_URL . 'admin/css/overview.css', array(), $cv);
        wp_enqueue_style('zappoppop-ab-css', ZAPPOPPOP_PRO_URL . 'assets/css/ab.css', array('zappoppop-dashboard-css', 'zappoppop-overview-css'), $pv);
        wp_enqueue_script('zappoppop-overview-js', ZAPPOPPOP_URL . 'admin/js/overview.js', array('jquery'), $cv, true);
        wp_enqueue_script('zappoppop-ab-modal-js', ZAPPOPPOP_PRO_URL . 'assets/js/ab/ab-modal.js', array('jquery'), $pv, true);
        wp_enqueue_script('zappoppop-ab-actions-js', ZAPPOPPOP_PRO_URL . 'assets/js/ab/ab-actions.js', array('jquery'), $pv, true);
        wp_enqueue_script('zappoppop-ab-js', ZAPPOPPOP_PRO_URL . 'assets/js/ab.js', array('jquery', 'zappoppop-ab-modal-js', 'zappoppop-ab-actions-js'), $pv, true);
        wp_localize_script('zappoppop-ab-js', 'zappoppop_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('zappoppop_admin_nonce'),
        ));
        wp_enqueue_script('zappoppop-ab-edit-js', ZAPPOPPOP_PRO_URL . 'assets/js/ab/ab-edit.js', array('jquery', 'zappoppop-ab-js'), $pv, true);
        wp_enqueue_script('zappoppop-ab-trash-js', ZAPPOPPOP_PRO_URL . 'assets/js/ab/ab-trash.js', array('jquery', 'zappoppop-ab-js'), $pv, true);
    }

    public static function bar_links($links) {
        return ZapPopPop_Pro_Gate::insert_link($links, 'zappoppop-builder', array(self::PAGE, __('A/B Campaigns', 'zappoppop-pro'), 'dashicons-randomize'));
    }

    /* ----------------------------------------------------- data consistency */
    /** A renamed popup keeps its place inside every campaign. */
    public static function on_uid_renamed($old, $new) {
        global $wpdb;
        $table = ZapPopPop_Pro_DB::campaigns();
        $rows  = $wpdb->get_results("SELECT id, popup_uids FROM {$table}", ARRAY_A);
        foreach ((array) $rows as $r) {
            $list = json_decode($r['popup_uids'], true);
            if (is_array($list) && in_array($old, $list, true)) {
                foreach ($list as $k => $v) {
                    if ($v === $old) {
                        $list[$k] = $new;
                    }
                }
                $wpdb->update($table, array('popup_uids' => wp_json_encode(array_values($list))), array('id' => (int) $r['id']), array('%s'), array('%d'));
            }
        }
        if (class_exists('ZapPopPop_Front_Ab')) {
            ZapPopPop_Front_Ab::reset_cache();
        }
    }

    public static function export_data($data) {
        global $wpdb;
        $table = ZapPopPop_Pro_DB::campaigns();
        $rows  = $wpdb->get_results("SELECT uid, title, popup_uids, status FROM {$table} ORDER BY id ASC", ARRAY_A);
        $data['campaigns'] = $rows ? $rows : array();
        return $data;
    }

    public static function import_has_data($has, $decoded) {
        return $has || (is_array($decoded) && !empty($decoded['campaigns']));
    }

    /** Restores campaigns from a backup. A WP_Error makes core roll the whole import back. */
    public static function import_extra($extra, $decoded, $uid_map, $dry_run) {
        global $wpdb;
        $out = is_array($extra) ? $extra : array('campaigns' => 0, 'skipped' => 0);
        if (empty($decoded['campaigns']) || !is_array($decoded['campaigns'])) {
            return $out;
        }
        $table = ZapPopPop_Pro_DB::campaigns();
        foreach ($decoded['campaigns'] as $camp) {
            if (!is_array($camp)) {
                $out['skipped']++;
                continue;
            }
            $title = isset($camp['title']) ? sanitize_text_field($camp['title']) : '';
            if ($title === '') {
                $title = __('Restored A/B Test', 'zappoppop-pro');
            }
            $uids = isset($camp['popup_uids']) ? $camp['popup_uids'] : array();
            if (is_string($uids)) {
                $uids = json_decode($uids, true);
            }
            $remap = array();
            if (is_array($uids)) {
                foreach ($uids as $u) {
                    $u       = sanitize_key($u);
                    $remap[] = isset($uid_map[$u]) ? $uid_map[$u] : $u;
                }
            }
            $cuid = isset($camp['uid']) ? sanitize_key($camp['uid']) : '';
            if ($cuid === '' || $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE uid = %s", $cuid))) {
                $cuid = wp_generate_uuid4();
            }
            $cstatus = isset($camp['status']) ? sanitize_key($camp['status']) : 'active';
            if (!$dry_run) {
                $ok = $wpdb->insert($table, array(
                    'uid'        => $cuid,
                    'title'      => $title,
                    'popup_uids' => wp_json_encode(array_values($remap)),
                    'status'     => $cstatus ? $cstatus : 'active',
                ), array('%s', '%s', '%s', '%s'));
                if ($ok === false) {
                    return new WP_Error('db', __('Database error during restore. No changes were kept.', 'zappoppop-pro'));
                }
            }
            $out['campaigns']++;
        }
        return $out;
    }

    public static function tools_tables($defs) {
        $new = array();
        foreach ((array) $defs as $k => $d) {
            $new[$k] = $d;
            if ($k === 'submissions') {
                $new['campaigns'] = array('label' => __('A/B Experiments', 'zappoppop-pro'), 'name' => ZapPopPop_Pro_DB::campaigns());
            }
        }
        if (!isset($new['campaigns'])) {
            $new['campaigns'] = array('label' => __('A/B Experiments', 'zappoppop-pro'), 'name' => ZapPopPop_Pro_DB::campaigns());
        }
        return $new;
    }

    public static function report_lines($lines) {
        global $wpdb;
        $table = ZapPopPop_Pro_DB::campaigns();
        $n     = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        return (string) $lines . '- A/B Split Campaigns: ' . $n . "\n";
    }

    /* ---------------------------------------------------------- auto-promote */
    protected static function z_score($s1, $n1, $s2, $n2) {
        if ($n1 < 1 || $n2 < 1) {
            return 0;
        }
        $p  = ($s1 + $s2) / ($n1 + $n2);
        $se = sqrt($p * (1 - $p) * (1 / $n1 + 1 / $n2));
        return $se > 0 ? (($s1 / $n1) - ($s2 / $n2)) / $se : 0;
    }

    /** Keeps only the winner once every variant has the minimum impressions and the lead is significant. */
    public static function auto_promote() {
        if (!ZapPopPop_Tracking::get('ab_auto_promote')) {
            return;
        }
        global $wpdb;
        $min   = (int) ZapPopPop_Tracking::get('ab_promote_min_impr');
        $zmap  = array(90 => 1.645, 95 => 1.96, 99 => 2.576);
        $conf  = (int) ZapPopPop_Tracking::get('ab_promote_confidence');
        $thr   = isset($zmap[$conf]) ? $zmap[$conf] : 1.96;
        $table = ZapPopPop_Pro_DB::campaigns();
        $camps = $wpdb->get_results("SELECT id, uid, popup_uids FROM {$table} WHERE LOWER(status) = 'active'");
        foreach ((array) $camps as $c) {
            $uids = array_values(array_filter(array_map('sanitize_key', (array) json_decode($c->popup_uids, true))));
            if (count($uids) < 2) {
                continue;
            }
            $ph   = implode(',', array_fill(0, count($uids), '%s'));
            $rows = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                "SELECT uid, impressions, submissions FROM {$wpdb->prefix}zappoppop_items WHERE uid IN ($ph)",
                $uids
            ));
            if (count($rows) < 2) {
                continue;
            }
            $ok    = true;
            $stats = array();
            foreach ($rows as $r) {
                if ((int) $r->impressions < $min) {
                    $ok = false;
                    break;
                }
                $stats[] = array('uid' => $r->uid, 'n' => (int) $r->impressions, 's' => (int) $r->submissions);
            }
            if (!$ok) {
                continue;
            }
            usort($stats, function ($a, $b) {
                return ($b['s'] / $b['n']) <=> ($a['s'] / $a['n']);
            });
            if (self::z_score($stats[0]['s'], $stats[0]['n'], $stats[1]['s'], $stats[1]['n']) >= $thr) {
                $wpdb->update($table, array('popup_uids' => wp_json_encode(array($stats[0]['uid']))), array('id' => (int) $c->id), array('%s'), array('%d'));
                if (function_exists('zappoppop_log_event')) {
                    zappoppop_log_event('ab_auto_promote', 'A/B winner promoted: ' . $stats[0]['uid'], array('campaign' => $c->uid, 'stats' => $stats));
                }
            }
        }
    }
}
