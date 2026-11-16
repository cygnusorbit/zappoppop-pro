<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$available_popups = $wpdb->get_results("SELECT uid, title, status FROM {$wpdb->prefix}zappoppop_items WHERE status != 'trash' ORDER BY id DESC");
?>
<div class="zpp-modal-backdrop" id="zappoppop-create-ab-modal">
    <div class="zpp-modal-card">
        <div class="zpp-modal-head">
            <h3><?php esc_html_e('Create A/B Split Testing Experiment', 'zappoppop-pro'); ?></h3>
            <button type="button" class="zpp-modal-close" id="zappoppop-create-ab-close" aria-label="<?php esc_attr_e('Close', 'zappoppop-pro'); ?>">&times;</button>
        </div>
        <div class="zpp-modal-body">
            <input type="hidden" id="zappoppop-ab-edit-uid" value="">
            <label for="zappoppop-ab-title-input"><?php esc_html_e('Campaign Title', 'zappoppop-pro'); ?></label>
            <input type="text" id="zappoppop-ab-title-input" class="widefat" placeholder="<?php esc_attr_e('e.g. Summer Promo Headline Test', 'zappoppop-pro'); ?>">

            <label class="zpp-modal-sep"><?php esc_html_e('Select Popups for Testing (Minimum 2)', 'zappoppop-pro'); ?></label>
            <p class="description"><?php esc_html_e('Traffic will be distributed randomly across your selected variations.', 'zappoppop-pro'); ?></p>

            <div class="zpp-modal-list">
                <?php if (empty($available_popups)) : ?>
                    <p class="zpp-ab-none"><?php esc_html_e('No published popups available. Please create and publish popups in the builder first.', 'zappoppop-pro'); ?></p>
                <?php else : ?>
                    <?php foreach ($available_popups as $ap) : ?>
                        <label class="zpp-modal-item">
                            <input type="checkbox" class="zappoppop-ab-popup-checkbox" value="<?php echo esc_attr($ap->uid); ?>">
                            <span><strong><?php echo esc_html($ap->title); ?></strong> <code>(<?php echo esc_html($ap->uid); ?>)</code><?php if (!in_array($ap->status, ['publish', 'published'], true)) : ?> <em class="zpp-ab-pop-off">&mdash; <?php esc_html_e('inactive', 'zappoppop-pro'); ?></em><?php endif; ?></span>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="zpp-modal-foot">
            <button type="button" class="button" id="zappoppop-create-ab-cancel"><?php esc_html_e('Cancel', 'zappoppop-pro'); ?></button>
            <button type="button" class="button button-primary" id="zappoppop-create-ab-submit"><?php esc_html_e('Launch Campaign', 'zappoppop-pro'); ?></button>
        </div>
    </div>
</div>
