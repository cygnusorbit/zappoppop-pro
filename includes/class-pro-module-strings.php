<?php
/**
 * Translatable names and descriptions of the Pro modules (their module.json files stay English).
 * Hooks the core filter 'zappoppop_module_text'; literal __() calls keep the strings in the Pro POT file.
 */
if (!defined('ABSPATH')) {
    exit;
}

class ZapPopPop_Pro_Module_Strings {

    public static function hooks() {
        add_filter('zappoppop_module_text', array(__CLASS__, 'filter'), 10, 4);
    }

    public static function filter($text, $field, $slug, $source) {
        if ($source !== 'pro') {
            return $text;
        }
        $map = self::map();
        return isset($map[$slug][$field]) ? $map[$slug][$field] : $text;
    }

    /** @return array<string, array{name: string, description: string}> slug => translated text */
    private static function map() {
        return array(
            'acc-animation' => array(
                'name'        => __('Animation', 'zappoppop-pro'),
                'description' => __('Open and close animations for the popup.', 'zappoppop-pro'),
            ),
            'acc-autoresponder' => array(
                'name'        => __('Autoresponder', 'zappoppop-pro'),
                'description' => __('Automatic reply sent to subscribers after they submit.', 'zappoppop-pro'),
            ),
            'acc-double-optin' => array(
                'name'        => __('Double Opt-In', 'zappoppop-pro'),
                'description' => __('Double opt-in confirmation for subscribers.', 'zappoppop-pro'),
            ),
            'acc-popup-overlay' => array(
                'name'        => __('Popup Overlay', 'zappoppop-pro'),
                'description' => __('Page overlay behind the popup: colour, opacity, image or animation.', 'zappoppop-pro'),
            ),
            'acc-popup-positions' => array(
                'name'        => __('Popup Positions', 'zappoppop-pro'),
                'description' => __('Popup position on screen: top, bottom, left or right.', 'zappoppop-pro'),
            ),
            'acc-security' => array(
                'name'        => __('Security & Protections', 'zappoppop-pro'),
                'description' => __('Technical info, repeated submission (antibot) protection, page scrollbar lock and personal data keys.', 'zappoppop-pro'),
            ),
            'acc-side-tabs' => array(
                'name'        => __('Side Tabs', 'zappoppop-pro'),
                'description' => __('Sticky side tabs attached to the screen edge, with icon, colour, corner radius and slide-in animation.', 'zappoppop-pro'),
            ),
            'acc-success-celebration' => array(
                'name'        => __('Success Celebration', 'zappoppop-pro'),
                'description' => __('Celebration animation after a successful submit.', 'zappoppop-pro'),
            ),
            'acc-targeting-triggers' => array(
                'name'        => __('Targeting Triggers', 'zappoppop-pro'),
                'description' => __('Show rules by event, schedule, role, area and URL.', 'zappoppop-pro'),
            ),
            'acc-targeting' => array(
                'name'        => __('Targeting', 'zappoppop-pro'),
                'description' => __('Visitor authentication, targeting and attribution rules.', 'zappoppop-pro'),
            ),
            'acc-woo-cart' => array(
                'name'        => __('WooCommerce Cart', 'zappoppop-pro'),
                'description' => __('Show popups by cart contents and fill live cart values into popup text.', 'zappoppop-pro'),
            ),
        );
    }
}
