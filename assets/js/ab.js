(function(window, $) {
    'use strict';

    $(document).ready(function() {
        if (!window.ZapPopPopAB) return;

        if (window.ZapPopPopAB.Modal) {
            window.ZapPopPopAB.Modal.init();
        }

        if (window.ZapPopPopAB.Actions) {
            window.ZapPopPopAB.Actions.init();
        }
    });
})(window, jQuery);
