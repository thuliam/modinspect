/* Admin theme toggle (light/dark). The choice is a per-viewer convenience kept in localStorage; the page works without it. */
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
    document.addEventListener('click', function (event) {
        var t = event.target.closest ? event.target.closest('#dxThemeToggle') : null;
        if (!t) { return; }
        var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        apply(next);
        try { window.localStorage.setItem('mi-admin-theme', next); } catch (e) { /* storage blocked: keep the choice for this page only */ }
    });
})();
