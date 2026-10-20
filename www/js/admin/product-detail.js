/*
 * "Admin - Detail produktu" (design_handoff_fikko) as a large modal on Produkty: the product card on one
 * screen – categories, photo, name, EAN, active, content, origin, the label texts with Složení (allergens
 * and nutrition values are added to it as in the design's buildSlozeni()), e-shop description, SEO – and
 * the prices of all the admin's branches (regular price for all, action for the selected branches, one
 * row per branch). Data: ProductDetailTrait (detailData! / detailSave!) → ProductQuickEditor.
 * Opened by .pd-open[data-pd] links (0 = new product) and window.pdOpen(id, code).
 */
(function () {
    'use strict';

    var cfgEl = document.getElementById('pd-config');
    if (!cfgEl) {
        return;
    }
    var cfg = JSON.parse(cfgEl.textContent);
    var t = cfg.t;
    var root = document.getElementById('pd-root');
    var modal = function (action) { window.jQuery('#modal-productDetail').modal(action); };
    var st = null;   // {id, d: data, dirty, sel: [row indexes], open: '' | 'nutri' | 'desc' | 'seo', saving, photo: File}

    // ---------- helpers ----------
    var esc = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
    };
    var fmt = function (s) {
        var args = Array.prototype.slice.call(arguments, 1);
        return String(s).replace(/%s/g, function () { return args.length ? args.shift() : ''; });
    };
    var num = function (v) { var n = parseFloat(String(v == null ? '' : v).replace(',', '.').replace(/\s/g, '')); return isNaN(n) ? null : n; };
    var kc = function (n) { return n == null ? '' : n.toFixed(2).replace('.', ','); };
    var input = function (v) { return v == null ? '' : String(v).replace('.', ','); };
    var slugify = function (s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); };
    var $ = function (sel) { return root.querySelector(sel); };
    var eanOk = function (code) {
        code = String(code || '').trim();
        if (!code || !/^\d+$/.test(code)) {
            return true;   // empty or the shop's own code: not checked
        }
        if (!/^(\d{8}|\d{13})$/.test(code)) {
            return false;
        }
        var n = code.length;
        var sum = 0;
        for (var i = 0; i < n - 1; i++) { sum += +code[i] * ((n - 1 - i) % 2 ? 3 : 1); }
        return (10 - sum % 10) % 10 === +code[n - 1];
    };
    var toastTimer;
    var toast = function (msg) {
        var el = $('.pd-toast');
        if (!el) {
            return;
        }
        el.textContent = msg;
        el.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { el.hidden = true; }, 2800);
    };

    // ---------- Složení: the text + "Obsahuje alergeny: …." + the nutrition line (buildSlozeni()) ----------
    var buildSlozeni = function () {
        var d = st.d;
        var base = String(d.ingredientsRaw || '').replace(/\s*Obsahuje alergeny:[^\n]*/gi, '').replace(/\s*Výživové údaje na [^:\n]*:[^\n]*/gi, '').replace(/\s+$/, '');
        var lines = [base];
        var al = cfg.allergens.filter(function (a) { return d.allergens.indexOf(a) >= 0; });
        if (al.length) {
            lines.push('Obsahuje alergeny: ' + al.map(function (a) { return a.toLowerCase(); }).join(', ') + '.');
        }
        var energy = [];
        var parts = [];
        d.nutrition.forEach(function (n) {
            var v = String(n.value || '').trim();
            if (!v) {
                return;
            }
            if (/^k(j|cal)$/i.test(n.unit)) {
                energy.push(v + ' ' + n.unit);
            } else {
                parts.push(n.name.replace(/:$/, '').toLowerCase() + ' ' + v + ' ' + n.unit);
            }
        });
        if (energy.length) {
            parts.unshift('energie ' + energy.join(' / '));
        }
        if (parts.length) {
            lines.push('Výživové údaje na ' + d.nutritionRef + ': ' + parts.join(', ') + '.');
        }
        d.ingredientsRaw = lines.filter(function (l) { return l.trim(); }).join('\n');
        var ta = $('[data-f="ingredientsRaw"]');
        if (ta && document.activeElement !== ta) {
            ta.value = d.ingredientsRaw;
        }
    };

    // ---------- prices: the status and the discount of a branch row ----------
    var rowInfo = function (b) {
        var p = num(b.price);
        var a = num(b.action);
        var info = { pct: '', pctBad: false, status: '', cls: '' };
        if (a == null) {
            return info;
        }
        if (p != null && p > 0) {
            if (a < p) {
                info.pct = '−' + Math.round((p - a) / p * 100) + ' %';
            } else {
                info.pct = t.akceBad;
                info.pctBad = true;
            }
        }
        if (b.to && b.to < cfg.today) {
            info.status = t.ended; info.cls = 'ended';
        } else if (b.from && b.from > cfg.today) {
            info.status = t.planned; info.cls = 'planned';
        } else if (a < p) {
            // the dates are optional: an action price alone runs
            info.status = t.running; info.cls = 'running';
        }
        return info;
    };
    var unitSuffix = function () { return st.d.priceUnit === 'Kč' ? 'Kč' : st.d.priceUnit; };
    // "cena za 100 g = …" from the branch of the list filter, the action price while it runs
    var unitPreview = function () {
        var d = st.d;
        var q = num(d.weight);
        var unit = (cfg.sizeUnits.find(function (u) { return u.id === d.unitsId; }) || {}).short || '';
        var b = d.branches.find(function (x) { return x.id === d.branchId; }) || d.branches[0] || {};
        var running = num(b.action) != null && rowInfo(b).cls === 'running';
        var price = num(running ? b.action : b.price);
        var base = { g: [100, '100 g'], kg: [1, '1 kg'], ml: [100, '100 ml'], l: [1, '1 l'], ks: [1, '1 ks'] }[unit.toLowerCase()];
        if (!q || !price || !base) {
            return t.unitPreviewEmpty;
        }
        return 'cena za ' + base[1] + ' = ' + kc(price / q * base[0]) + ' Kč (' + (b.name || '') + ': ' + kc(price) + ' Kč)';
    };

    // ---------- render ----------
    var render = function () {
        var d = st.d;
        var chip = function (on, attr, label, cls) { return '<button type="button" class="pd-chip' + (cls ? ' ' + cls : '') + (on ? ' pd-on' : '') + '" ' + attr + '>' + esc(label) + '</button>'; };
        var html = '<div class="pd-bar">'
            + '<button type="button" class="pd-btn" data-pdclose>' + esc(t.back) + '</button>'
            + '<div class="pd-bar-title"><small>' + esc(t.title) + '</small><b id="pd-name">' + esc(d.name || t.newTitle) + '</b></div>'
            + '<span class="pd-state' + (st.dirty ? ' pd-dirty' : '') + '" id="pd-state">' + esc(st.dirty ? t.unsaved : t.saved) + '</span>'
            + (st.id ? '<a class="pd-btn pd-btn-link" href="' + esc(cfg.urls.edit.replace('999999', st.id)) + '">' + esc(t.advanced) + '</a>' : '')
            + (st.id ? '<button type="button" class="pd-btn" data-pdprint>' + esc(t.print) + '</button>' : '')
            + '<button type="button" class="pd-save' + (st.dirty ? ' pd-save-on' : '') + '" data-pdsave' + (st.saving ? ' disabled' : '') + '>' + esc(st.saving ? t.saving : t.save) + ' <span>Ctrl+S</span></button>'
            + '</div><div class="pd-body"><div class="pd-col">';
        // categories
        html += '<section class="pd-card pd-cats"><b class="pd-label">' + esc(t.categories) + '</b><div class="pd-chips">'
            + cfg.categories.map(function (c) { return chip(d.categories.indexOf(c.id) >= 0, 'data-cat="' + c.id + '"', c.name); }).join('') + '</div></section>';
        // photo, name, EAN, active, content, origin
        var photo = st.photoUrl || d.image;
        html += '<section class="pd-card pd-main"><label class="pd-photo"' + (photo ? ' style="background-image:url(\'' + esc(photo) + '\')"' : '') + '>'
            + '<input type="file" accept="image/*" data-pdphoto hidden>' + (photo ? '' : '<span>📷<br>' + esc(t.photo) + '</span>') + '</label>'
            + '<div class="pd-main-fields">'
            + '<label class="pd-field"><span>' + esc(t.name) + '</span><input class="pd-input pd-input-name" data-f="name" value="' + esc(d.name) + '" maxlength="250"></label>'
            + '<div class="pd-grid2"><div><div class="pd-addon' + (eanOk(d.code) ? '' : ' pd-warn-border') + '" id="pd-ean"><span>' + esc(t.ean) + '</span><input data-f="code" value="' + esc(d.code) + '" inputmode="numeric" maxlength="32"></div>'
            + '<small class="pd-warn" id="pd-ean-warn"' + (eanOk(d.code) ? ' hidden' : '') + '>' + esc(t.eanBad) + '</small></div>'
            + '<button type="button" class="pd-toggle' + (d.active ? ' pd-on' : '') + '" data-pdactive><i></i>' + esc(t.active) + ': ' + esc(d.active ? t.yes : t.no) + '</button></div>'
            + '<div class="pd-field"><span>' + esc(t.content) + ' <em>· ' + esc(t.contentNote) + ' · ' + esc(t.origin) + '</em></span><div class="pd-row-inline">'
            + '<input class="pd-input pd-input-qty" data-f="weight" value="' + esc(input(d.weight)) + '" inputmode="decimal">'
            + '<div class="pd-seg">' + cfg.sizeUnits.map(function (u) { return '<button type="button" class="' + (u.id === d.unitsId ? 'pd-on' : '') + '" data-unit="' + u.id + '">' + esc(u.short) + '</button>'; }).join('') + '</div>'
            + '<select class="pd-input" data-f="country"><option value="0">—</option>' + cfg.countries.map(function (c) { return '<option value="' + c.id + '"' + (c.id === d.country ? ' selected' : '') + '>' + esc(c.name) + '</option>'; }).join('') + '</select></div>'
            + '<small class="pd-note">' + esc(t.unitPreview) + ' <b id="pd-unitprev">' + esc(unitPreview()) + '</b></small></div>'
            + '</div></section>';
        // on the label
        html += '<section class="pd-card"><b class="pd-h">' + esc(t.onLabel) + '</b>'
            + [0, 1, 2].map(function (i) { return '<div class="pd-addon"><span class="pd-addon-w">' + esc(fmt(t.note, i + 1)) + '</span><input data-note="' + i + '" value="' + esc(d.notes[i]) + '" placeholder="—" maxlength="255"></div>'; }).join('')
            + '<label class="pd-field"><span>' + esc(t.ingredients) + '</span><textarea class="pd-input" rows="5" data-f="ingredientsRaw">' + esc(d.ingredientsRaw) + '</textarea></label>'
            + '<div class="pd-field"><span>' + esc(t.allergens) + ' <em>· ' + esc(t.allergensNote) + '</em></span><div class="pd-chips">'
            + cfg.allergens.map(function (a, i) { return chip(d.allergens.indexOf(a) >= 0, 'data-allergen="' + esc(a) + '"', (i + 1) + ' ' + a, 'pd-chip-al'); }).join('') + '</div></div>'
            + '<label class="pd-check"><input type="checkbox" data-f="thawed"' + (d.thawed ? ' checked' : '') + '> ❄ Rozmrazeno</label></section>';
        // more
        var filled = d.nutrition.some(function (n) { return String(n.value || '').trim(); });
        var more = [['nutri', t.nutrition + ' (' + fmt(t.nutritionNote, d.nutritionRef).split(' –')[0] + ')', filled ? t.filled : t.empty],
            ['desc', t.perex, d.perex ? t.filled : t.empty], ['seo', t.seo, t.auto]];
        html += '<section class="pd-card pd-more">' + more.map(function (m) {
            var open = st.open === m[0];
            var body = '';
            if (open && m[0] === 'nutri') {
                body = '<small class="pd-note">' + esc(fmt(t.nutritionNote, d.nutritionRef)) + '</small>' + d.nutrition.map(function (n, i) {
                    return '<label class="pd-nutri' + (/^z toho/i.test(n.name) ? ' pd-sub' : '') + '"><span>' + esc(n.name) + '</span><span class="pd-addon pd-addon-r"><input data-nutri="' + i + '" value="' + esc(n.value) + '" inputmode="decimal"><i>' + esc(n.unit) + '</i></span></label>';
                }).join('');
            } else if (open && m[0] === 'desc') {
                body = '<textarea class="pd-input" rows="4" data-f="perex" placeholder="' + esc(t.perexPh) + '">' + esc(d.perex) + '</textarea>';
            } else if (open && m[0] === 'seo') {
                body = '<label class="pd-field"><span>' + esc(t.slug) + '</span><input class="pd-input" readonly value="' + esc(d.slug || slugify(d.name)) + '" id="pd-slug"></label>'
                    + '<label class="pd-field"><span>' + esc(t.metaTitle) + '</span><input class="pd-input" data-f="metaTitle" value="' + esc(d.metaTitle) + '" placeholder="' + esc(d.name) + '" maxlength="255"></label>'
                    + '<label class="pd-field"><span>' + esc(t.metaDesc) + '</span><textarea class="pd-input" rows="2" data-f="metaDesc">' + esc(d.metaDesc) + '</textarea></label>';
            }
            return '<div class="pd-more-item"><button type="button" class="pd-more-head" data-more="' + m[0] + '"><b>' + esc(m[1]) + '</b><small>' + esc(m[2]) + '</small><i>' + (open ? '▲' : '▼') + '</i></button>'
                + (open ? '<div class="pd-more-body">' + body + '</div>' : '') + '</div>';
        }).join('') + '</section></div>';
        // prices in the branches
        var all = st.sel.length === d.branches.length && d.branches.length;
        html += '<section class="pd-card pd-prices"><div class="pd-prices-head"><b class="pd-h">' + esc(t.prices) + '</b>'
            + '<div class="pd-row-inline"><span class="pd-label">' + esc(t.priceUnit) + '</span><div class="pd-seg">' + cfg.priceUnits.map(function (u) { return '<button type="button" class="' + (u === d.priceUnit ? 'pd-on' : '') + '" data-punit="' + esc(u) + '">' + esc(u) + '</button>'; }).join('') + '</div></div></div>'
            + '<div class="pd-prices-all"><span class="pd-label">' + esc(t.allPrice) + ' <em>· ' + esc(t.allPriceNote) + '</em></span>'
            + '<input class="pd-input pd-num" id="pd-allprice" inputmode="decimal" placeholder="24,90" value="' + esc(st.ui.all) + '"><button type="button" class="pd-btn pd-btn-teal" data-pdallprice>' + esc(t.apply) + '</button></div>'
            + '<div class="pd-prices-bulk"><b>' + esc(fmt(t.bulk, st.sel.length)) + '</b>'
            + '<input class="pd-input pd-num" id="pd-bulk-price" inputmode="decimal" placeholder="' + esc(t.bulkPrice) + '" value="' + esc(st.ui.price) + '">'
            + '<input class="pd-input" type="date" id="pd-bulk-from" value="' + esc(st.ui.from) + '"><span>–</span><input class="pd-input" type="date" id="pd-bulk-to" value="' + esc(st.ui.to) + '">'
            + '<button type="button" class="pd-btn pd-btn-red" data-pdbulk>' + esc(t.setAkce) + '</button><button type="button" class="pd-btn" data-pdbulkend>' + esc(t.endAkce) + '</button></div>'
            + '<div class="pd-prow pd-prow-head"><button type="button" class="pd-box' + (all ? ' pd-on' : '') + '" data-pdselall>' + (all ? '✓' : '') + '</button>'
            + '<span>' + esc(t.branch) + '</span><span class="pd-r">' + esc(t.price) + '</span><span>' + esc(t.akceRange) + '</span></div>'
            + d.branches.map(function (b, i) { return '<div class="pd-prow' + (st.sel.indexOf(i) >= 0 ? ' pd-prow-on' : '') + '" data-row="' + i + '">' + rowHtml(b, i) + '</div>'; }).join('')
            + '<p class="pd-note">' + esc(fmt(t.pricesNote, unitSuffix())) + '</p></section>';
        html += '</div><p class="pd-error" hidden></p><div class="pd-toast" hidden></div>';
        root.innerHTML = html;
    };
    var rowHtml = function (b, i) {
        var info = rowInfo(b);
        var on = st.sel.indexOf(i) >= 0;
        var badRange = b.from && b.to && b.to < b.from;
        return '<button type="button" class="pd-box' + (on ? ' pd-on' : '') + '" data-pdsel="' + i + '">' + (on ? '✓' : '') + '</button>'
            + '<div class="pd-prow-name"><span>' + esc(b.name) + '</span>' + (info.pct ? '<i class="pd-pct' + (info.pctBad ? ' pd-pct-bad' : '') + '">' + esc(info.pct) + '</i>' : '') + '</div>'
            + '<div class="pd-addon pd-addon-r"><input data-b="' + i + '|price" value="' + esc(input(b.price)) + '" inputmode="decimal"><i>' + esc(unitSuffix()) + '</i></div>'
            + '<div class="pd-prow-akce"><div class="pd-addon pd-addon-r' + (num(b.action) != null ? ' pd-akce-on' : '') + '"><input data-b="' + i + '|action" value="' + esc(input(b.action)) + '" placeholder="' + esc(t.akcePrice) + '" inputmode="decimal"><i>' + esc(unitSuffix()) + '</i></div>'
            + '<input class="pd-input pd-date" type="date" data-b="' + i + '|from" value="' + esc(b.from || '') + '" title="od"><span>–</span>'
            + '<input class="pd-input pd-date' + (badRange ? ' pd-warn-border' : '') + '" type="date" data-b="' + i + '|to" value="' + esc(b.to || '') + '" title="do">'
            + (info.status ? '<span class="pd-status pd-st-' + info.cls + '">' + esc(info.status) + '</span>' : '')
            + (num(b.action) != null || b.from || b.to ? '<button type="button" class="pd-x" data-pdclear="' + i + '" title="' + esc(t.clear) + '">×</button>' : '') + '</div>';
    };
    var renderRow = function (i) {
        var el = root.querySelector('.pd-prow[data-row="' + i + '"]');
        if (!el) {
            return;
        }
        // keep the focused input: only the badges and status are redrawn
        var info = rowInfo(st.d.branches[i]);
        var name = el.querySelector('.pd-prow-name');
        name.innerHTML = '<span>' + esc(st.d.branches[i].name) + '</span>' + (info.pct ? '<i class="pd-pct' + (info.pctBad ? ' pd-pct-bad' : '') + '">' + esc(info.pct) + '</i>' : '');
        var b = st.d.branches[i];
        el.querySelector('[data-b="' + i + '|to"]').classList.toggle('pd-warn-border', !!(b.from && b.to && b.to < b.from));
        var old = el.querySelector('.pd-status');
        if (old) {
            old.remove();
        }
        // × (clear the action) while there is something to clear, also once typed after a clear
        var x = el.querySelector('.pd-x');
        var clearable = num(b.action) != null || b.from || b.to;
        if (clearable && !x) {
            x = document.createElement('button');
            x.type = 'button';
            x.className = 'pd-x';
            x.setAttribute('data-pdclear', String(i));
            x.title = t.clear;
            x.textContent = '×';
            el.querySelector('.pd-prow-akce').appendChild(x);
        } else if (!clearable && x) {
            x.remove();
            x = null;
        }
        if (info.status) {
            var s = document.createElement('span');
            s.className = 'pd-status pd-st-' + info.cls;
            s.textContent = info.status;
            el.querySelector('.pd-prow-akce').insertBefore(s, x);
        }
    };
    var dirty = function () {
        if (st.dirty) {
            return;
        }
        st.dirty = true;
        var s = $('#pd-state');
        s.textContent = t.unsaved;
        s.classList.add('pd-dirty');
        $('[data-pdsave]').classList.add('pd-save-on');
    };
    var refreshPreview = function () {
        var el = $('#pd-unitprev');
        if (el) {
            el.textContent = unitPreview();
        }
    };

    // ---------- open / close / save ----------
    var load = function (pid, code) {
        var u = new URL(cfg.urls.data, location.href);
        u.searchParams.set('productsId', pid || 0);
        if (code) {
            u.searchParams.set('code', code);
        }
        return fetch(u, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); });
    };
    window.pdOpen = function (pid, code) {
        pid = parseInt(pid, 10) || 0;
        if ((pid && !cfg.canEdit) || (!pid && !cfg.canCreate)) {
            return false;
        }
        root.innerHTML = '<div class="pd-loading">…</div>';
        modal('show');
        load(pid, code).then(function (d) {
            if (!d) {
                modal('hide');
                return;
            }
            // ui = the "all branches" price and the bulk action inputs (kept over redraws)
            st = { id: d.id, d: d, dirty: false, sel: [], open: '', saving: false, photo: null, photoUrl: null, ui: { all: '', price: '', from: cfg.today, to: cfg.today } };
            render();
            setTimeout(function () { var n = $('[data-f="name"]'); if (n && !pid) { n.focus(); } }, 300);
        }).catch(function () { modal('hide'); });
        return true;
    };
    var close = function () {
        if (st && st.dirty && !window.confirm(t.leave)) {
            return;
        }
        st = null;
        modal('hide');
    };
    // phone photos are large (the server takes 2 MB): sent as a JPEG of at most 1600 px
    var shrink = function (file) {
        return new Promise(function (resolve) {
            if (!file) {
                resolve(null);
                return;
            }
            var img = new Image();
            var url = URL.createObjectURL(file);
            img.onload = function () {
                var k = Math.min(1, 1600 / Math.max(img.naturalWidth, img.naturalHeight));
                var c = document.createElement('canvas');
                c.width = Math.round(img.naturalWidth * k);
                c.height = Math.round(img.naturalHeight * k);
                c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                URL.revokeObjectURL(url);
                c.toBlob(function (b) { resolve(b); }, 'image/jpeg', 0.85);
            };
            img.onerror = function () { URL.revokeObjectURL(url); resolve(file.size < 1900000 ? file : null); };
            img.src = url;
        });
    };
    var save = function () {
        if (!st || st.saving) {
            return Promise.resolve(false);
        }
        var d = st.d;
        var f = new FormData();
        f.append('name', d.name);
        f.append('code', d.code);
        if (d.active) { f.append('active', '1'); }
        d.categories.forEach(function (c) { f.append('categories[]', c); });
        f.append('weight', String(d.weight == null ? '' : d.weight).replace(',', '.'));
        f.append('units_id', d.unitsId);
        f.append('country', d.country || '');
        f.append('price_unit', d.priceUnit);
        d.branches.forEach(function (b) {
            ['price', 'action', 'from', 'to'].forEach(function (k) {
                var name = { price: 'price', action: 'price_action', from: 'action_from', to: 'action_to' }[k];
                var v = b[k] == null ? '' : String(b[k]);
                f.append('prices[' + b.id + '][' + name + ']', k === 'price' || k === 'action' ? v.replace(',', '.') : v);
            });
        });
        d.notes.forEach(function (n, i) { f.append('label_note_' + (i + 1), n); });
        f.append('ingredients', d.ingredientsRaw);
        d.allergens.forEach(function (a) { f.append('allergens[]', a); });
        if (d.thawed) { f.append('rozmrazeno', '1'); }
        d.nutrition.forEach(function (n) { f.append('nutrition[' + n.id + ']', String(n.value || '').replace(',', '.')); });
        f.append('perex', d.perex);
        f.append('meta_title', d.metaTitle);
        f.append('meta_desc', d.metaDesc);
        st.saving = true;
        var btn = $('[data-pdsave]');
        btn.disabled = true;
        btn.firstChild.textContent = t.saving + ' ';
        $('.pd-error').hidden = true;
        var u = new URL(cfg.urls.save, location.href);
        u.searchParams.set('productsId', st.id || 0);
        var wasNew = !st.id;
        return shrink(st.photo).then(function (blob) {
            if (blob) {
                f.append('photo', blob, 'produkt.jpg');
            }
            return fetch(u, { method: 'POST', body: f, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        }).then(function (r) { return r.json(); }).then(function (r) {
            st.saving = false;
            if (!r.ok) {
                var e = $('.pd-error');
                e.textContent = r.error || t.error;
                e.hidden = false;
                btn.disabled = false;
                btn.firstChild.textContent = t.save + ' ';
                return false;
            }
            st.id = r.id;
            // the list behind the modal, filters kept
            if (window.ajaxGet) {
                window.ajaxGet(cfg.urls.refresh);
            }
            return load(r.id).then(function (fresh) {
                st.d = fresh;
                st.dirty = false;
                st.photo = null;
                st.photoUrl = null;
                render();
                toast(wasNew ? t.createdToast : t.savedToast);
                return true;
            });
        }).catch(function () {
            st.saving = false;
            var e = $('.pd-error');
            e.textContent = t.error;
            e.hidden = false;
            btn.disabled = false;
            btn.firstChild.textContent = t.save + ' ';
            return false;
        });
    };

    // ---------- events ----------
    document.addEventListener('click', function (ev) {
        var opener = ev.target.closest('.pd-open[data-pd]');
        if (opener && !ev.ctrlKey && !ev.metaKey && !ev.shiftKey && ev.button === 0) {
            if (window.pdOpen(opener.getAttribute('data-pd'))) {
                ev.preventDefault();
            }
            return;
        }
        if (!st || !root.contains(ev.target)) {
            return;
        }
        var b = ev.target.closest('button, [data-pdphoto]');
        if (!b) {
            return;
        }
        var dd = b.dataset;
        var d = st.d;
        if (dd.pdclose !== undefined) {
            close();
        } else if (dd.pdsave !== undefined) {
            save();
        } else if (dd.pdprint !== undefined) {
            // the row's "Možnosti tisku" (templates, counts) on Produkty
            var go = function () {
                var pid = st.id;
                st = null;
                modal('hide');
                var row = document.querySelector('.pv-row[data-id="' + pid + '"]');
                var btn = row && row.querySelector('.pv-open-labels');
                if (btn) {
                    if (!row.classList.contains('pv-open-labels-on')) { btn.click(); }
                    row.scrollIntoView({ block: 'start', behavior: 'smooth' });
                }
            };
            if (st.dirty) {
                save().then(function (ok) { if (ok) { go(); } });
            } else {
                go();
            }
        } else if (dd.cat) {
            var c = parseInt(dd.cat, 10);
            d.categories = d.categories.indexOf(c) >= 0 ? d.categories.filter(function (x) { return x !== c; }) : d.categories.concat([c]);
            b.classList.toggle('pd-on');
            dirty();
        } else if (dd.allergen) {
            var a = dd.allergen;
            d.allergens = d.allergens.indexOf(a) >= 0 ? d.allergens.filter(function (x) { return x !== a; }) : d.allergens.concat([a]);
            b.classList.toggle('pd-on');
            buildSlozeni();
            dirty();
        } else if (dd.pdactive !== undefined) {
            d.active = !d.active;
            b.classList.toggle('pd-on', d.active);
            b.lastChild.textContent = t.active + ': ' + (d.active ? t.yes : t.no);
            dirty();
        } else if (dd.unit) {
            d.unitsId = parseInt(dd.unit, 10);
            b.parentNode.querySelectorAll('button').forEach(function (x) { x.classList.toggle('pd-on', x === b); });
            refreshPreview();
            dirty();
        } else if (dd.punit) {
            d.priceUnit = dd.punit;
            dirty();
            render();
        } else if (dd.more) {
            st.open = st.open === dd.more ? '' : dd.more;
            render();
        } else if (dd.pdsel !== undefined) {
            var i = parseInt(dd.pdsel, 10);
            st.sel = st.sel.indexOf(i) >= 0 ? st.sel.filter(function (x) { return x !== i; }) : st.sel.concat([i]);
            render();
        } else if (dd.pdselall !== undefined) {
            st.sel = st.sel.length === d.branches.length ? [] : d.branches.map(function (x, j) { return j; });
            render();
        } else if (dd.pdallprice !== undefined) {
            var v = $('#pd-allprice').value.trim();
            if (!v) {
                return;
            }
            d.branches.forEach(function (x) { x.price = v; });
            st.ui.all = '';
            dirty();
            render();
            toast(fmt(t.toastAll, v));
        } else if (dd.pdbulk !== undefined) {
            var price = $('#pd-bulk-price').value.trim();
            if (!st.sel.length || !price) {
                toast(t.toastPick);
                return;
            }
            var from = $('#pd-bulk-from').value;
            var to = $('#pd-bulk-to').value;
            var n = st.sel.length;
            st.sel.forEach(function (j) { var x = d.branches[j]; x.action = price; x.from = from; x.to = to; });
            st.sel = [];
            st.ui.price = '';
            dirty();
            render();
            toast(fmt(t.toastBulk, price, n));
        } else if (dd.pdbulkend !== undefined) {
            if (!st.sel.length) {
                return;
            }
            st.sel.forEach(function (j) { var x = d.branches[j]; x.action = null; x.from = null; x.to = null; });
            st.sel = [];
            dirty();
            render();
        } else if (dd.pdclear !== undefined) {
            var r = d.branches[parseInt(dd.pdclear, 10)];
            r.action = null; r.from = null; r.to = null;
            dirty();
            render();
        }
    });

    document.addEventListener('input', function (ev) {
        if (!st || !root.contains(ev.target)) {
            return;
        }
        var el = ev.target;
        var d = st.d;
        var ds = el.dataset;
        var ui = { 'pd-allprice': 'all', 'pd-bulk-price': 'price', 'pd-bulk-from': 'from', 'pd-bulk-to': 'to' }[el.id];
        if (ui) {
            st.ui[ui] = el.value;
            if (ui === 'from' && el.value && st.ui.to < el.value) {
                st.ui.to = el.value;
                $('#pd-bulk-to').value = el.value;
            }
            return;
        }
        if (ds.f) {
            d[ds.f] = el.type === 'checkbox' ? el.checked : el.value;
            if (ds.f === 'name') {
                $('#pd-name').textContent = el.value || t.newTitle;
                var slug = $('#pd-slug');
                if (slug && !d.slug) { slug.value = slugify(el.value); }
            } else if (ds.f === 'code') {
                var ok = eanOk(el.value);
                $('#pd-ean').classList.toggle('pd-warn-border', !ok);
                $('#pd-ean-warn').hidden = ok;
            } else if (ds.f === 'weight') {
                refreshPreview();
            } else if (ds.f === 'country') {
                d.country = parseInt(el.value, 10) || 0;
            }
        } else if (ds.note) {
            d.notes[parseInt(ds.note, 10)] = el.value;
        } else if (ds.nutri) {
            d.nutrition[parseInt(ds.nutri, 10)].value = el.value;
            buildSlozeni();
        } else if (ds.b) {
            var p = ds.b.split('|');
            var i = parseInt(p[0], 10);
            var row = d.branches[i];
            row[p[1]] = el.value || null;
            // as in the design: an action price typed without dates starts today
            if (p[1] === 'action' && el.value && !row.from) {
                row.from = cfg.today;
                row.to = row.to || cfg.today;
                var rowEl = root.querySelector('.pd-prow[data-row="' + i + '"]');
                rowEl.querySelector('[data-b="' + i + '|from"]').value = row.from;
                rowEl.querySelector('[data-b="' + i + '|to"]').value = row.to;
            }
            if (p[1] === 'from' && el.value && (!row.to || row.to < el.value)) {
                row.to = el.value;
                root.querySelector('[data-b="' + i + '|to"]').value = row.to;
            }
            renderRow(i);
            refreshPreview();
        } else {
            return;
        }
        dirty();
    });
    document.addEventListener('change', function (ev) {
        if (!st || !root.contains(ev.target)) {
            return;
        }
        if (ev.target.dataset.pdphoto !== undefined) {
            var file = ev.target.files && ev.target.files[0];
            if (file && /^image\//.test(file.type)) {
                st.photo = file;
                st.photoUrl = URL.createObjectURL(file);
                var label = ev.target.closest('.pd-photo');
                label.style.backgroundImage = 'url("' + st.photoUrl + '")';
                var span = label.querySelector('span');
                if (span) { span.remove(); }
                dirty();
            }
        } else if (ev.target.dataset.f === 'thawed') {
            st.d.thawed = ev.target.checked;
            dirty();
        } else if (ev.target.dataset.f === 'country') {
            st.d.country = parseInt(ev.target.value, 10) || 0;
            dirty();
        }
    });
    document.addEventListener('keydown', function (ev) {
        if (!st) {
            return;
        }
        if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 's') {
            ev.preventDefault();
            save();
        } else if (ev.key === 'Escape') {
            close();
        }
    });
})();
