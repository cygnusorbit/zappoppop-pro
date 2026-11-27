<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Licence status for the rest of the plugin. Easy Digital Downloads (ZapPopPop_Pro_EDD) is the only provider.
 * Policy: an active or an expired licence keeps every feature working; expiry only stops updates and support.
 * Until the licence is activated, Pro stays locked.
 */
class ZapPopPop_Pro_License {

    public static function is_configured() {
        return ZapPopPop_Pro_EDD::is_configured();
    }

    public static function is_unlocked() {
        return ZapPopPop_Pro_EDD::is_unlocked();
    }

    /** Callback for the core filter zappoppop_pro_licensed. */
    public static function filter_licensed($licensed) {
        return self::is_unlocked();
    }

    /** Where "Activate licence" buttons lead. */
    public static function activate_url() {
        return ZapPopPop_Pro_EDD::page_url(is_network_admin());
    }
}
