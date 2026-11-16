<?php
if (!defined('ABSPATH')) {
    exit;
}

$bar_title   = $locked_title;
$bar_icon    = $locked_icon;
$bar_actions = '';
?>
<div class="wrap zappoppop-dash zappoppop-license-wrap">
    <h1 class="screen-reader-text"><?php echo esc_html($locked_title); ?></h1>
    <?php
    if (file_exists(ZAPPOPPOP_PATH . 'templates/partials/dash-bar.php')) {
        include ZAPPOPPOP_PATH . 'templates/partials/dash-bar.php';
    }
    ?>
    <div class="zpp-lic-card">
        <div class="zpp-lic-head">
            <span class="dashicons dashicons-lock"></span>
            <h3><?php echo esc_html($locked_title); ?></h3>
        </div>
        <p><?php echo esc_html($locked_desc); ?></p>
        <p class="zpp-lic-help"><?php esc_html_e('Your campaigns, statistics and settings are safe. Everything you created stays stored and keeps working on your site.', 'zappoppop-pro'); ?></p>
        <div class="zpp-lic-actions">
            <a class="button button-primary zpp-lic-btn" href="<?php echo esc_url(ZapPopPop_Pro_License::activate_url()); ?>"><span class="dashicons dashicons-admin-network"></span> <?php esc_html_e('Activate licence', 'zappoppop-pro'); ?></a>
        </div>
    </div>
</div>
