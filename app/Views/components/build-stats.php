<?php
/**
 * LS-14 Module 6 (OD-1): the six spec-based stats of a saved build as a hexagon radar, and the compatibility checks.
 * @var list<array{key:string,label:string,value:?float,coverage:float,reason:string}> $buildStats  @var list<array{label:string,status:string,text:string}> $buildChecks
 */
$buildStats = $buildStats ?? []; $buildChecks = $buildChecks ?? [];
$statsId = 'stats' . bin2hex(random_bytes(4));
$statusText = ['ok' => 'ผ่าน', 'fail' => 'ไม่ผ่าน', 'unknown' => 'ตรวจไม่ได้'];
$statusIcon = ['ok' => 'fa-check-circle text-success', 'fail' => 'fa-exclamation-triangle text-danger', 'unknown' => 'fa-question-circle text-warning'];
?>
<?php if ($buildStats): ?>
<div class="build-radar">
    <h3>ค่าพลังของสเปกนี้</h3>
    <div class="build-radar-canvas"><canvas id="<?= $statsId ?>" role="img" aria-label="ค่าพลัง <?= htmlspecialchars(implode(', ', array_map(static fn($a) => $a['label'] . ' ' . ($a['value'] === null ? 'ไม่มีข้อมูล' : (string)round($a['value'])), $buildStats))) ?>"></canvas></div>
    <?php if (empty($GLOBALS['mi_chartjs_loaded'])): $GLOBALS['mi_chartjs_loaded'] = true; ?>
        <script src="<?= $base ?>/assets/vendor/chartjs/chart.umd.min.js"></script>
    <?php endif; ?>
    <script>
    (function () {
        var axes = <?= json_encode(array_map(static fn($a) => ['l' => $a['label'], 'v' => $a['value']], $buildStats), JSON_UNESCAPED_UNICODE) ?>;
        var css = getComputedStyle(document.body), c = (css.getPropertyValue('--ci-primary') || '#bbf246').trim(); Chart.defaults.color = (css.getPropertyValue('--ci-text-2') || '#94a3b8').trim(); Chart.defaults.borderColor = (css.getPropertyValue('--ci-border') || '#334155').trim();
        new Chart(document.getElementById('<?= $statsId ?>'), {
            type: 'radar',
            data: { labels: axes.map(function (a) { return a.l; }), datasets: [{ data: axes.map(function (a) { return a.v; }), spanGaps: true, borderColor: c, backgroundColor: c + '55', borderWidth: 2, pointRadius: 4, pointBackgroundColor: c }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { r: { min: 0, max: 100, ticks: { display: false, stepSize: 25 }, pointLabels: { font: { size: 13, weight: '600' } } } } }
        });
    })();
    </script>
    <ul class="mi-trend-table">
        <?php foreach ($buildStats as $a): ?>
            <li><span><?= htmlspecialchars($a['label']) ?></span><b><?= $a['value'] === null ? 'ไม่มีข้อมูล' : number_format($a['value']) ?></b><em><?= htmlspecialchars($a['reason']) ?></em></li>
        <?php endforeach; ?>
    </ul>
    <p class="meta"><?= htmlspecialchars(\App\Services\Build\BuildStats::NOTE) ?> (<?= \App\Services\Build\BuildStats::VERSION ?>)</p>
</div>
<?php endif; ?>
<?php if ($buildChecks): ?>
<div class="build-checks">
    <h3>ชิ้นส่วนเข้ากันได้ไหม</h3>
    <ul class="list-unstyled mb-0">
        <?php foreach ($buildChecks as $chk): ?>
            <li class="mb-2"><i class="fas <?= $statusIcon[$chk['status']] ?? 'fa-question-circle' ?>" aria-hidden="true"></i> <b><?= htmlspecialchars($chk['label']) ?>: <?= $statusText[$chk['status']] ?? '' ?></b> <span><?= htmlspecialchars($chk['text']) ?></span></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
