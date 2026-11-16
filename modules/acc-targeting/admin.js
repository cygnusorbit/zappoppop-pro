/* Targeting: Inspector DOM <-> JS model.
 * Wraps getSettings / setSettings so the Visitor Authentication select is saved to and restored from
 * settings.targeting ({ auth: 'all' | 'guest' | 'user' }). Sanitising on save and the front-end gate live in the core class (ZapPopPop_Targeting).
 * Only loaded while the module is installed. ES5 only (Chrome, Safari, Firefox, Edge). */
/* ZAPPOPPOP_TARGETING_UI: Targeting module (Inspector DOM <-> settings.targeting) */
(function($) {
    'use strict';
    var S = window.ZapPopPopBuilderSettings;
    if (!S || S._targetingWrapped) return;
    S._targetingWrapped = true;

    function pick(v) { return (v === 'guest' || v === 'user') ? v : 'all'; }

    var origGet = S.getSettings, origSet = S.setSettings;

    S.getSettings = function() {
        var out = origGet.apply(this, arguments) || {};
        out.targeting = { auth: pick($('#set-target-auth').val()) };
        return out;
    };

    S.setSettings = function(settings) {
        var r = origSet.apply(this, arguments);
        var m = (settings && settings.targeting && typeof settings.targeting === 'object') ? settings.targeting : {};
        $('#set-target-auth').val(pick(m.auth));
        return r;
    };
})(jQuery);
