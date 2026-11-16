<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * A/B Campaigns AJAX: edit campaign + status toggle.
 * Whitelist: uid (sanitize_key), title (text), popup_uids (existing popup uids, >= 2), status (active|paused).
 */
class ZapPopPop_Ajax_Campaigns {
    public function __construct() {
        add_action('wp_ajax_zappoppop_save_campaign', [$this, 'save_campaign']);
        add_action('wp_ajax_zappoppop_delete_campaign', [$this, 'delete_campaign']);
        add_action('wp_ajax_zappoppop_update_campaign', [$this, 'update_campaign']);
        add_action('wp_ajax_zappoppop_toggle_campaign', [$this, 'toggle_campaign']);
        add_action('wp_ajax_zappoppop_trash_campaign', [$this, 'trash_campaign']);
        add_action('wp_ajax_zappoppop_restore_campaign', [$this, 'restore_campaign']);
    }

    private function verify_security() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized action.', 'zappoppop-pro')], 403);
        }
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'zappoppop_admin_nonce') && !wp_verify_nonce($nonce, 'zappoppop_builder_nonce')) {
            wp_send_json_error(['message' => __('Security check failed. Please refresh the page.', 'zappoppop-pro')], 403);
        }
    }

    private function find($uid) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT id, uid, status FROM {$wpdb->prefix}zappoppop_campaigns WHERE uid = %s", $uid), ARRAY_A);
    }

    public function update_campaign() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized user capability.', 'zappoppop-pro' ) ), 403 );
            wp_die();
        }
        $zpp_verified = check_ajax_referer( 'zappoppop_nonce', 'nonce', false )
            || check_ajax_referer( 'zappoppop_nonce', '_ajax_nonce', false )
            || check_ajax_referer( 'zappoppop_admin', 'nonce', false )
            || check_ajax_referer( 'zappoppop_nonce', 'security', false )
            || check_ajax_referer( 'zappoppop_builder_nonce', 'nonce', false )
            || check_ajax_referer( 'zappoppop_admin_nonce', 'nonce', false )
            || check_ajax_referer( 'zappoppop_settings_nonce', 'nonce', false );
        if ( ! $zpp_verified ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid security token.', 'zappoppop-pro' ) ), 403 );
            wp_die();
        }

        $this->verify_security();
        global $wpdb;

        $uid   = isset($_POST['uid']) ? sanitize_key(wp_unslash($_POST['uid'])) : '';
        $title = isset($_POST['title']) ? trim(sanitize_text_field(wp_unslash($_POST['title']))) : '';
        $raw   = isset($_POST['popup_uids']) ? (array) wp_unslash($_POST['popup_uids']) : [];
        $uids  = array_values(array_unique(array_filter(array_map('sanitize_key', $raw))));

        if ($uid === '' || !$this->find($uid)) {
            wp_send_json_error(['message' => __('Campaign not found.', 'zappoppop-pro')]);
        }
        if ($title === '') {
            wp_send_json_error(['message' => __('Please enter a campaign title.', 'zappoppop-pro')]);
        }
        if (count($uids) < 2) {
            wp_send_json_error(['message' => __('Select at least 2 popups for split-testing.', 'zappoppop-pro')]);
        }

        // Keep only popups that really exist (and are not trashed).
        $ph    = implode(',', array_fill(0, count($uids), '%s'));
        $found = $wpdb->get_col($wpdb->prepare("SELECT uid FROM {$wpdb->prefix}zappoppop_items WHERE status != 'trash' AND uid IN ($ph)", $uids));
        $uids  = array_values(array_intersect($uids, (array) $found));
        if (count($uids) < 2) {
            wp_send_json_error(['message' => __('At least 2 of the selected popups must exist.', 'zappoppop-pro')]);
        }

        $ok = $wpdb->update(
            $wpdb->prefix . 'zappoppop_campaigns',
            ['title' => $title, 'popup_uids' => wp_json_encode($uids)],
            ['uid' => $uid],
            ['%s', '%s'],
            ['%s']
        );
        if ($ok === false) {
            wp_send_json_error(['message' => __('Database error while saving.', 'zappoppop-pro')]);
        }
        wp_send_json_success(['uid' => $uid, 'title' => $title, 'popup_uids' => $uids]);
    }

    public function toggle_campaign() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized user capability.', 'zappoppop-pro' ) ), 403 );
            wp_die();
        }
        $zpp_verified = check_ajax_referer( 'zappoppop_nonce', 'nonce', false )
            || check_ajax_referer( 'zappoppop_nonce', '_ajax_nonce', false )
            || check_ajax_referer( 'zappoppop_admin', 'nonce', false )
            || check_ajax_referer( 'zappoppop_nonce', 'security', false )
            || check_ajax_referer( 'zappoppop_builder_nonce', 'nonce', false )
            || check_ajax_referer( 'zappoppop_admin_nonce', 'nonce', false )
            || check_ajax_referer( 'zappoppop_settings_nonce', 'nonce', false );
        if ( ! $zpp_verified ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid security token.', 'zappoppop-pro' ) ), 403 );
            wp_die();
        }

        $this->verify_security();
        global $wpdb;

        $uid    = isset($_POST['uid']) ? sanitize_key(wp_unslash($_POST['uid'])) : '';
        $active = isset($_POST['active']) && !in_array((string) wp_unslash($_POST['active']), ['', '0', 'false'], true);

        if ($uid === '' || !$this->find($uid)) {
            wp_send_json_error(['message' => __('Campaign not found.', 'zappoppop-pro')]);
        }
        $row = $this->find($uid);
        if ($row && strtolower((string) $row['status']) === 'trash') {
            wp_send_json_error(['message' => __('Restore this campaign before changing its status.', 'zappoppop-pro')]);
        }
        $status = $active ? 'active' : 'paused';
        $ok = $wpdb->update($wpdb->prefix . 'zappoppop_campaigns', ['status' => $status], ['uid' => $uid], ['%s'], ['%s']);
        if ($ok === false) {
            wp_send_json_error(['message' => __('Database error while saving.', 'zappoppop-pro')]);
        }
        wp_send_json_success(['uid' => $uid, 'status' => $status]);
    }

    /* ZAPPOPPOP_AB_FILTERS: soft trash / restore (permanent delete stays zappoppop_delete_campaign) */
    private function set_status($status) {
        $this->verify_security();
        global $wpdb;

        $uid = isset($_POST['uid']) ? sanitize_key(wp_unslash($_POST['uid'])) : '';
        if ($uid === '' || !$this->find($uid)) {
            wp_send_json_error(['message' => __('Campaign not found.', 'zappoppop-pro')]);
        }
        $ok = $wpdb->update($wpdb->prefix . 'zappoppop_campaigns', ['status' => $status], ['uid' => $uid], ['%s'], ['%s']);
        if ($ok === false) {
            wp_send_json_error(['message' => __('Database error while saving.', 'zappoppop-pro')]);
        }
        wp_send_json_success(['uid' => $uid, 'status' => $status]);
    }

    public function trash_campaign() {
        $this->set_status('trash');
    }

    public function restore_campaign() {
        $this->set_status('paused');
    }

    public function save_campaign() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized user capability.', 'zappoppop-pro' ) ), 403 );
            wp_die();
        }
        $zpp_verified = check_ajax_referer( 'zappoppop_nonce', 'nonce', false )
            || check_ajax_referer( 'zappoppop_nonce', '_ajax_nonce', false )
            || check_ajax_referer( 'zappoppop_admin', 'nonce', false )
            || check_ajax_referer( 'zappoppop_nonce', 'security', false )
            || check_ajax_referer( 'zappoppop_builder_nonce', 'nonce', false )
            || check_ajax_referer( 'zappoppop_admin_nonce', 'nonce', false )
            || check_ajax_referer( 'zappoppop_settings_nonce', 'nonce', false );
        if ( ! $zpp_verified ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid security token.', 'zappoppop-pro' ) ), 403 );
            wp_die();
        }

        $this->verify_security(); /* ZAPPOPPOP_AB_VALIDATED */
        global $wpdb;

        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $raw   = isset($_POST['popup_uids']) ? (array) wp_unslash($_POST['popup_uids']) : [];
        $uids  = array_values(array_unique(array_filter(array_map('sanitize_key', $raw))));

        if ($title === '') {
            wp_send_json_error(['message' => __('Please enter a campaign title.', 'zappoppop-pro')]);
        }
        if (count($uids) < 2) {
            wp_send_json_error(['message' => 'Select at least 2 popups for split-testing.']);
        }

        $ok = $wpdb->insert($wpdb->prefix . 'zappoppop_campaigns', [
            'uid'        => wp_generate_uuid4(),
            'title'      => $title,
            'popup_uids' => wp_json_encode($uids),
            'status'     => 'active'
        ]);
        if ($ok === false) {
            wp_send_json_error(['message' => __('Database error: could not save the campaign. Try Tools > Repair Tables.', 'zappoppop-pro')]);
        }
        wp_send_json_success(['message' => __('A/B Campaign saved!', 'zappoppop-pro')]);
    }

    public function delete_campaign() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized user capability.', 'zappoppop-pro' ) ), 403 );
            wp_die();
        }
        $zpp_verified = check_ajax_referer( 'zappoppop_nonce', 'nonce', false )
            || check_ajax_referer( 'zappoppop_nonce', '_ajax_nonce', false )
            || check_ajax_referer( 'zappoppop_admin', 'nonce', false )
            || check_ajax_referer( 'zappoppop_nonce', 'security', false )
            || check_ajax_referer( 'zappoppop_builder_nonce', 'nonce', false )
            || check_ajax_referer( 'zappoppop_admin_nonce', 'nonce', false )
            || check_ajax_referer( 'zappoppop_settings_nonce', 'nonce', false );
        if ( ! $zpp_verified ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid security token.', 'zappoppop-pro' ) ), 403 );
            wp_die();
        }

        $this->verify_security();
        global $wpdb;

        $uid = isset($_POST['uid']) ? sanitize_key(wp_unslash($_POST['uid'])) : '';
        if ($uid === '') {
            wp_send_json_error(['message' => __('Missing campaign UID.', 'zappoppop-pro')]);
        }
        $deleted = $wpdb->delete($wpdb->prefix . 'zappoppop_campaigns', ['uid' => $uid], ['%s']);
        if (!$deleted) {
            wp_send_json_error(['message' => __('Campaign not found or already deleted.', 'zappoppop-pro')]);
        }
        wp_send_json_success(['message' => __('Campaign deleted.', 'zappoppop-pro')]);
    }
}
