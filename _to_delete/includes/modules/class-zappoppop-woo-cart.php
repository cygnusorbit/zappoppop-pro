<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce Cart module (Pro). ZPP_WC_CART
 * Cart-aware display rules, a cart-state bridge for the front end and live cart text tokens.
 *
 * Shape: settings.woo_cart = {
 *   enabled, cart (any|has_items|empty), min_items, min_total, max_total,
 *   products[] (product / variation ids), cats[] (product_cat term ids), show_on_add, free_ship_min
 * }
 * Defaults are never stored. A popup behaves exactly as before unless this module is installed, switched on in
 * Settings > Modules and enabled for the popup. Rules are evaluated in the browser (front-woo-cart.js) from a
 * no-cache WooCommerce endpoint, so a full-page cache can never show one visitor's cart to another.
 * No WooCommerce code lives in core: everything attaches through core filters and actions.
 */
class ZapPopPop_Woo_Cart {

    const MODULE_KEY = 'acc-woo-cart';
    const KEY        = 'woo_cart';
    const ENDPOINT   = 'zappoppop_wc_cart_state';
    const APPLY      = 'zappoppop_wc_apply_coupon'; /* ZPP_WC_P2 */
    const ADD_UP     = 'zappoppop_wc_add_upsell'; /* ZPP_WC_P3 */
    const PAGES      = array('any', 'product', 'selected');
    const UMODES     = array('fixed', 'auto');
    const CARTS      = array('any', 'has_items', 'empty');
    const MAX_AMOUNT = 9999999;

    protected static $booted = false;

    public static function boot() {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        /* Feature compatibility is declared whether or not the module is switched on. */
        if (did_action('before_woocommerce_init')) {
            self::declare_compat();
        } else {
            add_action('before_woocommerce_init', array(__CLASS__, 'declare_compat'));
        }

        add_filter('zappoppop_extension_settings_keys', array(__CLASS__, 'extension_keys'));
        add_filter('zappoppop_handled_settings_keys', array(__CLASS__, 'handled_keys'));
        add_filter('zappoppop_module_adoptable_keys', array(__CLASS__, 'adoptable_keys'));
        add_filter('zappoppop_sanitize_popup_settings', array(__CLASS__, 'sanitize_settings'), 11, 2);
        add_filter('zappoppop_popup_wrapper_attrs', array(__CLASS__, 'wrapper_attrs'), 11, 3);
        add_action('zappoppop_front_enqueue', array(__CLASS__, 'front_assets'));
        add_filter('zappoppop_front_vars', array(__CLASS__, 'front_vars'));
        add_action('zappoppop_admin_builder_assets', array(__CLASS__, 'localize_builder'), 21);
        add_action('wc_ajax_' . self::ENDPOINT, array(__CLASS__, 'ajax_cart_state'));
        add_action('wc_ajax_' . self::APPLY, array(__CLASS__, 'ajax_apply_coupon'));
        add_filter('zappoppop_progress_modes', array(__CLASS__, 'progress_modes'));
        add_action('wc_ajax_' . self::ADD_UP, array(__CLASS__, 'ajax_add_upsell'));
        add_filter('zappoppop_popup_eligible', array(__CLASS__, 'eligible'), 12, 3);
        ZapPopPop_Woo_Recover::boot(); /* ZPP_WC_P4 */
    }

    /* ---------------------------------------------------------------- WooCommerce compatibility */

    public static function declare_compat() {
        if (!class_exists('Automattic\WooCommerce\Utilities\FeaturesUtil') || !defined('ZAPPOPPOP_PRO_FILE')) {
            return;
        }
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', ZAPPOPPOP_PRO_FILE, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', ZAPPOPPOP_PRO_FILE, true);
    }

    public static function woo_active() {
        return class_exists('WooCommerce') && function_exists('WC');
    }

    /** Settings > Modules switch. A module that is switched off or deleted never affects the front end. */
    public static function module_active() {
        if (!class_exists('ZapPopPop_Modules')) {
            return true;
        }
        /* Not registered: the bundled package is seeded on the first admin load, so stay on until then. A deleted module is off. */
        if (!ZapPopPop_Modules::get(self::MODULE_KEY)) {
            $orphans = ZapPopPop_Modules::orphans();
            return !isset($orphans[self::MODULE_KEY]);
        }
        if (!class_exists('ZapPopPop_Admin_Settings')) {
            require_once ZAPPOPPOP_PATH . 'includes/admin/class-admin-settings.php';
        }
        return ZapPopPop_Admin_Settings::module_enabled(self::MODULE_KEY);
    }

    /* ---------------------------------------------------------------- model */

    public static function defaults() {
        return array(
            'enabled'       => false,
            'cart'          => 'any',
            'min_items'     => 0,
            'min_total'     => 0,
            'max_total'     => 0,
            'products'      => array(),
            'cats'          => array(),
            'show_on_add'   => false,
            'free_ship_min' => 0,
            'coupon_on'     => false,
            'coupon_code'   => '',
            'coupon_apply'  => 'open',
            'page'          => 'any',
            'page_products' => array(),
            'page_cats'     => array(),
            'upsell_on'     => false,
            'upsell_mode'   => 'fixed',
            'upsell_product' => 0,
        ) + ZapPopPop_Woo_Recover::defaults();
    }

    private static function flag($v) {
        return in_array($v, array(true, 1, '1', 'true', 'on'), true);
    }

    private static function num($v, $min, $max) {
        if (!is_scalar($v) || !is_numeric($v)) {
            return $min;
        }
        return max($min, min($max, (int) round((float) $v)));
    }

    /** Money-like amount, 0 = off. Whole numbers stay integers so a stored default compares equal to the factory default. */
    private static function money($v) {
        if (!is_scalar($v) || !is_numeric($v)) {
            return 0;
        }
        $n = round(max(0, min((float) self::MAX_AMOUNT, (float) $v)), 2);
        return ($n == floor($n)) ? (int) $n : $n;
    }

    private static function ids($v) {
        $out = array();
        if (!is_array($v)) {
            return $out;
        }
        foreach (array_slice($v, 0, 500) as $x) {
            if (is_scalar($x)) {
                $n = absint($x);
                if ($n) {
                    $out[$n] = $n;
                }
            }
            if (count($out) >= 200) {
                break;
            }
        }
        return array_values($out);
    }

    /** Backend AJAX whitelist. Returns a clean array. */
    public static function sanitize($raw) {
        $in = json_decode(wp_json_encode($raw), true);
        if (!is_array($in)) {
            $in = array();
        }
        $d = self::defaults();
        $g = function ($k) use ($in, $d) {
            return array_key_exists($k, $in) ? $in[$k] : $d[$k];
        };
        $cart = $g('cart');
        $cart = (is_string($cart) && in_array($cart, self::CARTS, true)) ? $cart : 'any';
        $min  = self::money($g('min_total'));
        $max  = self::money($g('max_total'));
        if ($min > 0 && $max > 0 && $min > $max) {
            $t   = $min;
            $min = $max;
            $max = $t;
        }
        $code  = $g('coupon_code');
        $code  = is_string($code) ? substr(trim(preg_replace('/[\x00-\x1F\x7F<>"\']+/', '', wp_strip_all_tags($code))), 0, 80) : '';
        $apply = $g('coupon_apply');
        $apply = (is_string($apply) && in_array($apply, array('open', 'click'), true)) ? $apply : 'open';
        $page  = $g('page');
        $page  = (is_string($page) && in_array($page, self::PAGES, true)) ? $page : 'any';
        $umode = $g('upsell_mode');
        $umode = (is_string($umode) && in_array($umode, self::UMODES, true)) ? $umode : 'fixed';
        $uprod = $g('upsell_product');
        $uprod = (is_scalar($uprod) && is_numeric($uprod)) ? min(999999999, absint($uprod)) : 0;
        return array(
            'enabled'       => self::flag($g('enabled')),
            'cart'          => $cart,
            'min_items'     => self::num($g('min_items'), 0, 9999),
            'min_total'     => $min,
            'max_total'     => $max,
            'products'      => self::ids($g('products')),
            'cats'          => self::ids($g('cats')),
            'show_on_add'   => self::flag($g('show_on_add')),
            'free_ship_min' => self::money($g('free_ship_min')),
            'coupon_on'     => self::flag($g('coupon_on')),
            'coupon_code'   => $code,
            'coupon_apply'  => $apply,
            'page'          => $page,
            'page_products' => self::ids($g('page_products')),
            'page_cats'     => self::ids($g('page_cats')),
            'upsell_on'     => self::flag($g('upsell_on')),
            'upsell_mode'   => $umode,
            'upsell_product' => $uprod,
        ) + ZapPopPop_Woo_Recover::sanitize($in);
    }

    /** Clean config of a popup, or null when the module is off / not enabled for it. */
    public static function active($settings) {
        if (!is_array($settings) || !isset($settings[self::KEY])) {
            return null;
        }
        $c = self::sanitize($settings[self::KEY]);
        if (!$c['enabled'] || !self::module_active()) {
            return null;
        }
        return $c;
    }

    /* ---------------------------------------------------------------- core extension points */

    public static function extension_keys($keys) {
        $keys = is_array($keys) ? $keys : array();
        if (!in_array(self::KEY, $keys, true)) {
            $keys[] = self::KEY;
        }
        return $keys;
    }

    public static function handled_keys($keys) {
        return array_merge((array) $keys, array(self::KEY));
    }

    public static function adoptable_keys($keys) {
        $keys = is_array($keys) ? $keys : array();
        if (!in_array(self::KEY, $keys, true)) {
            $keys[] = self::KEY;
        }
        return $keys;
    }

    /** Save / template: allow-lists settings.woo_cart. Defaults are never stored. */
    public static function sanitize_settings($settings, $ctx) {
        if (!is_object($settings)) {
            return $settings;
        }
        $raw = (is_array($ctx) && isset($ctx['raw']) && is_array($ctx['raw'])) ? $ctx['raw'] : array();
        if (isset($raw[self::KEY])) {
            $clean = self::sanitize($raw[self::KEY]);
            if ($clean != self::defaults()) {
                $settings->{self::KEY} = json_decode(wp_json_encode($clean));
            }
        }
        return $settings;
    }

    /** data-wc-cart carries the compact rule set to the browser. Only non-default rules are listed. */
    public static function frontend($settings) {
        $c = self::active($settings);
        if ($c === null) {
            return '';
        }
        $r = array('c' => $c['cart']);
        if ($c['cart'] !== 'empty') {
            if ($c['min_items'] > 0) {
                $r['mi'] = $c['min_items'];
            }
            if ($c['min_total'] > 0) {
                $r['mn'] = $c['min_total'];
            }
            if ($c['max_total'] > 0) {
                $r['mx'] = $c['max_total'];
            }
            if ($c['products']) {
                $r['p'] = $c['products'];
            }
            if ($c['cats']) {
                $r['k'] = $c['cats'];
            }
        }
        if ($c['show_on_add']) {
            $r['a'] = 1;
        }
        if ($c['free_ship_min'] > 0) {
            $r['f'] = $c['free_ship_min'];
        }
        /* u = this popup needs an upsell product (its details come from the cart-state endpoint). */
        if ($c['upsell_on']) {
            $r['u'] = 1;
        }
        /* cc = coupon code (tokens, countdown), x = its real WooCommerce expiry, cm = when to apply it. The code is visible in the page source. */
        if ($c['coupon_code'] !== '') {
            $r['cc'] = $c['coupon_code'];
            $x       = self::coupon_expiry($c['coupon_code']);
            if ($x > 0) {
                $r['x'] = $x;
            }
            if ($c['coupon_on']) {
                $r['cm'] = $c['coupon_apply'];
            }
        }
        return ' data-wc-cart="' . esc_attr(wp_json_encode($r)) . '"';
    }

    public static function wrapper_attrs($attrs, $settings, $args) {
        return (string) $attrs . self::frontend(is_array($settings) ? $settings : array());
    }

    /* ---------------------------------------------------------------- cart-state bridge */

    /** Visitor's live cart as plain numbers and ids. Never cached: reached through WooCommerce's ?wc-ajax= endpoint. */
    public static function cart_state($uids = array()) {
        $out = array('count' => 0, 'subtotal' => 0, 'total' => 0, 'products' => array(), 'cats' => array(), 'coupons' => array(), 'free_min' => 0, 'now' => time(), 'upsell' => array());
        if (!self::woo_active()) {
            return $out;
        }
        if (function_exists('wc_load_cart') && null === WC()->cart) {
            wc_load_cart();
        }
        $cart = WC()->cart;
        if (!$cart) {
            return $out;
        }
        try {
            $dec              = function_exists('wc_get_price_decimals') ? (int) wc_get_price_decimals() : 2;
            $out['count']     = (int) $cart->get_cart_contents_count();
            $out['subtotal']  = round((float) $cart->get_displayed_subtotal(), $dec);
            $out['total']     = round((float) $cart->get_total('edit'), $dec);
            $pids             = array();
            $cids             = array();
            foreach ((array) $cart->get_cart() as $line) {
                if (empty($line['product_id'])) {
                    continue;
                }
                $pid        = (int) $line['product_id'];
                $pids[$pid] = $pid;
                if (!empty($line['variation_id'])) {
                    $vid        = (int) $line['variation_id'];
                    $pids[$vid] = $vid;
                }
                foreach ((array) wc_get_product_term_ids($pid, 'product_cat') as $tid) {
                    $tid        = (int) $tid;
                    $cids[$tid] = $tid;
                    foreach ((array) get_ancestors($tid, 'product_cat', 'taxonomy') as $aid) {
                        $aid        = (int) $aid;
                        $cids[$aid] = $aid;
                    }
                }
            }
            $out['products'] = array_values($pids);
            $out['cats']     = array_values($cids);
            $out['coupons']  = array_values(array_map('strval', (array) $cart->get_applied_coupons()));
            $out['free_min'] = self::free_shipping_min($cart);
            /* ZPP_WC_P3: the upsell product of every popup on this page that asks for one. */
            if ($uids && class_exists('ZapPopPop_Module_Bridge')) {
                foreach (array_slice($uids, 0, 10) as $uid) {
                    $c = self::active(ZapPopPop_Module_Bridge::stored_settings($uid));
                    if ($c === null || !$c['upsell_on']) {
                        continue;
                    }
                    $u = self::upsell_for($c, $cart);
                    if ($u) {
                        $out['upsell'][$uid] = $u;
                    }
                }
            }
        } catch (Throwable $e) {
            /* A broken third-party hook must never break the popup: return what was read so far. */
            unset($e);
        }
        return $out;
    }

    /** Minimum order amount of the free shipping method that serves this visitor's address (0 = none found). */
    protected static function free_shipping_min($cart) {
        if (!class_exists('WC_Shipping_Zones')) {
            return 0;
        }
        $package  = null;
        $packages = (array) $cart->get_shipping_packages();
        if ($packages) {
            $package = reset($packages);
        }
        if (!is_array($package) || empty($package['destination'])) {
            $c = WC()->customer;
            if (!$c) {
                return 0;
            }
            $package = array('destination' => array(
                'country'   => (string) $c->get_shipping_country(),
                'state'     => (string) $c->get_shipping_state(),
                'postcode'  => (string) $c->get_shipping_postcode(),
                'city'      => (string) $c->get_shipping_city(),
                'address'   => (string) $c->get_shipping_address(),
                'address_1' => (string) $c->get_shipping_address(),
                'address_2' => (string) $c->get_shipping_address_2(),
            ));
        }
        $zone = WC_Shipping_Zones::get_zone_matching_package($package);
        foreach ($zone->get_shipping_methods(true) as $method) {
            if ($method->id !== 'free_shipping') {
                continue;
            }
            $req = (string) $method->get_option('requires');
            if (in_array($req, array('min_amount', 'either', 'both'), true)) {
                return (float) $method->get_option('min_amount');
            }
        }
        return 0;
    }

    public static function ajax_cart_state() {
        if (!self::module_active()) {
            wp_send_json_error(array('message' => 'inactive'));
        }
        nocache_headers();
        $uids = array();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only lookup of the caller's own cart; the cart-state request carries no secret.
        if (isset($_POST['uids']) && is_array($_POST['uids'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every item is sanitized below.
            foreach (array_slice(wp_unslash($_POST['uids']), 0, 10) as $u) {
                $u = is_scalar($u) ? sanitize_text_field((string) $u) : '';
                if ($u !== '') {
                    $uids[$u] = $u;
                }
            }
        }
        wp_send_json_success(self::cart_state(array_values($uids)));
    }

    /* ---------------------------------------------------------------- front end */

    /** Lets the Progress Bar layer choose "Auto: WooCommerce free shipping" (core validates the saved value through this filter). */
    public static function progress_modes($modes) {
        $modes = is_array($modes) ? $modes : array();
        if (!in_array('shipping', $modes, true)) {
            $modes[] = 'shipping';
        }
        return $modes;
    }

    /** Unix time the coupon stops working (0 = unknown coupon or no expiry). The same value WooCommerce validates against. */
    protected static function coupon_expiry($code) {
        static $cache = array();
        $key = strtolower((string) $code);
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $ts = 0;
        if (class_exists('WC_Coupon') && $key !== '') {
            try {
                $coupon = new WC_Coupon($code);
                if ($coupon->get_id()) {
                    $d  = $coupon->get_date_expires();
                    $ts = $d ? (int) $d->getTimestamp() : 0;
                }
            } catch (Throwable $e) {
                unset($e);
            }
        }
        $cache[$key] = $ts;
        return $ts;
    }

    /**
     * Applies the one coupon this popup is set up with to the visitor's own cart. The browser sends only the popup uid:
     * the code comes from the saved popup, so this endpoint cannot be used to try other codes.
     */
    public static function ajax_apply_coupon() {
        nocache_headers();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- applies a server-side configured code to the caller's own cart only.
        $uid = isset($_POST['uid']) ? sanitize_text_field(wp_unslash($_POST['uid'])) : '';
        if ($uid === '' || !self::module_active() || !self::woo_active() || !class_exists('ZapPopPop_Module_Bridge')) {
            wp_send_json_error(array('message' => 'inactive'));
        }
        $c = self::active(ZapPopPop_Module_Bridge::stored_settings($uid));
        if ($c === null || !$c['coupon_on'] || $c['coupon_code'] === '') {
            wp_send_json_error(array('message' => 'inactive'));
        }
        if (function_exists('wc_load_cart') && null === WC()->cart) {
            wc_load_cart();
        }
        $cart = WC()->cart;
        if (!$cart) {
            wp_send_json_error(array('message' => 'inactive'));
        }
        /* Guests need a session cookie, or the cart (and the coupon) is lost on the next page. */
        if (WC()->session && method_exists(WC()->session, 'has_session') && !WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }
        $code = wc_format_coupon_code($c['coupon_code']);
        $ok   = $cart->has_discount($code) ? true : (bool) $cart->add_discount($code);
        $msg  = '';
        if (!$ok) {
            foreach ((array) wc_get_notices('error') as $n) {
                $msg = wp_strip_all_tags(is_array($n) && isset($n['notice']) ? (string) $n['notice'] : (string) $n);
                break;
            }
        }
        wc_clear_notices();
        if ($ok) {
            wp_send_json_success(array('applied' => true));
        }
        wp_send_json_error(array('message' => $msg));
    }

    /* ---------------------------------------------------------------- ZPP_WC_P3: product-page rule + upsell */

    /** Server-side page rule. Product pages are cached per URL, so a full-page cache stays correct. Fails open without WooCommerce. */
    public static function eligible($ok, $popup, $config) {
        if (!$ok || is_admin() || !self::woo_active() || !self::module_active()) {
            return $ok;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag, no state is changed.
        if (isset($_GET['zappoppop_preview'])) {
            return $ok;
        }
        $settings = (is_array($config) && isset($config['settings']) && is_array($config['settings'])) ? $config['settings'] : array();
        $c        = self::active($settings);
        if ($c === null || $c['page'] === 'any') {
            return $ok;
        }
        if (!function_exists('is_product') || !is_product()) {
            return false;
        }
        if ($c['page'] === 'selected' && ($c['page_products'] || $c['page_cats'])) {
            $id = (int) get_queried_object_id();
            if (in_array($id, $c['page_products'], true)) {
                return true;
            }
            if ($c['page_cats']) {
                $have = array();
                foreach ((array) wc_get_product_term_ids($id, 'product_cat') as $tid) {
                    $have[(int) $tid] = 1;
                    foreach ((array) get_ancestors((int) $tid, 'product_cat', 'taxonomy') as $aid) {
                        $have[(int) $aid] = 1;
                    }
                }
                foreach ($c['page_cats'] as $want) {
                    if (isset($have[(int) $want])) {
                        return true;
                    }
                }
            }
            return false;
        }
        return true;
    }

    /** The one product this popup offers right now, or null (not purchasable, out of stock, or already in the cart). */
    protected static function upsell_for($c, $cart) {
        if (empty($c['upsell_on']) || !function_exists('wc_get_product')) {
            return null;
        }
        $in = array();
        foreach ((array) $cart->get_cart() as $line) {
            if (!empty($line['product_id'])) {
                $in[(int) $line['product_id']] = 1;
            }
            if (!empty($line['variation_id'])) {
                $in[(int) $line['variation_id']] = 1;
            }
        }
        $cands = array();
        if ($c['upsell_mode'] === 'fixed') {
            if ($c['upsell_product'] > 0) {
                $cands[] = (int) $c['upsell_product'];
            }
        } else {
            foreach ((array) $cart->get_cross_sells() as $id) {
                $cands[] = (int) $id;
            }
            foreach ((array) $cart->get_cart() as $line) {
                if (!empty($line['data']) && is_object($line['data']) && method_exists($line['data'], 'get_upsell_ids')) {
                    foreach ((array) $line['data']->get_upsell_ids() as $id) {
                        $cands[] = (int) $id;
                    }
                }
            }
        }
        foreach (array_slice(array_unique($cands), 0, 30) as $id) {
            if (isset($in[$id])) {
                continue;
            }
            $p = wc_get_product($id);
            if (!$p || $p->get_status() !== 'publish' || !$p->is_purchasable() || !$p->is_in_stock()) {
                continue;
            }
            $price = function_exists('wc_get_price_to_display') ? (float) wc_get_price_to_display($p) : (float) $p->get_price();
            return array(
                'id'    => (int) $p->get_id(),
                'name'  => wp_strip_all_tags((string) $p->get_name()),
                'price' => round($price, 2),
                'url'   => esc_url_raw((string) $p->get_permalink()),
                'buy'   => $p->is_type('simple') || $p->is_type('variation'),
            );
        }
        return null;
    }

    /**
     * Adds the popup's upsell product to the visitor's own cart. The browser sends only the popup uid; the product is
     * resolved again on the server from the saved popup, so this endpoint cannot add arbitrary products.
     */
    public static function ajax_add_upsell() {
        nocache_headers();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- adds a server-side configured product to the caller's own cart only.
        $uid = isset($_POST['uid']) ? sanitize_text_field(wp_unslash($_POST['uid'])) : '';
        if ($uid === '' || !self::module_active() || !self::woo_active() || !class_exists('ZapPopPop_Module_Bridge')) {
            wp_send_json_error(array('message' => 'inactive'));
        }
        $c = self::active(ZapPopPop_Module_Bridge::stored_settings($uid));
        if ($c === null || !$c['upsell_on']) {
            wp_send_json_error(array('message' => 'inactive'));
        }
        if (function_exists('wc_load_cart') && null === WC()->cart) {
            wc_load_cart();
        }
        $cart = WC()->cart;
        if (!$cart) {
            wp_send_json_error(array('message' => 'inactive'));
        }
        $u = self::upsell_for($c, $cart);
        if (!$u || !$u['buy']) {
            wp_send_json_error(array('message' => 'unavailable'));
        }
        if (WC()->session && method_exists(WC()->session, 'has_session') && !WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }
        $p = wc_get_product($u['id']);
        if ($p && $p->is_type('variation')) {
            $key = $cart->add_to_cart($p->get_parent_id(), 1, $p->get_id(), $p->get_variation_attributes());
        } else {
            $key = $cart->add_to_cart($u['id'], 1);
        }
        wc_clear_notices();
        if (!$key) {
            wp_send_json_error(array('message' => 'failed'));
        }
        wp_send_json_success(array('added' => true));
    }

    public static function front_assets() {
        if (!self::module_active()) {
            return;
        }
        wp_enqueue_script('zappoppop-front-woo-cart-js', ZAPPOPPOP_PRO_URL . 'assets/js/front/front-woo-cart.js', array('jquery', 'zappoppop-front-js'), ZAPPOPPOP_PRO_VERSION, true);
    }

    public static function front_vars($vars) {
        if (!is_array($vars) || !self::module_active()) {
            return $vars;
        }
        $w = array('active' => false);
        if (self::woo_active()) {
            $w = array(
                'active'   => true,
                'endpoint' => class_exists('WC_AJAX') ? WC_AJAX::get_endpoint(self::ENDPOINT) : '',
                'applyUrl' => class_exists('WC_AJAX') ? WC_AJAX::get_endpoint(self::APPLY) : '',
                'addUrl'   => class_exists('WC_AJAX') ? WC_AJAX::get_endpoint(self::ADD_UP) : '',
                'fmt'      => array(
                    'symbol' => self::symbol(),
                    'pos'    => (string) get_option('woocommerce_currency_pos', 'left'),
                    'dec'    => (int) wc_get_price_decimals(),
                    'dsep'   => (string) wc_get_price_decimal_separator(),
                    'tsep'   => (string) wc_get_price_thousand_separator(),
                ),
            );
        }
        $vars['wc'] = $w;
        return $vars;
    }

    protected static function symbol() {
        return self::woo_active() ? html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8') : '$';
    }

    /* ---------------------------------------------------------------- builder */

    protected static function cats_list() {
        if (!taxonomy_exists('product_cat')) {
            return array();
        }
        $terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false, 'number' => 500, 'orderby' => 'name'));
        if (is_wp_error($terms) || !is_array($terms)) {
            return array();
        }
        $out = array();
        foreach ($terms as $t) {
            $out[] = array((string) $t->term_id, wp_strip_all_tags((string) $t->name));
        }
        return $out;
    }

    public static function localize_builder() {
        if (!class_exists('ZapPopPop_Modules') || !ZapPopPop_Modules::is_installed(self::MODULE_KEY) || !wp_script_is('zappoppop-builder-ext-js', 'enqueued')) {
            return;
        }
        wp_localize_script('zappoppop-builder-ext-js', 'ZapPopPopWooCartData', array(
            'wc_active' => self::woo_active(),
            'symbol'    => self::symbol(),
            'cats'      => self::cats_list(),
            'i18n'      => array(
                'WooCommerce Cart' => __('WooCommerce Cart', 'zappoppop-pro'),
                'Turn the WooCommerce cart features for this popup on or off' => __('Turn the WooCommerce cart features for this popup on or off', 'zappoppop-pro'),
                'Enable WooCommerce Cart' => __('Enable WooCommerce Cart', 'zappoppop-pro'),
                'Cart-aware display rules and live cart text for this popup.' => __('Cart-aware display rules and live cart text for this popup.', 'zappoppop-pro'),
                'WooCommerce is not active on this site, so the cart rules are ignored.' => __('WooCommerce is not active on this site, so the cart rules are ignored.', 'zappoppop-pro'),
                'Show this popup when the cart is' => __('Show this popup when the cart is', 'zappoppop-pro'),
                'Any' => __('Any', 'zappoppop-pro'),
                'Has items' => __('Has items', 'zappoppop-pro'),
                'Empty' => __('Empty', 'zappoppop-pro'),
                'Empty ignores the conditions below.' => __('Empty ignores the conditions below.', 'zappoppop-pro'),
                'Cart conditions' => __('Cart conditions', 'zappoppop-pro'),
                'Minimum items' => __('Minimum items', 'zappoppop-pro'),
                'Minimum subtotal' => __('Minimum subtotal', 'zappoppop-pro'),
                'Maximum subtotal' => __('Maximum subtotal', 'zappoppop-pro'),
                'items' => __('items', 'zappoppop-pro'),
                'A value of 0 turns that condition off.' => __('A value of 0 turns that condition off.', 'zappoppop-pro'),
                'Product IDs' => __('Product IDs', 'zappoppop-pro'),
                'Comma-separated. The popup shows when any of these products is in the cart. Empty = off.' => __('Comma-separated. The popup shows when any of these products is in the cart. Empty = off.', 'zappoppop-pro'),
                'Product categories' => __('Product categories', 'zappoppop-pro'),
                'Search...' => __('Search...', 'zappoppop-pro'),
                'Nothing found.' => __('Nothing found.', 'zappoppop-pro'),
                'No product categories found.' => __('No product categories found.', 'zappoppop-pro'),
                'The popup shows when the cart holds a product from any selected category. None selected = off.' => __('The popup shows when the cart holds a product from any selected category. None selected = off.', 'zappoppop-pro'),
                'Show right after an item is added to the cart' => __('Show right after an item is added to the cart', 'zappoppop-pro'),
                'Works with AJAX add to cart and the cart block. Shown once per page view, and only when the conditions above match.' => __('Works with AJAX add to cart and the cart block. Shown once per page view, and only when the conditions above match.', 'zappoppop-pro'),
                'Free shipping' => __('Free shipping', 'zappoppop-pro'),
                'Free shipping threshold' => __('Free shipping threshold', 'zappoppop-pro'),
                '0 = detect it from your WooCommerce free shipping method.' => __('0 = detect it from your WooCommerce free shipping method.', 'zappoppop-pro'),
                'Dynamic text' => __('Dynamic text', 'zappoppop-pro'),
                'Type these into any text layer. Live Preview shows sample values; visitors see their own cart.' => __('Type these into any text layer. Live Preview shows sample values; visitors see their own cart.', 'zappoppop-pro'),
                'Cart total' => __('Cart total', 'zappoppop-pro'),
                'Cart subtotal' => __('Cart subtotal', 'zappoppop-pro'),
                'Number of items' => __('Number of items', 'zappoppop-pro'),
                'Amount left for free shipping' => __('Amount left for free shipping', 'zappoppop-pro'),
                'Applied coupon code' => __('Applied coupon code', 'zappoppop-pro'),
                'Coupon' => __('Coupon', 'zappoppop-pro'),
                'Coupon code' => __('Coupon code', 'zappoppop-pro'),
                'An existing WooCommerce coupon. It fills {coupon_code} and the countdown. The code is visible in the page source, so do not use a private code.' => __('An existing WooCommerce coupon. It fills {coupon_code} and the countdown. The code is visible in the page source, so do not use a private code.', 'zappoppop-pro'),
                'Apply it to the cart automatically' => __('Apply it to the cart automatically', 'zappoppop-pro'),
                'When the popup opens' => __('When the popup opens', 'zappoppop-pro'),
                'When a button is clicked' => __('When a button is clicked', 'zappoppop-pro'),
                'Give a button the link #zpp-apply-coupon.' => __('Give a button the link #zpp-apply-coupon.', 'zappoppop-pro'),
                'Only this code can be applied. If it is invalid or expired, nothing happens.' => __('Only this code can be applied. If it is invalid or expired, nothing happens.', 'zappoppop-pro'),
                'Coupon countdown' => __('Coupon countdown', 'zappoppop-pro'),
                'Coupon expiry date' => __('Coupon expiry date', 'zappoppop-pro'),
                'Show on pages' => __('Show on pages', 'zappoppop-pro'),
                'Anywhere' => __('Anywhere', 'zappoppop-pro'),
                'Product pages' => __('Product pages', 'zappoppop-pro'),
                'Selected products' => __('Selected products', 'zappoppop-pro'),
                'Product pages: the popup is not shown on any other page. Selected products: only on the products or categories below (none selected = every product page).' => __('Product pages: the popup is not shown on any other page. Selected products: only on the products or categories below (none selected = every product page).', 'zappoppop-pro'),
                'Products' => __('Products', 'zappoppop-pro'),
                'Comma-separated product IDs. Empty = off.' => __('Comma-separated product IDs. Empty = off.', 'zappoppop-pro'),
                'Product page categories' => __('Product page categories', 'zappoppop-pro'),
                'This rule is checked on the server, so it also works with full-page caches.' => __('This rule is checked on the server, so it also works with full-page caches.', 'zappoppop-pro'),
                'Upsell' => __('Upsell', 'zappoppop-pro'),
                'Offer an upsell product' => __('Offer an upsell product', 'zappoppop-pro'),
                'Chosen by you' => __('Chosen by you', 'zappoppop-pro'),
                'Automatic' => __('Automatic', 'zappoppop-pro'),
                'Upsell product ID' => __('Upsell product ID', 'zappoppop-pro'),
                'The product to offer. A variation ID is allowed.' => __('The product to offer. A variation ID is allowed.', 'zappoppop-pro'),
                'Automatic uses the cross-sells of the visitor\'s cart, then the upsells of the products in it. Set them on each WooCommerce product.' => __('Automatic uses the cross-sells of the visitor\'s cart, then the upsells of the products in it. Set them on each WooCommerce product.', 'zappoppop-pro'),
                'The popup is not shown when no upsell is available, for example when the product is already in the cart or out of stock.' => __('The popup is not shown when no upsell is available, for example when the product is already in the cart or out of stock.', 'zappoppop-pro'),
                'Give a button the link #zpp-add-upsell to add the product to the cart (the popup then closes), or #zpp-view-upsell to open its page. Variable products can only use the view link.' => __('Give a button the link #zpp-add-upsell to add the product to the cart (the popup then closes), or #zpp-view-upsell to open its page. Variable products can only use the view link.', 'zappoppop-pro'),
                'Upsell product name' => __('Upsell product name', 'zappoppop-pro'),
                'Upsell product price' => __('Upsell product price', 'zappoppop-pro'),
                'Abandoned cart email' => __('Abandoned cart email', 'zappoppop-pro'),
                'Send a cart reminder email' => __('Send a cart reminder email', 'zappoppop-pro'),
                'Sent once to visitors who submit this popup\'s form with the consent box ticked and items in their cart. Nothing is saved without consent.' => __('Sent once to visitors who submit this popup\'s form with the consent box ticked and items in their cart. Nothing is saved without consent.', 'zappoppop-pro'),
                'Send after' => __('Send after', 'zappoppop-pro'),
                'minutes' => __('minutes', 'zappoppop-pro'),
                'Consent field name' => __('Consent field name', 'zappoppop-pro'),
                'Add a checkbox layer to the form (for example "Email me a reminder about my cart") and type the same name in its Advanced > Field name. Unticked = nothing is saved.' => __('Add a checkbox layer to the form (for example "Email me a reminder about my cart") and type the same name in its Advanced > Field name. Unticked = nothing is saved.', 'zappoppop-pro'),
                'Email subject' => __('Email subject', 'zappoppop-pro'),
                'You left something in your cart' => __('You left something in your cart', 'zappoppop-pro'),
                'Email message' => __('Email message', 'zappoppop-pro'),
                'Your cart is saved. Come back any time to finish your order.' => __('Your cart is saved. Come back any time to finish your order.', 'zappoppop-pro'),
                'Coupon code (optional)' => __('Coupon code (optional)', 'zappoppop-pro'),
                'Shown in the email and applied when the visitor returns to the cart. Use a real WooCommerce coupon.' => __('Shown in the email and applied when the visitor returns to the cart. Use a real WooCommerce coupon.', 'zappoppop-pro'),
                'No email is sent if an order was placed with the same address. Every email has an unsubscribe link; unsubscribed addresses are never saved again. Sending uses WordPress cron and wp_mail().' => __('No email is sent if an order was placed with the same address. Every email has an unsubscribe link; unsubscribed addresses are never saved again. Sending uses WordPress cron and wp_mail().', 'zappoppop-pro'),
                'The countdown and expiry date come from the coupon\'s real expiry date in WooCommerce.' => __('The countdown and expiry date come from the coupon\'s real expiry date in WooCommerce.', 'zappoppop-pro'),
                'Free shipping bar: add a Progress Bar layer, set its mode to "Auto: WooCommerce free shipping" and write its label like: Add {shipping_remaining} for free shipping || You got free shipping!' => __('Free shipping bar: add a Progress Bar layer, set its mode to "Auto: WooCommerce free shipping" and write its label like: Add {shipping_remaining} for free shipping || You got free shipping!', 'zappoppop-pro'),
            ),
        ));
    }
}
