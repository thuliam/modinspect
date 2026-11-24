/* Public site behaviour (jQuery 3.7.1 + Bootstrap 4 bundle are loaded before this file; no network calls).
   Bootstrap handles the navbar collapse and the category dropdown. This file adds:
   - scroll reveal (CSS draws it; this flips a class), count-up numbers, header state
   - featured-grid category tabs. Motion mode (full, or calm = the OS asks for reduced motion: fades only) is set in <head> by mi-motion-init.js; there is no visitor toggle (LS-46). */
jQuery(function ($) {
    var $root = $(document.documentElement), $win = $(window);

    /* header state */
    var $header = $('.site-header');
    function onScroll() { $header.toggleClass('sc', $win.scrollTop() > 40); }
    $win.on('scroll', onScroll); onScroll();

    /* inner pages: opt their main blocks into the same reveal without touching every view */
    if (!$('body').hasClass('is-home')) {
        var late = [];
        ['.mi-page-heading', '.mi-product-hero', '.mi-filter-card', '.mi-card-grid > *', '.mi-compact-list li', '.mi-product-summary-card',
            '.mi-price-answer-card', '.mi-cmp-card', '.mi-radar-card', '.mi-table-card', '.mi-method-card', '.mi-history-card', '.mi-confidence-card', '.mi-deal-cta', '.mi-empty-state', '.mi-deal-form', '.mi-result-panel', '.mi-deal-help > *', '.mi-ask', '.mi-up-card', '.mi-builder .card', 'main fieldset'
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
    var $rev = $('.reveal, .mi-sec, #sec-bench');   // LS-41: home sections get the same flag, CSS stages their parts
    if ('IntersectionObserver' in window) {
        var rio = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { if (en.isIntersecting) { $(en.target).addClass('in'); rio.unobserve(en.target); } });
        }, { threshold: 0.05, rootMargin: '0px 0px -6% 0px' });
        $rev.each(function () { rio.observe(this); });
    } else { $rev.addClass('in'); }

    /* LS-43 heading motion, after kyma.framer.ai: titles are split into words (Intl.Segmenter knows Thai word boundaries; a letter split would tear Thai vowel and tone marks off their base),
       the hero title rises word by word on load, section titles rise word by word when their section scrolls in, and the long statement of section 02 lights up word by word as it is scrolled
       through. The split is always done; CSS animates it only in full-motion mode, so the motion mode needs no reload. */
    (function () {
        var seg = window.Intl && Intl.Segmenter ? new Intl.Segmenter('th', { granularity: 'word' }) : null;
        function parts(t) { return seg ? Array.from(seg.segment(t), function (x) { return x.segment; }) : t.split(/(\s+)/); }
        function split(el) {
            var i = 0;
            (function walk(node) {
                Array.prototype.slice.call(node.childNodes).forEach(function (n) {
                    if (n.nodeType === 3) {
                        var f = document.createDocumentFragment();
                        parts(n.nodeValue).forEach(function (w) {
                            if (!w.trim()) { f.appendChild(document.createTextNode(w)); return; }
                            var sp = document.createElement('span'); sp.className = 'mi-w'; sp.style.setProperty('--w', i++); sp.textContent = w; f.appendChild(sp);
                        });
                        n.parentNode.replaceChild(f, n);
                    } else if (n.nodeType === 1 && n.tagName !== 'BR') { walk(n); }
                });
            })(el);
        }
        $('.mi-hero h1, .mi-sec h2, .container.page h1').each(function () { split(this); });
        var $lit = $('#about h2').addClass('mi-lit'), ticking = false;
        function light() {
            ticking = false;
            $lit.each(function () {
                var r = this.getBoundingClientRect(), vh = window.innerHeight, p = Math.max(0, Math.min(1, (vh * 0.85 - r.top) / (r.height + vh * 0.3))), words = $(this).find('.mi-w'), n = Math.round(p * words.length);
                words.each(function (k) { this.classList.toggle('lit', k < n); });
            });
        }
        if ($lit.length) { light(); $(window).on('scroll resize', function () { if (!ticking) { ticking = true; requestAnimationFrame(light); } }); }
    })();

    /* LS-43 back to top: shown once the page is long and the visitor is a screen or more down */
    (function () {
        var $b = $('#to-top');
        if (!$b.length) { return; }
        function show() { $b.toggleClass('show', document.documentElement.scrollHeight > window.innerHeight * 2.5 && window.scrollY > Math.max(600, window.innerHeight)); }
        $(window).on('scroll resize', show); show();
        $b.on('click', function () { window.scrollTo({ top: 0, behavior: $('html').hasClass('motion-full') ? 'smooth' : 'auto' }); var m = document.getElementById('main'); if (m) { m.focus({ preventScroll: true }); } });
    })();
    /* decode (LS-41, after the number scramble on kyma.framer.ai): the figure starts as noise and settles into the real number from left to right; the markup already holds the real number,
       so without script or while the observer waits, it reads correctly. A changing number is not travel, so calm mode keeps it. */
    function runCount(el) {
        var end = String(parseInt(el.getAttribute('data-count'), 10) || 0), t0 = null, dur = 1500, noise = '0123456789#%/+<>';
        if (end === '0') { el.textContent = '0'; return; }
        function tick(t) {
            if (t0 === null) { t0 = t; }
            var p = Math.min(1, (t - t0) / dur), settled = Math.floor(p * (end.length + 1)), out = end.slice(0, settled);
            while (out.length < end.length) { out += noise.charAt(Math.floor(Math.random() * noise.length)); }
            el.textContent = p < 1 ? out : end;
            if (p < 1) { requestAnimationFrame(tick); }
        }
        requestAnimationFrame(tick);
    }
    if ('IntersectionObserver' in window) {
        var cio = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { if (en.isIntersecting) { runCount(en.target); cio.unobserve(en.target); } });
        }, { threshold: 0.6 });
        $('[data-count]').each(function () { cio.observe(this); });
    }

    /* LS-45 score run-up: a benchmark figure counts up from 0 to its published value when its card scrolls in (the markup already holds the real value) */
    function runScore(el) {
        var end = parseFloat(el.getAttribute('data-score')), t0 = null, dur = 1300;
        if (isNaN(end) || end <= 0) { return; }
        function tick(t) {
            if (t0 === null) { t0 = t; }
            var p = Math.min(1, (t - t0) / dur);
            el.textContent = (end * (1 - Math.pow(1 - p, 4))).toFixed(1).replace(/\.0$/, '');
            if (p < 1) { requestAnimationFrame(tick); }
        }
        el.textContent = '0';
        requestAnimationFrame(tick);
    }
    if ('IntersectionObserver' in window) {
        var sio = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { if (en.isIntersecting) { runScore(en.target); sio.unobserve(en.target); } });
        }, { threshold: 0.6 });
        $('[data-score]').each(function () { sio.observe(this); });
    }
    /* featured tabs */
    $('.mi-tabs').on('click', 'button', function () {
        var f = $(this).data('filter');
        $(this).addClass('active').attr('aria-pressed', 'true').siblings().removeClass('active').attr('aria-pressed', 'false');
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
            var t = $(this).attr('data-nav');   // LS-35: icon dock; the label is the tooltip and the accessible name
            $('<a class="nav-link">').attr({ href: '#' + this.id, 'data-title': t, 'aria-label': t }).append($('<span class="mi-dock-ico">').append($('<i class="fas" aria-hidden="true">').addClass($(this).attr('data-icon') || 'fa-circle'))).appendTo($pnav);
        });
        if ($pnav.children().length > 2) {
            $pnav.prop('hidden', false);
            $('body').scrollspy({ target: '#product-nav', offset: 200 });
            /* the spy marks nothing until the first section reaches its offset; at the very top of the page the first link is the current one */
            var firstActive = function () { if (!$pnav.find('.active').length) { $pnav.children().first().addClass('active'); } };
            firstActive(); $(window).on('scroll', firstActive);
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

// LS-22: copy-link and print buttons (glue only)
$(function () {
    $(document).on('click', '[data-print]', function () { window.print(); });
    $(document).on('click', '[data-copy-text]', function () {   // LS-24 C2: copies the text in the attribute (the share line of a product)
        var $b = $(this), text = $b.attr('data-copy-text'), done = function () { $b.find('span').text('คัดลอกแล้ว'); };
        if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done); return; }
        var $t = $('<textarea>').val(text).appendTo('body').select();
        try { if (document.execCommand('copy')) { done(); } } catch (e) { /* the text stays on the page */ }
        $t.remove();
    });
    $(document).on('click', '[data-copy-url]', function () {
        var $b = $(this), done = function () { $b.find('span').text('คัดลอกแล้ว'); };
        if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(window.location.href).then(done); return; }
        var $t = $('<input>').val(window.location.href).appendTo('body').select(); // plain-http fallback
        try { if (document.execCommand('copy')) { done(); } } catch (e) { /* the address bar still has the link */ }
        $t.remove();
    });
});
// PWA P1: register the service worker (it sits next to the manifest, so its path is taken from the manifest link and stays on the page's own origin)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        var m = document.querySelector('link[rel=manifest]');
        if (m) { navigator.serviceWorker.register(new URL(m.href).pathname.replace(/[^/]*$/, '') + 'sw.js').catch(function () { /* the site works without it */ }); }
    });
}
