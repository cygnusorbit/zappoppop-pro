<?php if (!defined('ABSPATH')) exit; ?>
<?php if (ZapPopPop_Pro_Elements::available('progress')) : ?>
<button type="button" class="zappoppop-ribbon-btn zappoppop-ribbon-tool zappoppop-draggable-tool" data-type="progress" title="<?php esc_attr_e('8. Progress Bar (progress)', 'zappoppop-pro'); ?>"><span class="dashicons dashicons-chart-bar"></span><span class="btn-label"><?php esc_html_e('Progress', 'zappoppop-pro'); ?></span></button>
<?php endif; ?>
<?php if (ZapPopPop_Pro_Elements::available('imageselect')) : ?>
<button type="button" class="zappoppop-ribbon-btn zappoppop-ribbon-tool zappoppop-draggable-tool" data-type="imageselect" title="<?php esc_attr_e('19. Image Select (imageselect)', 'zappoppop-pro'); ?>"><span class="dashicons dashicons-images-alt2"></span><span class="btn-label"><?php esc_html_e('ImgSel', 'zappoppop-pro'); ?></span></button>
<?php endif; ?>
<?php if (ZapPopPop_Pro_Elements::available('tile')) : ?>
<button type="button" class="zappoppop-ribbon-btn zappoppop-ribbon-tool zappoppop-draggable-tool" data-type="tile" title="<?php esc_attr_e('20. Tile (tile)', 'zappoppop-pro'); ?>"><span class="dashicons dashicons-grid-view"></span><span class="btn-label"><?php esc_html_e('Tile', 'zappoppop-pro'); ?></span></button>
<?php endif; ?>
<?php if (ZapPopPop_Pro_Elements::available('rating')) : ?>
<button type="button" class="zappoppop-ribbon-btn zappoppop-ribbon-tool zappoppop-draggable-tool" data-type="rating" title="<?php esc_attr_e('25. Rating (rating)', 'zappoppop-pro'); ?>"><span class="dashicons dashicons-star-half"></span><span class="btn-label"><?php esc_html_e('Rating', 'zappoppop-pro'); ?></span></button>
<?php endif; ?>
