/*
 * Phone app "Tisk štítků" (AdminModule/Mobil, predani-tisk-stitku "Admin – tisk štítků mobil").
 * Scan or find a product, pick templates and counts, keep the queue on the phone (localStorage)
 * and send it to the admin (Tisk z mobilu). Prices are edited on the product card (server).
 * Camera: BarcodeDetector (Android Chrome), otherwise ZXing; https is required for the camera.
 */
(function () {
    'use strict';

    var cfg = JSON.parse(document.getElementById('m-config').textContent);
    var t = cfg.t;
    var types = cfg.types;
    var typeById = {};
    types.forEach(function (ty) { typeById[ty.id] = ty; });

    // ---------- state ----------
    var store = {
        load: function () {
            try {
                return JSON.parse(localStorage.getItem(cfg.storageKey) || '{}');
            } catch (e) {
                return {};
            }
        },
        save: function () {
            try {
                localStorage.setItem(cfg.storageKey, JSON.stringify({ queue: state.queue, recent: state.recent, quick: state.quick, lastType: state.lastType, screen: state.screen }));
            } catch (e) {
                // private mode: the queue lives until the page is closed
            }
        }
    };
    var saved = store.load();
    var state = {
        screen: saved.screen || 'scan',
        queue: Array.isArray(saved.queue) ? saved.queue : [],   // [{pid, type, qty, header, ov}]
        recent: Array.isArray(saved.recent) ? saved.recent : [],
        quick: !!saved.quick,
        lastType: saved.lastType || (types[0] && types[0].id),
        products: {},      // id → info from the server
        results: null,     // search results (ids) or null = recent
        priceOpen: null,   // product id with the price panel open
        sending: false
    };

    // ---------- helpers ----------
    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var esc = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
    };
    var kc = function (n) { return n == null ? '' : Number(n).toFixed(2).replace('.', ','); };
    var fmt = function (s) {
        var args = Array.prototype.slice.call(arguments, 1);
        return String(s).replace(/%s/g, function () { return args.length ? args.shift() : ''; });
    };
    var ajax = function (url, opts) {
        opts = opts || {};
        var headers = { 'X-Requested-With': 'XMLHttpRequest' };
        if (opts.json) {
            headers['Content-Type'] = 'application/json';
        }
        return fetch(url, { method: opts.method || 'GET', headers: headers, credentials: 'same-origin', body: opts.json ? JSON.stringify(opts.json) : undefined })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error(r.status);
                }
                return r.json();
            });
    };
    var withParams = function (url, params) {
        var u = new URL(url, location.href);
        Object.keys(params).forEach(function (k) { u.searchParams.set(k, params[k] == null ? '' : params[k]); });
        return u.toString();
    };
    var queueCount = function () { return state.queue.reduce(function (a, e) { return a + e.qty; }, 0); };
    var device = function () {
        var ua = navigator.userAgent;
        if (/iPhone/.test(ua)) { return 'iPhone'; }
        if (/iPad/.test(ua)) { return 'iPad'; }
        if (/SM-|Samsung/i.test(ua)) { return 'Samsung'; }
        if (/Android/.test(ua)) { return 'Android'; }
        return 'Mobil';
    };
    var statusText = { running: t.statusRunning, planned: t.statusPlanned, ended: t.statusEnded, none: t.statusNone };

    // ---------- toast ----------
    var toastTimer;
    var toast = function (msg, undo) {
        var el = $('#m-toast');
        el.innerHTML = '<span>' + esc(msg) + '</span>' + (undo ? '<button type="button">' + esc(t.undo) + '</button>' : '');
        el.hidden = false;
        if (undo) {
            el.querySelector('button').onclick = function () { undo(); el.hidden = true; };
        }
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { el.hidden = true; }, undo ? 6000 : 3500);
    };

    // ---------- products ----------
    var remember = function (info) {
        if (info) {
            state.products[info.id] = info;
        }
        return info;
    };
    var touchRecent = function (pid) {
        state.recent = [pid].concat(state.recent.filter(function (x) { return x !== pid; })).slice(0, 8);
        store.save();
    };
    var loadProducts = function (ids) {
        ids = ids.filter(function (id, i) { return ids.indexOf(id) === i; });
        if (!ids.length) {
            return Promise.resolve();
        }
        return ajax(withParams(cfg.urls.products, { ids: ids.join(',') })).then(function (data) {
            Object.keys(data || {}).forEach(function (id) { remember(data[id]); });
        });
    };

    // ---------- queue ----------
    var entry = function (pid, type) {
        return state.queue.find(function (e) { return e.pid === pid && e.type === type; });
    };
    var setCount = function (pid, type, qty) {
        var e = entry(pid, type);
        if (qty <= 0) {
            state.queue = state.queue.filter(function (x) { return x !== e; });
        } else if (e) {
            e.qty = Math.min(999, qty);
        } else {
            state.queue.push({ pid: pid, type: type, qty: qty, header: typeById[type].header, ov: {} });
        }
        store.save();
        badges();
    };
    var snapshot = function () { return JSON.parse(JSON.stringify(state.queue)); };
    var restore = function (snap) { state.queue = snap; store.save(); badges(); render(); };

    var badges = function () {
        var n = queueCount();
        ['#m-qbadge', '#m-tbadge'].forEach(function (s) {
            var el = $(s);
            el.textContent = n;
            el.hidden = !n;
        });
    };

    // ---------- navigation ----------
    var titles = { overview: t.overview, scan: t.scan, queue: t.queueTitle, jobs: t.sent };
    var go = function (screen) {
        state.screen = screen;
        store.save();
        document.querySelectorAll('.m-screen').forEach(function (s) { s.hidden = s.getAttribute('data-screen') !== screen; });
        document.querySelectorAll('.m-tabs button').forEach(function (b) { b.classList.toggle('m-on', b.getAttribute('data-go') === screen); });
        $('#m-title').textContent = titles[screen];
        $('#m-drawer').hidden = true;
        if (screen !== 'scan') {
            stopCamera();
        }
        render();
    };

    // ---------- product card (scan results / recent) ----------
    var priceBlock = function (p) {
        var akce = p.action != null;
        var html = '<div class="m-price">';
        html += '<div class="m-price-l"><small>' + esc(t.regular) + '</small><b>' + kc(p.base) + ' Kč</b>'
            + (akce && p.ref30 ? '<small>' + esc(t.lowest30) + ': ' + kc(p.ref30) + ' Kč</small>' : '') + '</div>';
        if (akce) {
            html += '<div class="m-price-r"><small>' + (p.pct ? '<em>−' + p.pct + ' %</em> ' : '') + esc(t.akcePrice) + '</small>'
                + '<b class="m-red">' + kc(p.action) + ' <span>' + esc(p.unit) + '</span></b><small>' + esc(p.dates) + '</small></div>';
        } else {
            html += '<div class="m-price-r"><small>' + esc(t.ourPrice) + '</small><b>' + kc(p.base) + ' <span>' + esc(p.unit) + '</span></b><small>' + esc(t.noAkce) + '</small></div>';
        }
        html += '</div>';
        if (p.unitText) {
            html += '<div class="m-unitprice">' + esc(p.unitText) + '</div>';
        }
        return html;
    };
    var pricePanel = function (p) {
        var iso = function (d) { var m = String(d || '').match(/^(\d{2})\.(\d{2})\.(\d{4})$/); return m ? m[3] + '-' + m[2] + '-' + m[1] : ''; };
        return '<form class="m-pricepanel" data-price="' + p.id + '">'
            + '<label>' + esc(t.unit) + '<select name="unit">' + cfg.units.map(function (u) { return '<option' + (u === p.unit ? ' selected' : '') + '>' + esc(u) + '</option>'; }).join('') + '</select></label>'
            + '<label>' + esc(t.regular) + '<input name="base" inputmode="decimal" value="' + (p.base != null ? p.base : '') + '"></label>'
            + '<label>' + esc(t.akcePrice) + '<input name="akce" inputmode="decimal" value="' + (p.action != null ? p.action : '') + '" placeholder="—"></label>'
            + '<label>' + esc(t.from) + '<input type="date" name="from" value="' + iso(p.from) + '"></label>'
            + '<label>' + esc(t.to) + '<input type="date" name="to" value="' + iso(p.to) + '"></label>'
            + '<small>' + esc(t.priceNote) + '</small>'
            + '<div class="m-row"><button type="button" class="m-btn m-btn-red" data-endakce="' + p.id + '">' + esc(t.endAkce) + '</button>'
            + '<span class="m-flex"></span><button type="button" class="m-btn" data-priceclose>' + esc(t.cancel) + '</button>'
            + '<button type="submit" class="m-btn m-btn-dark">' + esc(t.save) + '</button></div></form>';
    };
    var productCard = function (p) {
        var chips = state.queue.filter(function (e) { return e.pid === p.id; }).map(function (e) {
            return '<span class="m-chip' + (typeById[e.type].akce ? ' m-chip-akce' : '') + '">' + esc(typeById[e.type].name) + ' × ' + e.qty + '</span>';
        }).join('');
        var n = state.queue.filter(function (e) { return e.pid === p.id; }).reduce(function (a, e) { return a + e.qty; }, 0);
        return '<div class="m-card m-product">'
            + '<div class="m-prod-top"><div class="m-thumb"' + (p.image ? ' style="background-image:url(\'' + esc(p.image) + '\')"' : '') + '>' + (p.image ? '' : '📷') + '</div>'
            + '<div class="m-prod-name"><b>' + esc(p.name) + '</b><small>' + esc([p.ean ? 'EAN ' + p.ean : '', p.weight, p.origin].filter(Boolean).join(' · ')) + '</small></div>'
            + '<span class="m-status m-status-' + p.status + '">' + esc(statusText[p.status] || '') + '</span></div>'
            + priceBlock(p)
            + (cfg.canSend ? '<button type="button" class="m-link" data-priceopen="' + p.id + '">✎ ' + esc(t.editPrice) + '</button>' : '')
            + (state.priceOpen === p.id ? pricePanel(p) : '')
            + '<div class="m-prod-foot"><div class="m-chips">' + (chips || '<span class="m-muted">' + esc(t.noLabels) + '</span>') + '</div>'
            + '<button type="button" class="m-btn m-btn-outline" data-sheet="' + p.id + '">🖨 ' + esc(t.labels) + (n ? ' · ' + n : '') + ' ▾</button></div>'
            + '</div>';
    };

    // ---------- screens ----------
    var renderOverview = function () {
        var el = $('[data-screen="overview"]');
        el.innerHTML = '<div class="m-kpis">'
            + '<div class="m-card m-kpi"><small>' + esc(t.kpiAkce) + '</small><b id="m-k-akce">…</b></div>'
            + '<div class="m-card m-kpi"><small>' + esc(t.kpiQueue) + '</small><b class="m-teal">' + queueCount() + '</b></div>'
            + '<div class="m-card m-kpi"><small>' + esc(t.kpiWaiting) + '</small><b class="m-orange" id="m-k-wait">…</b></div>'
            + '<div class="m-card m-kpi"><small>' + esc(t.kpiVykup) + '</small><b id="m-k-vykup">…</b></div>'
            + '</div><div class="m-label">' + esc(t.quickActions) + '</div>'
            + '<button type="button" class="m-big m-big-dark" data-go="scan">' + esc(t.scanBtn) + '</button>'
            + '<button type="button" class="m-big" data-go="scan" data-focus-search>' + esc(t.productsBtn) + '</button>';
        ajax(cfg.urls.stats).then(function (s) {
            $('#m-k-akce').textContent = s.akce;
            $('#m-k-wait').textContent = s.waiting;
            $('#m-k-vykup').textContent = s.vykup == null ? '–' : s.vykup.toLocaleString('cs-CZ') + ' Kč';
        }).catch(function () {});
    };

    var renderScan = function () {
        var ty = typeById[state.lastType];
        $('#m-quick').checked = state.quick;
        $('#m-quick-hint').textContent = fmt(t.quickHint, ty ? ty.name : '');
        var ids = state.results !== null ? state.results : state.recent;
        var list = ids.map(function (id) { return state.products[id]; }).filter(Boolean);
        var html = '<div class="m-label">' + esc(state.results !== null ? t.results : t.recent) + '</div>';
        if (!list.length && state.results !== null) {
            html += '<div class="m-card m-empty">' + esc(t.noResults) + '</div>';
        }
        html += list.map(productCard).join('');
        $('#m-results').innerHTML = html;
    };

    var renderQueue = function () {
        var el = $('[data-screen="queue"]');
        if (!state.queue.length) {
            el.innerHTML = '<div class="m-card m-empty">' + esc(t.queueEmpty) + '</div>';
            return;
        }
        var html = '';
        types.forEach(function (ty) {
            var items = state.queue.filter(function (e) { return e.type === ty.id; });
            if (!items.length) {
                return;
            }
            var count = items.reduce(function (a, e) { return a + e.qty; }, 0);
            html += '<div class="m-card m-group"><div class="m-group-head"><b>' + esc(ty.name) + '</b><small>' + esc(ty.dims) + ' mm</small>'
                + (ty.akce ? '<span class="m-akce">AKCE</span>' : '') + '<span class="m-flex"></span><small>' + fmt(t.pcsPages, count, Math.ceil(count / ty.perPage)) + '</small></div>';
            items.forEach(function (e, i) {
                var p = state.products[e.pid] || { id: e.pid, name: '…', unit: 'Kč' };
                var name = e.ov.name != null ? e.ov.name : p.name;
                var edited = e.ov.name != null && e.ov.name !== p.name;
                var ref = ty.akce ? (p.ref30 || p.base) : p.base;
                var sale = ty.akce ? (p.action != null ? p.action : p.base) : (p.status === 'running' && p.action != null ? p.action : p.base);
                var k = e.pid + '|' + e.type;
                html += '<div class="m-qitem' + (ty.akce ? ' m-qitem-akce' : '') + '" data-key="' + k + '">'
                    + '<div class="m-row"><span class="m-no">' + (i + 1) + '</span>'
                    + '<select class="m-hdr" data-hdr="' + k + '">' + cfg.headers.map(function (h) { return '<option value="' + h.id + '"' + (h.id === e.header ? ' selected' : '') + '>' + esc(h.name) + '</option>'; }).join('') + '</select>'
                    + '<span class="m-flex"></span><span class="m-stepper"><button type="button" data-step="' + k + '|-1">−</button><b>' + e.qty + '</b><button type="button" data-step="' + k + '|1">+</button></span>'
                    + '<button type="button" class="m-x" data-del="' + k + '" aria-label="' + esc(t.remove) + '">×</button></div>'
                    + '<input class="m-name' + (edited ? ' m-name-edited' : '') + '" data-name="' + k + '" value="' + esc(name) + '">'
                    + '<small class="m-note' + (edited ? ' m-note-edited' : '') + '">' + esc(edited ? t.nameEdited : t.nameNote) + '</small>'
                    + '<div class="m-qprice"><span>' + (ty.akce || (p.status === 'running' && p.action != null) ? '<small>' + esc(ty.akce ? t.lowest30 : t.regular) + '</small> ' + kc(ref) + ' Kč' : '') + '</span>'
                    + '<b class="' + (ty.akce ? 'm-red' : '') + '">' + kc(sale) + ' <span>' + esc(e.ov.price_unit || p.unit) + '</span></b></div>'
                    + (p.unitText ? '<small class="m-unitprice">' + esc(p.unitText) + '</small>' : '')
                    + '<div class="m-row m-qtools"><select class="m-origin" data-origin="' + k + '"><option value="">' + esc(t.origin) + '</option>'
                    + cfg.countries.map(function (c) { var sel = (e.ov.origin_countries_id || p.originId) === c.id; return '<option value="' + c.id + '"' + (sel ? ' selected' : '') + '>' + esc(c.name) + '</option>'; }).join('') + '</select>'
                    + '<button type="button" class="m-toggle' + (e.ov.is_more_species ? ' m-on' : '') + '" data-species="' + k + '">' + (e.ov.is_more_species ? '✓ ' : '+ ') + esc(t.moreSpecies) + '</button>'
                    + '<button type="button" class="m-btn m-btn-outline" data-preview="' + k + '">👁</button></div>'
                    + '</div>';
            });
            html += '</div>';
        });
        html += '<div class="m-info">' + esc(t.sendInfo) + '</div>'
            + '<button type="button" class="m-btn m-btn-outline m-wide" data-preview="all">👁 ' + esc(t.previewAll) + '</button>'
            + '<div class="m-sendbar"><button type="button" class="m-big m-big-teal" id="m-send"' + (state.sending || !cfg.canSend ? ' disabled' : '') + '>'
            + esc(state.sending ? t.sending : fmt(t.sendCount, queueCount())) + '</button></div>';
        el.innerHTML = html;
    };

    var renderJobs = function () {
        var el = $('[data-screen="jobs"]');
        el.innerHTML = '<div class="m-card m-empty">…</div>';
        ajax(cfg.urls.lists).then(function (lists) {
            state.jobs = lists;
            if (!lists.length) {
                el.innerHTML = '<div class="m-card m-empty">' + esc(t.jobsEmpty) + '</div>';
                return;
            }
            el.innerHTML = lists.map(function (j) {
                return '<div class="m-card m-job"><div class="m-row"><b>' + esc(j.no) + '</b><small>' + esc(j.time) + ' · ' + j.count + ' ' + esc(t.labels.toLowerCase()) + '</small><span class="m-flex"></span>'
                    + '<span class="m-status m-status-' + (j.status === 'printed' ? 'running' : 'planned') + '">' + esc(j.status === 'printed' ? t.printed : t.waiting) + '</span></div>'
                    + '<small class="m-muted">' + esc(j.sizes) + '</small>'
                    + '<button type="button" class="m-link" data-again="' + j.id + '">' + esc(t.again) + '</button></div>';
            }).join('');
        }).catch(function () { el.innerHTML = '<div class="m-card m-empty">' + esc(t.error) + '</div>'; });
    };

    var render = function () {
        badges();
        ({ overview: renderOverview, scan: renderScan, queue: renderQueue, jobs: renderJobs })[state.screen]();
    };

    // ---------- the template sheet ----------
    var sheetDraft = null;
    var openSheet = function (pid) {
        var p = state.products[pid];
        if (!p) {
            return;
        }
        touchRecent(pid);
        sheetDraft = { pid: pid, counts: {} };
        types.forEach(function (ty) { var e = entry(pid, ty.id); sheetDraft.counts[ty.id] = e ? e.qty : 0; });
        if (!state.queue.some(function (e) { return e.pid === pid; })) {
            sheetDraft.counts[state.lastType] = 1;
        }
        renderSheet();
    };
    var renderSheet = function () {
        var el = $('#m-sheet');
        if (!sheetDraft) {
            el.hidden = true;
            return;
        }
        var p = state.products[sheetDraft.pid];
        var total = 0;
        var groups = [['std', t.groupStd], ['spec', t.groupSpec], ['akce', t.groupAkce]];
        var html = '<div class="m-sheet-panel"><div class="m-sheet-head"><div><b>' + esc(p.name) + '</b><small>' + esc(t.sheetHint) + '</small></div>'
            + '<button type="button" class="m-x" data-sheetclose>×</button></div><div class="m-sheet-body">';
        groups.forEach(function (g) {
            var list = types.filter(function (ty) { return ty.group === g[0]; });
            if (!list.length) {
                return;
            }
            html += '<div class="m-label">' + esc(g[1]) + '</div>';
            list.forEach(function (ty) {
                var c = sheetDraft.counts[ty.id] || 0;
                total += c;
                html += '<div class="m-size' + (c ? ' m-on' : '') + (ty.akce ? ' m-size-akce' : '') + '"><div><b>' + esc(ty.name) + '</b><small>' + esc(ty.dims) + ' mm'
                    + (ty.akce && p.action == null ? ' · ' + esc(t.akceNoPrice) : '') + '</small></div>'
                    + '<span class="m-stepper"><button type="button" data-sz="' + ty.id + '|-1">−</button><b>' + c + '</b><button type="button" data-sz="' + ty.id + '|1">+</button></span></div>';
            });
        });
        html += '</div><button type="button" class="m-big m-big-teal" data-sheetsave>' + esc(t.addToQueue) + ' · ' + total + '</button></div>';
        el.innerHTML = html;
        el.hidden = false;
    };
    var saveSheet = function () {
        var pid = sheetDraft.pid;
        Object.keys(sheetDraft.counts).forEach(function (type) {
            type = parseInt(type, 10);
            var c = sheetDraft.counts[type];
            if (c > 0) {
                state.lastType = type;
            }
            setCount(pid, type, c);
        });
        sheetDraft = null;
        renderSheet();
        render();
    };

    // ---------- scanning ----------
    var lastCode = '';
    var lastCodeAt = 0;
    var REPEAT_MS = 1500;
    var onCode = function (code) {
        code = String(code || '').trim();
        var now = Date.now();
        if (!code || (code === lastCode && now - lastCodeAt < REPEAT_MS)) {
            return;
        }
        lastCode = code;
        lastCodeAt = now;
        ajax(withParams(cfg.urls.byCode, { code: code })).then(function (p) {
            if (!p) {
                toast(fmt(t.notFound, code));
                return;
            }
            if (navigator.vibrate) {
                navigator.vibrate(40);
            }
            remember(p);
            touchRecent(p.id);
            if (state.quick) {
                var snap = snapshot();
                var e = entry(p.id, state.lastType);
                setCount(p.id, state.lastType, (e ? e.qty : 0) + 1);
                toast(fmt(t.added, p.name, 1, typeById[state.lastType].name), function () { restore(snap); });
                render();
            } else {
                state.results = null;
                render();
                openSheet(p.id);
            }
        }).catch(function () { toast(t.error); });
    };

    // A code counts only when it is read twice within 1.5 s: a blurry or low-res frame (a code on
    // a screen, small print) gives random codes that still pass the EAN check digit.
    var seenCode = '';
    var seenAt = 0;
    var onRead = function (code) {
        code = String(code || '').trim();
        var now = Date.now();
        if (!code) {
            return;
        }
        // templates are being chosen: a new read would reopen the sheet and drop the counts
        if (sheetDraft) {
            if (code === lastCode) {
                lastCodeAt = now;
            }
            return;
        }
        // the code just taken is still in view: count it once, again only after it was away
        if (code === lastCode && now - lastCodeAt < REPEAT_MS) {
            lastCodeAt = now;
            return;
        }
        if (code === seenCode && now - seenAt < 1500) {
            seenCode = '';
            onCode(code);
            return;
        }
        seenCode = code;
        seenAt = now;
    };
    var videoConstraints = { facingMode: 'environment', width: { ideal: 1920 }, height: { ideal: 1080 } };
    var autofocus = function (s) {
        var track = s && s.getVideoTracks ? s.getVideoTracks()[0] : null;
        var caps = track && track.getCapabilities ? track.getCapabilities() : {};
        if (caps.focusMode && caps.focusMode.indexOf('continuous') !== -1) {
            track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(function () {});
        }
    };

    // The detector shrinks the whole (portrait) frame, while the box shows only a strip of it: decode
    // just what is visible – the whole box and the green frame in turns – so the code keeps its pixels.
    var crop = document.createElement('canvas');
    var cropCtx = crop.getContext('2d', { willReadFrequently: true });
    var visiblePart = function (video, el) {
        var box = $('#m-cam').getBoundingClientRect();
        var r = el.getBoundingClientRect();
        var scale = Math.max(box.width / video.videoWidth, box.height / video.videoHeight);
        crop.width = Math.round(r.width / scale);
        crop.height = Math.round(r.height / scale);
        cropCtx.drawImage(video,
            (video.videoWidth - box.width / scale) / 2 + (r.left - box.left) / scale,
            (video.videoHeight - box.height / scale) / 2 + (r.top - box.top) / scale,
            crop.width, crop.height, 0, 0, crop.width, crop.height);
        return crop;
    };

    var stream = null;
    var timer = null;
    var zxing = null;
    var stopCamera = function () {
        clearInterval(timer);
        timer = null;
        if (zxing) {
            try { zxing.reset(); } catch (e) {}
            zxing = null;
        }
        if (stream) {
            stream.getTracks().forEach(function (tr) { tr.stop(); });
            stream = null;
        }
        $('#m-cam').classList.remove('m-cam-on');
        $('#m-cam-stop').hidden = true;
        $('#m-cam-msg').textContent = t.cameraStart;
    };
    var loadScript = function (src) {
        return new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = src;
            s.onload = resolve;
            s.onerror = reject;
            document.head.appendChild(s);
        });
    };
    var startCamera = function () {
        if (stream || zxing) {
            return;
        }
        var msg = $('#m-cam-msg');
        if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            msg.textContent = window.isSecureContext ? t.cameraOff : t.cameraHttps;
            return;
        }
        var video = $('#m-video');
        var started = function () {
            $('#m-cam').classList.add('m-cam-on');
            $('#m-cam-stop').hidden = false;
            msg.textContent = t.cameraAim;
        };
        if ('BarcodeDetector' in window) {
            navigator.mediaDevices.getUserMedia({ video: videoConstraints, audio: false }).then(function (s) {
                stream = s;
                autofocus(s);
                video.srcObject = s;
                return video.play();
            }).then(function () {
                var detector = new window.BarcodeDetector({ formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128'] });
                var busy = false;
                var turn = 0;
                started();
                timer = setInterval(function () {
                    if (busy || !video.videoWidth) {
                        return;
                    }
                    busy = true;
                    turn++;
                    detector.detect(visiblePart(video, turn % 2 ? $('#m-cam') : $('#m-cam .m-cam-frame'))).then(function (codes) {
                        busy = false;
                        onRead(codes.length ? codes[0].rawValue : '');
                    }).catch(function () { busy = false; });
                }, 150);
            }).catch(function () { stopCamera(); msg.textContent = t.cameraOff; });
            return;
        }
        // iPhone and others: ZXing
        var ready = window.ZXing ? Promise.resolve() : loadScript('https://unpkg.com/@zxing/library@0.20.0/umd/index.min.js');
        ready.then(function () {
            zxing = new window.ZXing.BrowserMultiFormatReader();
            return zxing.decodeFromConstraints({ video: videoConstraints }, video, function (result) {
                if (result) {
                    onRead(result.getText());
                }
            });
        }).then(started).catch(function () { stopCamera(); msg.textContent = t.cameraOff; });
    };

    // ---------- search ----------
    var searchTimer;
    var search = function (term) {
        term = term.trim();
        if (term.length < 2) {
            state.results = null;
            renderScan();
            return;
        }
        ajax(withParams(cfg.urls.search, { term: term })).then(function (list) {
            list.forEach(remember);
            state.results = list.map(function (p) { return p.id; });
            renderScan();
        }).catch(function () { toast(t.error); });
    };

    // ---------- preview (PDF of the queue or one item, nothing saved) ----------
    var preview = function (entries) {
        var form = document.createElement('form');
        form.method = 'post';
        form.action = cfg.urls.preview;
        form.target = '_blank';
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'items';
        input.value = JSON.stringify(entries.map(payload));
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
        form.remove();
    };
    var payload = function (e) {
        var ov = {};
        Object.keys(e.ov || {}).forEach(function (k) {
            if (e.ov[k] !== '' && e.ov[k] != null) {
                ov[k] = e.ov[k];
            }
        });
        var p = state.products[e.pid];
        if (p && ov.name === p.name) {
            delete ov.name;
        }
        return { products_id: e.pid, type_id: e.type, qty: e.qty, header: e.header, overrides: ov };
    };

    // ---------- events ----------
    document.addEventListener('click', function (ev) {
        var b = ev.target.closest('button, a, [data-go], #m-cam');
        if (!b) {
            if (ev.target.id === 'm-drawer') {
                $('#m-drawer').hidden = true;
            }
            if (ev.target.id === 'm-sheet') {
                sheetDraft = null;
                renderSheet();
            }
            return;
        }
        var d = b.dataset || {};
        if (b.id === 'm-cam' || ev.target.closest('#m-cam') && !ev.target.closest('#m-cam-stop')) {
            startCamera();
            return;
        }
        if (b.id === 'm-cam-stop') {
            ev.stopPropagation();
            stopCamera();
            return;
        }
        if (d.drawer !== undefined) {
            $('#m-drawer').hidden = false;
            return;
        }
        if (d.go) {
            go(d.go);
            if (d.focusSearch !== undefined) {
                setTimeout(function () { $('#m-search').focus(); }, 50);
            }
            return;
        }
        if (d.sheet) {
            openSheet(parseInt(d.sheet, 10));
            return;
        }
        if (d.sheetclose !== undefined) {
            sheetDraft = null;
            renderSheet();
            return;
        }
        if (d.sz) {
            var sz = d.sz.split('|');
            sheetDraft.counts[sz[0]] = Math.max(0, (sheetDraft.counts[sz[0]] || 0) + parseInt(sz[1], 10));
            renderSheet();
            return;
        }
        if (d.sheetsave !== undefined) {
            saveSheet();
            return;
        }
        if (d.priceopen) {
            state.priceOpen = state.priceOpen === parseInt(d.priceopen, 10) ? null : parseInt(d.priceopen, 10);
            renderScan();
            return;
        }
        if (d.priceclose !== undefined) {
            state.priceOpen = null;
            renderScan();
            return;
        }
        if (d.endakce) {
            var f = b.closest('form');
            f.akce.value = '';
            f.from.value = '';
            f.to.value = '';
            f.requestSubmit ? f.requestSubmit() : f.dispatchEvent(new Event('submit', { cancelable: true }));
            return;
        }
        var key = d.step || d.del || d.species || d.preview;
        if (key) {
            var parts = key.split('|');
            if (d.preview === 'all') {
                preview(state.queue);
                return;
            }
            var e = entry(parseInt(parts[0], 10), parseInt(parts[1], 10));
            if (!e) {
                return;
            }
            if (d.step) {
                setCount(e.pid, e.type, e.qty + parseInt(parts[2], 10));
            } else if (d.del) {
                var snap = snapshot();
                setCount(e.pid, e.type, 0);
                toast(t.remove + ': ' + (state.products[e.pid] || {}).name, function () { restore(snap); });
            } else if (d.species) {
                e.ov.is_more_species = e.ov.is_more_species ? 0 : 1;
                store.save();
            } else if (d.preview) {
                preview([Object.assign({}, e, { qty: 1 })]);
                return;
            }
            renderQueue();
            return;
        }
        if (d.again) {
            var job = (state.jobs || []).find(function (j) { return String(j.id) === d.again; });
            if (!job) {
                return;
            }
            job.items.forEach(function (it) {
                var ex = entry(it.products_id, it.type_id);
                if (ex) {
                    ex.qty = Math.min(999, ex.qty + it.qty);
                } else {
                    state.queue.push({ pid: it.products_id, type: it.type_id, qty: it.qty, header: it.header, ov: it.overrides || {} });
                }
            });
            store.save();
            loadProducts(job.items.map(function (it) { return it.products_id; })).then(function () { go('queue'); });
            toast(fmt(t.againDone, job.no));
            return;
        }
        if (b.id === 'm-send') {
            if (!state.queue.length || state.sending) {
                return;
            }
            state.sending = true;
            renderQueue();
            var count = queueCount();
            ajax(cfg.urls.send, { method: 'POST', json: { device: device(), items: state.queue.map(payload) } }).then(function (r) {
                state.sending = false;
                if (!r.ok) {
                    toast(r.error || t.error);
                    renderQueue();
                    return;
                }
                state.queue = [];
                store.save();
                toast(fmt(t.sentOk, count, r.no));
                go('jobs');
            }).catch(function () {
                state.sending = false;
                toast(t.error);
                renderQueue();
            });
        }
    });

    document.addEventListener('change', function (ev) {
        var d = ev.target.dataset || {};
        var key = d.hdr || d.origin;
        if (ev.target.id === 'm-quick') {
            state.quick = ev.target.checked;
            store.save();
            return;
        }
        if (key) {
            var parts = key.split('|');
            var e = entry(parseInt(parts[0], 10), parseInt(parts[1], 10));
            if (e && d.hdr) {
                e.header = parseInt(ev.target.value, 10);
            }
            if (e && d.origin) {
                e.ov.origin_countries_id = ev.target.value ? parseInt(ev.target.value, 10) : null;
            }
            store.save();
        }
    });

    document.addEventListener('input', function (ev) {
        if (ev.target.id === 'm-search') {
            clearTimeout(searchTimer);
            var term = ev.target.value;
            searchTimer = setTimeout(function () { search(term); }, 300);
            return;
        }
        var key = ev.target.dataset && ev.target.dataset.name;
        if (key) {
            var parts = key.split('|');
            var e = entry(parseInt(parts[0], 10), parseInt(parts[1], 10));
            if (e) {
                e.ov.name = ev.target.value;
                store.save();
            }
        }
    });

    document.addEventListener('keydown', function (ev) {
        // a code typed or sent by a Bluetooth scanner + Enter
        if (ev.target.id === 'm-search' && ev.key === 'Enter') {
            ev.preventDefault();
            var v = ev.target.value.trim();
            if (/^\d{6,}$/.test(v)) {
                ev.target.value = '';
                onCode(v);
            } else {
                search(v);
            }
        }
    });

    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!form.dataset || !form.dataset.price) {
            return;
        }
        ev.preventDefault();
        var pid = parseInt(form.dataset.price, 10);
        var dmy = function (iso) { var m = String(iso || '').match(/^(\d{4})-(\d{2})-(\d{2})$/); return m ? m[3] + '.' + m[2] + '.' + m[1] : ''; };
        var num = function (v) { return String(v || '').replace(',', '.').trim(); };
        ajax(withParams(cfg.urls.savePrice, {
            productsId: pid, price: num(form.base.value), price_action: num(form.akce.value),
            action_from: dmy(form.from.value), action_to: dmy(form.to.value), price_unit: form.unit.value
        })).then(function (r) {
            if (r.ok) {
                remember(r.product);
                state.priceOpen = null;
                toast(fmt(t.priceSaved, r.product.name));
                render();
            } else {
                toast(t.noRight);
            }
        }).catch(function () { toast(t.error); });
    });

    // ---------- start ----------
    badges();
    loadProducts(state.recent.concat(state.queue.map(function (e) { return e.pid; }))).then(function () { go(state.screen); }).catch(function () { go(state.screen); });
})();
