<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pro internationalization bootstrap.
 *
 * Gives every script this add-on registers the wp-i18n dependency and its JSON translations
 * (text domain zappoppop-pro), so strings wrapped with wp.i18n.__() in Pro JS are translatable.
 * Core scripts are skipped here: core attaches its own. Bundled third-party libs are skipped too.
 */
class ZapPopPop_Pro_I18n {
    const DOMAIN = 'zappoppop-pro';

    public static function hooks() {
        foreach (array('admin_enqueue_scripts', 'wp_enqueue_scripts') as $hook) {
            add_action($hook, array(__CLASS__, 'attach_script_translations'), 999);
        }
        foreach (array('wp_print_scripts', 'wp_print_footer_scripts', 'admin_print_footer_scripts') as $hook) {
            add_action($hook, array(__CLASS__, 'attach_script_translations'), 0);
        }
    }

    public static function languages_dir() {
        return ZAPPOPPOP_PRO_PATH . 'languages';
    }

    public static function attach_script_translations() {
        $scripts = wp_scripts();
        if (!$scripts instanceof WP_Scripts) {
            return;
        }

        foreach ($scripts->registered as $handle => $script) {
            if (strpos($handle, 'zappoppop') !== 0 || !is_string($script->src) || strpos($script->src, ZAPPOPPOP_PRO_URL) !== 0) {
                continue;
            }
            if (strpos($script->src, ZAPPOPPOP_PRO_URL . 'assets/lib/') === 0) {
                continue;
            }
            if (!in_array('wp-i18n', $script->deps, true)) {
                $script->deps[] = 'wp-i18n';
            }
            if (empty($script->textdomain)) {
                wp_set_script_translations($handle, self::DOMAIN, self::languages_dir());
            }
        }
    }
}
