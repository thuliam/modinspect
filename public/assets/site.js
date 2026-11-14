/* Public site behaviour: header shrink, mobile menu, and the decorative home point cloud. No network calls. */
(function () {
    var nav = document.querySelector('.site-header');
    var onScroll = function () { if (nav) { nav.classList.toggle('sc', window.scrollY > 40); } };
    addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    var burger = document.getElementById('burger');
    var menu = document.getElementById('site-menu');
    if (burger && menu) {
        burger.addEventListener('click', function () {
            var open = menu.classList.toggle('open');
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        menu.addEventListener('click', function (e) { if (e.target.tagName === 'A') { menu.classList.remove('open'); burger.setAttribute('aria-expanded', 'false'); } });
        addEventListener('keydown', function (e) { if (e.key === 'Escape') { menu.classList.remove('open'); burger.setAttribute('aria-expanded', 'false'); } });
    }

    var c = document.getElementById('cloud');
    if (!c || !c.getContext) { return; }
    var x = c.getContext('2d');
    var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    var W, H, pts = [], mx = 0.5, my = 0.5;

    function gauss() { return (Math.random() + Math.random() + Math.random() + Math.random() - 2) / 2; }
    function dot(px, py, z, s) { pts.push({ x: px, y: py, z: z, s: s, t: Math.random() * 6.28, v: 0 }); }

    function build() {
        W = c.width = c.clientWidth;
        H = c.height = c.clientHeight;
        pts = [];
        var area = Math.max(0.35, W * H / 1300000), i, j, k;
        for (i = 0; i < Math.round(15 * area) + 6; i++) {
            var cx = Math.random() * W, cy = H * (0.1 + Math.random() * 0.7), n = 22 + Math.random() * 50, s = 18 + Math.random() * 46;
            for (j = 0; j < n; j++) { dot(cx + gauss() * s * 1.6, cy + gauss() * s, 0.4 + Math.random() * 1.1, Math.random() < 0.12 ? 3 : 2); }
        }
        for (i = 0; i < Math.round(7 * W / 1440) + 2; i++) {
            var vx = Math.random() * W, top = H * (0.08 + Math.random() * 0.2), len = H * (0.25 + Math.random() * 0.4);
            for (k = 0; k < len; k += 9 + Math.random() * 9) { dot(vx + (Math.random() - 0.5) * 2, top + k, 0.5 + Math.random() * 0.6, 2); }
        }
        [[W * 0.2, H * 0.22, 1], [W * 0.88, H * 0.36, -1]].forEach(function (o) {
            for (i = 0; i < 16; i++) {
                var ang = (Math.random() - 0.5) * 0.55 + (o[2] > 0 ? 0.05 : Math.PI - 0.05), len2 = 80 + Math.random() * 420;
                for (k = 0; k < len2; k += 5 + Math.random() * 6) { dot(o[0] + Math.cos(ang) * k, o[1] + Math.sin(ang) * k * 0.4 + (Math.random() - 0.5) * 3, 0.4 + (1 - k / len2) * 0.9, 1.5); }
            }
        });
        for (i = 0; i < Math.round(130 * area) + 40; i++) { dot(Math.random() * W, H * (0.05 + Math.random() * 0.85), 0.3 + Math.random() * 0.5, 2); }
    }

    function draw(t) {
        x.clearRect(0, 0, W, H);
        x.fillStyle = '#dfe8e0';
        for (var i = 0; i < pts.length; i++) {
            var p = pts[i];
            if (!reduce) { p.v = Math.sin(t / 1800 + p.t) * 0.12; }
            x.globalAlpha = Math.min(1, (0.3 + 0.55 * Math.abs(Math.sin(t / 1100 + p.t))) * p.z) * 0.8;
            x.fillRect(p.x + (mx - 0.5) * 34 * p.z + p.v * 6, p.y + (my - 0.5) * 22 * p.z + p.v * 3, p.s, p.s);
        }
        if (!reduce) { requestAnimationFrame(draw); }
    }

    build();
    addEventListener('resize', function () { build(); if (reduce) { draw(0); } });
    addEventListener('pointermove', function (e) { mx = e.clientX / innerWidth; my = e.clientY / innerHeight; });
    requestAnimationFrame(draw);
})();
