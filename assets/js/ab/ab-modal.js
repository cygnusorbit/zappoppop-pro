(function(window, $) {
    'use strict';
    window.ZapPopPopAB = window.ZapPopPopAB || {};

    var Modal = {
        init: function() {
            this.bindOpen();
            this.bindClose();
        },

        bindOpen: function() {
            $('#zappoppop-btn-open-create-ab').on('click', function() {
                $('#zappoppop-ab-title-input').val('');
                $('.zappoppop-ab-popup-checkbox').prop('checked', false);
                $('#zappoppop-create-ab-modal').css('display', 'flex');
            });
        },

        bindClose: function() {
            $('#zappoppop-create-ab-close, #zappoppop-create-ab-cancel').on('click', function() {
                $('#zappoppop-create-ab-modal').hide();
            });

            $('#zappoppop-create-ab-modal').on('click', function(e) {
                if (e.target === this) {
                    $(this).hide();
                }
            });
        }
    };

    window.ZapPopPopAB.Modal = Modal;
})(window, jQuery);
