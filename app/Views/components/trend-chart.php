<?php
/** @var array $trend rows from Product::trend() (period_start, q1, median, q3, sample_size) */
$n = count($trend);
if ($n < 1) { return; }
$thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
$label = static function (string $d) use ($thaiMonths): string {
    $t = strtotime($d);
    return $thaiMonths[(int)date('n', $t)] . ' ' . substr((string)((int)date('Y', $t) + 543), 2);
};
$firstMed = (float)$trend[0]['median'];
$lastMed = (float)$trend[$n - 1]['median'];
$delta = $firstMed > 0 ? ($lastMed - $firstMed) / $firstMed * 100 : 0;
?>
<div class="mi-trend">
    <?php if ($n > 1): $chartId = 'trend' . bin2hex(random_bytes(4)); ?>
    <div class="mi-trend-canvas"><canvas id="<?= $chartId ?>" data-chart="trend" data-source="<?= $chartId ?>-data" role="img" aria-label="กราฟราคากลางรายเดือน แถบสีคือช่วงราคาส่วนกลาง"></canvas></div>
    <?php if (empty($GLOBALS['mi_chartjs_loaded'])): $GLOBALS['mi_chartjs_loaded'] = true; ?>
        <script src="<?= $base ?>/assets/vendor/chartjs/chart.umd.min.js"></script>
        <script src="<?= $base ?>/assets/mi-charts.js?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/mi-charts.js') ?>" defer></script>
    <?php endif; ?>
    <?php // LS-19 D1: data block (not run), drawn by mi-charts.js ?>
    <script type="application/json" id="<?= $chartId ?>-data"><?= json_encode(array_map(static fn($r) => ['m' => $label($r['period_start']), 'q1' => (float)$r['q1'], 'med' => (float)$r['median'], 'q3' => (float)$r['q3'], 'n' => (int)$r['sample_size']], $trend), 259) ?></script>
    <?php endif; ?>
    <ul class="mi-trend-table">
        <?php foreach ($trend as $r): ?>
            <li><span><?= $label($r['period_start']) ?></span><b>฿<?= number_format((float)$r['median']) ?></b><em>฿<?= number_format((float)$r['q1']) ?>–<?= number_format((float)$r['q3']) ?> · <?= (int)$r['sample_size'] ?> ตัวอย่าง</em></li>
        <?php endforeach; ?>
    </ul>
    <?php if ($n > 1): ?>
        <p class="mi-trend-note">ราคากลางเปลี่ยน <?= $delta >= 0 ? '+' : '' ?><?= number_format($delta, 1) ?>% จาก <?= $label($trend[0]['period_start']) ?> ถึง <?= $label($trend[$n - 1]['period_start']) ?> นับเฉพาะเดือนที่มีตัวอย่างอย่างน้อย 3 รายการ และเป็นราคาประกาศ ไม่ใช่ราคาปิดดีล</p>
    <?php else: ?>
        <p class="mi-trend-note">ตอนนี้มีเพียงหนึ่งเดือนที่มีตัวอย่างพอ ยังบอกแนวโน้มไม่ได้ ระบบจะวาดเส้นเมื่อมีข้อมูลจากเดือนอื่นเพิ่มเติม</p>
    <?php endif; ?>
</div>
