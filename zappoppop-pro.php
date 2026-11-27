<?php
/**
 * Plugin Name: ZapPopPop Pro
 * Plugin URI: https://zappoppop.com/
 * Description: Pro add-on for ZapPopPop: extra builder elements, modules, templates, A/B Campaigns and Statistics.
 * Version: 1.0.14
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: ZapPopPop
 * Author URI: 
 * License: GPL-2.0+
 * Text Domain: zappoppop-pro
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

if (defined('ZAPPOPPOP_PRO_VERSION')) {
    return;
}

define('ZAPPOPPOP_PRO_VERSION', '1.0.14');
define('ZAPPOPPOP_PRO_FILE', __FILE__);
define('ZAPPOPPOP_PRO_PATH', plugin_dir_path(__FILE__));
define('ZAPPOPPOP_PRO_URL', plugin_dir_url(__FILE__));
define('ZAPPOPPOP_PRO_BASENAME', plugin_basename(__FILE__));
/* Lowest ZapPopPop core extension API this add-on can run on. */
define('ZAPPOPPOP_PRO_MIN_API', 1);
/* Lowest ZapPopPop release that ships that API (shown on the Plugins screen). */
define('ZAPPOPPOP_PRO_MIN_CORE', '1.0.00');

/*
 * ============================ EASY DIGITAL DOWNLOADS LICENSING ============================
 * Default licence provider. Pro talks to the EDD Software Licensing add-on on your store.
 * The store URL and Download ID are built in, so customers only enter their licence key.
 * To override them (for example a staging store), define these in wp-config.php
 * (above "That's all, stop editing!"); a wp-config.php value always wins:
 *
 *   define( 'ZAPPOPPOP_PRO_EDD_URL', 'https://your-store.com/' );   Store home URL (https).
 *   define( 'ZAPPOPPOP_PRO_EDD_ITEM_ID', 123 );                     Download ID of ZapPopPop Pro.
 * ==========================================================================================
 */
if (!defined('ZAPPOPPOP_PRO_EDD_URL')) {
    define('ZAPPOPPOP_PRO_EDD_URL', 'https://zappoppop.com/');
}
if (!defined('ZAPPOPPOP_PRO_EDD_ITEM_ID')) {
    define('ZAPPOPPOP_PRO_EDD_ITEM_ID', 3030);
}
/*
 * All Access Pass: Download IDs of the paid add-on plugins in your store. 0 = not on sale yet, so the add-on stays hidden
 * and never contacts the store. Define them in wp-config.php (a wp-config.php value wins) or fill them in here once
 * the downloads exist:  ZAPPOPPOP_PRO_EDD_ID_WOO_CART, ZAPPOPPOP_PRO_EDD_ID_MAILCHIMP, ZAPPOPPOP_PRO_EDD_ID_GETRESPONSE.
 */
foreach (array('ZAPPOPPOP_PRO_EDD_ID_WOO_CART', 'ZAPPOPPOP_PRO_EDD_ID_MAILCHIMP', 'ZAPPOPPOP_PRO_EDD_ID_GETRESPONSE', 'ZAPPOPPOP_PRO_EDD_ID_SUCCESS_CELEBRATION', 'ZAPPOPPOP_PRO_EDD_ID_POPUP_OVERLAY', 'ZAPPOPPOP_PRO_EDD_ID_POPUP_POSITIONS', 'ZAPPOPPOP_PRO_EDD_ID_TARGETING_TRIGGERS', 'ZAPPOPPOP_PRO_EDD_ID_TARGETING', 'ZAPPOPPOP_PRO_EDD_ID_SIDE_TABS', 'ZAPPOPPOP_PRO_EDD_ID_ANIMATION', 'ZAPPOPPOP_PRO_EDD_ID_DOUBLE_OPTIN', 'ZAPPOPPOP_PRO_EDD_ID_AUTORESPONDER', 'ZAPPOPPOP_PRO_EDD_ID_SECURITY') as $zappoppop_pro_const) {
    if (!defined($zappoppop_pro_const)) {
        define($zappoppop_pro_const, 0);
    }
}
unset($zappoppop_pro_const);
require_once ZAPPOPPOP_PRO_PATH . 'includes/class-pro-edd-updater.php';
require_once ZAPPOPPOP_PRO_PATH . 'includes/class-pro-edd.php';
require_once ZAPPOPPOP_PRO_PATH . 'includes/class-pro-pass.php';
require_once ZAPPOPPOP_PRO_PATH . 'includes/class-pro-multisite.php';
require_once ZAPPOPPOP_PRO_PATH . 'includes/class-pro-uninstall.php';
require_once ZAPPOPPOP_PRO_PATH . 'includes/class-pro-license.php';
require_once ZAPPOPPOP_PRO_PATH . 'includes/class-pro-admin.php';
require_once ZAPPOPPOP_PRO_PATH . 'includes/class-pro-plugin.php';

ZapPopPop_Pro_EDD::init();
ZapPopPop_Pro_Pass::hooks();

ZapPopPop_Pro_Plugin::boot();
