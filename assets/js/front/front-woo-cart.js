/**
 * ZapPopPop Pro Front: WooCommerce Cart runtime.   ZPP_WC_CART
 * - Cart-state bridge: reads the visitor's live cart from a no-cache WooCommerce endpoint, so full-page caches stay safe.
 * - Display rules: reads data-wc-cart (ZapPopPop_Woo_Cart::frontend) and gates ZapPopPopFrontDisplay.show().
 *   show() answers false (blocked) or 'wait' (cart still loading) so a blocked view is never counted as seen.
 * - Dynamic text: {cart_total} {cart_subtotal} {cart_count} {shipping_remaining} {free_shipping_min} {coupon_code}.
 * - Fails open: if the cart cannot be read, popups show as they did before.
 * Skips gating for manual (button / shortcode) and inline popups. Live Preview uses sample values.
 * Cross-browser: ES5 only (Chrome, Safari, Firefox, Edge).
 */
(function (window, document, $) {
    'use strict';
    if (window.__zppWCBound) { return; }
    window.__zppWCBound = true;

    var D = window.ZapPopPopFrontDisplay;
    if (!D || !$) { return; }
    var vars = window.zappoppop_front_vars || {};
    var W = vars.wc || {};
    var SEL = '.zappoppop-popup-wrap[data-wc-cart]';
    var TOKEN_TEST = /\{(?:cart_total|cart_subtotal|cart_count|shipping_remaining|free_shipping_min|coupon_code|coupon_countdown|coupon_expires|upsell_name|upsell_price)\}/;
    var TOKEN_RE = /\{(cart_total|cart_subtotal|cart_count|shipping_remaining|free_shipping_min|coupon_code|coupon_countdown|coupon_expires|upsell_name|upsell_price)\}/g;
    var SHIP_TEST = /\{(?:shipping_remaining|free_shipping_min)\}/;
    var SAMPLE = { count: 2, subtotal: 49, total: 49, free_min: 75, products: [], cats: [], coupons: ['SAVE10'], upAny: { name: 'Sample product', price: 19, url: '', buy: true } }; /* ZPP_WC_P3 */
    var ZERO = { count: 0, subtotal: 0, total: 0, free_min: 0, products: [], cats: [], coupons: [] };

    var cart = null;            /* null = not read yet, { failed: true } = could not be read */
    var loading = false, queued = false, timer = null, lastLoad = 0, pendingAdd = false;
    var pending = {};           /* uid -> 1: show() answered 'wait' */
    var deferred = {};          /* uid -> 1: blocked, replay when the cart changes (only with "show after add to cart") */
    var fired = {};             /* uid -> 1: already shown by this module on this page view */
    var origShow = D.show;

    function isPreview() {
        return (document.body && document.body.className.indexOf('zappoppop-preview-frame') !== -1) || /[?&]zappoppop_preview=/.test(window.location.search);
    }
    function getCookie(name) {
        var parts = document.cookie ? document.cookie.split('; ') : [], i, kv;
        for (i = 0; i < parts.length; i++) {
            kv = parts[i].split('=');
            if (kv.shift() === name) { try { return decodeURIComponent(kv.join('=')); } catch (e) { return ''; } }
        }
        return '';
    }
    function wrap(uid) { return D.getPopupWrap(uid); }

    function parse($w) {
        var raw = $w.attr('data-wc-cart'), r;
        if (!raw) { return null; }
        try { r = JSON.parse(raw); } catch (e) { return null; }
        if (!r || typeof r !== 'object') { return null; }
        r.c = r.c || 'any';
        r.mi = +r.mi || 0;
        r.mn = +r.mn || 0;
        r.mx = +r.mx || 0;
        r.f = +r.f || 0;
        r.a = r.a ? 1 : 0;
        r.x = +r.x || 0;
        r.cc = (typeof r.cc === 'string') ? r.cc : '';
        r.cm = (r.cm === 'open' || r.cm === 'click') ? r.cm : '';
        r.p = $.isArray(r.p) ? r.p : [];
        r.k = $.isArray(r.k) ? r.k : [];
        r.u = r.u ? 1 : 0;
        r.uid = $w.attr('data-uid') || '';
        return r;
    }

    function anyIn(wanted, have) {
        var i;
        for (i = 0; i < wanted.length; i++) {
            if ($.inArray(+wanted[i], have) !== -1) { return true; }
        }
        return false;
    }
    function pass(r, c) {
        if (r.u && !upOf(c, r)) { return false; }
        if (r.c === 'empty') { return c.count === 0; }
        if (r.c === 'has_items' && c.count < 1) { return false; }
        if (r.mi > 0 && c.count < r.mi) { return false; }
        if (r.mn > 0 && c.subtotal < r.mn) { return false; }
        if (r.mx > 0 && c.subtotal > r.mx) { return false; }
        if (r.p.length && !anyIn(r.p, c.products)) { return false; }
        if (r.k.length && !anyIn(r.k, c.cats)) { return false; }
        return true;
    }

    /* ------------------------------------------------------------ tokens */
    function money(n) {
        var f = W.fmt || {}, d = isNaN(+f.dec) ? 2 : +f.dec, parts = Math.abs(+n || 0).toFixed(d).split('.'),
            tsep = (f.tsep === undefined || f.tsep === null) ? ',' : f.tsep,
            int = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, tsep),
            out = d ? int + (f.dsep || '.') + parts[1] : int,
            sym = f.symbol || '$';
        switch (f.pos) {
            case 'right': return out + sym;
            case 'left_space': return sym + ' ' + out;
            case 'right_space': return out + ' ' + sym;
            default: return sym + out;
        }
    }
    function shipMin(c, r) { return (r && r.f > 0) ? r.f : (+c.free_min || 0); }
    function tokenValue(key, c, r) {
        var min, u;
        switch (key) {
            case 'cart_count': return String(c.count);
            case 'cart_subtotal': return money(c.subtotal);
            case 'cart_total': return money(c.total);
            case 'coupon_code': return (r && r.cc) ? r.cc : (c.coupons && c.coupons.length ? String(c.coupons[0]) : '');
            case 'coupon_countdown': return countdown(r);
            case 'coupon_expires': return (r && r.x) ? dateText(r.x) : '';
            case 'upsell_name':
                u = upOf(c, r);
                return u ? String(u.name) : '';
            case 'upsell_price':
                u = upOf(c, r);
                return u ? money(u.price) : '';
            case 'free_shipping_min':
                min = shipMin(c, r);
                return min > 0 ? money(min) : '';
            case 'shipping_remaining':
                min = shipMin(c, r);
                return min > 0 ? money(Math.max(0, min - c.subtotal)) : '';
        }
        return '';
    }
    function walk(node, fn) {
        var n = node.firstChild;
        while (n) {
            if (n.nodeType === 3) { fn(n); }
            else if (n.nodeType === 1 && !/^(SCRIPT|STYLE|TEXTAREA)$/.test(n.nodeName)) { walk(n, fn); }
            n = n.nextSibling;
        }
    }
    /* The original text is kept on the node, so the tokens can be filled again whenever the cart changes. */
    function render($w, c, r) {
        if (!$w.length || !c || c.failed) { return; }
        walk($w[0], function (t) {
            var src = (t.__zppWcO !== undefined) ? t.__zppWcO : t.nodeValue;
            if (t.__zppWcO === undefined && !TOKEN_TEST.test(src)) { return; }
            t.__zppWcO = src;
            t.nodeValue = src.replace(TOKEN_RE, function (m, k) { return tokenValue(k, c, r); });
        });
        shipBars($w, c, r);
    }

    /* ------------------------------------------------------------ cart state */
    /* ZPP_WC_P2: free-shipping bars, coupon countdown, coupon apply ------------------------------------ */
    var skew = 0, applying = {}, autoApplied = {}, tickTimer = null;

    function two(n) { return (n < 10 ? '0' : '') + n; }
    /* Time left until the coupon's real WooCommerce expiry. Uses the server clock once the cart has been read. */
    function countdown(r) {
        var s, d, h, m;
        if (!r || !r.x) { return ''; }
        s = Math.max(0, Math.floor(r.x - (new Date().getTime() + skew) / 1000));
        d = Math.floor(s / 86400);
        h = Math.floor((s % 86400) / 3600);
        m = Math.floor((s % 3600) / 60);
        s = s % 60;
        return (d > 0 ? d + 'd ' : '') + two(h) + ':' + two(m) + ':' + two(s);
    }
    function dateText(ts) {
        try { return new Date(ts * 1000).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }); }
        catch (e) { return new Date(ts * 1000).toDateString(); }
    }
    /* Progress Bar layers in "Auto: WooCommerce free shipping" mode. Label: "text before || text after the goal is reached". */
    function shipBars($w, c, r) {
        var min = shipMin(c, r);
        if (!min || min <= 0) { return; }
        $w.find('[data-progress-mode="shipping"]').each(function () {
            var bar = this, pct = Math.max(0, Math.min(100, Math.floor((c.subtotal / min) * 100))),
                fill = bar.querySelector('.zappoppop-progress-fill'), badge = bar.querySelector('.zappoppop-progress-badge'),
                parts = (bar.getAttribute('data-progress-label') || '').split('||'),
                show = bar.getAttribute('data-progress-showpct') !== '0', lbl;
            lbl = (pct >= 100 && parts.length > 1 ? parts[1] : parts[0]).replace(/^\s+|\s+$/g, '');
            lbl = lbl.replace(TOKEN_RE, function (m, k) { return tokenValue(k, c, r); });
            if (fill) { fill.style.width = pct + '%'; }
            if (badge) { badge.textContent = lbl ? (lbl + (show ? ' (' + pct + '%)' : '')) : (show ? pct + '%' : ''); }
            bar.setAttribute('role', 'progressbar');
            bar.setAttribute('aria-valuenow', pct);
        });
    }
    function hasCoupon(c, code) {
        var i, want = String(code).toLowerCase();
        for (i = 0; c.coupons && i < c.coupons.length; i++) {
            if (String(c.coupons[i]).toLowerCase() === want) { return true; }
        }
        return false;
    }
    /* The browser sends only the popup uid; the server applies the code saved with that popup. */
    function applyCoupon(uid, r) {
        if (!r || !r.cc || !W.applyUrl || applying[uid]) { return; }
        applying[uid] = 1;
        $.ajax({ url: W.applyUrl, type: 'POST', dataType: 'json', cache: false, timeout: 8000, data: { uid: uid } })
            .done(function (res) {
                var ok = !!(res && res.success);
                $(document).trigger('zappoppop:wc_coupon', [uid, ok, res && res.data]);
                if (ok) {
                    $(document.body).trigger('wc_fragment_refresh');
                    lastLoad = 0;
                    load();
                }
            })
            .always(function () { delete applying[uid]; });
    }
    function maybeApply(uid, r, c) {
        if (!c || c.failed || !r || r.cm !== 'open' || !r.cc || autoApplied[uid] || hasCoupon(c, r.cc)) { return; }
        autoApplied[uid] = 1;
        applyCoupon(uid, r);
    }
    /* A button whose link is #zpp-apply-coupon applies the popup's coupon instead of navigating. */
    function onCouponClick(e) {
        var t = e.target, el = (t && t.closest) ? t.closest('[data-zpp-href="#zpp-apply-coupon"]') : null, w, r;
        if (!el) { return; }
        w = el.closest ? el.closest('.zappoppop-popup-wrap') : null;
        if (!w) { return; }
        e.preventDefault();
        e.stopPropagation();
        if (isPreview() || !W.active) { return; }
        r = parse($(w));
        if (r && r.cm === 'click') { applyCoupon(w.getAttribute('data-uid'), r); }
    }
    document.addEventListener('click', onCouponClick, true);

    /* ZPP_WC_P3: upsell product ------------------------------------------------------------------------ */
    var adding = {};
    /* The upsell product the server resolved for this popup (Live Preview: a sample). */
    function upOf(c, r) {
        if (!c || !r) { return null; }
        if (c.upAny) { return c.upAny; }
        return (r.uid && c.upsell && c.upsell[r.uid]) ? c.upsell[r.uid] : null;
    }
    function upsellUids() {
        var out = [];
        $(SEL).each(function () {
            var r = parse($(this));
            if (r && r.u && r.uid && out.length < 10) { out.push(r.uid); }
        });
        return out;
    }
    /* The browser sends only the popup uid; the server resolves and adds the product saved for that popup. */
    function addUpsell(uid) {
        if (!W.addUrl || adding[uid]) { return; }
        adding[uid] = 1;
        $.ajax({ url: W.addUrl, type: 'POST', dataType: 'json', cache: false, timeout: 8000, data: { uid: uid } })
            .done(function (res) {
                var ok = !!(res && res.success);
                $(document).trigger('zappoppop:wc_upsell', [uid, ok]);
                if (ok) {
                    $(document.body).trigger('wc_fragment_refresh');
                    lastLoad = 0;
                    load();
                    if (D.close) { D.close(uid); }
                }
            })
            .always(function () { delete adding[uid]; });
    }
    /* #zpp-add-upsell adds the product to the cart, #zpp-view-upsell opens its page. */
    function onUpsellClick(e) {
        var t = e.target, el = (t && t.closest) ? t.closest('[data-zpp-href="#zpp-add-upsell"],[data-zpp-href="#zpp-view-upsell"]') : null, w, r, u;
        if (!el) { return; }
        w = el.closest ? el.closest('.zappoppop-popup-wrap') : null;
        if (!w) { return; }
        e.preventDefault();
        e.stopPropagation();
        if (isPreview() || !W.active) { return; }
        r = parse($(w));
        if (!r || !r.u) { return; }
        if (el.getAttribute('data-zpp-href') === '#zpp-add-upsell') {
            addUpsell(w.getAttribute('data-uid'));
        } else {
            u = upOf(cart, r);
            if (u && /^https?:\/\//i.test(String(u.url))) { window.location.href = u.url; }
        }
    }
    document.addEventListener('click', onUpsellClick, true);
    /* Keeps {coupon_countdown} ticking while a popup is open. */
    function startTick() {
        var any = false;
        if (tickTimer) { return; }
        $(SEL).each(function () { var r = parse($(this)); if (r && r.x) { any = true; } });
        if (!any) { return; }
        tickTimer = window.setInterval(function () {
            var c = isPreview() ? SAMPLE : (W.active ? cart : ZERO);
            if (!c || c.failed) { return; }
            $(SEL).filter(':visible').each(function () {
                var $x = $(this), r = parse($x);
                if (r && r.x) { render($x, c, r); }
            });
        }, 1000);
    }

    function fail() {
        cart = { failed: true };
        settle();
    }
    function setCart(d) {
        var i, list = function (a) {
            var out = [];
            if ($.isArray(a)) { for (i = 0; i < a.length; i++) { out.push(+a[i]); } }
            return out;
        };
        if (+d.now) { skew = (+d.now * 1000) - new Date().getTime(); }
        cart = {
            count: parseInt(d.count, 10) || 0,
            subtotal: +d.subtotal || 0,
            total: +d.total || 0,
            free_min: +d.free_min || 0,
            products: list(d.products),
            cats: list(d.cats),
            coupons: $.isArray(d.coupons) ? d.coupons : [],
            upsell: (d.upsell && typeof d.upsell === 'object') ? d.upsell : {}
        };
        settle();
    }
    function load() {
        if (loading) { queued = true; return; }
        if (!W.endpoint) { fail(); return; }
        loading = true;
        $.ajax({ url: W.endpoint, type: 'POST', dataType: 'json', cache: false, timeout: 8000, data: { t: new Date().getTime(), uids: upsellUids() } })
            .done(function (res) {
                if (res && res.success && res.data) { setCart(res.data); } else { fail(); }
            })
            .fail(fail)
            .always(function () {
                loading = false;
                lastLoad = new Date().getTime();
                if (queued) { queued = false; load(); }
            });
    }
    function schedule(isAdd) {
        if (isAdd) { pendingAdd = true; }
        else if (new Date().getTime() - lastLoad < 1500) { return; }
        window.clearTimeout(timer);
        timer = window.setTimeout(load, 200);
    }

    /* Releases everything that was waiting for, or reacting to, a cart read. */
    function settle() {
        var c = cart, uid, $w, r, ok;
        for (uid in pending) {
            if (pending.hasOwnProperty(uid)) {
                delete pending[uid];
                $w = wrap(uid);
                r = parse($w);
                ok = !r || c.failed || pass(r, c);
                if (ok) {
                    if (r) { render($w, c, r); maybeApply(uid, r, c); }
                    origShow.call(D, uid);
                } else if (r && r.a) {
                    deferred[uid] = 1;
                }
                $(document).trigger('zappoppop:wc_released', [uid, ok]);
            }
        }
        if (c.failed) { return; }
        if (pendingAdd) {
            pendingAdd = false;
            $(SEL).each(function () {
                var $x = $(this), u = $x.attr('data-uid'), rr = parse($x);
                if (!u || !rr || !rr.a || fired[u] || $x.is(':visible') || !pass(rr, c)) { return; }
                fired[u] = 1;
                delete deferred[u];
                D.show(u);
            });
        }
        for (uid in deferred) {
            if (deferred.hasOwnProperty(uid)) {
                $w = wrap(uid);
                r = parse($w);
                if (r && !fired[uid] && !$w.is(':visible') && pass(r, c)) {
                    delete deferred[uid];
                    fired[uid] = 1;
                    D.show(uid);
                }
            }
        }
        $(SEL).filter(':visible').each(function () { render($(this), c, parse($(this))); });
    }

    /* ------------------------------------------------------------ display gate */
    D.show = function (uid) {
        var $w = wrap(uid), r = $w.length ? parse($w) : null, gated;
        if (!r) { return origShow.apply(this, arguments); }
        if (isPreview()) { render($w, SAMPLE, r); return origShow.apply(this, arguments); }
        if (!W.active) { render($w, ZERO, r); return origShow.apply(this, arguments); }
        gated = $w.attr('data-manual') !== '1' && $w.attr('data-inline') !== '1';
        if (cart === null) {
            if (!gated) { return origShow.apply(this, arguments); }
            pending[uid] = 1;
            if (!loading) { load(); }
            return 'wait';
        }
        if (cart.failed) { return origShow.apply(this, arguments); }
        if (gated && !pass(r, cart)) {
            if (r.a) { deferred[uid] = 1; }
            return false;
        }
        render($w, cart, r);
        maybeApply(uid, r, cart);
        return origShow.apply(this, arguments);
    };

    /* ------------------------------------------------------------ start */
    function bindEvents() {
        $(document.body).on('added_to_cart', function () { schedule(true); });
        $(document.body).on('removed_from_cart updated_cart_totals wc_fragments_refreshed wc_cart_emptied updated_checkout', function () { schedule(false); });
        document.addEventListener('wc-blocks_added_to_cart', function () { schedule(true); }, false);
        document.addEventListener('wc-blocks_removed_from_cart', function () { schedule(false); }, false);
    }
    function needsServerForShipping() {
        var need = false;
        $(SEL).each(function () {
            var r = parse($(this));
            if (r && (r.u || (!r.f && (SHIP_TEST.test($(this).text()) || $(this).find('[data-progress-mode="shipping"]').length)))) { need = true; }
        });
        return need;
    }

    window.ZapPopPopWooCart = {
        state: function () { return cart; },
        refresh: function () { schedule(false); }
    };

    $(function () {
        if (!$(SEL).length) { return; }
        bindEvents();
        startTick();
        if (isPreview() || !W.active) { return; }
        /* WooCommerce sets these cookies only while the cart holds items: without them the cart is empty and no request is needed. */
        if (!getCookie('woocommerce_items_in_cart') && !getCookie('woocommerce_cart_hash') && !needsServerForShipping()) {
            cart = $.extend({}, ZERO);
            settle();
            return;
        }
        load();
    });
})(window, document, window.jQuery);
