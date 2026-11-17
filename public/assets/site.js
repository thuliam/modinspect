/* Public site behaviour (jQuery 3.7.1 + Bootstrap 4 bundle are loaded before this file; no network calls).
   Bootstrap handles the navbar collapse and the category dropdown. This file adds:
   - scroll reveal (CSS draws it; this flips a class), count-up numbers, header state
   - featured-grid category tabs, motion toggle (full / calm). Calm = OS asks for reduced motion: fades only. */
jQuery(function ($) {
    var $root = $(document.documentElement), $win = $(window);
    var full = function () { return $root.hasClass('motion-full'); };

    /* motion toggle */
    var $toggle = $('#motion-toggle');
    function paintToggle() {
        $toggle.text(full() ? 'เอฟเฟกต์: เต็ม' : 'เอฟเฟกต์: ลดลง').attr('aria-pressed', full() ? 'true' : 'false')
            .attr('title', full() ? 'กดเพื่อลดการเคลื่อนไหว' : 'กดเพื่อเปิดเอฟเฟกต์เต็ม');
    }
    $toggle.on('click', function () {
        var next = full() ? 'calm' : 'full';
        $root.removeClass('motion-full motion-calm').addClass('motion-' + next);
        try { localStorage.setItem('mi-motion', next); } catch (e) { /* storage may be blocked */ }
        paintToggle();
    });
    paintToggle();

    /* header state */
    var $header = $('.site-header');
    function onScroll() { $header.toggleClass('sc', $win.scrollTop() > 40); }
    $win.on('scroll', onScroll); onScroll();

    /* inner pages: opt their main blocks into the same reveal without touching every view */
    if (!$('body').hasClass('is-home')) {
        var late = [];
        ['.mi-page-heading', '.mi-product-hero', '.mi-filter-card', '.mi-card-grid > *', '.mi-compact-list li', '.mi-product-summary-card',
            '.mi-price-answer-card', '.mi-method-card', '.mi-history-card', '.mi-confidence-card', '.mi-deal-cta', '.mi-empty-state', 'main fieldset'
        ].forEach(function (sel) {
            $('main ' + sel).each(function (i) {
                var el = this;
                if ($(el).hasClass('reveal') || $(el).closest('.reveal').length) { return; }
                el.style.transition = 'none';                 // hide instantly, then let the entrance play
                $(el).addClass('reveal')[0].style.setProperty('--d', Math.min(i, 8) * 70 + 'ms');
                late.push(el);
            });
        });
        if (late.length) {
            void document.body.offsetHeight;
            requestAnimationFrame(function () { late.forEach(function (el) { el.style.transition = ''; }); });
        }
    }

    /* reveals */
    var $rev = $('.reveal');
    if ('IntersectionObserver' in window) {
        var rio = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { if (en.isIntersecting) { $(en.target).addClass('in'); rio.unobserve(en.target); } });
        }, { threshold: 0.05, rootMargin: '0px 0px -6% 0px' });
        $rev.each(function () { rio.observe(this); });
    } else { $rev.addClass('in'); }

    /* count-up (a changing number is not travel, so calm mode keeps it) */
    function runCount(el) {
        var end = parseInt(el.getAttribute('data-count'), 10) || 0, t0 = null, dur = 1600;
        if (end === 0) { el.textContent = '0'; return; }
        function tick(t) {
            if (t0 === null) { t0 = t; }
            var p = Math.min(1, (t - t0) / dur);
            el.textContent = String(Math.round(end * (1 - Math.pow(1 - p, 4))));
            if (p < 1) { requestAnimationFrame(tick); }
        }
        el.textContent = '0';
        requestAnimationFrame(tick);
    }
    if ('IntersectionObserver' in window) {
        var cio = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { if (en.isIntersecting) { runCount(en.target); cio.unobserve(en.target); } });
        }, { threshold: 0.6 });
        $('[data-count]').each(function () { cio.observe(this); });
    }

    /* featured tabs */
    $('.mi-tabs').on('click', 'button', function () {
        var f = $(this).data('filter');
        $(this).addClass('active').siblings().removeClass('active');
        $('.mi-featured > [data-cat]').each(function () { $(this).toggle(f === 'all' || $(this).data('cat') === f); });
    });
});
