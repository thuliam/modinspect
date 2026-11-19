/* LS-19 D1: was inline in the page head. Runs synchronously in <head> (not deferred) so the motion mode is on <html> before the first paint:
   "calm" when the OS asks for reduced motion or the visitor chose it (localStorage, may be blocked), "full" otherwise. */
(function () { var d = document.documentElement, m = matchMedia('(prefers-reduced-motion: reduce)').matches ? 'calm' : 'full'; try { var s = localStorage.getItem('mi-motion'); if (s === 'full' || s === 'calm') { m = s; } } catch (e) { /* storage blocked */ } d.classList.add('js', 'motion-' + m); })();
