<?php
if (!defined('ABSPATH')) {
    exit;
}

/* Included by license-page.php, which sets $bar_title / $bar_icon / $bar_actions for core's dashboard bar. */
$lic        = ZapPopPop_Pro_EDD::data();
$configured = ZapPopPop_Pro_EDD::is_configured();
$can        = ZapPopPop_Pro_EDD::can_manage();
$in_network = is_network_admin();
$has_key    = $lic['key'] !== '';
$active     = $has_key && !empty($lic['activated']);
$status     = (string) $lic['status'];
$pill       = '';
$label      = __('Not activated', 'zappoppop-pro');
if ($active && $status === 'valid') {
    $pill  = 'is-ok';
    $label = __('Active', 'zappoppop-pro');
} elseif ($active && $status === 'expired') {
    $pill  = 'is-warn';
    $label = __('Expired', 'zappoppop-pro');
} elseif ($has_key && $status !== '') {
    $pill  = 'is-off';
    $label = ZapPopPop_Pro_EDD::status_label($status);
}
$store_host = $configured ? (string) wp_parse_url(ZapPopPop_Pro_EDD::store_url(), PHP_URL_HOST) : '';
$show_note  = isset($_GET['zappoppop_edd']) && $lic['message'] !== ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
?>
<div class="wrap zappoppop-dash zappoppop-license-wrap">
    <h1 class="screen-reader-text"><?php esc_html_e('License', 'zappoppop-pro'); ?></h1>
    <?php
    if (file_exists(ZAPPOPPOP_PATH . 'templates/partials/dash-bar.php')) {
        include ZAPPOPPOP_PATH . 'templates/partials/dash-bar.php';
    }
    ?>
    <?php if ($show_note) : ?>
        <div class="notice inline <?php echo !empty($lic['ok']) ? 'notice-success' : 'notice-error'; ?>"><p><?php echo esc_html($lic['message']); ?></p></div>
    <?php endif; ?>

    <?php if (!$configured) : ?>
        <div class="zpp-lic-card">
            <div class="zpp-lic-head">
                <span class="dashicons dashicons-admin-network"></span>
                <h3><?php esc_html_e('Licensing is not set up yet', 'zappoppop-pro'); ?></h3>
            </div>
            <p><?php esc_html_e('ZapPopPop Pro checks your licence key and delivers updates through Easy Digital Downloads. Add your store details to wp-config.php, above the line that says "That\'s all, stop editing!":', 'zappoppop-pro'); ?></p>
            <ul class="zpp-lic-list">
                <li><code>ZAPPOPPOP_PRO_EDD_URL</code> &mdash; <?php esc_html_e('Store home URL (https)', 'zappoppop-pro'); ?></li>
                <li><code>ZAPPOPPOP_PRO_EDD_ITEM_ID</code> &mdash; <?php esc_html_e('Download ID of ZapPopPop Pro in your store', 'zappoppop-pro'); ?></li>
            </ul>
            <pre class="zpp-lic-code"><code><?php echo esc_html("define( 'ZAPPOPPOP_PRO_EDD_URL', 'https://your-store.com/' );\ndefine( 'ZAPPOPPOP_PRO_EDD_ITEM_ID', 123 );"); ?></code></pre>
            <p class="zpp-lic-help"><?php esc_html_e('Your campaigns, statistics and settings are safe. Everything you created stays stored and keeps working on your site.', 'zappoppop-pro'); ?></p>
        </div>
    <?php else : ?>
        <div class="zpp-lic-layout">
            <div>
                <div class="zpp-lic-card">
                    <div class="zpp-lic-head">
                        <span class="dashicons dashicons-admin-network"></span>
                        <h3><?php esc_html_e('Licence key', 'zappoppop-pro'); ?></h3>
                        <span class="zpp-lic-pill <?php echo esc_attr($pill); ?>"><?php echo esc_html($label); ?></span>
                    </div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" autocomplete="off">
                        <input type="hidden" name="action" value="<?php echo esc_attr(ZapPopPop_Pro_EDD::ACTION); ?>">
                        <input type="hidden" name="zappoppop_network" value="<?php echo $in_network ? '1' : '0'; ?>">
                        <?php wp_nonce_field(ZapPopPop_Pro_EDD::ACTION, 'zappoppop_edd_nonce'); ?>
                        <?php if ($active) : ?>
                            <span class="zpp-lic-key"><?php echo esc_html(ZapPopPop_Pro_EDD::masked_key($lic['key'])); ?></span>
                            <?php if ($status === 'expired') : ?>
                                <p class="zpp-lic-help"><?php esc_html_e('Your licence has expired. Every Pro feature keeps working; renew it to receive updates and support.', 'zappoppop-pro'); ?></p>
                            <?php endif; ?>
                            <dl class="zpp-lic-meta">
                                <dt><?php esc_html_e('Status', 'zappoppop-pro'); ?></dt>
                                <dd><?php echo esc_html($label); ?></dd>
                                <dt><?php esc_html_e('Expires', 'zappoppop-pro'); ?></dt>
                                <dd><?php echo esc_html(ZapPopPop_Pro_EDD::expires_label($lic['expires']) ?: '—'); ?></dd>
                                <dt><?php esc_html_e('Last checked', 'zappoppop-pro'); ?></dt>
                                <dd><?php echo $lic['checked'] ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), (int) $lic['checked'])) : '—'; ?></dd>
                            </dl>
                            <div class="zpp-lic-actions">
                                <button type="submit" name="zappoppop_do" value="check" class="button zpp-lic-btn"<?php disabled(!$can); ?>><span class="dashicons dashicons-update"></span> <?php esc_html_e('Check now', 'zappoppop-pro'); ?></button>
                                <button type="submit" name="zappoppop_do" value="deactivate" class="button zpp-lic-btn"<?php disabled(!$can); ?>><span class="dashicons dashicons-dismiss"></span> <?php esc_html_e('Deactivate', 'zappoppop-pro'); ?></button>
                            </div>
                        <?php else : ?>
                            <label class="zpp-lic-label" for="zpp-edd-key"><?php esc_html_e('Enter your licence key', 'zappoppop-pro'); ?></label>
                            <input type="text" id="zpp-edd-key" name="zappoppop_key" class="regular-text zpp-lic-input" value="" spellcheck="false" autocomplete="off"<?php disabled(!$can); ?>>
                            <p class="zpp-lic-help"><?php esc_html_e('You can find the key in your purchase receipt and in your account on the store.', 'zappoppop-pro'); ?></p>
                            <div class="zpp-lic-actions">
                                <button type="submit" name="zappoppop_do" value="activate" class="button button-primary zpp-lic-btn"<?php disabled(!$can); ?>><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Activate licence', 'zappoppop-pro'); ?></button>
                            </div>
                        <?php endif; ?>
                        <?php if (!$can) : ?>
                            <p class="zpp-lic-help"><?php esc_html_e('Only a network administrator can change the licence.', 'zappoppop-pro'); ?></p>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <div>
                <div class="zpp-lic-card">
                    <div class="zpp-lic-head">
                        <span class="dashicons dashicons-cloud"></span>
                        <h3><?php esc_html_e('Licence server', 'zappoppop-pro'); ?></h3>
                    </div>
                    <dl class="zpp-lic-meta">
                        <dt><?php esc_html_e('Store', 'zappoppop-pro'); ?></dt>
                        <dd><?php echo esc_html($store_host); ?></dd>
                        <dt><?php esc_html_e('Product ID', 'zappoppop-pro'); ?></dt>
                        <dd><?php echo esc_html((string) ZapPopPop_Pro_EDD::item_id()); ?></dd>
                        <dt><?php esc_html_e('This site', 'zappoppop-pro'); ?></dt>
                        <dd><?php echo esc_html(ZapPopPop_Pro_EDD::site_url()); ?></dd>
                    </dl>
                    <p class="zpp-lic-help"><?php esc_html_e('Activating uses one site slot. Deactivate the licence before you move the site to another domain. Updates arrive in Dashboard > Updates while the licence is active.', 'zappoppop-pro'); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
