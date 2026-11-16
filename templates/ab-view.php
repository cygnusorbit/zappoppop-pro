<?php
if (!defined('ABSPATH')) {
    exit;
}
$bar_title   = __('A/B Campaigns', 'zappoppop-pro');
$bar_icon    = 'dashicons-randomize';
$bar_actions = ZAPPOPPOP_PRO_PATH . 'templates/ab/bar-actions.php';
?>
<div class="wrap zappoppop-dashboard-wrap zappoppop-ab-wrap" style="width: 100%; padding-right: 30px; max-width: none; box-sizing: border-box;">
    <h1 class="screen-reader-text"><?php esc_html_e('A/B Campaigns', 'zappoppop-pro'); ?></h1>

    <!-- Top Bar (shared with Popups) -->
    <div class="zappoppop-dash" style="margin:0 0 20px;max-width:none;">
        <?php include ZAPPOPPOP_PATH . 'templates/partials/dash-bar.php'; ?>
    </div>

    <!-- Notice container -->
    <?php include ZAPPOPPOP_PRO_PATH . 'templates/ab/header.php'; ?>

    <!-- Experiments List Table -->
    <?php include ZAPPOPPOP_PRO_PATH . 'templates/ab/table.php'; ?>

    <!-- Create Experiment Modal Dialog -->
    <?php include ZAPPOPPOP_PRO_PATH . 'templates/ab/modal-create.php'; ?>
</div>
