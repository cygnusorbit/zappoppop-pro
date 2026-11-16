<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$all_popups_raw = $wpdb->get_results( "SELECT uid, title, impressions, submissions FROM {$wpdb->prefix}zappoppop_items" );
$popups_by_uid = [];
foreach ($all_popups_raw as $p) {
    $popups_by_uid[$p->uid] = $p;
}
if (!isset($ab_state) || !is_array($ab_state)) { // ZAPPOPPOP_AB_FILTERS
    require_once ZAPPOPPOP_PRO_PATH . 'includes/ab/class-admin-ab-query.php';
    $ab_state = ZapPopPop_Admin_AB_Query::run();
}
$campaigns = $ab_state['items'];
$ab_total  = (int) $ab_state['total'];
/* ZAPPOPPOP_AB_COLS_FIX: header cells are built from escaped, translatable labels (absint() on HTML printed "0" and hid the columns) */
$ab_col_defs = [
    'column-num'        => '#',
    'column-title'      => __('Campaign', 'zappoppop-pro'),
    'column-variations' => __('Variations & Performance', 'zappoppop-pro'),
    'column-status'     => __('Status', 'zappoppop-pro'),
    'column-shortcode'  => __('Shortcode', 'zappoppop-pro'),
];
$ab_cols = '';
foreach ($ab_col_defs as $ab_col_class => $ab_col_label) {
    $ab_cols .= '<th scope="col" class="manage-column ' . esc_attr($ab_col_class) . '">' . esc_html($ab_col_label) . '</th>';
}
?>
<div class="zappoppop-table-wrap zpp-ab-table-wrap">
    <?php include ZAPPOPPOP_PRO_PATH . 'templates/ab/toolbar.php'; ?>
    <?php $ab_pos = 'top'; include ZAPPOPPOP_PRO_PATH . 'templates/ab/pagination.php'; ?>
    <table class="wp-list-table widefat fixed striped table-view-list" id="zappoppop-ab-table">
        <thead><tr><?php echo $ab_cols; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr()/esc_html() above ?></tr></thead>
        <tbody>
            <?php if (empty($campaigns)) : ?>
                <tr class="no-items">
                    <td class="colspanchange" colspan="5">
                        <div class="zpp-ab-empty">
                            <span class="dashicons dashicons-randomize"></span>
                            <?php if ($ab_state['search'] !== '' || $ab_state['status'] !== 'all') :
                                esc_html_e('No campaigns match your filters.', 'zappoppop-pro');
                            else :
                                esc_html_e('No A/B testing campaigns found. Click "New A/B Campaign" in the top bar to launch your first split-test experiment.', 'zappoppop-pro');
                            endif; ?>
                        </div>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($campaigns as $idx => $c) :
                    $uids      = json_decode($c->popup_uids, true) ?: [];
                    $shortcode = '[zappoppop_ab uid="' . $c->uid . '"]';
                    $status    = strtolower((string) $c->status);
                    $is_off    = in_array($status, ['draft', 'paused', 'inactive', 'trash'], true);
                    $is_trash  = ($status === 'trash');
                    ?>
                    <tr class="zappoppop-ab-row" data-uid="<?php echo esc_attr($c->uid); ?>" data-title="<?php echo esc_attr($c->title); ?>" data-popups="<?php echo esc_attr(wp_json_encode(array_values($uids))); ?>">
                        <td class="column-num"><?php echo (int) ($ab_state['offset'] + $idx + 1); ?></td>
                        <td class="column-title has-row-actions">
                            <strong class="zpp-ab-title"><?php if ($is_trash) :
                                echo esc_html($c->title);
                            else : ?><a class="row-title zappoppop-edit-ab-btn" href="#" data-uid="<?php echo esc_attr($c->uid); ?>"><?php echo esc_html($c->title); ?></a> <span class="zpp-ab-state-badge <?php echo $is_off ? 'zappoppop-inactive-badge' : 'zappoppop-active-badge'; ?>"><?php echo $is_off ? esc_html__('INACTIVE', 'zappoppop-pro') : esc_html__('ACTIVE', 'zappoppop-pro'); ?></span><?php endif; ?></strong>
                            <div class="zpp-ab-meta"><code><?php echo esc_html($c->uid); ?></code></div>
                            <div class="zpp-ab-meta"><?php esc_html_e('Created:', 'zappoppop-pro'); ?> <?php echo esc_html($c->created_at); ?></div>
                            <div class="row-actions">
                                <?php if ($is_trash) : ?>
                                    <span class="untrash"><button type="button" class="button-link zappoppop-restore-ab-btn" data-uid="<?php echo esc_attr($c->uid); ?>"><?php esc_html_e('Restore', 'zappoppop-pro'); ?></button> | </span>
                                    <span class="delete"><button type="button" class="button-link zappoppop-del-ab-btn" data-uid="<?php echo esc_attr($c->uid); ?>"><?php esc_html_e('Delete Permanently', 'zappoppop-pro'); ?></button></span>
                                <?php else : ?>
                                    <span class="edit"><button type="button" class="button-link zappoppop-edit-ab-btn" data-uid="<?php echo esc_attr($c->uid); ?>"><?php esc_html_e('Edit', 'zappoppop-pro'); ?></button> | </span>
                                    <span class="delete"><button type="button" class="button-link zappoppop-trash-ab-btn" data-uid="<?php echo esc_attr($c->uid); ?>"><?php esc_html_e('Trash', 'zappoppop-pro'); ?></button></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="column-variations">
                            <?php if (empty($uids)) : ?>
                                <span class="zpp-ab-none"><?php esc_html_e('No variations attached.', 'zappoppop-pro'); ?></span>
                            <?php else : ?>
                                <div class="zpp-ab-vars">
                                    <?php foreach ($uids as $var_idx => $v_uid) :
                                        $pop     = isset($popups_by_uid[$v_uid]) ? $popups_by_uid[$v_uid] : null;
                                        $v_title = $pop ? $pop->title : 'Unknown (' . $v_uid . ')';
                                        $v_impr  = $pop ? intval($pop->impressions) : 0;
                                        $v_subs  = $pop ? intval($pop->submissions) : 0;
                                        $v_cr    = $v_impr > 0 ? round(($v_subs / $v_impr) * 100, 1) : 0;
                                        $letter  = chr(65 + ($var_idx % 26));
                                        ?>
                                        <div class="zpp-ab-var">
                                            <div class="head">
                                                <span class="letter"><?php /* translators: %s: variant letter, e.g. A or B. */ echo esc_html(sprintf(__('Variant %s', 'zappoppop-pro'), $letter)); ?></span>
                                                <span class="cr"><?php /* translators: %s: conversion rate value. */ echo esc_html(sprintf(__('%s%% CR', 'zappoppop-pro'), $v_cr)); ?></span>
                                            </div>
                                            <div class="name" title="<?php echo esc_attr($v_title); ?>"><?php echo esc_html($v_title); ?></div>
                                            <div class="stats"><?php esc_html_e('Impr:', 'zappoppop-pro'); ?> <strong><?php echo esc_html(number_format($v_impr)); ?></strong> <?php esc_html_e('• Leads:', 'zappoppop-pro'); ?> <strong><?php echo esc_html(number_format($v_subs)); ?></strong></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="column-status">
                            <?php if ($is_trash) : ?>
                            <span class="zpp-badge is-off"><?php esc_html_e('Trash', 'zappoppop-pro'); ?></span>
                            <?php else : ?>
                            <label class="zpp-ab-switch" title="<?php echo esc_attr($is_off ? __('Inactive', 'zappoppop-pro') : __('Active', 'zappoppop-pro')); ?>">
                                <input type="checkbox" class="zpp-ab-status-toggle" data-uid="<?php echo esc_attr($c->uid); ?>" role="switch" aria-label="<?php esc_attr_e('Campaign active', 'zappoppop-pro'); ?>"<?php echo $is_off ? '' : ' checked="checked"'; ?> />
                                <span class="zpp-ab-switch-slider" aria-hidden="true"></span>
                            </label>
                            <?php endif; /* ZAPPOPPOP_AB_MATCH */ ?>
                        </td>
                        <td class="column-shortcode">
                            <code class="zappoppop-shortcode-chip zappoppop-copy-ab-sc-btn" data-shortcode="<?php echo esc_attr($shortcode); ?>" title="<?php esc_attr_e('Click to copy shortcode', 'zappoppop-pro'); ?>"><?php echo esc_html($shortcode); ?></code>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot><tr><?php echo $ab_cols; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr()/esc_html() above ?></tr></tfoot>
    </table>
    <?php $ab_pos = 'bottom'; include ZAPPOPPOP_PRO_PATH . 'templates/ab/pagination.php'; ?>
</div>
