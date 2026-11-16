/**
 * ZapPopPop A/B: Trash / Restore actions (soft delete)
 * Cross-browser: Chrome, Safari, Firefox, Edge (ES5 only)
 * ZAPPOPPOP_AB_FILTERS
 */
(function (window, $) {
    'use strict';

    var Trash = {
        vars: function () {
            return window.zappoppop_vars || { ajax_url: window.ajaxurl || '', nonce: '' };
        },

        init: function () {
            var self = this;
            $(document).off('.zppAbTrash');
            $(document).on('click.zppAbTrash', '.zappoppop-trash-ab-btn', function (e) {
                e.preventDefault();
                self.run($(this), 'zappoppop_trash_campaign');
            });
            $(document).on('click.zppAbTrash', '.zappoppop-restore-ab-btn', function (e) {
                e.preventDefault();
                self.run($(this), 'zappoppop_restore_campaign');
            });
        },

        run: function ($btn, action) {
            var v = this.vars();
            $btn.prop('disabled', true);
            $.ajax({
                url: v.ajax_url, type: 'POST', dataType: 'json',
                data: { action: action, nonce: v.nonce, uid: $btn.attr('data-uid') }
            }).done(function (res) {
                if (res && res.success) {
                    window.location.reload();
                } else {
                    $btn.prop('disabled', false);
                    window.alert((res && res.data && res.data.message) || wp.i18n.__('Could not update campaign.', 'zappoppop'));
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                window.alert(wp.i18n.__('Server error while updating campaign.', 'zappoppop'));
            });
        }
    };

    window.ZapPopPopAB = window.ZapPopPopAB || {};
    window.ZapPopPopAB.Trash = Trash;
    $(function () { Trash.init(); });
})(window, jQuery);
