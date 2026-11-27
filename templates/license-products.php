<?php
if (!defined('ABSPATH')) {
    exit;
}

/* Included by license-edd.php (shares its $active, $can and $in_network). Add-ons covered by the same licence key. */
$zpp_rows = ZapPopPop_Pro_Pass::rows();
if (empty($active) || !$zpp_rows) {
    return;
}
$zpp_missing = false;
foreach ($zpp_rows as $zpp_row) {
    if (!$zpp_row['included']) {
        $zpp_missing = true;
    }
}
?>
                <div class="zpp-lic-card">
                    <div class="zpp-lic-head">
                        <span class="dashicons dashicons-screenoptions"></span>
                        <h3><?php esc_html_e('Add-ons on this licence', 'zappoppop-pro'); ?></h3>
                    </div>
                    <dl class="zpp-lic-meta">
                        <?php foreach ($zpp_rows as $zpp_row) : ?>
                            <dt><?php echo esc_html($zpp_row['name']); ?></dt>
                            <dd>
                                <span class="zpp-lic-pill <?php echo esc_attr($zpp_row['pill']); ?>"><?php echo esc_html($zpp_row['label']); ?></span>
                                <?php if ($zpp_row['version'] !== '') : ?>
                                    <small>v<?php echo esc_html($zpp_row['version']); ?></small>
                                <?php endif; ?>
                            </dd>
                        <?php endforeach; ?>
                    </dl>
                    <?php if ($zpp_missing) : ?>
                        <p class="zpp-lic-help"><?php esc_html_e('Your key does not cover every add-on. An All Access Pass unlocks Pro and all add-ons with one key.', 'zappoppop-pro'); ?></p>
                    <?php endif; ?>
                    <p class="zpp-lic-help"><?php esc_html_e('Install an add-on like any plugin. It is activated with the key above and updated from Dashboard > Updates.', 'zappoppop-pro'); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(ZapPopPop_Pro_EDD::ACTION); ?>">
                        <input type="hidden" name="zappoppop_network" value="<?php echo !empty($in_network) ? '1' : '0'; ?>">
                        <input type="hidden" name="zappoppop_edd_nonce" value="<?php echo esc_attr(wp_create_nonce(ZapPopPop_Pro_EDD::ACTION)); ?>">
                        <div class="zpp-lic-actions">
                            <button type="submit" name="zappoppop_do" value="sync" class="button zpp-lic-btn"<?php disabled(empty($can)); ?>><span class="dashicons dashicons-update"></span> <?php esc_html_e('Sync add-ons', 'zappoppop-pro'); ?></button>
                        </div>
                    </form>
                </div>
