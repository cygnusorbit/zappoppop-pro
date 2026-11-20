<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Licence status for the rest of the plugin. The provider is Easy Digital Downloads (ZapPopPop_Pro_EDD) by default;
 * define ZAPPOPPOP_PRO_LICENSE_PROVIDER as 'freemius' to use Freemius (ZapPopPop_Pro_Freemius) instead.
 * Policy: an active or an expired licence keeps every feature working; expiry only stops updates and support.
 * Until Freemius is configured in zappoppop-pro.php, Pro stays locked.
 */
class ZapPopPop_Pro_License {

    /** Active provider: 'edd' (default) or 'freemius'. Set ZAPPOPPOP_PRO_LICENSE_PROVIDER in wp-config.php to change it. */
    public static function provider() {
        static $provider = null;
        if ($provider === null) {
            $p = defined('ZAPPOPPOP_PRO_LICENSE_PROVIDER') ? strtolower(trim((string) ZAPPOPPOP_PRO_LICENSE_PROVIDER)) : 'edd';
            if (function_exists('apply_filters')) {
                $p = strtolower(trim((string) apply_filters('zappoppop_pro_license_provider', $p)));
            }
            $provider = ($p === 'freemius') ? 'freemius' : 'edd';
        }
        return $provider;
    }

    public static function is_configured() {
        if (self::provider() === 'edd') {
            return ZapPopPop_Pro_EDD::is_configured();
        }
        return ZapPopPop_Pro_Freemius::is_configured();
    }

    public static function is_unlocked() {
        if (self::provider() === 'edd') {
            return ZapPopPop_Pro_EDD::is_unlocked();
        }
        return ZapPopPop_Pro_Freemius::is_unlocked();
    }

    /** Callback for the core filter zappoppop_pro_licensed. */
    public static function filter_licensed($licensed) {
        return self::is_unlocked();
    }

    /** Where "Activate licence" buttons lead. */
    public static function activate_url() {
        if (self::provider() === 'edd') {
            return ZapPopPop_Pro_EDD::page_url(is_network_admin());
        }
        if (self::is_configured() && ZapPopPop_Pro_Freemius::account_url() !== '') {
            return ZapPopPop_Pro_Freemius::account_url();
        }
        return admin_url('admin.php?page=' . ZapPopPop_Pro_Admin::PAGE);
    }
}
