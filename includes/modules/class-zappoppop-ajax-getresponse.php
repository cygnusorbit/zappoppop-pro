<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * GetResponse module admin endpoints (builder only): load campaigns and custom fields for the Inspector cards.
 * Every call needs manage_options and the builder (or admin) nonce; the API key is only forwarded to GetResponse.
 */
class ZapPopPop_Ajax_Getresponse {

    public function __construct() {
        add_action('wp_ajax_zappoppop_getresponse_campaigns', array($this, 'campaigns'));
        add_action('wp_ajax_zappoppop_getresponse_fields',    array($this, 'fields'));
    }

    private function guard() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        $ok = $nonce !== '' && (wp_verify_nonce($nonce, 'zappoppop_builder_nonce') || wp_verify_nonce($nonce, 'zappoppop_admin_nonce'));
        if (!$ok || !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'zappoppop')), 403);
        }
        $key = ZapPopPop_Getresponse::clean_key(isset($_POST['api_key']) ? wp_unslash($_POST['api_key']) : '');
        if ($key === '') {
            wp_send_json_error(array('message' => __('Enter a valid GetResponse API key.', 'zappoppop')));
        }
        return $key;
    }

    private function reply($data) {
        if (is_wp_error($data)) { wp_send_json_error(array('message' => $data->get_error_message())); }
        wp_send_json_success($data);
    }

    public function campaigns() {
        $key = $this->guard();
        $res = ZapPopPop_Getresponse::fetch_campaigns($key);
        if (!is_wp_error($res)) {
            $rows = array();
            foreach ($res as $id => $name) { $rows[] = array('id' => (string) $id, 'name' => $name); }
            $res = array('campaigns' => $rows);
        }
        $this->reply($res);
    }

    public function fields() {
        $key = $this->guard();
        $res = ZapPopPop_Getresponse::fetch_fields($key);
        if (!is_wp_error($res)) {
            $rows = array();
            foreach ($res as $tag => $label) { $rows[] = array('tag' => (string) $tag, 'label' => $label); }
            $res = array('fields' => $rows);
        }
        $this->reply($res);
    }
}
