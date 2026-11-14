/* Public site behaviour. No network calls.
   One small motion engine:
   - reveals: blocks fade up, headings slide in line by line (CSS does the drawing, this only flips a class)
   - scroll-linked values (card entrance and lean, hero drift, cloud dolly) are damped: each value chases its target
     with an exponential ease every frame, so the page glides instead of snapping to the scroll position
   - the loop sleeps when nothing is moving
   Motion modes (set early in the page head): "full", or "calm" when the OS asks for reduced motion.
   Calm keeps reveals, count-up and the cloud, and fades cards in as they arrive, but nothing tilts or tracks the scroll. */
(function () {
    var root = document.documentElement;
    var full = function () { return root.classList.contains('motion-full'); };
    var canHover = matchMedia('(hover: hover)').matches;
    var clamp = function (v, a, b) { return v < a ? a : v > b ? b : v; };

    /* ---------- motion toggle ---------- */
    var toggle = document.getElementById('motion-toggle');
    function paintToggle() {
        if (!toggle) { return; }
        toggle.textContent = full() ? 'เอฟเฟกต์: เต็ม' : 'เอฟเฟกต์: ลดลง';
        toggle.setAttribute('aria-pressed', full() ? 'true' : 'false');
        toggle.title = full() ? 'กดเพื่อลดการเคลื่อนไหว' : 'กดเพื่อเปิดเอฟเฟกต์เต็ม';
    }
    if (toggle) {
        toggle.addEventListener('click', function () {
            var next = full() ? 'calm' : 'full';
            root.classList.remove('motion-full', 'motion-calm');
            root.classList.add('motion-' + next);
            try { localStorage.setItem('mi-motion', next); } catch (e) { /* storage may be blocked */ }
            paintToggle();
            kick();
        });
        paintToggle();
    }

    /* ---------- header + menu ---------- */
    var header = document.querySelector('.site-header');
    var burger = document.getElementById('burger');
    var menu = document.getElementById('site-menu');
    if (burger && menu) {
        var closeMenu = function () { menu.classList.remove('open'); burger.setAttribute('aria-expanded', 'false'); };
        burger.addEventListener('click', function () {
            var open = menu.classList.toggle('open');
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        menu.addEventListener('click', function (e) { if (e.target.tagName === 'A') { closeMenu(); } });
        addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeMenu(); } });
    }

    /* ---------- reveals ---------- */
    // inner pages: opt their main blocks into the same reveal without touching every view
    if (!document.body.classList.contains('is-home')) {
        var groups = ['.mi-page-heading', '.mi-product-hero', '.mi-filter-card', '.mi-card-grid > *', '.mi-compact-list li',
            '.mi-product-summary-card', '.mi-price-answer-card', '.mi-method-card', '.mi-history-card', '.mi-confidence-card', '.mi-deal-cta', '.mi-empty-state', 'main fieldset'];
        var late = [];
        groups.forEach(function (sel) {
            [].slice.call(document.querySelectorAll('main ' + sel + ', ' + sel)).forEach(function (el, i) {
                if (el.classList.contains('reveal') || el.closest('.reveal')) { return; }
                el.style.transition = 'none';                 // hide instantly, then let the entrance play
                el.classList.add('reveal');
                el.style.setProperty('--d', Math.min(i, 8) * 70 + 'ms');
                late.push(el);
            });
        });
        if (late.length) {
            void document.body.offsetHeight;
            requestAnimationFrame(function () { late.forEach(function (el) { el.style.transition = ''; }); });
        }
    }
    var reveals = [].slice.call(document.querySelectorAll('.reveal, .split'));
    if ('IntersectionObserver' in window) {
        var rio = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); rio.unobserve(en.target); } });
        }, { threshold: 0.05, rootMargin: '0px 0px -6% 0px' });
        reveals.forEach(function (el) { rio.observe(el); });
    } else {
        reveals.forEach(function (el) { el.classList.add('in'); });
    }

    /* ---------- count-up (a changing number is not travel, so calm mode keeps it) ---------- */
    var counters = [].slice.call(document.querySelectorAll('[data-count]'));
    function runCount(el) {
        var end = parseInt(el.getAttribute('data-count'), 10) || 0, t0 = null, dur = 1600;
        if (end === 0) { el.textContent = '0'; return; }
        function tick(t) {
            if (t0 === null) { t0 = t; }
            var p = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - p, 4);
            el.textContent = String(Math.round(end * e));
            if (p < 1) { requestAnimationFrame(tick); }
        }
        el.textContent = '0';
        requestAnimationFrame(tick);
    }
    if ('IntersectionObserver' in window) {
        var cio = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { if (en.isIntersecting) { runCount(en.target); cio.unobserve(en.target); } });
        }, { threshold: 0.6 });
        counters.forEach(function (el) { cio.observe(el); });
    }

    /* ---------- motion engine ---------- */
    var heroCopy = document.querySelector('[data-hero]');
    var vh = innerHeight, S = scrollY;            // S = damped scroll position, read by the hero cloud too
    var cells = [].slice.call(document.querySelectorAll('.vs-cell')).map(function (el, i) {
        return { el: el, card: el.firstElementChild, i: i, top: 0, h: 0, y: 0, rx: 0, rz: 0, s: 1, o: 0, hov: 0, px: 0, py: 0, thov: 0, tpx: 0, tpy: 0, first: true };
    });
    var running = false, last = 0, hoverCell = null;

    function measure() {
        vh = innerHeight;
        cells.forEach(function (c) { var r = c.el.getBoundingClientRect(); c.top = r.top + scrollY; c.h = r.height; });
    }

    function loop(t) {
        var dt = Math.min(64, t - last); last = t;
        var sy = scrollY, move = full(), busy = false;
        var ks = 1 - Math.exp(-dt / 90), kc = 1 - Math.exp(-dt / 130), kh = 1 - Math.exp(-dt / 160);

        S += (sy - S) * ks;
        if (Math.abs(sy - S) > 0.2) { busy = true; } else { S = sy; }

        // hero words drift up and fade as you leave the first screen
        if (heroCopy) {
            if (move) {
                var f = clamp(S / (vh * 0.75), 0, 1);
                heroCopy.style.transform = 'translate3d(0,' + (S * 0.16).toFixed(1) + 'px,0)';
                heroCopy.style.opacity = (1 - f * f).toFixed(3);
            } else { heroCopy.style.transform = ''; heroCopy.style.opacity = ''; }
        }

        // service cards: glide in from below, flatten, then lean away as they leave
        for (var n = 0; n < cells.length; n++) {
            var c = cells[n], dir = c.i % 2 ? 1 : -1;
            var rel = c.top - sy;                                     // distance of the cell top from the viewport top
            var enter = clamp((vh * 1.0 - rel) / (vh * 0.62), 0, 1);
            var e = 1 - Math.pow(1 - enter, 3);
            var p = clamp((rel + c.h / 2 - vh / 2) / vh, -1, 1.2);     // 0 at screen centre
            var ty, rx, rz, sc, op;
            if (move && innerWidth > 900) {
                ty = (1 - e) * 120 + p * (c.i % 2 ? -40 : 30);
                rx = (1 - e) * 26 + p * -8;
                rz = (1 - e) * dir * 6 + p * dir * 3.4;
                sc = 0.9 + 0.1 * e;
                op = clamp(e * 1.7, 0, 1);
            } else { ty = 0; rx = 0; rz = 0; sc = 1; op = clamp(e * 1.8, 0, 1); }
            if (c.first) { c.y = ty; c.rx = rx; c.rz = rz; c.s = sc; c.o = op; c.first = false; }
            c.y += (ty - c.y) * kc; c.rx += (rx - c.rx) * kc; c.rz += (rz - c.rz) * kc; c.s += (sc - c.s) * kc; c.o += (op - c.o) * kc;
            var hv = (move && c === hoverCell) ? 1 : 0;
            c.hov += (hv - c.hov) * kh; c.px += ((hv ? c.tpx : 0) - c.px) * kh; c.py += ((hv ? c.tpy : 0) - c.py) * kh;
            if (Math.abs(ty - c.y) + Math.abs(rx - c.rx) + Math.abs(rz - c.rz) + Math.abs(op - c.o) + Math.abs(hv - c.hov) > 0.01 || Math.abs(c.px) + Math.abs(c.py) > 0.01 && hv) { busy = true; }
            c.card.style.opacity = c.o.toFixed(3);
            c.card.style.transform = move
                ? 'perspective(1200px) translate3d(0,' + (c.y - c.hov * 10).toFixed(2) + 'px,0) rotateX(' + (c.rx + c.py * -7).toFixed(2) + 'deg) rotateY(' + (c.px * 8).toFixed(2) + 'deg) rotateZ(' + c.rz.toFixed(2) + 'deg) scale(' + (c.s + c.hov * 0.015).toFixed(4) + ')'
                : '';
        }

        if (busy) { requestAnimationFrame(loop); } else { running = false; }
    }
    function kick() { if (!running) { running = true; last = performance.now(); requestAnimationFrame(loop); } }

    addEventListener('scroll', function () {
        if (header) { header.classList.toggle('sc', scrollY > 40); }
        kick();
    }, { passive: true });
    addEventListener('resize', function () { measure(); kick(); });
    addEventListener('load', function () { measure(); kick(); });
    if (document.fonts && document.fonts.ready) { document.fonts.ready.then(function () { measure(); kick(); }); }
    if (header) { header.classList.toggle('sc', scrollY > 40); }
    measure(); kick();

    if (canHover) {
        cells.forEach(function (c) {
            c.card.addEventListener('pointerenter', function () { hoverCell = c; kick(); });
            c.card.addEventListener('pointermove', function (e) {
                var r = c.card.getBoundingClientRect();
                c.tpx = clamp((e.clientX - r.left) / r.width - 0.5, -0.5, 0.5);
                c.tpy = clamp((e.clientY - r.top) / r.height - 0.5, -0.5, 0.5);
                hoverCell = c; kick();
            });
            c.card.addEventListener('pointerleave', function () { if (hoverCell === c) { hoverCell = null; } kick(); });
        });
    }

    /* ---------- hero point cloud ---------- */
    var cv = document.getElementById('cloud');
    if (!cv || !cv.getContext) { return; }
    var ctx = cv.getContext('2d');
    var W = 0, H = 0, dpr = 1, pts = [], mx = 0, my = 0, tmx = 0, tmy = 0, crun = false, visible = true, born = 0;

    function rnd(a, b) { return a + Math.random() * (b - a); }
    function gauss() { return (Math.random() + Math.random() + Math.random() + Math.random() - 2) / 2; }
    function add(x, y, z, s, a) { pts.push({ x: x, y: y, z: z, s: s, a: a, t: Math.random() * 6.28 }); }

    /* world units: x right, y down, z away from the camera. The camera sits at z = -420. */
    function build() {
        dpr = Math.min(window.devicePixelRatio || 1, 1.5);
        W = cv.clientWidth; H = cv.clientHeight;
        cv.width = Math.round(W * dpr); cv.height = Math.round(H * dpr);
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        pts = [];
        var k = Math.min(1.3, (W * H) / 1000000) * (W < 700 ? 0.55 : 1), i, j, n;
        // clusters of listings spread through the depth of the scene
        for (i = 0; i < 26; i++) {
            var cx = rnd(-1100, 1100), cy = rnd(-300, 300), cz = rnd(-120, 1000), sp = rnd(30, 100);
            n = Math.round(rnd(70, 190) * k);
            for (j = 0; j < n; j++) { add(cx + gauss() * sp * 1.7, cy + gauss() * sp, cz + gauss() * sp * 1.4, Math.random() < 0.12 ? 2.4 : 1.5, rnd(0.5, 1)); }
        }
        // a perspective lattice below the horizon: rows of dots running away from the camera
        for (i = -11; i <= 11; i++) {
            for (j = 0; j < Math.round(60 * k) + 12; j++) { add(i * 110 + rnd(-3, 3), 220 + rnd(-2, 2), -60 + j * 34, 1.2, rnd(0.35, 0.8)); }
        }
        // dotted vertical columns
        for (i = 0; i < 14; i++) {
            var vx = rnd(-1200, 1200), vz = rnd(0, 1000), top = rnd(-340, -60), len = rnd(160, 420);
            for (j = 0; j < len; j += rnd(8, 14)) { add(vx, top + j, vz, 1.3, rnd(0.35, 0.8)); }
        }
        // streak fans
        [[-560, -150, 120, 1], [660, -40, 300, -1], [-100, 40, 520, 1]].forEach(function (o) {
            for (i = 0; i < 18; i++) {
                var ang = rnd(-0.3, 0.3) + (o[3] > 0 ? 0 : Math.PI), len2 = rnd(180, 820);
                for (j = 0; j < len2; j += rnd(6, 11)) { add(o[0] + Math.cos(ang) * j, o[1] + Math.sin(ang) * j * 0.3, o[2] + rnd(-6, 6) + j * 0.5, 1.3, rnd(0.4, 0.95) * (1 - j / len2)); }
            }
        });
        // dust
        for (i = 0; i < Math.round(520 * k); i++) { add(rnd(-1400, 1400), rnd(-420, 420), rnd(-100, 1100), 1, rnd(0.2, 0.55)); }
    }

    function frame(t) {
        if (!crun) { return; }
        if (!born) { born = t; }
        var move = full();
        var yaw = (move ? 0.18 + Math.sin(t / 14000) * 0.16 : 0.18 + Math.sin(t / 22000) * 0.05);
        mx += ((move ? tmx : 0) - mx) * 0.04; my += ((move ? tmy : 0) - my) * 0.04;
        var cosY = Math.cos(yaw + mx * 0.14), sinY = Math.sin(yaw + mx * 0.14), f = Math.min(W, 1200) * 0.9;
        var sc = move ? Math.min(S, H * 1.2) : 0;        // damped scroll: the camera flies forward as the page leaves
        var dolly = sc * 0.55;
        var cxs = W * 0.56, cys = H * 0.4 - sc * 0.12, i, p, x, z, zz, k, sx, sy, a, s;
        ctx.clearRect(0, 0, W, H);
        ctx.fillStyle = '#e6efe6';
        var fade = Math.min(1, (t - born) / 2200);
        for (i = 0; i < pts.length; i++) {
            p = pts[i];
            x = p.x * cosY - (p.z - 380) * sinY;
            z = p.x * sinY + (p.z - 380) * cosY + 380;
            zz = z + 420 - dolly;
            if (zz < 60) { continue; }
            k = f / zz;
            sx = cxs + x * k;
            sy = cys + (p.y + my * 26) * k;
            if (sx < -4 || sx > W + 4 || sy < -4 || sy > H + 4) { continue; }
            a = p.a * Math.min(1, 1.25 - zz / 1900) * (0.6 + 0.4 * Math.sin(t / 900 + p.t)) * fade;
            if (a <= 0.02) { continue; }
            s = Math.max(1, p.s * k * 1.2);
            ctx.globalAlpha = a;
            ctx.fillRect(sx, sy, s, s);
        }
        ctx.globalAlpha = 1;
        requestAnimationFrame(frame);
    }

    function start() { if (crun) { return; } crun = true; requestAnimationFrame(frame); }
    function stop() { crun = false; }

    build();
    if ('IntersectionObserver' in window) {
        new IntersectionObserver(function (e) { visible = e[0].isIntersecting; if (visible && !document.hidden) { start(); } else { stop(); } }, { threshold: 0 }).observe(cv);
    } else { start(); }
    document.addEventListener('visibilitychange', function () { if (document.hidden) { stop(); } else if (visible) { start(); } });
    var rz;
    addEventListener('resize', function () { clearTimeout(rz); rz = setTimeout(build, 150); });
    addEventListener('pointermove', function (e) { tmx = e.clientX / innerWidth - 0.5; tmy = e.clientY / innerHeight - 0.5; }, { passive: true });
    cv.classList.add('ready');
    start();
})();
