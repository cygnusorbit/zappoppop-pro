<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Popup overlay settings: sanitize (save) + render bits (front).
 * Shape: settings.overlay = { opacity:0-100, color:#hex, transparent:bool, selected:id,
 *        backgrounds:[ {id,name,type:image|animation,url,html} ] }
 */
class ZapPopPop_Overlay {

    const DEFAULT_CSS = 'align-items:center;justify-content:center;background:rgba(15,23,42,0.65);-webkit-backdrop-filter:blur(3px);backdrop-filter:blur(3px);';
    const MAX_ITEMS   = 20;
    const MAX_HTML    = 400000;

    private static function truthy($v) {
        return !empty($v) && $v !== 'false' && $v !== '0';
    }

    /** Overlay master switch. Missing = on; legacy 'transparent' = off. */
    private static function is_enabled($ov) {
        if (isset($ov['enabled']) && $ov['enabled'] !== '') {
            return self::truthy($ov['enabled']);
        }
        return !self::truthy(isset($ov['transparent']) ? $ov['transparent'] : false);
    }

    private static function hex($c, $def) {
        $c = is_string($c) ? sanitize_hex_color($c) : '';
        return $c ? $c : $def;
    }

    private static function rgba($hex, $alpha) {
        $h = ltrim($hex, '#');
        if (strlen($h) === 3) {
            $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        }
        return sprintf('rgba(%d,%d,%d,%s)', hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2)), number_format((float) $alpha, 2, '.', ''));
    }

    private static function image_url($u) {
        $u = esc_url_raw(trim((string) $u), array('http', 'https'));
        if ($u === '') {
            return '';
        }
        $path = (string) wp_parse_url($u, PHP_URL_PATH);
        return preg_match('/\.(jpe?g|png)$/i', $path) ? $u : '';
    }

    private static function cut($s, $n) {
        return function_exists('mb_substr') ? mb_substr($s, 0, $n) : substr($s, 0, $n);
    }

    /** Backend AJAX whitelist. Returns a clean array. */
    public static function sanitize($raw) {
        $ov = json_decode(wp_json_encode($raw), true);
        if (!is_array($ov)) {
            $ov = array();
        }
        $out = array(
            'opacity'     => (isset($ov['opacity']) && $ov['opacity'] !== '') ? max(0, min(100, intval($ov['opacity']))) : 65,
            'color'       => self::hex(isset($ov['color']) ? $ov['color'] : '', '#0f172a'),
            'enabled'     => self::is_enabled($ov), /* ZAPPOPPOP_OVERLAY_ENABLE: strict boolean, default true */
            'selected'    => '',
            'backgrounds' => array(),
        );

        $list       = (isset($ov['backgrounds']) && is_array($ov['backgrounds'])) ? array_values($ov['backgrounds']) : array();
        $unfiltered = current_user_can('unfiltered_html');
        $seen       = array();

        foreach (array_slice($list, 0, self::MAX_ITEMS) as $bg) {
            if (!is_array($bg)) {
                continue;
            }
            $type = (isset($bg['type']) && $bg['type'] === 'animation') ? 'animation' : 'image';
            $id   = preg_replace('/[^A-Za-z0-9_\-]/', '', isset($bg['id']) ? (string) $bg['id'] : '');
            if ($id === '' || isset($seen[$id])) {
                $id = 'bg_' . strtolower(wp_generate_password(8, false, false));
            }
            $name = self::cut(sanitize_text_field(isset($bg['name']) ? (string) $bg['name'] : ''), 60);
            $url  = '';
            $html = '';

            if ($type === 'image') {
                $url = self::image_url(isset($bg['url']) ? $bg['url'] : '');
                if ($url === '') {
                    continue;
                }
            } else {
                $html = self::cut(isset($bg['html']) && is_string($bg['html']) ? $bg['html'] : '', self::MAX_HTML);
                if (!$unfiltered) {
                    $html = wp_kses_post($html); /* strips <script> for users without unfiltered_html */
                }
                if (trim($html) === '') {
                    continue;
                }
            }
            $seen[$id]            = true;
            $out['backgrounds'][] = array(
                'id'   => $id,
                'name' => $name !== '' ? $name : ($type === 'animation' ? 'Animation' : 'Picture'),
                'type' => $type,
                'url'  => $url,
                'html' => $html,
                'opacity' => (isset($bg['opacity']) && $bg['opacity'] !== '') ? max(0, min(100, intval($bg['opacity']))) : 100, /* ZAPPOPPOP_OVERLAY_BGOP */
            );
        }

        $sel = preg_replace('/[^A-Za-z0-9_\-]/', '', isset($ov['selected']) ? (string) $ov['selected'] : '');
        if ($sel !== '' && isset($seen[$sel])) {
            $out['selected'] = $sel;
        }
        return $out;
    }

        /** ZAPPOPPOP_OVERLAY_ENQUEUE: lazy loader is an enqueued file, registered early (wp_enqueue_scripts) because popup markup is rendered in wp_footer, after which printed script tags are removed by kses_render(). */
    public static function enqueue_loader() {
        wp_enqueue_script('zappoppop-overlay-lazy', ZAPPOPPOP_PRO_URL . 'assets/js/front/overlay-lazy.js', array(), ZAPPOPPOP_PRO_VERSION, true);
    }

    /**
     * Frontend renderer bits.
     * @return array{css:string,layers:string}
     */
    public static function frontend($settings) {
        $res = array('css' => self::DEFAULT_CSS, 'layers' => '');
        if (!is_array($settings) || !isset($settings['overlay']) || !is_array($settings['overlay'])) {
            return $res;
        }
        $ov    = $settings['overlay'];
        $op    = (isset($ov['opacity']) && $ov['opacity'] !== '') ? max(0, min(100, intval($ov['opacity']))) : 65;
        $color = self::hex(isset($ov['color']) ? $ov['color'] : '', '#0f172a');
        if (!self::is_enabled($ov)) {
            /* ZAPPOPPOP_OVERLAY_ENABLE: overlay fully off. No tint, blur or custom background; the invisible backdrop stays so Click Background to Close still works */
            $res['css']    = 'align-items:center;justify-content:center;background:transparent;';
            $res['layers'] = '<span class="zappoppop-ov-off" hidden></span>' . ZapPopPop_Libraries::inline_style_tag('overlay-off', '.zappoppop-ov-off ~ .zappoppop-backdrop{background:transparent !important;-webkit-backdrop-filter:none !important;backdrop-filter:none !important;}');
            return $res;
        }
        $alpha = $op / 100;
        $tint  = self::rgba($color, $alpha);

        $active = null;
        $sel    = isset($ov['selected']) ? (string) $ov['selected'] : '';
        if ($sel !== '' && !empty($ov['backgrounds']) && is_array($ov['backgrounds'])) {
            foreach ($ov['backgrounds'] as $bg) {
                if (is_array($bg) && isset($bg['id']) && (string) $bg['id'] === $sel) {
                    $active = $bg;
                    break;
                }
            }
        }

        $blur = $alpha > 0 ? '-webkit-backdrop-filter:blur(3px);backdrop-filter:blur(3px);' : '';
        $layers = '';

        if ($active) {
            $type = (isset($active['type']) && $active['type'] === 'animation') ? 'animation' : 'image';
            if ($type === 'image') {
                $url = self::image_url(isset($active['url']) ? $active['url'] : '');
                if ($url !== '') {
                    $layers .= '<div class="zappoppop-ov-bg" aria-hidden="true" style="' . esc_attr('position:absolute;top:0;right:0;bottom:0;left:0;pointer-events:none;background:url(\'' . esc_url($url) . '\') center/cover no-repeat;') . '"></div>';
                }
            } else {
                $html = isset($active['html']) && is_string($active['html']) ? $active['html'] : '';
                if (trim($html) !== '') {
                    $doc = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>html,body{margin:0;width:100%;height:100%;overflow:hidden;background:transparent}</style></head><body>' . $html . '</body></html>';
                    $layers .= '<iframe class="zappoppop-ov-bg" aria-hidden="true" tabindex="-1" sandbox="allow-scripts" data-srcdoc="' . esc_attr($doc) . '" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;pointer-events:none;background:transparent;"></iframe>';
                }
            }
            if ($layers !== '') {
                /* ZAPPOPPOP_OVERLAY_BGOP: opacity applies to the picture / animation layer, under the tint */
                $zappoppop_bgop = (isset($active['opacity']) && $active['opacity'] !== '') ? max(0, min(100, intval($active['opacity']))) / 100 : 1;
                if ($zappoppop_bgop < 1) {
                    $layers = preg_replace('/(class="zappoppop-ov-bg"[^>]*?\sstyle=")/', '${1}opacity:' . number_format($zappoppop_bgop, 2, '.', '') . ';', $layers, 1);
                }
                /* ZAPPOPPOP_OVERLAY_LAZY: transparent backdrop over custom layers; iframe loads only while visible */
                $layers .= ZapPopPop_Libraries::inline_style_tag('overlay-bg', '.zappoppop-ov-bg ~ .zappoppop-backdrop{background:transparent !important;-webkit-backdrop-filter:none !important;backdrop-filter:none !important;}.zappoppop-ov-bg{z-index:0;}');
                if (strpos($layers, 'data-srcdoc') !== false) {
                }
                $layers .= '<div class="zappoppop-ov-tint" aria-hidden="true" style="' . esc_attr('position:absolute;top:0;right:0;bottom:0;left:0;pointer-events:none;background:' . $tint . ';') . '"></div>';
                $res['css']    = 'align-items:center;justify-content:center;background:transparent;';
                $res['layers'] = $layers;
                return $res;
            }
        }

        $res['css'] = 'align-items:center;justify-content:center;background:' . $tint . ';' . $blur;
        return $res;
    }
}
