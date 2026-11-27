<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registry of the e-mail marketing integrations (Mailchimp, GetResponse, and later the add-on plugins that ship them).
 *
 * ZapPopPop_Pro_Pipeline asks this class instead of naming each integration, so an integration can live in Pro or in its
 * own add-on plugin. An add-on registers itself on the filter zappoppop_pro_integrations (before zappoppop_loaded):
 *   $defs['mykey'] = array('class' => 'My_Class', 'ajax' => 'My_Ajax_Class' (optional), 'clear' => array('api_key', ...));
 * The class must provide static sanitize( $raw ), redact( $config ), dispatch( $event, $uid, $title, $config, $email,
 * $fields, $submission_id, $country ) and module_active(). 'clear' lists the fields blanked when a popup is saved as a
 * template (account keys and list ids never travel inside a template).
 *
 * Data safety, whether or not an integration is installed:
 *  - The popup-settings key of a missing integration is NOT handled here, so core keeps the value stored for the popup
 *    (see ZapPopPop_Module_Bridge::sanitize_settings) and nothing fires.
 *  - Every known key is always removed from a popup config that is printed or served publicly (account keys are private).
 */
class ZapPopPop_Pro_Integrations {

    /** Settings keys that are private account data even when the integration is not installed. */
    const KNOWN = array('mailchimp', 'getresponse');

    /** Installed integrations (settings key => class, ajax, clear). A class that is missing or incomplete is left out. */
    public static function all() {
        $defs = array(); /* ZAPPOPPOP_P2B: add-on plugins register themselves */
        $defs = apply_filters('zappoppop_pro_integrations', $defs);
        $out  = array();
        if (!is_array($defs)) {
            return $out;
        }
        foreach ($defs as $key => $def) {
            $key = is_string($key) ? sanitize_key($key) : '';
            if ($key === '' || !is_array($def) || empty($def['class']) || !is_string($def['class']) || !class_exists($def['class'])) {
                continue;
            }
            foreach (array('sanitize', 'redact', 'dispatch', 'module_active') as $method) {
                if (!is_callable(array($def['class'], $method))) {
                    continue 2;
                }
            }
            $clear = array();
            if (isset($def['clear']) && is_array($def['clear'])) {
                foreach ($def['clear'] as $field) {
                    if (is_string($field) && $field !== '') {
                        $clear[] = $field;
                    }
                }
            }
            $out[$key] = array(
                'class' => $def['class'],
                'ajax'  => (isset($def['ajax']) && is_string($def['ajax'])) ? $def['ajax'] : '',
                'clear' => $clear,
            );
        }
        return $out;
    }

    /** Keys that never reach a public popup config: the fixed list plus every registered one. */
    public static function known_keys() {
        return array_values(array_unique(array_merge(self::KNOWN, array_keys(self::all()))));
    }

    /** Keys sanitised here (installed integrations only). Other keys keep the value stored for the popup. */
    public static function handled_keys() {
        return array_keys(self::all());
    }

    /** Stores each posted integration list; for a template the account fields are blanked. Not posted = not stored. */
    public static function sanitize($settings, $raw, $template) {
        if (!is_object($settings) || !is_array($raw)) {
            return $settings;
        }
        foreach (self::all() as $key => $def) {
            if (!isset($raw[$key])) {
                continue;
            }
            $items = call_user_func(array($def['class'], 'sanitize'), $raw[$key]);
            if ($template && is_array($items)) {
                foreach ($items as $i => $item) {
                    foreach ($def['clear'] as $field) {
                        $items[$i][$field] = '';
                    }
                }
            }
            $clean          = json_decode(wp_json_encode($items));
            $settings->$key = is_array($clean) ? $clean : array();
        }
        return $settings;
    }

    /** Runs one event (after_submission, after_confirmation) for every installed integration that is switched on. */
    public static function dispatch($event, $uid, $title, $config, $email, $fields, $submission_id, $country) {
        foreach (self::all() as $def) {
            if (call_user_func(array($def['class'], 'module_active'))) {
                call_user_func(array($def['class'], 'dispatch'), $event, $uid, $title, $config, $email, $fields, $submission_id, $country);
            }
        }
    }

    /** Removes the private integration data from a popup config. Runs for installed and missing integrations alike. */
    public static function redact($config) {
        foreach (self::all() as $def) {
            $config = call_user_func(array($def['class'], 'redact'), $config);
        }
        if (is_array($config) && isset($config['settings']) && is_array($config['settings'])) {
            foreach (self::known_keys() as $key) {
                unset($config['settings'][$key]);
            }
        }
        return $config;
    }

    /** Builder-only AJAX endpoints of the installed integrations (audience / campaign / field lookups). */
    public static function boot_admin() {
        foreach (self::all() as $def) {
            $class = $def['ajax'];
            if ($class !== '' && class_exists($class)) {
                new $class();
            }
        }
    }
}
