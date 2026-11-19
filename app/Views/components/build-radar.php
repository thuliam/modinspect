<?php
/** @var array $result a saved build: budget_max and items (see SpecBuild::find) */
$axes = \App\Services\SpecRadar::axes($result['items'], (float)$result['budget_max']);
$chartId = 'radar' . bin2hex(random_bytes(4));
?>
<div class="build-radar">
    <h3>ความน่าเชื่อถือของข้อมูลราคา</h3>
    <div class="build-radar-canvas"><canvas id="<?= $chartId ?>" data-chart="radar" data-source="<?= $chartId ?>-data" role="img" aria-label="เรดาร์ <?= htmlspecialchars(implode(', ', array_map(static fn($a) => $a['label'] . ' ' . $a['value'], $axes))) ?>"></canvas></div>
    <?php if (empty($GLOBALS['mi_chartjs_loaded'])): $GLOBALS['mi_chartjs_loaded'] = true; ?>
        <script src="<?= $base ?>/assets/vendor/chartjs/chart.umd.min.js"></script>
        <script src="<?= $base ?>/assets/mi-charts.js?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/mi-charts.js') ?>" defer></script>
    <?php endif; ?>
    <?php // LS-19 D1: data block (not run), drawn by mi-charts.js ?>
    <script type="application/json" id="<?= $chartId ?>-data"><?= json_encode(array_map(static fn($a) => ['l' => $a['label'], 'v' => $a['value']], $axes), 259) ?></script>
    <ul class="mi-trend-table">
        <?php foreach ($axes as $a): ?>
            <li><span><?= htmlspecialchars($a['label']) ?></span><b><?= number_format($a['value']) ?></b><em><?= htmlspecialchars($a['note']) ?></em></li>
        <?php endforeach; ?>
    </ul>
    <p class="meta">กราฟนี้วัดเฉพาะข้อมูลราคาของสเปกนี้ ไม่ใช่ความแรงของเครื่อง ค่าพลังอยู่ในกราฟด้านบน ซึ่งคำนวณจากสเปกของผู้ผลิต ไม่มีผลทดสอบจริง</p>
</div>
