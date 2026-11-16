(function (window, $) {
    'use strict';

    var V = window.zappoppop_stats_vars || {};
    var MAX = parseInt(V.max_series, 10) || 8;

    function post(action, data) {
        data = data || {};
        data.action = action;
        data.nonce = V.nonce;
        return $.post(V.ajax_url, data);
    }

    function toast(msg, bad) {
        var $n = $('<div class="notice is-dismissible ' + (bad ? 'notice-error' : 'notice-success') + '" style="margin:8px 0;"><p></p></div>');
        $n.find('p').text(msg);
        $('.zappoppop-stats-header').first().before($n);
        setTimeout(function () { $n.fadeOut(200, function () { $n.remove(); }); }, 2200);
    }

    /* ---- label colors: add / edit (on change) / delete ---- */
    var timers = {};
    $(document).on('input change', '.zpp-lbl-input', function () {
        var $w = $(this).closest('.zpp-lbl'), uid = $w.data('uid'), color = this.value;
        clearTimeout(timers[uid]);
        timers[uid] = setTimeout(function () {
            post('zappoppop_stats_label_save', { uid: uid, color: color }).done(function (r) {
                if (r && r.success) {
                    $w.removeClass('is-empty').addClass('has-label').find('.zpp-lbl-del').prop('hidden', false);
                    $('.zpp-name-dot[data-uid="' + uid + '"]').css({ background: color, display: 'inline-block' });
                } else { toast((r && r.data && r.data.message) || wp.i18n.__('Error', 'zappoppop'), true); }
            }).fail(function (xhr) { toast(xhr && xhr.status ? wp.i18n.sprintf(
                /* translators: %s: HTTP status code. */
                wp.i18n.__('Request failed (HTTP %s)', 'zappoppop'), xhr.status) : wp.i18n.__('Request failed', 'zappoppop'), true); });
        }, 250);
    });

    $(document).on('click', '.zpp-lbl-del', function () {
        var $w = $(this).closest('.zpp-lbl'), uid = $w.data('uid');
        post('zappoppop_stats_label_delete', { uid: uid }).done(function (r) {
            if (r && r.success) {
                $w.removeClass('has-label').addClass('is-empty').find('.zpp-lbl-input').val('#94a3b8');
                $w.find('.zpp-lbl-del').prop('hidden', true);
                $('.zpp-name-dot[data-uid="' + uid + '"]').css('display', 'none');
            } else { toast((r && r.data && r.data.message) || wp.i18n.__('Error', 'zappoppop'), true); }
        });
    });

    /* ---- column visibility (live + saved) ---- */
    $(document).on('change', '.zpp-col-cb', function () {
        var cols = [];
        $('.zpp-col-cb').each(function () {
            var on = this.checked || this.value === 'popup';
            $('.zpp-col-' + this.value).toggleClass('zpp-col-off', !on);
            if (on) { cols.push(this.value); }
        });
        post('zappoppop_stats_prefs_save', { columns: cols });
    });

    /* ---- column order: drag a header cell (or a row in the Columns panel) ---- */
    var DRAG_SEL = '#zpp-stats-table thead th[data-col], #zpp-cols-pick .zpp-pick-row[data-col]';
    var dragKey = null;

    function currentOrder() {
        var o = [];
        $('#zpp-stats-table thead th[data-col]').each(function () { o.push($(this).attr('data-col')); });
        return o;
    }
    function applyOrder(order) {
        var i, $panel = $('#zpp-cols-pick .zpp-pick-panel');
        $('#zpp-stats-table tr').each(function () {
            var $tr = $(this);
            for (i = 0; i < order.length; i++) {
                var $c = $tr.children('.zpp-col-' + order[i]);
                if ($c.length) { $tr.append($c); }
            }
        });
        for (i = 0; i < order.length; i++) {
            var $r = $panel.children('.zpp-pick-row[data-col="' + order[i] + '"]');
            if ($r.length) { $panel.append($r); }
        }
    }
    function clearMarks() {
        $('.zpp-drop-before, .zpp-drop-after, .zpp-dragging').removeClass('zpp-drop-before zpp-drop-after zpp-dragging');
    }
    function dropBefore(el, e) {
        var r = el.getBoundingClientRect(), oe = e.originalEvent || e;
        return $(el).is('th') ? (oe.clientX < r.left + r.width / 2) : (oe.clientY < r.top + r.height / 2);
    }

    $(document).on('dragstart', DRAG_SEL, function (e) {
        var dt = e.originalEvent && e.originalEvent.dataTransfer;
        dragKey = $(this).attr('data-col');
        $(this).addClass('zpp-dragging');
        if (dt) {
            dt.effectAllowed = 'move';
            try { dt.setData('text/plain', dragKey); } catch (err) { /* old Edge */ }
        }
    });
    $(document).on('dragover', DRAG_SEL, function (e) {
        if (!dragKey) { return; }
        var dt = e.originalEvent && e.originalEvent.dataTransfer;
        e.preventDefault();
        if (dt) { dt.dropEffect = 'move'; }
        $('.zpp-drop-before, .zpp-drop-after').removeClass('zpp-drop-before zpp-drop-after');
        if ($(this).attr('data-col') !== dragKey) { $(this).addClass(dropBefore(this, e) ? 'zpp-drop-before' : 'zpp-drop-after'); }
    });
    $(document).on('drop', DRAG_SEL, function (e) {
        if (!dragKey) { return; }
        e.preventDefault();
        var target = $(this).attr('data-col'), before = dropBefore(this, e), moved = dragKey;
        var order = $.grep(currentOrder(), function (k) { return k !== moved; });
        var idx = $.inArray(target, order);
        clearMarks();
        dragKey = null;
        if (idx < 0) { return; }
        order.splice(before ? idx : idx + 1, 0, moved);
        applyOrder(order);
        post('zappoppop_stats_prefs_save', { order: order });
    });
    $(document).on('dragend', DRAG_SEL, function () { clearMarks(); dragKey = null; });

    /* ---- per page ---- */
    $(document).on('change', '.zpp-per', function () { this.form.submit(); });

    /* ---- chart popup selection ---- */
    $(document).on('change', '.zpp-chart-cb', function () {
        var n = $('.zpp-chart-cb:checked').length;
        if (n > MAX) { this.checked = false; $('.zpp-pick-msg').text(V.max_msg || ('Max ' + MAX)); }
    });
    $(document).on('click', '#zpp-chart-apply', function () {
        var uids = [];
        $('.zpp-chart-cb:checked').each(function () { uids.push(this.value); });
        var $b = $(this).prop('disabled', true);
        post('zappoppop_stats_prefs_save', { chart_set: 1, chart_uids: uids }).always(function () {
            $b.prop('disabled', false);
            window.location.reload();
        });
    });

    /* ---- close dropdown panels on outside click / Esc ---- */
    $(document).on('click', function (e) {
        $('details.zpp-pick[open]').each(function () {
            if (!$.contains(this, e.target)) { this.removeAttribute('open'); }
        });
    });
    $(document).on('keydown', function (e) {
        if (e.which === 27) { $('details.zpp-pick[open]').removeAttr('open'); }
    });
})(window, jQuery);
