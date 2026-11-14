/* Public site behaviour. No network calls.
   - header shrink + mobile menu
   - home hero: a slowly orbiting 3D point cloud (original, canvas 2D with perspective projection)
   - scroll reveals, count-up numbers, card parallax and pointer tilt
   Motion modes (set early in the page head): "full", or "calm" when the OS asks for reduced motion.
   Calm keeps fades, colour changes, the count-up and an in-place twinkle, but nothing travels. The footer button switches. */
(function () {
    var root = document.documentElement;
    var full = function () { return root.classList.contains('motion-full'); };
    var canHover = matchMedia('(hover: hover)').matches;

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
            onScroll();
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

    /* ---------- scroll reveal (fade only in calm mode, via CSS) ---------- */
    var reveals = [].slice.call(document.querySelectorAll('.reveal'));
    if ('IntersectionObserver' in window) {
        var rio = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); rio.unobserve(en.target); } });
        }, { threshold: 0.14, rootMargin: '0px 0px -6% 0px' });
        reveals.forEach(function (el) { rio.observe(el); });
    } else {
        reveals.forEach(function (el) { el.classList.add('in'); });
    }

    /* ---------- count-up (a changing number is not travel, so calm mode keeps it) ---------- */
    var counters = [].slice.call(document.querySelectorAll('[data-count]'));
    function runCount(el) {
        var end = parseInt(el.getAttribute('data-count'), 10) || 0, t0 = null, dur = 1400;
        if (end === 0) { el.textContent = '0'; return; }
        function tick(t) {
            if (t0 === null) { t0 = t; }
            var p = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - p, 3);
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

    /* ---------- scroll: header state, card column parallax ---------- */
    var par = document.querySelector('[data-parallax]');
    var cards = [].slice.call(document.querySelectorAll('.vs-card'));
    var ticking = false;
    function onScroll() {
        ticking = false;
        if (header) { header.classList.toggle('sc', window.scrollY > 40); }
        // Cards lean away from the middle of the screen and settle flat as they reach it (like the reference).
        cards.forEach(function (card) {
            if (full() && innerWidth > 900) {
                var cr = card.getBoundingClientRect();
                if (cr.bottom < -200 || cr.top > innerHeight + 200) { return; }
                var p = Math.max(-1.2, Math.min(1.2, ((cr.top + cr.height / 2) - innerHeight / 2) / innerHeight));
                card.style.setProperty('--sz', (p * 8).toFixed(2) + 'deg');
                card.style.setProperty('--sx', (p * -13).toFixed(2) + 'deg');
            } else { card.style.removeProperty('--sz'); card.style.removeProperty('--sx'); }
        });
        if (par) {
            if (full() && innerWidth > 900) {
                var r = par.getBoundingClientRect(), mid = (r.top + r.height / 2 - innerHeight / 2) / innerHeight;
                par.style.setProperty('--py', Math.max(-1, Math.min(1, mid)) * -36 + 'px');
            } else { par.style.removeProperty('--py'); }
        }
    }
    addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
    addEventListener('resize', onScroll);
    onScroll();

    /* ---------- pointer tilt on cards (full mode only) ---------- */
    if (canHover) {
        [].slice.call(document.querySelectorAll('.vs-card')).forEach(function (card) {
            card.addEventListener('pointermove', function (e) {
                if (!full()) { return; }
                var r = card.getBoundingClientRect(), px = (e.clientX - r.left) / r.width - 0.5, py = (e.clientY - r.top) / r.height - 0.5;
                card.style.setProperty('--rx', (-py * 5).toFixed(2) + 'deg');
                card.style.setProperty('--ry', (px * 6).toFixed(2) + 'deg');
            });
            card.addEventListener('pointerleave', function () { card.style.removeProperty('--rx'); card.style.removeProperty('--ry'); });
        });
    }

    /* ---------- hero point cloud ---------- */
    var c = document.getElementById('cloud');
    if (!c || !c.getContext) { return; }
    var ctx = c.getContext('2d');
    var W = 0, H = 0, dpr = 1, pts = [], mx = 0, my = 0, tmx = 0, tmy = 0, running = false, visible = true, born = 0;

    function rnd(a, b) { return a + Math.random() * (b - a); }
    function gauss() { return (Math.random() + Math.random() + Math.random() + Math.random() - 2) / 2; }
    function add(x, y, z, s, a) { pts.push({ x: x, y: y, z: z, s: s, a: a, t: Math.random() * 6.28 }); }

    /* world units: x right, y down, z away from the camera. The camera sits at z = -420. */
    function build() {
        dpr = Math.min(window.devicePixelRatio || 1, 1.5);
        W = c.clientWidth; H = c.clientHeight;
        c.width = Math.round(W * dpr); c.height = Math.round(H * dpr);
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        pts = [];
        var k = Math.min(1, (W * H) / 1100000) * (W < 700 ? 0.6 : 1), i, j, n;
        // clusters of listings spread through the depth of the scene
        for (i = 0; i < 14; i++) {
            var cx = rnd(-900, 900), cy = rnd(-260, 260), cz = rnd(-100, 900), sp = rnd(30, 90);
            n = Math.round(rnd(40, 110) * k);
            for (j = 0; j < n; j++) { add(cx + gauss() * sp * 1.7, cy + gauss() * sp, cz + gauss() * sp * 1.4, Math.random() < 0.1 ? 2.2 : 1.4, rnd(0.45, 1)); }
        }
        // a perspective lattice below the horizon: rows of dots running away from the camera
        for (i = -9; i <= 9; i++) {
            for (j = 0; j < Math.round(46 * k) + 10; j++) { add(i * 120 + rnd(-3, 3), 210 + rnd(-2, 2), -60 + j * 38, 1.1, rnd(0.3, 0.7)); }
        }
        // dotted vertical columns
        for (i = 0; i < 9; i++) {
            var vx = rnd(-1000, 1000), vz = rnd(0, 900), top = rnd(-300, -60), len = rnd(160, 380);
            for (j = 0; j < len; j += rnd(9, 15)) { add(vx, top + j, vz, 1.2, rnd(0.3, 0.7)); }
        }
        // two streak fans
        [[-520, -150, 120, 1], [620, -40, 300, -1]].forEach(function (o) {
            for (i = 0; i < 14; i++) {
                var ang = rnd(-0.28, 0.28) + (o[3] > 0 ? 0 : Math.PI), len2 = rnd(160, 760);
                for (j = 0; j < len2; j += rnd(7, 12)) { add(o[0] + Math.cos(ang) * j, o[1] + Math.sin(ang) * j * 0.3, o[2] + rnd(-6, 6) + j * 0.5, 1.2, rnd(0.4, 0.9) * (1 - j / len2)); }
            }
        });
        // dust
        for (i = 0; i < Math.round(240 * k); i++) { add(rnd(-1200, 1200), rnd(-380, 380), rnd(-100, 1000), 1, rnd(0.2, 0.5)); }
    }

    function frame(t) {
        if (!running) { return; }
        if (!born) { born = t; }
        var move = full();
        var yaw = move ? 0.18 + Math.sin(t / 14000) * 0.16 : 0.18;
        mx += ((move ? tmx : 0) - mx) * 0.04; my += ((move ? tmy : 0) - my) * 0.04;
        var cosY = Math.cos(yaw + mx * 0.12), sinY = Math.sin(yaw + mx * 0.12), f = Math.min(W, 1100) * 0.9;
        var cxs = W * 0.62, cys = H * 0.46, i, p, x, z, zz, k, sx, sy, a, s;
        ctx.clearRect(0, 0, W, H);
        ctx.fillStyle = '#e6efe6';
        var fade = Math.min(1, (t - born) / 1800);
        for (i = 0; i < pts.length; i++) {
            p = pts[i];
            x = p.x * cosY - (p.z - 380) * sinY;
            z = p.x * sinY + (p.z - 380) * cosY + 380;
            zz = z + 420;
            if (zz < 60) { continue; }
            k = f / zz;
            sx = cxs + x * k;
            sy = cys + (p.y + my * 24) * k;
            if (sx < -4 || sx > W + 4 || sy < -4 || sy > H + 4) { continue; }
            a = p.a * Math.min(1, 1.2 - zz / 1900) * (0.62 + 0.38 * Math.sin(t / 900 + p.t)) * fade;
            if (a <= 0.02) { continue; }
            s = Math.max(1, p.s * k * 1.15);
            ctx.globalAlpha = a;
            ctx.fillRect(sx, sy, s, s);
        }
        ctx.globalAlpha = 1;
        requestAnimationFrame(frame);
    }

    function start() { if (running) { return; } running = true; requestAnimationFrame(frame); }
    function stop() { running = false; }

    build();
    if ('IntersectionObserver' in window) {
        new IntersectionObserver(function (e) { visible = e[0].isIntersecting; if (visible && !document.hidden) { start(); } else { stop(); } }, { threshold: 0 }).observe(c);
    } else { start(); }
    document.addEventListener('visibilitychange', function () { if (document.hidden) { stop(); } else if (visible) { start(); } });
    var rz;
    addEventListener('resize', function () { clearTimeout(rz); rz = setTimeout(build, 150); });
    addEventListener('pointermove', function (e) { tmx = e.clientX / innerWidth - 0.5; tmy = e.clientY / innerHeight - 0.5; }, { passive: true });
    c.classList.add('ready');
    start();
})();
