/**
 * ZapPopPop Pro: Google Analytics events and AdBlock signal for the core popup runtime.
 * Core announces popup events on the document; this file only listens.
 */
(function ($) {
    'use strict';
    var vars = window.zappoppop_front_vars || {};

    function sendGA(eventName, params) {
        try {
            if (typeof window.gtag === 'function') {
                window.gtag('event', eventName, params);
            }
            if (Array.isArray(window.dataLayer)) {
                window.dataLayer.push($.extend({ event: eventName }, params));
            }
        } catch (err) {}
    }

    if (vars.ga_tracking) {
        $(document).on('zappoppop:popup_shown', function (e, uid) {
            sendGA('zappoppop_impression', { popup_uid: uid });
        });
        $(document).on('zappoppop:popup_closed', function (e, uid) {
            sendGA('zappoppop_close', { popup_uid: uid });
        });
    }

    if (vars.adblock_detector) {
        $(function () {
            if (window.zappoppop_adblock_detected) {
                $(document).trigger('zappoppop:adblock_active');
            }
        });
    }
})(jQuery);
