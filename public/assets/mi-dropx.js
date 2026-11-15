/* Admin shell glue (theme toggle, mobile drawer, fullscreen, Ctrl K nav search). Theme choice is a per-viewer convenience in localStorage. */
(function () {
    'use strict';
    var root = document.documentElement;
    function apply(theme) {
        if (theme === 'dark') { root.setAttribute('data-theme', 'dark'); } else { root.removeAttribute('data-theme'); }
        var btn = document.getElementById('dxThemeToggle');
        if (btn) {
            btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
            btn.querySelector('i').className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
    }
    var saved = null;
    try { saved = window.localStorage.getItem('mi-admin-theme'); } catch (e) { saved = null; }
    apply(saved === 'dark' ? 'dark' : 'light');
    document.addEventListener('DOMContentLoaded', function () { apply(root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'); });
    document.addEventListener('click', function (event) {
        var t = event.target.closest ? event.target : null;
        if (!t) { return; }
        if (t.closest('#dxThemeToggle')) {
            var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            apply(next);
            try { window.localStorage.setItem('mi-admin-theme', next); } catch (e) { /* storage blocked: choice kept for this page only */ }
        } else if (t.closest('.js-nav-open')) {
            document.body.classList.add('dx-nav-open');
        } else if (t.closest('.dx-backdrop') || t.closest('.dx-close')) {
            document.body.classList.remove('dx-nav-open');
        } else if (t.closest('#dxFullscreen')) {
            if (!document.fullscreenElement) { document.documentElement.requestFullscreen(); } else { document.exitFullscreen(); }
        }
    });
    document.addEventListener('keydown', function (event) {
        var input = document.getElementById('dxSearch');
        if (input && (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); input.focus(); }
    });
    document.addEventListener('input', function (event) {
        if (event.target.id !== 'dxSearch') { return; }
        var needle = event.target.value.toLowerCase();
        document.querySelectorAll('.dx-link').forEach(function (a) { a.style.display = a.textContent.toLowerCase().indexOf(needle) === -1 ? 'none' : ''; });
    });
})();
