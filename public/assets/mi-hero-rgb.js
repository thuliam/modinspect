/* MODINSPECT-HERO-RGB-001 / 002: RGB lighting engine + HUD controller for the home hero.
   The picture never moves: a neutral base, lime lighting overlays (one per lit group) and a dimmer layer.
   ONE selected-mode state drives everything. For each mode a "spec" says what every target does:
     - lit overlays        : filter hue-rotate() + brightness()   (Web Animations, compositor friendly)
     - dimmer + wheel veil : opacity
     - wheel hand          : rotate(), follows the hue of the reference overlay
   A mode change tweens from the colour a target shows right now to the new mode's first state, then starts the loop,
   so colours never jump. Reduced motion: no loops, every mode is shown as a still state; the HUD stays operable.
   Layers load only when the page is idle, so the hero picture (LCP) is never delayed. Any failure leaves the approved picture. */
(function () {
    'use strict';
    var root = document.querySelector('[data-mi-rgb]'), hud = document.querySelector('[data-mi-hud]');
    if (!root || !hud) { return; }
    if (!root.animate || !window.matchMedia) { hud.style.display = 'none'; return; }

    var mq = matchMedia('(prefers-reduced-motion: reduce)');
    var KEY = 'miRgbMode', LIME = 68;                     // brand lime sits at hue 68; hue-rotate(0) is lime
    var MODES = ['static', 'breathing', 'strobing', 'rainbow', 'cycle', 'starry', 'smart', 'dark'];
    var NAME = { static: 'Static', breathing: 'Breathing', strobing: 'Strobing', rainbow: 'Rainbow', cycle: 'Color Cycle', starry: 'Starry Night', smart: 'Smart', dark: 'Dark' };
    var REF = 'fan-1';
    var RAINBOW_MS = 12000, CYCLE_MS = 12000, BREATH_MS = 3600, STROBE_MS = 1700, SMART_MS = 26000;
    // Rainbow Flow: hue phase per lit group (fraction of a cycle); the three case fans are staggered
    var PHASE = { 'fan-1': 0, 'fan-2': 0.07, 'fan-3': 0.14, 'floor': 0.1, 'scene-r': 0.12, 'scene-c': 0.24, 'scene-l': 0.36 };
    // Smart: a predefined palette (absolute hues) walked slowly; groups sit a few degrees apart so the colours stay coordinated
    var SMART = [68, 176, 266, 326, 28];
    var SMART_OFF = { 'fan-1': 0, 'fan-2': 16, 'fan-3': 32, 'floor': 8, 'scene-r': 6, 'scene-c': 12, 'scene-l': 20 };
    var STAR_MS = { 'fan-1': 5200, 'fan-2': 7300, 'fan-3': 6100, 'floor': 8300, 'scene-r': 4700, 'scene-c': 6700, 'scene-l': 5600 };
    var STAR_HUE = 190;                                    // cool blue-violet night

    var imgs = [].slice.call(root.querySelectorAll('[data-src]'));
    var ctl = [].slice.call(hud.querySelectorAll('[data-rgb-mode]'));
    var statusEl = hud.querySelector('[data-rgb-status]');
    var targets = [], byKey = {}, mode = null, ready = false, visible = true;

    function stored() { return 'rainbow'; }                  // every visit starts on Rainbow; a pick lasts until the page is left
    function store() { }
    function ident(h, b) { return Math.abs(((h % 360) + 360) % 360) < 0.01 && Math.abs(b - 1) < 0.001; }

    // ---- targets -----------------------------------------------------------------------------------------------
    function filterT(el, key) {
        return { el: el, key: key, kind: 'filter', a: null,
            mk: function (h, b) { return { filter: 'hue-rotate(' + h.toFixed(2) + 'deg) brightness(' + b.toFixed(3) + ')' }; },
            hold: function (h, b) { el.style.filter = ident(h, b) ? 'none' : this.mk(h, b).filter; } };
    }
    function opacityT(el) {
        return { el: el, kind: 'opacity', a: null,
            mk: function (o) { return { opacity: o.toFixed(3) }; },
            hold: function (o) { el.style.opacity = o.toFixed(3); } };
    }
    function handT(el) {
        return { el: el, kind: 'hand', a: null,
            mk: function (h) { return { transform: 'rotate(' + (LIME + h).toFixed(2) + 'deg)' }; },
            hold: function (h) { el.style.transform = this.mk(h).transform; } };
    }

    function build() {
        [].forEach.call(root.querySelectorAll('[data-layer]'), function (el) {
            var t = filterT(el, el.getAttribute('data-layer')); targets.push(t); byKey[t.key] = t;
        });
        var d = root.querySelector('.mi-rgb-dim'); if (d) { targets.push(opacityT(d)); }
        var v = hud.querySelector('[data-rgb-veil]'); if (v) { targets.push(opacityT(v)); }
        var h = hud.querySelector('[data-rgb-hand]'); if (h) { targets.push(handT(h)); }
        var c = hud.querySelector('[data-rgb-core]'); if (c) { targets.push(filterT(c, 'core')); }
    }

    // ---- what each mode asks of each target ---------------------------------------------------------------------
    // returns { start: {h,b} | {o}, frames: [...] | null, dur, easing }
    function spec(m, t) {
        var k = (t.key === 'core' || t.kind === 'hand') ? REF : t.key;
        if (t.kind === 'opacity') {
            if (m === 'breathing') { return { start: { o: 0 }, frames: [{ o: 0 }, { o: 0.56 }, { o: 0 }], dur: BREATH_MS, easing: 'ease-in-out' }; }
            if (m === 'strobing') { return { start: { o: 0 }, frames: [{ o: 0, offset: 0 }, { o: 0.5, offset: 0.1 }, { o: 0.12, offset: 0.46 }, { o: 0, offset: 1 }], dur: STROBE_MS, easing: 'ease-in-out' }; }
            if (m === 'dark') { return { start: { o: 0.8 }, frames: null }; }
            return { start: { o: 0 }, frames: null };
        }
        if (m === 'rainbow' || m === 'cycle') {
            var h0 = (m === 'rainbow' ? (PHASE[k] || 0) : 0) * 360;
            return { start: { h: h0, b: 1 }, frames: [{ h: h0, b: 1 }, { h: h0 + 360, b: 1 }], dur: m === 'rainbow' ? RAINBOW_MS : CYCLE_MS, easing: 'linear' };
        }
        if (m === 'starry') {
            var i = Object.keys(STAR_MS).indexOf(k), lo = 0.55 + (i % 3) * 0.06;
            return { start: { h: STAR_HUE, b: 1 }, frames: [{ h: STAR_HUE, b: 1 }, { h: STAR_HUE, b: lo }, { h: STAR_HUE, b: 1.14 }, { h: STAR_HUE, b: lo + 0.12 }, { h: STAR_HUE, b: 1 }], dur: STAR_MS[k] || 6000, easing: 'ease-in-out' };
        }
        if (m === 'smart') {
            var off = SMART_OFF[k] || 0, cur = SMART[0] + off - LIME, first = cur, fr = [];
            for (var j = 0; j < SMART.length; j++) {
                var nxt = SMART[(j + 1) % SMART.length] + off - LIME, dd = ((nxt - cur) % 360 + 540) % 360 - 180;   // shortest way round
                fr.push({ h: cur, b: 1 }); cur += dd; fr.push({ h: cur, b: 1 });                                    // hold, then move
            }
            return { start: { h: first, b: 1 }, frames: fr, dur: SMART_MS, easing: 'ease-in-out' };
        }
        return { start: { h: 0, b: 1 }, frames: null };       // static, breathing, strobing, dark: brand lime
    }

    // ---- reading what a target shows right now ------------------------------------------------------------------
    function read(t) {
        var cs = getComputedStyle(t.el);
        if (t.kind === 'opacity') { var o = parseFloat(cs.opacity); return { o: isNaN(o) ? 0 : o }; }
        if (t.kind === 'hand') { return null; }                  // the hand takes the reference overlay's hue
        var f = cs.filter || '', hm = /hue-rotate\((-?[\d.]+)(deg|rad|turn)?\)/.exec(f), bm = /brightness\((-?[\d.]+)(%)?\)/.exec(f);
        var h = hm ? parseFloat(hm[1]) : 0; if (hm && hm[2] === 'rad') { h = h * 180 / Math.PI; } else if (hm && hm[2] === 'turn') { h = h * 360; }
        return { h: h, b: bm ? parseFloat(bm[1]) / (bm[2] ? 100 : 1) : 1 };
    }

    function stop(t) { if (t.a) { try { t.a.cancel(); } catch (e) { /* gone */ } t.a = null; } }

    // move one target to the spec's first state, tweening from `from` over T ms, then run the loop
    function drive(t, from, sp, T, still) {
        stop(t);
        var isOp = t.kind === 'opacity', shift = 0, s0 = sp.start;
        if (!isOp) { shift = 360 * Math.round((from.h - s0.h) / 360); s0 = { h: s0.h + shift, b: s0.b }; }   // take the short way round the colour wheel
        var arg = function (s) { return isOp ? [s.o] : [s.h, s.b]; };
        if (still || !T) { t.hold.apply(t, arg(s0)); return; }
        var a0 = t.el.animate([t.mk.apply(t, arg(from)), t.mk.apply(t, arg(s0))], { duration: T, easing: 'ease-in-out', fill: 'forwards' });
        t.a = a0;
        a0.onfinish = function () {
            if (t.a !== a0) { return; }
            t.hold.apply(t, arg(s0)); a0.cancel(); t.a = null;       // keep the state as an inline style, then release the tween
            if (!sp.frames) { return; }
            var kf = sp.frames.map(function (f) {
                var o = isOp ? t.mk(f.o) : t.mk(f.h + shift, f.b);
                if (f.offset !== undefined) { o.offset = f.offset; }
                return o;
            });
            t.a = t.el.animate(kf, { duration: sp.dur, iterations: Infinity, easing: sp.easing || 'linear' });
            if (!visible || document.hidden) { t.a.pause(); }
        };
    }

    function setMode(m, force) {
        if (!ready || (m === mode && !force)) { return; }
        var first = mode === null;
        mode = m; store(m);
        root.setAttribute('data-mode', m); hud.setAttribute('data-mode', m);
        ctl.forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-rgb-mode') === m ? 'true' : 'false'); });
        if (statusEl) { statusEl.textContent = NAME[m]; }
        var still = mq.matches, T = still ? 0 : (first ? 2400 : 1200);
        var refFrom = read(byKey[REF]) || { h: 0, b: 1 };
        targets.forEach(function (t) { drive(t, t.kind === 'hand' ? refFrom : read(t), spec(m, t), T, still); });
    }

    function sync() {
        var on = visible && !document.hidden;
        targets.forEach(function (t) { if (t.a) { try { if (on) { t.a.play(); } else { t.a.pause(); } } catch (e) { /* gone */ } } });
    }

    // ---- start-up -----------------------------------------------------------------------------------------------
    function start() {
        if (ready) { return; }
        Promise.all(imgs.map(function (el) {
            return new Promise(function (res, rej) {
                el.onload = function () { (el.decode ? el.decode() : Promise.resolve()).then(res, res); };
                el.onerror = rej;
                el.src = el.getAttribute('data-src');
            });
        })).then(function () {
            build(); ready = true;
            root.classList.add('is-rgb'); hud.classList.add('is-ready');
            setMode(stored());
        }).catch(function () { root.classList.remove('is-rgb'); hud.style.display = 'none'; });   // the approved picture stays
    }

    ctl.forEach(function (b) { b.addEventListener('click', function () { setMode(b.getAttribute('data-rgb-mode')); }); });
    hud.addEventListener('keydown', function (e) {
        var i = ctl.indexOf(document.activeElement), n = ctl.length;
        if (i < 0) { return; }
        var j = e.key === 'ArrowRight' ? (i + 1) % n : e.key === 'ArrowLeft' ? (i + n - 1) % n : -1;
        if (j < 0) { return; }
        e.preventDefault(); ctl[j].focus();
    });
    if ('IntersectionObserver' in window) {
        new IntersectionObserver(function (es) { visible = es[es.length - 1].isIntersecting; sync(); }, { threshold: 0.05 }).observe(root);
    }
    document.addEventListener('visibilitychange', sync);
    var onMq = function () { if (ready) { setMode(mode, true); } };
    if (mq.addEventListener) { mq.addEventListener('change', onMq); } else if (mq.addListener) { mq.addListener(onMq); }

    if (document.readyState === 'complete') { setTimeout(start, 0); } else { window.addEventListener('load', function () { setTimeout(start, 0); }); }
})();
