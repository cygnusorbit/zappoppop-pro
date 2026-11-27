<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * All Access Pass support (Easy Digital Downloads).
 *
 * The customer enters ONE licence key on ZapPopPop > License. That key activates ZapPopPop Pro as before and is then
 * used, with each add-on's own Download ID, to activate, re-check and update every paid add-on plugin that is installed.
 * An add-on is a normal WordPress plugin (core's add-on model: it ships its own modules/ folder and registers it through
 * the zappoppop_module_roots filter). A key that only covers Pro simply marks the add-ons "not in your licence".
 *
 * Policy is the same as for Pro: an active or an expired licence keeps an add-on working; expiry only stops updates and
 * support. A store that cannot be reached never changes a stored status.
 *
 * An add-on plugin asks ZapPopPop_Pro_Pass::is_unlocked( 'its-slug' ) and registers itself with the filter
 * zappoppop_pro_edd_products (array of: slug, name, basename, item_id, description, icon).
 * Planned add-ons are listed in planned(); each one stays hidden and silent until its Download ID is defined.
 */
class ZapPopPop_Pro_Pass {

    const OPTION = 'zappoppop_pro_edd_products';
    const CACHE  = 'zappoppop_pro_edd_up_';

    public static function hooks() {
        add_action('zappoppop_pro_edd_activated', array(__CLASS__, 'sync'));
        add_action('zappoppop_pro_edd_checked', array(__CLASS__, 'sync'));
        add_action('zappoppop_pro_edd_deactivating', array(__CLASS__, 'deactivate_all'));
        add_action('zappoppop_pro_edd_saved', array(__CLASS__, 'clear_cache'));
        add_action(ZapPopPop_Pro_EDD::CRON, array(__CLASS__, 'sync'), 20);
        add_action('activated_plugin', array(__CLASS__, 'on_plugin_activated'));
        add_filter('zappoppop_addon_catalog', array(__CLASS__, 'catalog'));
        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'inject'), 20);
        add_filter('plugins_api', array(__CLASS__, 'plugin_info'), 20, 3);
    }

    /* ------------------------------------------------------------------ product list */

    protected static function const_id($name) {
        return defined($name) ? max(0, (int) constant($name)) : 0;
    }

    /** Add-ons planned for the pass (slug => data). A Download ID of 0 keeps the add-on out of every list and out of every store call. */
    public static function planned() {
        return array_merge(self::planned_modules(), self::planned_builtin());
    }

    /** ZAPPOPPOP_PRO_MODREG: module add-ons listed in defaults/pass-addons.json (slug, const, name, description, icon). */
    protected static function planned_modules() {
        $out  = array();
        $file = ZAPPOPPOP_PRO_PATH . 'defaults/pass-addons.json';
        $json = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;
        $list = (is_array($json) && isset($json['addons']) && is_array($json['addons'])) ? $json['addons'] : array();
        foreach ($list as $a) {
            if (!is_array($a)) {
                continue;
            }
            $slug  = (isset($a['slug']) && is_string($a['slug'])) ? sanitize_key($a['slug']) : '';
            $const = (isset($a['const']) && is_string($a['const']) && preg_match('/^ZAPPOPPOP_PRO_EDD_ID_[A-Z0-9_]+$/', $a['const'])) ? $a['const'] : '';
            if ($slug === '' || $const === '') {
                continue;
            }
            $out[$slug] = array(
                'name'        => (isset($a['name']) && is_string($a['name'])) ? $a['name'] : $slug,
                'description' => (isset($a['description']) && is_string($a['description'])) ? $a['description'] : '',
                'icon'        => (isset($a['icon']) && is_string($a['icon'])) ? $a['icon'] : 'dashicons-admin-plugins',
                'item_id'     => self::const_id($const),
            );
        }
        return $out;
    }

    protected static function planned_builtin() {
        return array(
            'zappoppop-mailchimp' => array(
                'name'        => did_action('init') ? __('Mailchimp', 'zappoppop-pro') : 'Mailchimp',
                'description' => did_action('init') ? __('Subscribe popup form submissions to Mailchimp audiences with merge fields, groups, tags and double opt-in.', 'zappoppop-pro') : 'Subscribe popup form submissions to Mailchimp audiences with merge fields, groups, tags and double opt-in.',
                'icon'        => 'dashicons-email-alt',
                'item_id'     => self::const_id('ZAPPOPPOP_PRO_EDD_ID_MAILCHIMP'),
            ),
            'zappoppop-getresponse' => array(
                'name'        => did_action('init') ? __('GetResponse', 'zappoppop-pro') : 'GetResponse',
                'description' => did_action('init') ? __('Add popup form submissions to GetResponse campaigns with custom field mapping.', 'zappoppop-pro') : 'Add popup form submissions to GetResponse campaigns with custom field mapping.',
                'icon'        => 'dashicons-email',
                'item_id'     => self::const_id('ZAPPOPPOP_PRO_EDD_ID_GETRESPONSE'),
            ),
        );
    }

    protected static function clean($p) {
        if (!is_array($p)) {
            return null;
        }
        $slug = (isset($p['slug']) && is_string($p['slug'])) ? sanitize_key($p['slug']) : '';
        $base = (isset($p['basename']) && is_string($p['basename'])) ? $p['basename'] : '';
        $id   = isset($p['item_id']) ? (int) $p['item_id'] : 0;
        if ($slug === '' || $id < 1 || !preg_match('#^[a-z0-9._-]+/[a-z0-9._-]+\.php$#i', $base) || strpos($base, '..') !== false) {
            return null;
        }
        return array(
            'slug'        => $slug,
            'basename'    => $base,
            'item_id'     => $id,
            'name'        => (isset($p['name']) && is_string($p['name']) && $p['name'] !== '') ? sanitize_text_field($p['name']) : $slug,
            'description' => (isset($p['description']) && is_string($p['description'])) ? sanitize_text_field($p['description']) : '',
            'icon'        => (isset($p['icon']) && is_string($p['icon']) && preg_match('/^dashicons-[a-z0-9-]+$/', $p['icon'])) ? $p['icon'] : 'dashicons-admin-plugins',
        );
    }

    /** Add-ons that can be licensed right now (slug => data): planned ones plus those registered through the filter. */
    public static function products() {
        $out = array();
        foreach (self::planned() as $slug => $p) {
            $c = self::clean(array_merge($p, array('slug' => $slug, 'basename' => $slug . '/' . $slug . '.php')));
            if ($c) {
                $out[$slug] = $c;
            }
        }
        $extra = apply_filters('zappoppop_pro_edd_products', array());
        if (is_array($extra)) {
            foreach ($extra as $p) {
                $c = self::clean($p);
                if ($c) {
                    $out[$c['slug']] = $c;
                }
            }
        }
        return $out;
    }

    /** Installed version of an add-on plugin ('' when it is not installed). */
    public static function installed_version($p) {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all = get_plugins();
        return isset($all[$p['basename']]['Version']) ? (string) $all[$p['basename']]['Version'] : '';
    }

    /* ------------------------------------------------------------------ stored state (site option, shared by a network) */

    public static function states() {
        $s = get_site_option(self::OPTION, array());
        return is_array($s) ? $s : array();
    }

    public static function state($slug) {
        $all = self::states();
        $s   = (isset($all[$slug]) && is_array($all[$slug])) ? $all[$slug] : array();
        return wp_parse_args($s, array('status' => '', 'activated' => 0, 'expires' => '', 'checked' => 0));
    }

    /** True when Pro is licensed and the key is active for this add-on (an expired licence stays unlocked, like Pro). */
    public static function is_unlocked($slug) {
        $products = self::products();
        if (!isset($products[$slug]) || !ZapPopPop_Pro_EDD::is_unlocked()) {
            return false;
        }
        $s = self::state($slug);
        return !empty($s['activated']) && in_array($s['status'], array('valid', 'expired'), true);
    }

    protected static function status_from($code) {
        $code = sanitize_key((string) $code);
        if ($code === 'item_name_mismatch' || $code === 'invalid_item_id') {
            return 'not_included';
        }
        return $code === '' ? 'invalid' : $code;
    }

    /* ------------------------------------------------------------------ store calls */

    /** Activates (first time) or re-checks (afterwards) the shared key for every installed add-on. */
    public static function sync() {
        if (!ZapPopPop_Pro_EDD::is_configured() || !ZapPopPop_Pro_EDD::is_unlocked()) {
            return;
        }
        $key = ZapPopPop_Pro_EDD::get_key();
        if ($key === '') {
            return;
        }
        $all = self::states();
        foreach (self::products() as $slug => $p) {
            if (self::installed_version($p) === '') {
                continue;
            }
            $s   = self::state($slug);
            $was = !empty($s['activated']);
            $r   = ZapPopPop_Pro_EDD::request($was ? 'check_license' : 'activate_license', $key, array('item_id' => $p['item_id']));
            if (is_wp_error($r)) {
                continue; /* store unreachable: keep the last known status */
            }
            $s['checked'] = time();
            if (isset($r['expires'])) {
                $s['expires'] = sanitize_text_field($r['expires']);
            }
            if ($was) {
                $status         = self::status_from(isset($r['license']) ? $r['license'] : 'invalid');
                $s['status']    = $status;
                $s['activated'] = ($status === 'valid' || $status === 'expired') ? 1 : 0;
            } elseif (!empty($r['success'])) {
                $s['status']    = isset($r['license']) ? sanitize_key($r['license']) : 'valid';
                $s['activated'] = 1;
            } else {
                $s['status']    = self::status_from(isset($r['error']) ? $r['error'] : (isset($r['license']) ? $r['license'] : 'invalid'));
                $s['activated'] = 0;
            }
            $all[$slug] = $s;
        }
        update_site_option(self::OPTION, $all);
        self::clear_cache();
        delete_site_transient('update_plugins');
    }

    /** Frees the add-on activations before Pro itself is deactivated. An activation is kept when the store cannot be reached. */
    public static function deactivate_all() {
        $all = self::states();
        if (!$all || !ZapPopPop_Pro_EDD::is_configured()) {
            return;
        }
        $key      = ZapPopPop_Pro_EDD::get_key();
        $products = self::products();
        foreach ($all as $slug => $s) {
            if ($key === '' || empty($s['activated']) || !isset($products[$slug])) {
                unset($all[$slug]);
                continue;
            }
            $r = ZapPopPop_Pro_EDD::request('deactivate_license', $key, array('item_id' => $products[$slug]['item_id']));
            if (!is_wp_error($r)) {
                unset($all[$slug]);
            }
        }
        update_site_option(self::OPTION, $all);
        self::clear_cache();
    }

    /** An add-on activated after the key was entered gets its activation straight away. */
    public static function on_plugin_activated($plugin) {
        foreach (self::products() as $p) {
            if ($p['basename'] === $plugin) {
                self::sync();
                return;
            }
        }
    }

    /* ------------------------------------------------------------------ core Add-ons catalog (Extend page) */

    /** Lists each add-on on sale as a "Get add-on" link on core's Extend page. Core itself stays free of any paid entry. */
    public static function catalog($items) {
        $items = is_array($items) ? $items : array();
        $store = ZapPopPop_Pro_EDD::store_url();
        if ($store === '') {
            return $items;
        }
        $have = array();
        foreach ($items as $it) {
            if (is_array($it) && isset($it['slug'])) {
                $have[$it['slug']] = true;
            }
        }
        foreach (self::planned() as $slug => $p) {
            if ($p['item_id'] < 1 || isset($have[$slug])) {
                continue;
            }
            $full    = self::products();
            $full    = isset($full[$slug]) ? $full[$slug] : null;
            $can     = ($full && self::installed_version($full) === '' && self::installable($full) !== null);
            $items[] = array(
                'slug'        => $slug,
                'file'        => $slug . '/' . $slug . '.php',
                'name'        => $p['name'],
                'description' => $p['description'],
                'kind'        => $can ? 'wporg' : 'link', /* ZAPPOPPOP_P2C */
                'url'         => trailingslashit($store) . 'downloads/' . $slug . '/',
                'icon'        => $p['icon'],
                'badge'       => __('All Access', 'zappoppop-pro'),
            );
        }
        return $items;
    }

    /* ------------------------------------------------------------------ updates for the add-on plugins */

    public static function clear_cache() {
        foreach (array_keys(self::products()) as $slug) {
            delete_site_transient(self::CACHE . md5($slug));
        }
    }

    protected static function info($p, $ver) {
        $key = ZapPopPop_Pro_EDD::get_key();
        if ($key === '') {
            return null;
        }
        $name   = self::CACHE . md5($p['slug']);
        $hash   = md5($key . '|' . $p['item_id'] . '|' . $ver);
        $cached = get_site_transient($name);
        if (is_array($cached) && isset($cached['hash']) && $cached['hash'] === $hash) {
            return $cached['data'] ? self::to_object($cached['data'], $p) : null;
        }
        $r = ZapPopPop_Pro_EDD::request('get_version', $key, array('item_id' => $p['item_id'], 'slug' => dirname($p['basename']), 'version' => $ver, 'beta' => 0));
        if (is_wp_error($r) || empty($r['new_version'])) {
            set_site_transient($name, array('hash' => $hash, 'data' => false), HOUR_IN_SECONDS);
            return null;
        }
        set_site_transient($name, array('hash' => $hash, 'data' => $r), 6 * HOUR_IN_SECONDS);
        return self::to_object($r, $p);
    }

    protected static function to_object($r, $p) {
        $o         = ZapPopPop_Pro_EDD_Updater::to_object($r);
        $o->id     = $p['basename'];
        $o->plugin = $p['basename'];
        $o->slug   = dirname($p['basename']);
        if (!isset($r['name'])) {
            $o->name = $p['name'];
        }
        return $o;
    }

    public static function inject($transient) {
        if (!is_object($transient) || !ZapPopPop_Pro_EDD::is_configured() || !ZapPopPop_Pro_EDD::is_unlocked()) {
            return $transient;
        }
        foreach (self::products() as $slug => $p) {
            $ver = self::installed_version($p);
            if ($ver === '' || !self::is_unlocked($slug)) {
                continue;
            }
            $info = self::info($p, $ver);
            if (!$info) {
                continue;
            }
            $bucket = version_compare($ver, $info->new_version, '<') ? 'response' : 'no_update';
            if (!isset($transient->$bucket) || !is_array($transient->$bucket)) {
                $transient->$bucket = array();
            }
            $transient->{$bucket}[$p['basename']] = $info;
        }
        return $transient;
    }

    /** Package info for an add-on that is not installed yet. Only while Pro itself is licensed and the store says the key covers it. */
    public static function installable($p) {
        if (!ZapPopPop_Pro_EDD::is_configured() || !ZapPopPop_Pro_EDD::is_unlocked() || (int) $p['item_id'] < 1) {
            return null;
        }
        $info = self::info($p, '0');
        return ($info && !empty($info->package)) ? $info : null;
    }

    /** Fills the "View details" popup on the Plugins screen for an add-on. */
    public static function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || !is_object($args) || empty($args->slug)) {
            return $result;
        }
        foreach (self::products() as $slug => $p) {
            if (dirname($p['basename']) !== $args->slug) {
                continue;
            }
            $ver = self::installed_version($p);
            if ($ver === '') {
                /* ZAPPOPPOP_P2C: not installed yet, so WordPress's own installer can fetch the package */
                $info = self::installable($p);
            } else {
                $info = self::is_unlocked($slug) ? self::info($p, $ver) : null;
            }
            if (!$info) {
                return $result;
            }
            $out                = clone $info;
            $out->version       = $info->new_version;
            $out->download_link = $info->package;
            if (empty($out->sections)) {
                $out->sections = array('description' => esc_html($p['name']));
            }
            return $out;
        }
        return $result;
    }

    /* ------------------------------------------------------------------ License page rows */

    public static function rows() {
        $rows = array();
        foreach (self::products() as $slug => $p) {
            $ver = self::installed_version($p);
            $s   = self::state($slug);
            if ($ver === '') {
                $pill  = 'is-off';
                $label = __('Not installed', 'zappoppop-pro');
            } elseif (!empty($s['activated']) && $s['status'] === 'valid') {
                $pill  = 'is-ok';
                $label = __('Active', 'zappoppop-pro');
            } elseif (!empty($s['activated']) && $s['status'] === 'expired') {
                $pill  = 'is-warn';
                $label = __('Expired', 'zappoppop-pro');
            } elseif ($s['status'] === 'not_included') {
                $pill  = 'is-off';
                $label = __('Not in your licence', 'zappoppop-pro');
            } else {
                $pill  = 'is-off';
                $label = ZapPopPop_Pro_EDD::status_label($s['status']);
            }
            $rows[] = array(
                'name'     => $p['name'],
                'version'  => $ver,
                'pill'     => $pill,
                'label'    => $label,
                'included' => $s['status'] !== 'not_included',
            );
        }
        return $rows;
    }
}
