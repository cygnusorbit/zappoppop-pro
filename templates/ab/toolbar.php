<?php
/* ZAPPOPPOP_AB_FILTERS */
if (!defined('ABSPATH')) {
    exit;
}
$ab_views = [
    'all'      => __('All', 'zappoppop-pro'),
    'active'   => __('Active', 'zappoppop-pro'),
    'inactive' => __('Inactive', 'zappoppop-pro'),
    'trash'    => __('Trash', 'zappoppop-pro'),
];
?>
<div class="zappoppop-list-toolbar zappoppop-ab-toolbar">
    <ul class="subsubsub">
        <?php foreach ($ab_views as $ab_key => $ab_label) :
            $ab_is_cur = ($ab_state['status'] === $ab_key);
            ?>
            <li class="<?php echo esc_attr($ab_key); ?>"><a href="<?php echo esc_url(ZapPopPop_Admin_AB_Query::url($ab_state, ['status' => $ab_key])); ?>" class="zappoppop-view-btn<?php echo $ab_is_cur ? ' current' : ''; ?>"<?php echo $ab_is_cur ? ' aria-current="page"' : ''; ?>><?php echo esc_html($ab_label); ?> <span class="count">(<?php echo (int) $ab_state['counts'][$ab_key]; ?>)</span></a></li>
        <?php endforeach; ?>
    </ul>
    <form method="get" class="search-box" action="<?php echo esc_url(admin_url('admin.php')); ?>" role="search">
        <input type="hidden" name="page" value="zappoppop-ab" />
        <?php if ($ab_state['status'] !== 'all') : ?>
            <input type="hidden" name="status" value="<?php echo esc_attr($ab_state['status']); ?>" />
        <?php endif; ?>
        <label class="screen-reader-text" for="zappoppop-ab-search-input"><?php esc_html_e('Search campaigns:', 'zappoppop-pro'); ?></label>
        <input type="search" id="zappoppop-ab-search-input" name="s" value="<?php echo esc_attr($ab_state['search']); ?>" placeholder="<?php esc_attr_e('Title or UID', 'zappoppop-pro'); ?>" />
        <input type="submit" class="button" value="<?php esc_attr_e('Search Campaigns', 'zappoppop-pro'); ?>" />
        <?php if ($ab_state['search'] !== '') : ?>
            <a class="zappoppop-ab-search-clear" href="<?php echo esc_url(ZapPopPop_Admin_AB_Query::url($ab_state, ['s' => ''])); ?>"><?php esc_html_e('Clear', 'zappoppop-pro'); ?></a>
        <?php endif; ?>
    </form>
</div>
