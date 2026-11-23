<?php
/**
 * Loads the Pro catalog of admin UI text built in JavaScript (translated by the core
 * zappoppop-i18n-dom.js script). Only on ZapPopPop admin pages and only for non-English sites.
 */
if (!defined('ABSPATH')) {
    exit;
}

class ZapPopPop_Pro_Dom_I18n {

    public static function hooks() {
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue'), 30);
    }

    public static function enqueue($hook) {
        if (false === strpos((string) $hook, 'zappoppop')) {
            return;
        }
        if (0 === strpos(determine_locale(), 'en_')) {
            return;
        }
        wp_enqueue_script('zappoppop-pro-i18n-catalog-js', ZAPPOPPOP_PRO_URL . 'assets/js/zappoppop-pro-i18n-catalog.js', array('wp-i18n'), ZAPPOPPOP_PRO_VERSION, true);
    }
}
