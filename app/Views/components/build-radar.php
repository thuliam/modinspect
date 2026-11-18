<?php
/** @var array $result a saved build: budget_max and items (see SpecBuild::find) */
$axes = \App\Services\SpecRadar::axes($result['items'], (float)$result['budget_max']);
$chartId = 'radar' . bin2hex(random_bytes(4));
?>
<div class="build-radar">
    <h3>ความน่าเชื่อถือของข้อมูลราคา</h3>
    <div class="build-radar-canvas"><canvas id="<?= $chartId ?>" role="img" aria-label="เรดาร์ <?= htmlspecialchars(implode(', ', array_map(static fn($a) => $a['label'] . ' ' . $a['value'], $axes))) ?>"></canvas></div>
    <?php if (empty($GLOBALS['mi_chartjs_loaded'])): $GLOBALS['mi_chartjs_loaded'] = true; ?>
        <script src="<?= $base ?>/assets/vendor/chartjs/chart.umd.min.js"></script>
    <?php endif; ?>
    <script>
    (function () {
        var axes = <?= json_encode(array_map(static fn($a) => ['l' => $a['label'], 'v' => $a['value']], $axes), JSON_UNESCAPED_UNICODE) ?>;
        var css = getComputedStyle(document.body), c = (css.getPropertyValue('--ci-primary') || '#bbf246').trim(); Chart.defaults.color = (css.getPropertyValue('--ci-text-2') || '#94a3b8').trim(); Chart.defaults.borderColor = (css.getPropertyValue('--ci-border') || '#334155').trim();
        new Chart(document.getElementById('<?= $chartId ?>'), {
            type: 'radar',
            data: { labels: axes.map(function (a) { return a.l; }), datasets: [{ data: axes.map(function (a) { return a.v; }), borderColor: c, backgroundColor: c + '33', borderWidth: 2, pointRadius: 3 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { r: { min: 0, max: 100, ticks: { display: false, stepSize: 25 }, pointLabels: { font: { size: 13 } } } } }
        });
    })();
    </script>
    <ul class="mi-trend-table">
        <?php foreach ($axes as $a): ?>
            <li><span><?= htmlspecialchars($a['label']) ?></span><b><?= number_format($a['value']) ?></b><em><?= htmlspecialchars($a['note']) ?></em></li>
        <?php endforeach; ?>
    </ul>
    <p class="meta">กราฟนี้วัดเฉพาะข้อมูลราคาของสเปกนี้ ไม่ใช่ความแรงของเครื่อง ค่าพลังอยู่ในกราฟด้านบน ซึ่งคำนวณจากสเปกของผู้ผลิต ไม่มีผลทดสอบจริง</p>
</div>
