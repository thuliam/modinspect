/* Nav, reveal, mailing-list demo and the home point cloud. No network calls. */
(function () {
    var nav = document.querySelector('.nav');
    var onScroll = function () { nav.classList.toggle('sc', window.scrollY > 40); };
    addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    var burger = document.getElementById('burger');
    var menu = document.getElementById('menu');
    if (burger && menu) {
        burger.addEventListener('click', function () {
            var open = menu.classList.toggle('open');
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        menu.addEventListener('click', function (e) { if (e.target.tagName === 'A') { menu.classList.remove('open'); } });
    }

    var items = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
        }, { threshold: 0.12 });
        items.forEach(function (el) { io.observe(el); });
    } else {
        items.forEach(function (el) { el.classList.add('in'); });
    }

    var form = document.getElementById('join');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var say = form.querySelector('.thanks');
            say.textContent = form.querySelector('input[type=email]').checkValidity() ? 'Thanks. This demo form does not send anything.' : 'Enter a valid email address.';
        });
    }

    var c = document.getElementById('cloud');
    if (!c) { return; }
    var x = c.getContext('2d');
    var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    var W, H, pts = [], mx = 0.5, my = 0.5;

    function gauss() { return (Math.random() + Math.random() + Math.random() + Math.random() - 2) / 2; }

    function build() {
        W = c.width = c.clientWidth;
        H = c.height = c.clientHeight;
        pts = [];
        var area = W * H / 1300000;
        var i, j, k;
        // 1. loose clusters, like grouped data points
        for (i = 0; i < Math.round(15 * area) + 6; i++) {
            var cx = Math.random() * W, cy = H * (0.18 + Math.random() * 0.62), n = 22 + Math.random() * 50, s = 18 + Math.random() * 46;
            for (j = 0; j < n; j++) { pts.push({ x: cx + gauss() * s * 1.6, y: cy + gauss() * s, z: 0.4 + Math.random() * 1.1, s: Math.random() < 0.12 ? 3 : 2, t: Math.random() * 6.28, v: 0 }); }
        }
        // 2. dotted vertical columns
        for (i = 0; i < Math.round(7 * W / 1440) + 2; i++) {
            var vx = Math.random() * W, top = H * (0.16 + Math.random() * 0.2), len = H * (0.25 + Math.random() * 0.4);
            for (k = 0; k < len; k += 9 + Math.random() * 9) { pts.push({ x: vx + (Math.random() - 0.5) * 2, y: top + k, z: 0.5 + Math.random() * 0.6, s: 2, t: Math.random() * 6.28, v: 0 }); }
        }
        // 3. two fans of dotted streaks (a perspective sweep)
        [[W * 0.2, H * 0.29, 1], [W * 0.88, H * 0.42, -1]].forEach(function (o) {
            for (i = 0; i < 16; i++) {
                var ang = (Math.random() - 0.5) * 0.55 + (o[2] > 0 ? 0.05 : Math.PI - 0.05), len2 = 80 + Math.random() * 420;
                for (k = 0; k < len2; k += 5 + Math.random() * 6) {
                    pts.push({ x: o[0] + Math.cos(ang) * k, y: o[1] + Math.sin(ang) * k * 0.4 + (Math.random() - 0.5) * 3, z: 0.4 + (1 - k / len2) * 0.9, s: 1.5, t: Math.random() * 6.28, v: 0 });
                }
            }
        });
        // 4. faint scatter
        for (i = 0; i < Math.round(130 * area) + 40; i++) { pts.push({ x: Math.random() * W, y: H * (0.1 + Math.random() * 0.8), z: 0.3 + Math.random() * 0.5, s: 2, t: Math.random() * 6.28, v: 0 }); }
    }

    function draw(t) {
        x.clearRect(0, 0, W, H);
        x.fillStyle = '#dfe8e0';
        for (var i = 0; i < pts.length; i++) {
            var p = pts[i];
            if (!reduce) { p.v = Math.sin(t / 1800 + p.t) * 0.12; }
            var px = p.x + (mx - 0.5) * 34 * p.z + p.v * 6;
            var py = p.y + (my - 0.5) * 22 * p.z + p.v * 3;
            x.globalAlpha = Math.min(1, (0.3 + 0.55 * Math.abs(Math.sin(t / 1100 + p.t))) * p.z);
            x.fillRect(px, py, p.s, p.s);
        }
        if (!reduce) { requestAnimationFrame(draw); }
    }

    build();
    addEventListener('resize', function () { build(); if (reduce) { draw(0); } });
    addEventListener('pointermove', function (e) { mx = e.clientX / innerWidth; my = e.clientY / innerHeight; });
    requestAnimationFrame(draw);
})();
