<?php
/**
 * Plugin Name: ZapPopPop Pro
 * Plugin URI: https://zappoppop.com/
 * Description: Pro add-on for ZapPopPop: extra builder elements, modules, templates, A/B Campaigns and Statistics.
 * Version: 1.0.00
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: ZapPopPop
 * Author URI: 
 * License: GPL-2.0+
 * Text Domain: zappoppop-pro
 */

if (!defined('ABSPATH')) {
    exit;
}

if (defined('ZAPPOPPOP_PRO_VERSION')) {
    return;
}

define('ZAPPOPPOP_PRO_VERSION', '1.0.00');
define('ZAPPOPPOP_PRO_FILE', __FILE__);
define('ZAPPOPPOP_PRO_PATH', plugin_dir_path(__FILE__));
define('ZAPPOPPOP_PRO_URL', plugin_dir_url(__FILE__));
define('ZAPPOPPOP_PRO_BASENAME', plugin_basename(__FILE__));
/* Lowest ZapPopPop core extension API this add-on can run on. */
define('ZAPPOPPOP_PRO_MIN_API', 1);
/* Lowest ZapPopPop release that ships that API (shown on the Plugins screen). */
define('ZAPPOPPOP_PRO_MIN_CORE', '1.0.00');

/*
 * ================================ FREEMIUS LICENSING ================================
 * Pro is sold as a paid-only Freemius product. Fill in the three values below (or define
 * them in wp-config.php; those win) with the data from your Freemius Developer Dashboard:
 * Products > ZapPopPop Pro > SDK Integration.
 *
 *   ZAPPOPPOP_PRO_FS_ID    Product ID (a number).
 *   ZAPPOPPOP_PRO_FS_KEY   Public key (starts with pk_). Never put the secret key here.
 *   ZAPPOPPOP_PRO_FS_SLUG  Product slug, exactly as in Freemius.
 *
 * While the ID is 0 the Freemius SDK stays off and Pro stays locked.
 * ================================================================================
 */
if (!defined('ZAPPOPPOP_PRO_FS_ID')) {
    define('ZAPPOPPOP_PRO_FS_ID', 45025);
}
if (!defined('ZAPPOPPOP_PRO_FS_KEY')) {
    define('ZAPPOPPOP_PRO_FS_KEY', 'pk_21d6e1f66c95c7b00ce1117f56015');
}
if (!defined('ZAPPOPPOP_PRO_FS_SLUG')) {
    define('ZAPPOPPOP_PRO_FS_SLUG', 'zappoppop');
}
if (!defined('ZAPPOPPOP_PRO_FS_PREMIUM_SLUG')) {
    /* Folder name of this plugin, as set in Freemius. */
    define('ZAPPOPPOP_PRO_FS_PREMIUM_SLUG', 'zappoppop-pro');
}

require_once ZAPPOPPOP_PRO_PATH . 'includes/class-pro-freemius.php';
require_once ZAPPOPPOP_PRO_PATH . 'includes/class-pro-multisite.php';
require_once ZAPPOPPOP_PRO_PATH . 'includes/class-pro-uninstall.php';
require_once ZAPPOPPOP_PRO_PATH . 'includes/class-pro-license.php';
require_once ZAPPOPPOP_PRO_PATH . 'includes/class-pro-admin.php';
require_once ZAPPOPPOP_PRO_PATH . 'includes/class-pro-plugin.php';

ZapPopPop_Pro_Freemius::init();

if (!function_exists('zpp_pro')) {
    /* Helper for the Freemius SDK instance, as used in the Freemius documentation. */
    function zpp_pro() {
        return ZapPopPop_Pro_Freemius::fs();
    }
}
ZapPopPop_Pro_Plugin::boot();
