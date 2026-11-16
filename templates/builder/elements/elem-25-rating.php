<?php
if (!defined('ABSPATH')) exit;
?>
<!-- DECOMPOSED MODULE 25: STAR RATING (rating) -->
<div class="element-panel zappoppop-panel-specific" id="panel-elem-rating" data-elem-family="form" style="display:none;background:#0f172a;border:1px solid #1e293b;border-radius:8px;padding:12px;margin-bottom:14px;">
    <div style="font-size:10px;font-weight:800;color:#f59e0b;text-transform:uppercase;margin-bottom:10px;letter-spacing:0.5px;"><?php esc_html_e('25. Interactive Rating Scale', 'zappoppop-pro'); ?></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px;">
        <div>
            <label style="display:block;font-size:10px;font-weight:700;color:#94a3b8;margin-bottom:4px;text-transform:uppercase;"><?php esc_html_e('SCALE MAX', 'zappoppop-pro'); ?></label>
            <select id="prop-rating-scale" class="widefat zappoppop-input zappoppop-gp-bind" data-prop="ratingMax" style="font-size:12px;">
                <option value="5"><?php esc_html_e('5 Stars / Icons', 'zappoppop-pro'); ?></option>
                <option value="10"><?php esc_html_e('10 Stars / Icons', 'zappoppop-pro'); ?></option>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:10px;font-weight:700;color:#94a3b8;margin-bottom:4px;text-transform:uppercase;"><?php esc_html_e('ICON GLYPH', 'zappoppop-pro'); ?></label>
            <select id="prop-rating-glyph" class="widefat zappoppop-input zappoppop-gp-bind" data-prop="ratingShape" style="font-size:12px;">
                <option value="star"><?php esc_html_e('★ Star', 'zappoppop-pro'); ?></option>
                <option value="heart"><?php esc_html_e('♥ Heart', 'zappoppop-pro'); ?></option>
                <option value="thumb"><?php esc_html_e('👍 Thumbs Up', 'zappoppop-pro'); ?></option>
            </select>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        <div>
            <label style="display:block;font-size:10px;font-weight:700;color:#94a3b8;margin-bottom:4px;text-transform:uppercase;"><?php esc_html_e('ACTIVE COLOR', 'zappoppop-pro'); ?></label>
            <input type="color" id="prop-rating-active-color" class="widefat zappoppop-gp-bind" data-prop="ratingColor" value="#f59e0b" style="height:34px;padding:2px;background:#090d16;border:1px solid #1e293b;border-radius:6px;cursor:pointer;">
        </div>
        <div>
            <label style="display:block;font-size:10px;font-weight:700;color:#94a3b8;margin-bottom:4px;text-transform:uppercase;"><?php esc_html_e('INACTIVE COLOR', 'zappoppop-pro'); ?></label>
            <input type="color" id="prop-rating-inactive-color" class="widefat zappoppop-gp-bind" data-prop="ratingInactiveColor" value="#475569" style="height:34px;padding:2px;background:#090d16;border:1px solid #1e293b;border-radius:6px;cursor:pointer;">
        </div>
    </div>
</div>

                <!-- RATING -->
                <div class="element-panel" id="panel-elem-rating-legacy" style="display:none;">
                    <label style="display:block;font-size:11px;font-weight:700;color:#475569;margin-bottom:4px;"><?php esc_html_e('DEFAULT STAR RATING (1-5)', 'zappoppop-pro'); ?></label>
                    <input type="number" id="prop-rating-val" min="1" max="5" value="5" class="widefat" style="font-size:12px;margin-bottom:8px;">
                    <label style="display:block;font-size:11px;font-weight:700;color:#475569;margin-bottom:4px;"><?php esc_html_e('STAR COLOR', 'zappoppop-pro'); ?></label>
                    <input type="color" id="prop-rating-color" value="#f59e0b" class="widefat" style="height:32px;margin-bottom:8px;">
                    <label style="display:block;font-size:11px;font-weight:700;color:#475569;margin-bottom:4px;"><?php esc_html_e('FIELD NAME / TOKEN', 'zappoppop-pro'); ?></label>
                    <input type="text" id="prop-rating-fieldname" value="rating" class="widefat" style="font-size:12px;">
                </div>
