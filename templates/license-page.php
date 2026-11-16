<?php
if (!defined('ABSPATH')) {
    exit;
}

$bar_title   = __('License', 'zappoppop-pro');
$bar_icon    = 'dashicons-admin-network';
$bar_actions = '';
?>
<div class="wrap zappoppop-dash zappoppop-license-wrap">
    <h1 class="screen-reader-text"><?php esc_html_e('License', 'zappoppop-pro'); ?></h1>
    <?php
    if (file_exists(ZAPPOPPOP_PATH . 'templates/partials/dash-bar.php')) {
        include ZAPPOPPOP_PATH . 'templates/partials/dash-bar.php';
    }
    ?>
    <div class="zpp-lic-card">
        <div class="zpp-lic-head">
            <span class="dashicons dashicons-admin-network"></span>
            <h3><?php esc_html_e('Licensing is not set up yet', 'zappoppop-pro'); ?></h3>
        </div>
        <p><?php esc_html_e('ZapPopPop Pro uses Freemius for licence keys, updates and your account. Add the product details from your Freemius Developer Dashboard (Products > ZapPopPop Pro > SDK Integration) to zappoppop-pro.php, or define them in wp-config.php:', 'zappoppop-pro'); ?></p>
        <ul class="zpp-lic-list">
            <li><code>ZAPPOPPOP_PRO_FS_ID</code> &mdash; <?php esc_html_e('Product ID', 'zappoppop-pro'); ?></li>
            <li><code>ZAPPOPPOP_PRO_FS_KEY</code> &mdash; <?php esc_html_e('Public key (starts with pk_)', 'zappoppop-pro'); ?></li>
            <li><code>ZAPPOPPOP_PRO_FS_SLUG</code> &mdash; <?php esc_html_e('Product slug', 'zappoppop-pro'); ?></li>
        </ul>
        <p class="zpp-lic-help"><?php esc_html_e('Your campaigns, statistics and settings are safe. Everything you created stays stored and keeps working on your site.', 'zappoppop-pro'); ?></p>
    </div>
</div>
