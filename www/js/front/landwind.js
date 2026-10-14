/* Front "Landwind" motion.
   - blocks come in when scrolled into view: text from the left, images from the right, cards pop up
     one after another, check-list rows slide in, numbers (data-count) count up
   - cards tilt towards the mouse and get a light sweep (CSS), the hero comes in on load
   Runs also with "reduce motion" (Windows "Show animations" off): then the moves are short and small
   (CSS), tilt and floating are off. Without IntersectionObserver everything is just shown. */
(function () {
    'use strict';
    var root = document.documentElement;
    if (!('IntersectionObserver' in window)) {
        return;
    }
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    root.classList.add('lw-motion');
    if (reduce) {
        root.classList.add('lw-calm');
    }

    // [selector, effect]; inside a reversed feature row left/right swap
    var GROUPS = [
        ['.lw-heading, .lw-intro p, .lw-article, .lw-form-card, .lw-map-card, .lw-footer-cta, .lw-contact-cta, .lw-footer-grid > div, .lw-stats > div:first-child, .lw-page-head .lw-container > div:first-child', 'up'],
        ['.lw-feature-text > h2, .lw-feature-text > .lw-lead, .lw-feature-text > .lw-btn, .lw-split > h2', 'left'],
        ['.lw-shots .lw-shot, .lw-page-head-img, .lw-split-text', 'right'],
        ['.lw-price-card, .lw-feat, .lw-job, .lw-tile, .lw-card, .lw-stat, .lw-gallery .lw-shot', 'pop'],
        ['.lw-checklist li, .lw-ticks li', 'slide']
    ];
    var STEP = reduce ? 90 : 110;

    var count = function (el) {
        var end = parseInt(el.getAttribute('data-count'), 10);
        if (!end) {
            return;
        }
        var start = null;
        var step = function (ts) {
            start = start === null ? ts : start;
            var p = Math.min(1, (ts - start) / 1100);
            el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3)));
            if (p < 1) {
                requestAnimationFrame(step);
            }
        };
        el.textContent = '0';
        requestAnimationFrame(step);
    };

    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) {
                return;
            }
            var el = entry.target;
            io.unobserve(el);
            el.classList.add('lw-in');
            [].forEach.call(el.querySelectorAll('[data-count]'), count);
            // after coming in: hover effects must not wait for the entrance delay
            var delay = parseInt(el.style.getPropertyValue('--lw-delay'), 10) || 0;
            setTimeout(function () {
                el.classList.add('lw-done');
            }, delay + 1000);
        });
    }, { rootMargin: '0px 0px -6% 0px', threshold: 0.06 });

    var prepare = function () {
        var marked = new Set();
        GROUPS.forEach(function (group) {
            [].forEach.call(document.querySelectorAll(group[0]), function (el) {
                if (marked.has(el) || el.closest('.lw-hero')) {
                    return;
                }
                marked.add(el);
                var effect = group[1];
                if (el.closest('.lw-feature-reverse')) {
                    effect = effect === 'left' ? 'right' : effect === 'right' ? 'left' : effect;
                }
                // siblings come in one after another
                var index = 0;
                for (var s = el.previousElementSibling; s && index < 8; s = s.previousElementSibling) {
                    if (marked.has(s)) {
                        index++;
                    }
                }
                // rows of a list inside a card start after the card
                var card = el.parentElement && el.parentElement.closest('.lw-job, .lw-feature-text');
                var base = card && effect === 'slide' ? 250 : 0;
                el.style.setProperty('--lw-delay', (base + index * STEP) + 'ms');
                el.classList.add('lw-reveal', 'lw-fx-' + effect);
                io.observe(el);
            });
        });
        requestAnimationFrame(function () {
            root.classList.add('lw-loaded');
        });
    };

    // cards lean towards the mouse
    var tilt = function () {
        if (reduce || !window.matchMedia('(hover: hover)').matches) {
            return;
        }
        // the angle eases towards the mouse every frame (no jump per mousemove), back to flat on leave
        var SEL = '.lw-price-card, .lw-feat, .lw-job, .lw-card, .lw-tile';
        var active = null;
        var target = { x: 0, y: 0 };
        var cur = { x: 0, y: 0 };
        var running = false;
        var frame = function () {
            cur.x += (target.x - cur.x) * 0.12;
            cur.y += (target.y - cur.y) * 0.12;
            if (active) {
                active.style.setProperty('--lw-ry', (cur.x * 7).toFixed(3) + 'deg');
                active.style.setProperty('--lw-rx', (-cur.y * 7).toFixed(3) + 'deg');
            }
            if (Math.abs(target.x - cur.x) > 0.001 || Math.abs(target.y - cur.y) > 0.001) {
                requestAnimationFrame(frame);
            } else {
                running = false;
                if (active && !target.x && !target.y) {
                    active.style.removeProperty('--lw-ry');
                    active.style.removeProperty('--lw-rx');
                    active = null;
                }
            }
        };
        var kick = function () {
            if (!running) {
                running = true;
                requestAnimationFrame(frame);
            }
        };
        document.addEventListener('mousemove', function (e) {
            var card = e.target.closest && e.target.closest(SEL);
            if (!card || !card.classList.contains('lw-done')) {
                return;
            }
            if (active && active !== card) {
                active.style.removeProperty('--lw-ry');
                active.style.removeProperty('--lw-rx');
                cur.x = cur.y = 0;
            }
            active = card;
            var r = card.getBoundingClientRect();
            target.x = (e.clientX - r.left) / r.width - 0.5;
            target.y = (e.clientY - r.top) / r.height - 0.5;
            kick();
        }, { passive: true });
        document.addEventListener('mouseout', function (e) {
            var card = e.target.closest && e.target.closest(SEL);
            if (card && card === active && !card.contains(e.relatedTarget)) {
                target.x = target.y = 0;
                kick();
            }
        }, { passive: true });
    };

    var init = function () {
        prepare();
        tilt();
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
