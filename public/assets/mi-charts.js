/* LS-19 D1: the public charts, drawn with Chart.js from data the page carries in <script type="application/json"> blocks (a data block, never run),
   so no inline script is needed (Content-Security-Policy). Same drawings as the inline code they replace (LS-14 radar, F-2 trend). Glue only. */
(function () {
    function draw() {
        if (typeof Chart === 'undefined') { return; }
        var css = getComputedStyle(document.body), c = (css.getPropertyValue('--ci-primary') || '#bbf246').trim();
        Chart.defaults.color = (css.getPropertyValue('--ci-text-2') || '#94a3b8').trim();
        Chart.defaults.font.family = css.fontFamily;   // the CI faces, not the Chart.js default
        Chart.defaults.borderColor = (css.getPropertyValue('--ci-border') || '#334155').trim();
        Chart.defaults.animation = document.documentElement.classList.contains('motion-calm') ? { duration: 0 } : { duration: 1200, easing: 'easeOutQuart' };
        function build(cv) {
            var src = document.getElementById(cv.getAttribute('data-source')), rows;
            try { rows = JSON.parse(src ? src.textContent : '[]'); } catch (e) { return; }
            cv.setAttribute('data-drawn', '1');
            if (cv.getAttribute('data-chart') === 'radar') {
                new Chart(cv, {
                    type: 'radar',
                    data: { labels: rows.map(function (a) { return a.l; }), datasets: [{ data: rows.map(function (a) { return a.v; }), borderColor: c, backgroundColor: c + '33', borderWidth: 2, pointRadius: 3 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { r: { min: 0, max: 100, ticks: { display: false, stepSize: 25 }, pointLabels: { font: { size: 13 } } } } }
                });
            } else if (cv.getAttribute('data-chart') === 'radar-multi') {
                // LS-45: radar after the 21st.dev radar chart: polygon grid, one translucent shape per series (they overlap), a dot legend, the value under each axis label when there is one series
                var one = rows.series.length === 1, top = Math.max.apply(null, [].concat.apply([], rows.series.map(function (s) { return s.values; }))), axisMax = Math.min(100, Math.ceil((top + 8) / 10) * 10);   // the grid ends just above the highest score so the shapes use the space; the scale is still points out of 100
                new Chart(cv, {
                    type: 'radar',
                    data: { labels: rows.labels, datasets: rows.series.map(function (s) { return { label: s.name, data: s.values, borderColor: s.color, backgroundColor: s.color + '2e', borderWidth: 2, pointRadius: 3, pointBackgroundColor: s.color, pointBorderColor: '#0a0d14', pointHoverRadius: 5 }; }) },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 16 } }, tooltip: { callbacks: { label: function (x) { return x.dataset.label + ': ' + x.formattedValue + ' / 100'; } } } },
                        scales: { r: { min: 0, max: axisMax, ticks: { display: false, stepSize: axisMax / 4 }, grid: { color: 'rgba(148,163,184,.2)' }, angleLines: { color: 'rgba(148,163,184,.2)' }, pointLabels: { font: { size: 13 }, callback: function (l, i) { return one ? [l, String(rows.series[0].values[i])] : l; } } } } }
                });
            } else if (cv.getAttribute('data-chart') === 'candles') {
                // LS-32 B: one candle per month. Body = Q1 to Q3 (green when the median rose, red when it fell, accent for the first month), thin wick = lowest to highest asking
                // price, dot = median; dashed lines at the latest Q1 and Q3 read like support and resistance. Built from core Chart.js floating bars, no plugin.
                var last = rows[rows.length - 1], tone = function (r, i) { return i === 0 ? c : (r.med > rows[i - 1].med ? '#4ade80' : (r.med < rows[i - 1].med ? '#f87171' : c)); };
                var flat = function (v) { return rows.map(function () { return v; }); },
                    yLo = Math.min.apply(null, rows.map(function (r) { return r.lo !== null ? r.lo : r.q1; })), yHi = Math.max.apply(null, rows.map(function (r) { return r.hi !== null ? r.hi : r.q3; }));   // bars start at zero by default; a price chart must not
                new Chart(cv, {
                    type: 'bar',
                    data: { labels: rows.map(function (r) { return r.m; }), datasets: [
                        { type: 'bar', label: 'ต่ำสุด-สูงสุด', data: rows.map(function (r) { return r.lo !== null && r.hi !== null ? [r.lo, r.hi] : [r.q1, r.q3]; }), grouped: false, barThickness: 2, backgroundColor: rows.map(tone), order: 3 },
                        { type: 'bar', label: 'Q1-Q3', data: rows.map(function (r) { return [r.q1, r.q3]; }), grouped: false, barThickness: 22, backgroundColor: rows.map(function (r, i) { return tone(r, i) + 'cc'; }), borderColor: rows.map(tone), borderWidth: 1, order: 2 },
                        { type: 'line', label: 'ราคากลาง', data: rows.map(function (r) { return r.med; }), showLine: false, pointStyle: 'rectRot', pointRadius: 5, pointBackgroundColor: '#fff', pointBorderColor: '#0f172a', order: 1 },
                        { type: 'line', label: 'แนวรับ (Q1 ล่าสุด)', data: flat(last.q1), borderColor: '#f8717199', borderDash: [6, 4], borderWidth: 1, pointRadius: 0, order: 4 },
                        { type: 'line', label: 'แนวต้าน (Q3 ล่าสุด)', data: flat(last.q3), borderColor: '#4ade8099', borderDash: [6, 4], borderWidth: 1, pointRadius: 0, order: 4 }
                    ] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (x) { var v = x.raw, t = Array.isArray(v) ? '฿' + Math.round(v[0]).toLocaleString('th-TH') + ' ถึง ฿' + Math.round(v[1]).toLocaleString('th-TH') : '฿' + Math.round(x.parsed.y).toLocaleString('th-TH'); return x.dataset.label + ': ' + t + (x.datasetIndex === 2 ? ' (' + rows[x.dataIndex].n + ' ตัวอย่าง)' : ''); } } } }, scales: { y: { min: Math.floor(yLo * .97 / 100) * 100, max: Math.ceil(yHi * 1.03 / 100) * 100, ticks: { callback: function (v) { return '฿' + v.toLocaleString('th-TH'); } } } } }
                });
            } else if (cv.getAttribute('data-chart') === 'history') {
                var hc = cv.getAttribute('data-color') || c;   // Admin snapshot history: median at each calculation
                new Chart(cv, { type: 'line', data: { labels: rows.map(function (r) { return r.t; }), datasets: [{ data: rows.map(function (r) { return r.m; }), borderColor: hc, backgroundColor: hc, borderWidth: 2, pointRadius: 4, tension: .2 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (x) { return '฿' + Math.round(x.parsed.y).toLocaleString() + ' · ' + rows[x.dataIndex].n + ' samples'; } } } }, scales: { y: { ticks: { callback: function (v) { return '฿' + Number(v).toLocaleString(); } } } } } });
            }
        }
        // LS-45: a chart is built when it scrolls into view, so its drawing animation is seen
        var cvs = Array.prototype.slice.call(document.querySelectorAll('canvas[data-chart]:not([data-drawn])'));
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { io.unobserve(e.target); build(e.target); } }); }, { threshold: 0.2 });
            cvs.forEach(function (cv) { io.observe(cv); });
        } else { cvs.forEach(build); }
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', draw); } else { draw(); }
})();
