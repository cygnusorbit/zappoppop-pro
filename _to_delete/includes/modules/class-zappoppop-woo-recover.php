<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abandoned-cart email (Pro, part of the WooCommerce Cart module). ZPP_WC_P4
 *
 * Flow: a visitor submits a popup form and ticks the consent checkbox -> their cart is saved with their email ->
 * after a delay one reminder email with a restore link goes out (unless they already ordered) -> the link refills the
 * cart. Nothing is stored without the consent checkbox. Every email carries an unsubscribe link, and an unsubscribed
 * address is never stored or emailed again. Personal data can be exported and erased through the WordPress privacy tools.
 *
 * Settings (flat keys inside settings.woo_cart, defaults never stored):
 *   rc_on, rc_delay (minutes), rc_consent (name of the form's consent checkbox field), rc_subject, rc_message, rc_coupon
 * Sending uses wp_mail() from one self-rescheduling WP-Cron event: no recurring job runs while there is nothing to send.
 * WP-Cron runs when the site gets visits; a site with DISABLE_WP_CRON needs a real cron calling wp-cron.php.
 */
class ZapPopPop_Woo_Recover {

    const VER   = '1';
    const OPT   = 'zappoppop_wc_recover_db';
    const HOOK  = 'zappoppop_wc_recover_send';
    const TTL   = 1209600;  /* restore link works for 14 days after the email */
    const KEEP  = 7776000;  /* finished rows are deleted after 90 days */
    const BATCH = 20;

    protected static $booted = false;

    public static function boot() {
        if (self::$booted) {
            return;
        }
        self::$booted = true;
        add_action('init', array(__CLASS__, 'maybe_install'));
        add_action('zappoppop_create_tables', array(__CLASS__, 'install'));
        add_action('zappoppop_site_setup', array(__CLASS__, 'maybe_install'));
        add_filter('zappoppop_site_tables', array(__CLASS__, 'site_tables'));
        add_action('zappoppop_submission_dispatch', array(__CLASS__, 'capture'), 20);
        add_action('zappoppop_after_confirmation', array(__CLASS__, 'confirmed'), 20);
        add_action(self::HOOK, array(__CLASS__, 'tick'));
        add_action('template_redirect', array(__CLASS__, 'links'), 1);
        add_filter('wp_privacy_personal_data_exporters', array(__CLASS__, 'register_exporter'));
        add_filter('wp_privacy_personal_data_erasers', array(__CLASS__, 'register_eraser'));
    }

    /* ---------------------------------------------------------------- settings */

    public static function defaults() {
        return array(
            'rc_on'      => false,
            'rc_delay'   => 60,
            'rc_consent' => 'consent',
            'rc_subject' => '',
            'rc_message' => '',
            'rc_coupon'  => '',
        );
    }

    /** Backend AJAX whitelist for the rc_* keys. Takes the raw woo_cart array, returns the clean rc_* keys. */
    public static function sanitize($in) {
        $d = self::defaults();
        $in = is_array($in) ? $in : array();
        $g = function ($k) use ($in, $d) {
            return array_key_exists($k, $in) ? $in[$k] : $d[$k];
        };
        $on = in_array($g('rc_on'), array(true, 1, '1', 'true', 'on'), true);
        $delay = $g('rc_delay');
        $delay = (is_scalar($delay) && is_numeric($delay)) ? max(15, min(4320, (int) round((float) $delay))) : 60;
        $consent = $g('rc_consent');
        $consent = is_string($consent) ? substr(sanitize_key($consent), 0, 60) : '';
        if ($consent === '') {
            $consent = 'consent';
        }
        $subject = $g('rc_subject');
        $subject = is_string($subject) ? substr(trim(preg_replace('/[\x00-\x1F\x7F<>]+/', '', wp_strip_all_tags($subject))), 0, 150) : '';
        $message = $g('rc_message');
        $message = is_string($message) ? substr(trim(sanitize_textarea_field($message)), 0, 1000) : '';
        $coupon = $g('rc_coupon');
        $coupon = is_string($coupon) ? substr(trim(preg_replace('/[\x00-\x1F\x7F<>"\']+/', '', wp_strip_all_tags($coupon))), 0, 80) : '';
        return array(
            'rc_on'      => $on,
            'rc_delay'   => $delay,
            'rc_consent' => $consent,
            'rc_subject' => $subject,
            'rc_message' => $message,
            'rc_coupon'  => $coupon,
        );
    }

    /** Clean woo_cart config of a popup when its abandoned-cart email is switched on, else null. */
    protected static function config($settings) {
        if (!class_exists('ZapPopPop_Woo_Cart') || !ZapPopPop_Woo_Cart::woo_active()) {
            return null;
        }
        $c = ZapPopPop_Woo_Cart::active(is_array($settings) ? $settings : array());
        return ($c !== null && !empty($c['rc_on'])) ? $c : null;
    }

    /* ---------------------------------------------------------------- database */

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'zappoppop_wc_recover';
    }

    public static function site_tables($tables) {
        $tables   = (array) $tables;
        $tables[] = 'zappoppop_wc_recover';
        return $tables;
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $cc = $wpdb->get_charset_collate();
        dbDelta('CREATE TABLE ' . self::table() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            popup_uid varchar(64) NOT NULL,
            submission_id bigint(20) unsigned NOT NULL DEFAULT 0,
            email varchar(190) NOT NULL,
            token varchar(32) NOT NULL,
            cart longtext NOT NULL,
            delay_min int(10) unsigned NOT NULL DEFAULT 60,
            status varchar(12) NOT NULL DEFAULT 'pending',
            created int(10) unsigned NOT NULL DEFAULT 0,
            send_at int(10) unsigned NOT NULL DEFAULT 0,
            sent_at int(10) unsigned NOT NULL DEFAULT 0,
            restored_at int(10) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY token (token),
            KEY status_send (status,send_at),
            KEY email (email)
        ) {$cc};");
        update_option(self::OPT, self::VER, false);
    }

    public static function maybe_install() {
        if (class_exists('ZapPopPop_Woo_Cart') && ZapPopPop_Woo_Cart::woo_active() && get_option(self::OPT) !== self::VER) {
            self::install();
        }
    }

    protected static function row_by_token($token) {
        global $wpdb;
        if (!is_string($token) || !preg_match('/^[A-Za-z0-9]{32}$/', $token)) {
            return null;
        }
        $t = self::table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- own table, value prepared.
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE token = %s", $token));
    }

    /** An address that unsubscribed is never stored or emailed again. */
    protected static function suppressed($email) {
        global $wpdb;
        $t = self::table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- own table, value prepared.
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE email = %s AND status = 'unsub' LIMIT 1", $email));
    }

    /* ---------------------------------------------------------------- capture */

    protected static function truthy($v) {
        return is_scalar($v) && !in_array(strtolower(trim((string) $v)), array('', '0', 'false', 'no', 'off'), true);
    }

    /** True only when the form posted the consent checkbox as ticked. */
    public static function consented($fields, $key) {
        if (!is_array($fields) || $key === '') {
            return false;
        }
        foreach ($fields as $k => $v) {
            if (sanitize_key($k) !== $key) {
                continue;
            }
            if (is_array($v)) {
                foreach ($v as $x) {
                    if (self::truthy($x)) {
                        return true;
                    }
                }
                return false;
            }
            return self::truthy($v);
        }
        return false;
    }

    /** The visitor's cart as plain ids and quantities (no prices, no personal data). */
    protected static function snapshot() {
        if (function_exists('wc_load_cart') && function_exists('WC') && null === WC()->cart) {
            wc_load_cart();
        }
        $cart = (function_exists('WC') && WC()) ? WC()->cart : null;
        if (!$cart) {
            return array();
        }
        $out = array();
        foreach ((array) $cart->get_cart() as $line) {
            if (empty($line['product_id']) || empty($line['quantity'])) {
                continue;
            }
            $attrs = array();
            if (!empty($line['variation']) && is_array($line['variation'])) {
                foreach ($line['variation'] as $k => $v) {
                    if (is_scalar($v)) {
                        $attrs[sanitize_text_field((string) $k)] = sanitize_text_field((string) $v);
                    }
                }
            }
            $out[] = array(
                'p' => (int) $line['product_id'],
                'v' => !empty($line['variation_id']) ? (int) $line['variation_id'] : 0,
                'q' => max(1, min(999, (int) $line['quantity'])),
                'a' => $attrs,
            );
            if (count($out) >= 50) {
                break;
            }
        }
        return $out;
    }

    /** Runs after the lead is stored. Saves the cart only for a valid email with the consent box ticked. */
    public static function capture($ctx) {
        global $wpdb;
        if (!is_array($ctx) || empty($ctx['uid'])) {
            return;
        }
        $settings = (isset($ctx['config']['settings']) && is_array($ctx['config']['settings'])) ? $ctx['config']['settings'] : array();
        $c = self::config($settings);
        if ($c === null) {
            return;
        }
        $email = isset($ctx['email']) ? strtolower(sanitize_email((string) $ctx['email'])) : '';
        if ($email === '' || !is_email($email) || strlen($email) > 190) {
            return;
        }
        if (!self::consented(isset($ctx['fields']) ? $ctx['fields'] : array(), $c['rc_consent'])) {
            return;
        }
        if (self::suppressed($email)) {
            return;
        }
        $lines = self::snapshot();
        if (!$lines) {
            return;
        }
        $uid = (string) $ctx['uid'];
        $t   = self::table();
        /* One open reminder per address and popup: a new submission replaces the older one. */
        $wpdb->delete($t, array('popup_uid' => $uid, 'email' => $email, 'status' => 'pending'), array('%s', '%s', '%s'));
        $wpdb->delete($t, array('popup_uid' => $uid, 'email' => $email, 'status' => 'await'), array('%s', '%s', '%s'));
        $now = time();
        $wpdb->insert($t, array(
            'popup_uid'     => $uid,
            'submission_id' => isset($ctx['submission_id']) ? (int) $ctx['submission_id'] : 0,
            'email'         => $email,
            'token'         => wp_generate_password(32, false),
            'cart'          => wp_json_encode($lines),
            'delay_min'     => (int) $c['rc_delay'],
            /* With Double Opt-In the reminder waits until the address is confirmed. */
            'status'        => !empty($ctx['double_optin']) ? 'await' : 'pending',
            'created'       => $now,
            'send_at'       => $now + ((int) $c['rc_delay'] * 60),
        ), array('%s', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d'));
        self::schedule_next();
    }

    /** Double Opt-In confirmed: start the delay now. */
    public static function confirmed($ctx) {
        global $wpdb;
        $sid = (is_array($ctx) && isset($ctx['submission_id'])) ? (int) $ctx['submission_id'] : 0;
        if ($sid < 1) {
            return;
        }
        $t = self::table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- own table, values prepared.
        $wpdb->query($wpdb->prepare("UPDATE {$t} SET status = 'pending', send_at = %d + (delay_min * 60) WHERE submission_id = %d AND status = 'await'", time(), $sid));
        self::schedule_next();
    }

    /* ---------------------------------------------------------------- sending */

    /** One WP-Cron event at the time the next reminder is due. */
    public static function schedule_next() {
        global $wpdb;
        $t = self::table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- own table, no input.
        $next = (int) $wpdb->get_var("SELECT MIN(send_at) FROM {$t} WHERE status = 'pending'");
        if ($next < 1) {
            return;
        }
        $when = max($next, time() + 60);
        $cur  = wp_next_scheduled(self::HOOK);
        if ($cur && $cur <= $when) {
            return;
        }
        if ($cur) {
            wp_unschedule_event($cur, self::HOOK);
        }
        wp_schedule_single_event($when, self::HOOK);
    }

    /** Cron callback: sends the reminders that are due, tidies old rows, plans the next run. */
    public static function tick() {
        global $wpdb;
        if (!class_exists('ZapPopPop_Woo_Cart') || !ZapPopPop_Woo_Cart::woo_active()) {
            return;
        }
        $t = self::table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- own table, values prepared.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t} WHERE status = 'pending' AND send_at <= %d ORDER BY send_at ASC LIMIT %d", time(), self::BATCH));
        foreach ((array) $rows as $row) {
            /* Claim the row first: two overlapping cron runs can never send the same email twice. */
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- own table, values prepared.
            $claimed = $wpdb->query($wpdb->prepare("UPDATE {$t} SET status = 'sending' WHERE id = %d AND status = 'pending'", (int) $row->id));
            if ((int) $claimed !== 1) {
                continue;
            }
            self::send_one($row);
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- own table, values prepared.
        $wpdb->query($wpdb->prepare("DELETE FROM {$t} WHERE status NOT IN ('pending', 'await', 'sending', 'unsub') AND created < %d", time() - self::KEEP));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- own table, values prepared.
        $wpdb->query($wpdb->prepare("DELETE FROM {$t} WHERE status = 'await' AND created < %d", time() - 604800));
        self::schedule_next();
    }

    protected static function set_status($id, $status, $extra = array()) {
        global $wpdb;
        $wpdb->update(self::table(), array_merge(array('status' => $status), $extra), array('id' => (int) $id));
    }

    /** True when an order from this address exists since the cart was saved. */
    protected static function ordered($email, $since) {
        if (!function_exists('wc_get_orders')) {
            return false;
        }
        $ids = wc_get_orders(array(
            'billing_email' => $email,
            'date_created'  => '>=' . (int) $since,
            'limit'         => 1,
            'return'        => 'ids',
            'status'        => array('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed'),
        ));
        return !empty($ids);
    }

    /** Cart lines that can still be bought: array of array(name, qty). */
    protected static function items($lines) {
        $out = array();
        foreach ((array) $lines as $l) {
            if (!is_array($l) || empty($l['p'])) {
                continue;
            }
            $p = wc_get_product(!empty($l['v']) ? (int) $l['v'] : (int) $l['p']);
            if (!$p || $p->get_status() !== 'publish' || !$p->is_purchasable()) {
                continue;
            }
            $out[] = array(wp_strip_all_tags((string) $p->get_name()), max(1, (int) $l['q']));
        }
        return $out;
    }

    protected static function send_one($row) {
        $settings = class_exists('ZapPopPop_Module_Bridge') ? ZapPopPop_Module_Bridge::stored_settings((string) $row->popup_uid) : array();
        $c = self::config($settings);
        if ($c === null) {
            self::set_status($row->id, 'cancelled');
            return;
        }
        if (self::ordered((string) $row->email, (int) $row->created)) {
            self::set_status($row->id, 'recovered');
            return;
        }
        $items = self::items(json_decode((string) $row->cart, true));
        if (!$items) {
            self::set_status($row->id, 'cancelled');
            return;
        }
        $restore = add_query_arg('zappoppop_restore', $row->token, home_url('/'));
        $unsub   = add_query_arg('zappoppop_unsub', $row->token, home_url('/'));
        $subject = $c['rc_subject'] !== '' ? $c['rc_subject'] : __('You left something in your cart', 'zappoppop-pro');
        $headers = array('Content-Type: text/html; charset=UTF-8', 'List-Unsubscribe: <' . esc_url_raw($unsub) . '>');
        $sent    = wp_mail((string) $row->email, wp_strip_all_tags($subject), self::email_html($c, $items, $restore, $unsub), $headers);
        self::set_status($row->id, $sent ? 'sent' : 'failed', array('sent_at' => time()));
    }

    protected static function email_html($c, $items, $restore, $unsub) {
        $site = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        $msg  = $c['rc_message'] !== '' ? $c['rc_message'] : __('Your cart is saved. Come back any time to finish your order.', 'zappoppop-pro');
        $li   = '';
        foreach ($items as $it) {
            $li .= '<li style="margin:0 0 4px">' . esc_html($it[0]) . ' &times; ' . (int) $it[1] . '</li>';
        }
        $coupon = '';
        if ($c['rc_coupon'] !== '') {
            /* translators: %s: coupon code */
            $coupon = '<p style="margin:16px 0;padding:10px 14px;background:#f6f7f7;border-radius:4px">' . sprintf(esc_html__('Use code %s at checkout.', 'zappoppop-pro'), '<strong>' . esc_html($c['rc_coupon']) . '</strong>') . '</p>';
        }
        return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;color:#1d2327;max-width:560px;margin:0 auto;padding:16px">'
            . '<h2 style="margin:0 0 12px;font-size:20px">' . esc_html($site) . '</h2>'
            . '<p style="margin:0 0 14px">' . nl2br(esc_html($msg)) . '</p>'
            . '<ul style="margin:0 0 14px;padding-left:20px">' . $li . '</ul>'
            . $coupon
            . '<p style="margin:20px 0"><a href="' . esc_url($restore) . '" style="display:inline-block;padding:11px 20px;background:#b4234f;color:#fff;text-decoration:none;border-radius:4px;font-weight:bold">' . esc_html__('Return to your cart', 'zappoppop-pro') . '</a></p>'
            . '<p style="margin:24px 0 0;font-size:12px;color:#646970">'
            /* translators: %s: site name */
            . sprintf(esc_html__('You are receiving this because you asked for a cart reminder on %s.', 'zappoppop-pro'), esc_html($site))
            . ' <a href="' . esc_url($unsub) . '" style="color:#646970">' . esc_html__('Unsubscribe', 'zappoppop-pro') . '</a></p>'
            . '</div>';
    }

    /* ---------------------------------------------------------------- restore + unsubscribe links */

    public static function links() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- links in an email: the unguessable token is the credential.
        if (isset($_GET['zappoppop_restore']) || isset($_GET['zpp_restore'])) { /* zpp_restore: links in emails sent by earlier versions */
            $zappoppop_tok = isset($_GET['zappoppop_restore']) ? $_GET['zappoppop_restore'] : $_GET['zpp_restore'];
            $url = self::restore(sanitize_text_field(wp_unslash($zappoppop_tok)));
            nocache_headers();
            wp_safe_redirect($url);
            exit;
        }
        if (isset($_GET['zappoppop_unsub']) || isset($_GET['zpp_unsub'])) { /* zpp_unsub: links in emails sent by earlier versions */
            $zappoppop_tok = isset($_GET['zappoppop_unsub']) ? $_GET['zappoppop_unsub'] : $_GET['zpp_unsub'];
            $ok = self::unsubscribe(sanitize_text_field(wp_unslash($zappoppop_tok)));
            nocache_headers();
            wp_die(
                esc_html($ok ? __('You have been unsubscribed. We will not send you cart reminders again.', 'zappoppop-pro') : __('This unsubscribe link is not valid.', 'zappoppop-pro')),
                esc_html__('Unsubscribe', 'zappoppop-pro'),
                array('response' => $ok ? 200 : 404)
            );
        }
        // phpcs:enable
    }

    /** Refills the visitor's cart from the saved snapshot. Returns the URL to send them to. */
    public static function restore($token) {
        $home = home_url('/');
        if (!class_exists('ZapPopPop_Woo_Cart') || !ZapPopPop_Woo_Cart::woo_active()) {
            return $home;
        }
        $row = self::row_by_token($token);
        if (!$row || !in_array($row->status, array('sent', 'restored'), true) || (int) $row->sent_at < time() - self::TTL) {
            return $home;
        }
        if (function_exists('wc_load_cart') && null === WC()->cart) {
            wc_load_cart();
        }
        $cart = WC()->cart;
        if (!$cart) {
            return $home;
        }
        if (WC()->session && method_exists(WC()->session, 'has_session') && !WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }
        foreach ((array) json_decode((string) $row->cart, true) as $l) {
            if (!is_array($l) || empty($l['p'])) {
                continue;
            }
            $pid   = (int) $l['p'];
            $vid   = !empty($l['v']) ? (int) $l['v'] : 0;
            $attrs = (!empty($l['a']) && is_array($l['a'])) ? $l['a'] : array();
            $p     = wc_get_product($vid ? $vid : $pid);
            if (!$p || $p->get_status() !== 'publish' || !$p->is_purchasable() || !$p->is_in_stock()) {
                continue;
            }
            if ($cart->find_product_in_cart($cart->generate_cart_id($pid, $vid, $attrs))) {
                continue;
            }
            $cart->add_to_cart($pid, max(1, (int) $l['q']), $vid, $attrs);
        }
        $c = self::config(class_exists('ZapPopPop_Module_Bridge') ? ZapPopPop_Module_Bridge::stored_settings((string) $row->popup_uid) : array());
        if ($c !== null && $c['rc_coupon'] !== '') {
            $code = wc_format_coupon_code($c['rc_coupon']);
            if (!$cart->has_discount($code)) {
                $cart->add_discount($code);
            }
        }
        wc_clear_notices();
        self::set_status($row->id, 'restored', array('restored_at' => time()));
        return wc_get_cart_url();
    }

    /** Marks the address as unsubscribed (kept as a do-not-email record). */
    public static function unsubscribe($token) {
        global $wpdb;
        $row = self::row_by_token($token);
        if (!$row) {
            return false;
        }
        $t = self::table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- own table, values prepared.
        $wpdb->query($wpdb->prepare("UPDATE {$t} SET status = 'unsub' WHERE email = %s AND status IN ('pending', 'await', 'sending', 'sent', 'restored', 'recovered', 'failed', 'cancelled')", $row->email));
        self::set_status($row->id, 'unsub');
        return true;
    }

    /* ---------------------------------------------------------------- privacy tools (Tools > Export / Erase Personal Data) */

    public static function register_exporter($exporters) {
        $exporters['zappoppop-wc-recover'] = array(
            'exporter_friendly_name' => __('ZapPopPop abandoned cart emails', 'zappoppop-pro'),
            'callback'               => array(__CLASS__, 'export'),
        );
        return $exporters;
    }

    public static function register_eraser($erasers) {
        $erasers['zappoppop-wc-recover'] = array(
            'eraser_friendly_name' => __('ZapPopPop abandoned cart emails', 'zappoppop-pro'),
            'callback'             => array(__CLASS__, 'erase'),
        );
        return $erasers;
    }

    public static function export($email, $page = 1) {
        global $wpdb;
        $t = self::table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- own table, value prepared.
        $rows  = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t} WHERE email = %s", strtolower((string) $email)));
        $items = array();
        foreach ((array) $rows as $r) {
            $cart    = json_decode((string) $r->cart, true);
            $items[] = array(
                'group_id'    => 'zappoppop-wc-recover',
                'group_label' => __('Abandoned cart emails', 'zappoppop-pro'),
                'item_id'     => 'zappoppop-wc-recover-' . (int) $r->id,
                'data'        => array(
                    array('name' => __('Email', 'zappoppop-pro'), 'value' => (string) $r->email),
                    array('name' => __('Status', 'zappoppop-pro'), 'value' => (string) $r->status),
                    array('name' => __('Saved on', 'zappoppop-pro'), 'value' => gmdate('Y-m-d H:i', (int) $r->created) . ' UTC'),
                    array('name' => __('Cart lines', 'zappoppop-pro'), 'value' => is_array($cart) ? (string) count($cart) : '0'),
                ),
            );
        }
        return array('data' => $items, 'done' => true);
    }

    public static function erase($email, $page = 1) {
        global $wpdb;
        $t     = self::table();
        $email = strtolower((string) $email);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- own table, value prepared.
        $removed = (int) $wpdb->query($wpdb->prepare("DELETE FROM {$t} WHERE email = %s AND status <> 'unsub'", $email));
        /* An unsubscribe record must survive, or the address could be emailed again: its cart and token are wiped. */
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- own table, value prepared.
        $kept = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$t} WHERE email = %s AND status = 'unsub'", $email));
        foreach ((array) $kept as $id) {
            $wpdb->update($t, array('cart' => '', 'token' => wp_generate_password(32, false)), array('id' => (int) $id));
        }
        return array(
            'items_removed'  => $removed > 0,
            'items_retained' => !empty($kept),
            'messages'       => !empty($kept) ? array(__('The address is kept on a do-not-email list because the person unsubscribed.', 'zappoppop-pro')) : array(),
            'done'           => true,
        );
    }
}
