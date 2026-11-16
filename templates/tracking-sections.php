<?php
if (!defined('ABSPATH')) {
    exit;
}
/* Printed inside Settings > Tracking. $t, $chk and $num are the helpers passed by core. */
?>
    <h3><?php esc_html_e('Statistics & Reports', 'zappoppop-pro'); ?></h3>
    <table class="form-table">
        <?php $num('track_retention_days', __('Daily Stats Retention', 'zappoppop-pro'), 0, 3650, __('days (0 = keep forever)', 'zappoppop-pro')); ?>
        <?php $chk('report_enabled', __('Weekly Email Report', 'zappoppop-pro'), __('Email impressions, leads and CR per campaign every week', 'zappoppop-pro')); ?>
        <tr>
            <th scope="row"><label for="zpp-report_email"><?php esc_html_e('Report Recipient', 'zappoppop-pro'); ?></label></th>
            <td><input type="email" id="zpp-report_email" name="settings[report_email]" value="<?php echo esc_attr($t('report_email')); ?>" class="regular-text" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>"></td>
        </tr>
    </table>

    <h3><?php esc_html_e('A/B Testing', 'zappoppop-pro'); ?></h3>
    <table class="form-table">
        <tr>
            <th scope="row"><label for="zpp-ab_default_mode"><?php esc_html_e('Default Embed Type', 'zappoppop-pro'); ?></label></th>
            <td>
                <select id="zpp-ab_default_mode" name="settings[ab_default_mode]">
                    <option value="inline" <?php selected($t('ab_default_mode'), 'inline'); ?>><?php esc_html_e('Inline Popup', 'zappoppop-pro'); ?></option>
                    <option value="onload" <?php selected($t('ab_default_mode'), 'onload'); ?>><?php esc_html_e('On-Load Popup', 'zappoppop-pro'); ?></option>
                </select>
                <p class="description"><?php esc_html_e('Used by [zappoppop_ab] when the shortcode has no mode attribute.', 'zappoppop-pro'); ?></p>
            </td>
        </tr>
        <?php $num('ab_cookie_days', __('Variant Cookie Lifetime', 'zappoppop-pro'), 1, 365, __('days', 'zappoppop-pro')); ?>
        <?php $chk('ab_sticky_user', __('Sticky Variant by User', 'zappoppop-pro'), __('Logged-in visitors always see the same variant on every device', 'zappoppop-pro')); ?>
        <?php $chk('ab_auto_promote', __('Auto-Promote Winner', 'zappoppop-pro'), __('Keep only the best variant once the result is statistically significant', 'zappoppop-pro'), __('Uses each popup\'s lifetime impressions and leads. Checked daily.', 'zappoppop-pro')); ?>
        <?php $num('ab_promote_min_impr', __('Minimum Impressions per Variant', 'zappoppop-pro'), 50, 100000); ?>
        <tr>
            <th scope="row"><label for="zpp-ab_promote_confidence"><?php esc_html_e('Confidence Level', 'zappoppop-pro'); ?></label></th>
            <td>
                <select id="zpp-ab_promote_confidence" name="settings[ab_promote_confidence]">
                    <?php foreach ([90, 95, 99] as $c) : ?>
                        <option value="<?php echo (int) $c; ?>" <?php selected((int) $t('ab_promote_confidence'), $c); ?>><?php echo (int) $c; ?>%</option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
    </table>

    <h3><?php esc_html_e('Cache Compatibility', 'zappoppop-pro'); ?></h3>
    <table class="form-table">
        <?php $chk('cache_nocache_ab', __('Exclude A/B Pages from Page Cache', 'zappoppop-pro'), __('Send the DONOTCACHEPAGE flag on pages that contain [zappoppop_ab]', 'zappoppop-pro'), __('Honoured by WP Rocket, W3 Total Cache, LiteSpeed and WP Super Cache.', 'zappoppop-pro')); ?>
    </table>

