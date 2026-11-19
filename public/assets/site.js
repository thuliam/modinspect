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

    /* data-autosubmit moved to mi-select.js (LS-21): that file is loaded by the public pages and by Admin, this one only by the public pages */

    /* LS-20 B1: a read-only field marked data-select-on-focus selects its text when focused (was an inline onfocus handler) */
    $(document).on('focus', '[data-select-on-focus]', function () { this.select(); });

    /* LS-19 B3: "/" jumps to the search box (or opens the price index, which has one); never while typing in a field or with a modifier key */
    $(document).on('keydown', function (e) {
        if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey || $(e.target).is('input, textarea, select, [contenteditable], .select2-search__field')) { return; }
        var $q = $('main input[name="q"]:visible').first();
        e.preventDefault();
        if ($q.length) { $q.trigger('focus').select(); } else { window.location.href = $('.nav-icon[href$="/price"]').attr('href') + '?focus=1'; }
    });
    if (/[?&]focus=1\b/.test(window.location.search)) { $('main input[name="q"]').first().trigger('focus'); }

    /* LS-19 B1: product page jump bar. Links are made from the sections that exist on this page (data-nav), Bootstrap scrollspy marks the current one. */
    var $pnav = $('#product-nav');
    if ($pnav.length) {
        $('main [data-nav][id]').each(function () {
            $('<a class="nav-link">').attr('href', '#' + this.id).text($(this).attr('data-nav')).appendTo($pnav);
        });
        if ($pnav.children().length > 2) {
            $pnav.prop('hidden', false);
            $('body').scrollspy({ target: '#product-nav', offset: 160 });
        }
    }

    /* LS-18 Compare: the first box that holds a model sets the category; the other boxes only list that category (an optgroup per category),
       and a box holding a model of another category is cleared. Display only: the server still compares whatever it is sent. */
    var $cmp = $('select.js-compare');
    if ($cmp.length && $.fn.select2) {
        var lockCat = null;
        var matcher = function (params, data, $self, locked) {
            if (locked && lockCat && data.children && data.text !== lockCat) { return null; }
            var out = $.fn.select2.defaults.defaults.matcher(params, data);
            if (out && out.children) {   // a model already chosen in another box is not offered again
                var taken = $cmp.not($self).map(function () { return $(this).val(); }).get();
                out = $.extend({}, out, { children: $.grep(out.children, function (c) { return $.inArray(c.id, taken) < 0; }) });
                if (!out.children.length) { return null; }
            }
            return out;
        };
        var relock = function () {
            var $first = $cmp.filter(function () { return $(this).val(); }).first();
            lockCat = $first.length ? String($first.find('option:selected').data('cat')) : null;
            $cmp.not($first).each(function () {
                var $s = $(this), cat = $s.find('option:selected').data('cat');
                if (lockCat && cat && String(cat) !== lockCat) { $s.val('').trigger('change.select2'); }
            });
            $('#compare-lock-note').prop('hidden', !lockCat).find('strong').text(lockCat || '');
        };
        $cmp.each(function () {
            var $s = $(this);
            $s.select2(window.miSelect2Options($s, { placeholder: $s.data('placeholder'), allowClear: true, minimumResultsForSearch: 0,
                matcher: function (params, data) { return matcher(params, data, $s, !$s.is($cmp.filter(function () { return $(this).val(); }).first())); } }));
        });
        $cmp.on('change', relock);
        relock();
    }
});
