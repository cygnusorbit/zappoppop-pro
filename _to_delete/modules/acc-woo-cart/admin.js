/**
 * ZapPopPop Builder: WooCommerce Cart module (Popup Settings > WooCommerce Cart)   ZPP_WC_CART
 * Data lifecycle: inspector DOM <-> JS model <-> getSettings()/setSettings() <-> save payload (settings.woo_cart).
 * The model (not the DOM) is the source of truth. Save / Clear / Reset as Default need no extra key list here: the
 * whole popup settings object is captured and re-applied through setSettings(), which repaints this UI.
 * Only loaded while the module is installed; the UI is built here from the data the core provides.
 * Cross-browser: ES5 only (Chrome, Safari, Firefox, Edge).
 */
(function ($) {
    'use strict';
    var S = window.ZapPopPopBuilderSettings;
    if (!S || S._wcCartWrapped) { return; }
    S._wcCartWrapped = true;

    var DRAWER = '#zappoppop-settings-drawer';
    var ROOT = '#zappoppop-wcc-item';
    var DATA = window.ZapPopPopWooCartData || {};
    var CARTS = ['any', 'has_items', 'empty'];
    var PAGES = ['any', 'product', 'selected'];  /* ZPP_WC_P3 */
    var UMODES = ['fixed', 'auto'];
    var TEXTF = { rc_consent: 1, rc_subject: 1, rc_message: 1, rc_coupon: 1 };  /* ZPP_WC_P4 */
    var NUM = { min_items: [0, 9999, 0], min_total: [0, 9999999, 2], max_total: [0, 9999999, 2], free_ship_min: [0, 9999999, 2], upsell_product: [0, 999999999, 0], rc_delay: [15, 4320, 0] };
    var model = blank();

    function L(s) { return (DATA.i18n && DATA.i18n[s]) ? DATA.i18n[s] : s; }
    function blank() {
        return { enabled: false, cart: 'any', min_items: 0, min_total: 0, max_total: 0, products: [], cats: [], show_on_add: false, free_ship_min: 0, coupon_on: false, coupon_code: '', coupon_apply: 'open', page: 'any', page_products: [], page_cats: [], upsell_on: false, upsell_mode: 'fixed', upsell_product: 0, rc_on: false, rc_delay: 60, rc_consent: 'consent', rc_subject: '', rc_message: '', rc_coupon: '' }; /* ZPP_WC_P2 ZPP_WC_P3 ZPP_WC_P4 */
    }
    function bool(v) { return v === true || v === 1 || v === '1' || v === 'true' || v === 'on'; }
    function num(v, min, max, dec) {
        var n = parseFloat(String(v).replace(',', '.'));
        if (isNaN(n)) { return min; }
        n = Math.max(min, Math.min(max, n));
        return dec ? Math.round(n * 100) / 100 : Math.round(n);
    }
    function ids(v) {
        var out = [], seen = {}, i, n;
        if (!$.isArray(v)) { return out; }
        for (i = 0; i < v.length && out.length < 200; i++) {
            n = parseInt(v[i], 10);
            if (n > 0 && !seen[n]) { seen[n] = 1; out.push(n); }
        }
        return out;
    }
    function clone(o) { return JSON.parse(JSON.stringify(o)); }
    function txt(v, max, multi) {
        var s = (typeof v === 'string') ? v : '';
        s = s.replace(/<[^>]*>/g, '').replace(multi ? /[\x00-\x08\x0B\x0C\x0E-\x1F\x7F<>]/g : /[\x00-\x1F\x7F<>]/g, '');
        return $.trim(s).substring(0, max);
    }
    function normalize(raw) {
        var m = blank(), t;
        raw = (raw && typeof raw === 'object') ? raw : {};
        m.enabled = bool(raw.enabled);
        m.cart = $.inArray(raw.cart, CARTS) !== -1 ? raw.cart : 'any';
        m.min_items = num(raw.min_items, NUM.min_items[0], NUM.min_items[1], 0);
        m.min_total = num(raw.min_total, NUM.min_total[0], NUM.min_total[1], 2);
        m.max_total = num(raw.max_total, NUM.max_total[0], NUM.max_total[1], 2);
        if (m.min_total > 0 && m.max_total > 0 && m.min_total > m.max_total) { t = m.min_total; m.min_total = m.max_total; m.max_total = t; }
        m.products = ids(raw.products);
        m.cats = ids(raw.cats);
        m.show_on_add = bool(raw.show_on_add);
        m.free_ship_min = num(raw.free_ship_min, NUM.free_ship_min[0], NUM.free_ship_min[1], 2);
        m.coupon_on = bool(raw.coupon_on);
        m.coupon_code = (typeof raw.coupon_code === 'string') ? $.trim(raw.coupon_code.replace(/[\x00-\x1F\x7F<>"']/g, '')).substring(0, 80) : '';
        m.coupon_apply = raw.coupon_apply === 'click' ? 'click' : 'open';
        m.page = $.inArray(raw.page, PAGES) !== -1 ? raw.page : 'any';
        m.page_products = ids(raw.page_products);
        m.page_cats = ids(raw.page_cats);
        m.upsell_on = bool(raw.upsell_on);
        m.upsell_mode = $.inArray(raw.upsell_mode, UMODES) !== -1 ? raw.upsell_mode : 'fixed';
        m.upsell_product = num(raw.upsell_product, 0, 999999999, 0);
        m.rc_on = bool(raw.rc_on);
        m.rc_delay = (raw.rc_delay === undefined || raw.rc_delay === null || raw.rc_delay === '') ? 60 : num(raw.rc_delay, 15, 4320, 0);
        m.rc_consent = (typeof raw.rc_consent === 'string' ? raw.rc_consent.toLowerCase().replace(/[^a-z0-9_\-]/g, '') : '').substring(0, 60) || 'consent';
        m.rc_subject = txt(raw.rc_subject, 150, false);
        m.rc_message = txt(raw.rc_message, 1000, true);
        m.rc_coupon = txt(raw.rc_coupon, 80, false).replace(/["']/g, '');
        return m;
    }

    /* ------------------------------------------------------------ DOM builders */
    function el(tag, attrs, text) {
        var $e = $(document.createElement(tag));
        if (attrs) { $.each(attrs, function (k, v) { $e.attr(k, v); }); }
        if (text !== undefined) { $e.text(text); }
        return $e;
    }
    function head(text) { return el('span', { 'class': 'wcc-h' }, text); }
    function hint(text, cond) {
        var $p = el('p', { 'class': 'wcc-hint' }, text);
        if (cond) { $p.attr('data-wcc-when', cond); }
        return $p;
    }
    /* Toggle-slide switch (never a check mark). attrName/attrVal tag the hidden checkbox for the delegated handlers. */
    function toggle(attrName, attrVal, label, small) {
        var a = { type: 'checkbox' };
        a[attrName] = attrVal;
        a.value = String(attrVal); /* ZPP_WC_P3: category toggles read this.value; without it they saw "on" and no category could be selected */
        return el('label', { 'class': 'wcc-tg' + (small ? ' wcc-tg-sm' : '') })
            .append(el('span', { 'class': 'wcc-tg-t' }, label))
            .append(el('span', { 'class': 'wcc-sw' }).append(el('input', a)).append(el('span', { 'class': 'wcc-track' })));
    }
    function seg(group, opts) {
        var $s = el('div', { 'class': 'wcc-segs', role: 'radiogroup' });
        $.each(opts, function (i, o) {
            $s.append(el('button', { type: 'button', 'class': 'wcc-seg', role: 'radio', 'aria-checked': 'false', 'data-wcc-group': group, 'data-wcc-val': o[0] }, o[1]));
        });
        return $s;
    }
    function numRow(field, label, unit) {
        var $r = el('div', { 'class': 'wcc-row' });
        $r.append(el('label', { 'for': 'wcc-f-' + field }, label));
        $r.append(el('input', { id: 'wcc-f-' + field, type: 'number', 'class': 'widefat', 'data-wcc-field': field, min: NUM[field][0], max: NUM[field][1], step: NUM[field][2] ? '0.01' : '1' }));
        if (unit) { $r.append(el('span', { 'class': 'wcc-unit' }, unit)); }
        return $r;
    }

    function build() {
        var $root = $(ROOT), $fs, $s, $w, $list, cats, sym = DATA.symbol || '$', tokens, i;
        if (!$root.length || $root.attr('data-built')) { return; }
        $root.attr('data-built', '1');

        $root.append(el('div', { 'class': 'wcc-top' })
            .append(head(L('WooCommerce Cart')))
            .append(el('label', { 'class': 'wcc-master', title: L('Turn the WooCommerce cart features for this popup on or off') })
                .append(el('input', { type: 'checkbox', id: 'set-wcc-enabled', 'aria-label': L('Enable WooCommerce Cart') }))
                .append(el('span', { 'class': 'wcc-track' }))));
        $root.append(hint(L('Cart-aware display rules and live cart text for this popup.')));

        $fs = el('fieldset', { id: 'zappoppop-wcc-fs' });
        if (!DATA.wc_active) { $fs.append(el('p', { 'class': 'wcc-note' }, L('WooCommerce is not active on this site, so the cart rules are ignored.'))); }

        $s = el('div', { 'class': 'wcc-sec' });
        $s.append(head(L('Show this popup when the cart is')));
        $s.append(seg('cart', [['any', L('Any')], ['has_items', L('Has items')], ['empty', L('Empty')]]));
        $s.append(hint(L('Empty ignores the conditions below.'), 'cart:empty'));
        $fs.append($s);

        $s = el('div', { 'class': 'wcc-sec', 'data-wcc-when': 'cart:!empty' });
        $s.append(head(L('Cart conditions')));
        $s.append(numRow('min_items', L('Minimum items'), L('items')));
        $s.append(numRow('min_total', L('Minimum subtotal'), sym));
        $s.append(numRow('max_total', L('Maximum subtotal'), sym));
        $s.append(hint(L('A value of 0 turns that condition off.')));
        $s.append(el('label', { 'class': 'wcc-lbl', 'for': 'wcc-f-products' }, L('Product IDs')));
        $s.append(el('input', { id: 'wcc-f-products', type: 'text', 'class': 'widefat', 'data-wcc-field': 'products', placeholder: '12, 48, 105', autocomplete: 'off' }));
        $s.append(hint(L('Comma-separated. The popup shows when any of these products is in the cart. Empty = off.')));
        $s.append(el('span', { 'class': 'wcc-lbl' }, L('Product categories')));
        cats = $.isArray(DATA.cats) ? DATA.cats : [];
        $list = el('div', { 'class': 'wcc-list' });
        if (!cats.length) { $list.append(el('p', { 'class': 'wcc-none' }, L('No product categories found.'))); }
        $.each(cats, function (j, c) { $list.append(toggle('data-wcc-cat', c[0], c[1], true).attr('data-wcc-name', String(c[1]).toLowerCase())); });
        if (cats.length > 8) { $s.append(el('input', { type: 'search', 'class': 'widefat wcc-filter', 'data-wcc-filter': '1', placeholder: L('Search...'), autocomplete: 'off' })); }
        $s.append($list);
        $s.append(hint(L('The popup shows when the cart holds a product from any selected category. None selected = off.')));
        $fs.append($s);

        $s = el('div', { 'class': 'wcc-sec' });
        $s.append(toggle('data-wcc-flag', 'show_on_add', L('Show right after an item is added to the cart')));
        $s.append(hint(L('Works with AJAX add to cart and the cart block. Shown once per page view, and only when the conditions above match.')));
        $fs.append($s);

        $s = el('div', { 'class': 'wcc-sec' });
        $s.append(head(L('Show on pages')));
        $s.append(seg('page', [['any', L('Anywhere')], ['product', L('Product pages')], ['selected', L('Selected products')]]));
        $s.append(hint(L('Product pages: the popup is not shown on any other page. Selected products: only on the products or categories below (none selected = every product page).')));
        $w = el('div', { 'data-wcc-when': 'page:selected' });
        $w.append(el('label', { 'class': 'wcc-lbl', 'for': 'wcc-f-page_products' }, L('Products')));
        $w.append(el('input', { id: 'wcc-f-page_products', type: 'text', 'class': 'widefat', 'data-wcc-field': 'page_products', placeholder: '12, 48, 105', autocomplete: 'off' }));
        $w.append(hint(L('Comma-separated product IDs. Empty = off.')));
        $w.append(el('span', { 'class': 'wcc-lbl' }, L('Product page categories')));
        $list = el('div', { 'class': 'wcc-list' });
        if (!cats.length) { $list.append(el('p', { 'class': 'wcc-none' }, L('No product categories found.'))); }
        $.each(cats, function (j, c) { $list.append(toggle('data-wcc-pcat', c[0], c[1], true).attr('data-wcc-pname', String(c[1]).toLowerCase())); });
        if (cats.length > 8) { $w.append(el('input', { type: 'search', 'class': 'widefat wcc-filter', 'data-wcc-pfilter': '1', placeholder: L('Search...'), autocomplete: 'off' })); }
        $w.append($list);
        $s.append($w);
        $s.append(hint(L('This rule is checked on the server, so it also works with full-page caches.')));
        $fs.append($s);

        $s = el('div', { 'class': 'wcc-sec' });
        $s.append(head(L('Upsell')));
        $s.append(toggle('data-wcc-flag', 'upsell_on', L('Offer an upsell product')));
        $w = el('div', { 'data-wcc-when': 'upsell_on:true' });
        $w.append(seg('upsell_mode', [['fixed', L('Chosen by you')], ['auto', L('Automatic')]]));
        $w.append(el('div', { 'data-wcc-when': 'upsell_mode:fixed' })
            .append(numRow('upsell_product', L('Upsell product ID')))
            .append(hint(L('The product to offer. A variation ID is allowed.'))));
        $w.append(hint(L('Automatic uses the cross-sells of the visitor\'s cart, then the upsells of the products in it. Set them on each WooCommerce product.'), 'upsell_mode:auto'));
        $w.append(hint(L('The popup is not shown when no upsell is available, for example when the product is already in the cart or out of stock.')));
        $w.append(hint(L('Give a button the link #zpp-add-upsell to add the product to the cart (the popup then closes), or #zpp-view-upsell to open its page. Variable products can only use the view link.')));
        $s.append($w);
        $fs.append($s);

        $s = el('div', { 'class': 'wcc-sec' });
        $s.append(head(L('Abandoned cart email')));
        $s.append(toggle('data-wcc-flag', 'rc_on', L('Send a cart reminder email')));
        $w = el('div', { 'data-wcc-when': 'rc_on:true' });
        $w.append(hint(L('Sent once to visitors who submit this popup\'s form with the consent box ticked and items in their cart. Nothing is saved without consent.')));
        $w.append(numRow('rc_delay', L('Send after'), L('minutes')));
        $w.append(el('label', { 'class': 'wcc-lbl', 'for': 'wcc-f-rc_consent' }, L('Consent field name')));
        $w.append(el('input', { id: 'wcc-f-rc_consent', type: 'text', 'class': 'widefat', 'data-wcc-field': 'rc_consent', maxlength: 60, placeholder: 'consent', autocomplete: 'off' }));
        $w.append(hint(L('Add a checkbox layer to the form (for example "Email me a reminder about my cart") and type the same name in its Advanced > Field name. Unticked = nothing is saved.')));
        $w.append(el('label', { 'class': 'wcc-lbl', 'for': 'wcc-f-rc_subject' }, L('Email subject')));
        $w.append(el('input', { id: 'wcc-f-rc_subject', type: 'text', 'class': 'widefat', 'data-wcc-field': 'rc_subject', maxlength: 150, placeholder: L('You left something in your cart'), autocomplete: 'off' }));
        $w.append(el('label', { 'class': 'wcc-lbl', 'for': 'wcc-f-rc_message' }, L('Email message')));
        $w.append(el('textarea', { id: 'wcc-f-rc_message', rows: 4, 'class': 'widefat', 'data-wcc-field': 'rc_message', maxlength: 1000, placeholder: L('Your cart is saved. Come back any time to finish your order.') }));
        $w.append(el('label', { 'class': 'wcc-lbl', 'for': 'wcc-f-rc_coupon' }, L('Coupon code (optional)')));
        $w.append(el('input', { id: 'wcc-f-rc_coupon', type: 'text', 'class': 'widefat', 'data-wcc-field': 'rc_coupon', maxlength: 80, placeholder: 'COMEBACK10', autocomplete: 'off' }));
        $w.append(hint(L('Shown in the email and applied when the visitor returns to the cart. Use a real WooCommerce coupon.')));
        $w.append(hint(L('No email is sent if an order was placed with the same address. Every email has an unsubscribe link; unsubscribed addresses are never saved again. Sending uses WordPress cron and wp_mail().')));
        $s.append($w);
        $fs.append($s);

        $s = el('div', { 'class': 'wcc-sec' });
        $s.append(head(L('Free shipping')));
        $s.append(numRow('free_ship_min', L('Free shipping threshold'), sym));
        $s.append(hint(L('0 = detect it from your WooCommerce free shipping method.')));
        $fs.append($s);

        $s = el('div', { 'class': 'wcc-sec' });
        $s.append(head(L('Coupon')));
        $s.append(el('label', { 'class': 'wcc-lbl', 'for': 'wcc-f-coupon_code' }, L('Coupon code')));
        $s.append(el('input', { id: 'wcc-f-coupon_code', type: 'text', 'class': 'widefat', 'data-wcc-field': 'coupon_code', maxlength: 80, placeholder: 'WELCOME10', autocomplete: 'off' }));
        $s.append(hint(L('An existing WooCommerce coupon. It fills {coupon_code} and the countdown. The code is visible in the page source, so do not use a private code.')));
        $s.append(toggle('data-wcc-flag', 'coupon_on', L('Apply it to the cart automatically')));
        $w = el('div', { 'data-wcc-when': 'coupon_on:true' });
        $w.append(seg('coupon_apply', [['open', L('When the popup opens')], ['click', L('When a button is clicked')]]));
        $w.append(hint(L('Give a button the link #zpp-apply-coupon.'), 'coupon_apply:click'));
        $w.append(hint(L('Only this code can be applied. If it is invalid or expired, nothing happens.')));
        $s.append($w);
        $fs.append($s);

        $s = el('div', { 'class': 'wcc-sec' });
        $s.append(head(L('Dynamic text')));
        tokens = [['{cart_total}', 'Cart total'], ['{cart_subtotal}', 'Cart subtotal'], ['{cart_count}', 'Number of items'],
                  ['{shipping_remaining}', 'Amount left for free shipping'], ['{free_shipping_min}', 'Free shipping threshold'], ['{coupon_code}', 'Applied coupon code'],
                  ['{coupon_countdown}', 'Coupon countdown'], ['{coupon_expires}', 'Coupon expiry date'],
                  ['{upsell_name}', 'Upsell product name'], ['{upsell_price}', 'Upsell product price']];
        $list = el('ul', { 'class': 'wcc-tokens' });
        for (i = 0; i < tokens.length; i++) {
            $list.append(el('li').append(el('code', null, tokens[i][0])).append(el('span', null, L(tokens[i][1]))));
        }
        $s.append($list);
        $s.append(hint(L('Type these into any text layer. Live Preview shows sample values; visitors see their own cart.')));
        $s.append(hint(L('The countdown and expiry date come from the coupon\'s real expiry date in WooCommerce.')));
        $s.append(hint(L('Free shipping bar: add a Progress Bar layer, set its mode to "Auto: WooCommerce free shipping" and write its label like: Add {shipping_remaining} for free shipping || You got free shipping!')));
        $fs.append($s);

        $root.append($fs);
    }

    /* ------------------------------------------------------------ model -> DOM */
    function paint() {
        var $root = $(ROOT);
        if (!$root.length) { return; }
        $('#set-wcc-enabled').prop('checked', model.enabled);
        $('#zappoppop-wcc-fs').prop('disabled', !model.enabled);
        $root.toggleClass('is-off', !model.enabled);

        $root.find('[data-wcc-group]').each(function () {
            var on = String(model[$(this).attr('data-wcc-group')]) === $(this).attr('data-wcc-val');
            $(this).toggleClass('is-on', on).attr('aria-checked', on ? 'true' : 'false');
        });
        $root.find('[data-wcc-field]').each(function () {
            var k = $(this).attr('data-wcc-field');
            if (document.activeElement === this) { return; }
            $(this).val($.isArray(model[k]) ? model[k].join(', ') : model[k]);
        });
        $root.find('[data-wcc-flag]').each(function () { this.checked = !!model[$(this).attr('data-wcc-flag')]; });
        $root.find('[data-wcc-cat]').each(function () { this.checked = $.inArray(parseInt(this.value, 10), model.cats) !== -1; });
        $root.find('[data-wcc-pcat]').each(function () { this.checked = $.inArray(parseInt(this.value, 10), model.page_cats) !== -1; });
        $root.find('[data-wcc-when]').each(function () {
            var p = $(this).attr('data-wcc-when').split(':'), neg = p[1].charAt(0) === '!', want = neg ? p[1].substring(1) : p[1], is = String(model[p[0]]) === want;
            $(this).toggle(neg ? !is : is);
        });
    }

    function changed() { $(DRAWER).trigger('change'); }

    /* ------------------------------------------------------------ save / load wrappers */
    var origGet = S.getSettings, origSet = S.setSettings;

    S.getSettings = function () {
        var out = origGet.apply(this, arguments) || {};
        out.woo_cart = clone(model);
        return out;
    };

    S.setSettings = function (settings) {
        var r = origSet.apply(this, arguments);
        model = normalize(settings && settings.woo_cart);
        paint();
        return r;
    };

    /* ------------------------------------------------------------ DOM -> model */
    function bind() {
        $(DRAWER)
            .off('.zppWCC')
            .on('change.zppWCC', '#set-wcc-enabled', function () {
                model.enabled = this.checked;
                paint();
            })
            .on('click.zppWCC', ROOT + ' [data-wcc-group]', function (e) {
                e.preventDefault();
                model[$(this).attr('data-wcc-group')] = $(this).attr('data-wcc-val');
                model = normalize(model);
                paint();
                changed();
            })
            .on('input.zppWCC change.zppWCC', ROOT + ' [data-wcc-field]', function (e) {
                var k = $(this).attr('data-wcc-field'), v = $(this).val();
                if (k === 'products' || k === 'page_products') {
                    model[k] = ids(String(v).split(/[\s,;]+/));
                } else if (k === 'upsell_product') {
                    model.upsell_product = num(v, 0, 999999999, 0);
                } else if (TEXTF[k]) {
                    model[k] = String(v);
                } else if (NUM[k]) {
                    model[k] = num(v, NUM[k][0], NUM[k][1], NUM[k][2]);
                } else if (k === 'coupon_code') {
                    model.coupon_code = String(v);
                }
                if (e.type === 'change') {
                    model = normalize(model);
                    $(this).val($.isArray(model[k]) ? model[k].join(', ') : model[k]);
                    paint();
                }
            })
            .on('change.zppWCC', ROOT + ' [data-wcc-flag]', function () {
                model[$(this).attr('data-wcc-flag')] = this.checked;
                paint(); /* ZPP_WC_P3: reveal / hide the options that depend on this switch (coupon auto-apply, upsell) */
            })
            .on('change.zppWCC', ROOT + ' [data-wcc-cat]', function () {
                var n = parseInt(this.value, 10), cur = $.grep(model.cats, function (x) { return x !== n; });
                if (this.checked) { cur.push(n); }
                model.cats = cur;
            })
            .on('change.zppWCC', ROOT + ' [data-wcc-pcat]', function () {
                var n = parseInt(this.value, 10), cur = $.grep(model.page_cats, function (x) { return x !== n; });
                if (this.checked) { cur.push(n); }
                model.page_cats = cur;
            })
            .on('input.zppWCC', ROOT + ' [data-wcc-pfilter]', function () {
                var q = String($(this).val()).replace(/^\s+|\s+$/g, '').toLowerCase();
                $(ROOT + ' [data-wcc-pname]').each(function () {
                    $(this).toggle(!q || $(this).attr('data-wcc-pname').indexOf(q) !== -1);
                });
            })
            .on('input.zppWCC', ROOT + ' [data-wcc-filter]', function () {
                var q = String($(this).val()).replace(/^\s+|\s+$/g, '').toLowerCase();
                $(ROOT + ' [data-wcc-name]').each(function () {
                    $(this).toggle(!q || $(this).attr('data-wcc-name').indexOf(q) !== -1);
                });
            });
    }

    if ($(DRAWER).length) { bind(); }
    $(function () { build(); bind(); paint(); });
})(jQuery);
