/* LS-19 D1: the public charts, drawn with Chart.js from data the page carries in <script type="application/json"> blocks (a data block, never run),
   so no inline script is needed (Content-Security-Policy). Same drawings as the inline code they replace (LS-14 radar, F-2 trend). Glue only. */
(function () {
    function draw() {
        if (typeof Chart === 'undefined') { return; }
        var css = getComputedStyle(document.body), c = (css.getPropertyValue('--ci-primary') || '#bbf246').trim();
        Chart.defaults.color = (css.getPropertyValue('--ci-text-2') || '#94a3b8').trim();
        Chart.defaults.borderColor = (css.getPropertyValue('--ci-border') || '#334155').trim();
        Array.prototype.forEach.call(document.querySelectorAll('canvas[data-chart]:not([data-drawn])'), function (cv) {
            var src = document.getElementById(cv.getAttribute('data-source')), rows;
            try { rows = JSON.parse(src ? src.textContent : '[]'); } catch (e) { return; }
            cv.setAttribute('data-drawn', '1');
            if (cv.getAttribute('data-chart') === 'radar') {
                new Chart(cv, {
                    type: 'radar',
                    data: { labels: rows.map(function (a) { return a.l; }), datasets: [{ data: rows.map(function (a) { return a.v; }), borderColor: c, backgroundColor: c + '33', borderWidth: 2, pointRadius: 3 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { r: { min: 0, max: 100, ticks: { display: false, stepSize: 25 }, pointLabels: { font: { size: 13 } } } } }
                });
            } else if (cv.getAttribute('data-chart') === 'trend') {
                new Chart(cv, {
                    type: 'line',
                    data: { labels: rows.map(function (r) { return r.m; }), datasets: [
                        { label: 'Q3', data: rows.map(function (r) { return r.q3; }), borderWidth: 0, pointRadius: 0, fill: '+1', backgroundColor: c + '24' },
                        { label: 'Q1', data: rows.map(function (r) { return r.q1; }), borderWidth: 0, pointRadius: 0, fill: false },
                        { label: 'ราคากลาง', data: rows.map(function (r) { return r.med; }), borderColor: c, backgroundColor: c, borderWidth: 2.5, pointRadius: 4, tension: .25 }
                    ] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (x) { return x.dataset.label + ': ฿' + Math.round(x.parsed.y).toLocaleString('th-TH') + (x.datasetIndex === 2 ? ' (' + rows[x.dataIndex].n + ' ตัวอย่าง)' : ''); } } } }, scales: { y: { ticks: { callback: function (v) { return '฿' + v.toLocaleString('th-TH'); } } } } }
                });
            } else if (cv.getAttribute('data-chart') === 'history') {
                var hc = cv.getAttribute('data-color') || c;   // Admin snapshot history: median at each calculation
                new Chart(cv, { type: 'line', data: { labels: rows.map(function (r) { return r.t; }), datasets: [{ data: rows.map(function (r) { return r.m; }), borderColor: hc, backgroundColor: hc, borderWidth: 2, pointRadius: 4, tension: .2 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (x) { return '฿' + Math.round(x.parsed.y).toLocaleString() + ' · ' + rows[x.dataIndex].n + ' samples'; } } } }, scales: { y: { ticks: { callback: function (v) { return '฿' + Number(v).toLocaleString(); } } } } } });
            }
        });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', draw); } else { draw(); }
})();
