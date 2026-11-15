<?php
/** @var array $trend rows from Product::trend() (period_start, q1, median, q3, sample_size) */
$n = count($trend);
if ($n < 1) { return; }
$thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
$label = static function (string $d) use ($thaiMonths): string {
    $t = strtotime($d);
    return $thaiMonths[(int)date('n', $t)] . ' ' . substr((string)((int)date('Y', $t) + 543), 2);
};
$W = 640; $H = 220; $padL = 56; $padR = 16; $padT = 16; $padB = 32;
$lo = min(array_map(static fn($r) => (float)$r['q1'], $trend));
$hi = max(array_map(static fn($r) => (float)$r['q3'], $trend));
if ($hi - $lo < 1) { $lo *= .95; $hi *= 1.05; }
$span = $hi - $lo; $lo -= $span * .1; $hi += $span * .1;
$monthIndex = static fn(string $d): int => (int)date('Y', strtotime($d)) * 12 + (int)date('n', strtotime($d));
$first = $monthIndex($trend[0]['period_start']);
$last = $monthIndex($trend[$n - 1]['period_start']);
$x = static fn(string $d) => $last === $first ? ($padL + ($W - $padL - $padR) / 2) : $padL + ($monthIndex($d) - $first) / ($last - $first) * ($W - $padL - $padR);
$y = static fn(float $v) => $padT + (1 - ($v - $lo) / ($hi - $lo)) * ($H - $padT - $padB);
$med = []; $up = []; $down = [];
foreach ($trend as $r) {
    $px = round($x($r['period_start']), 1);
    $med[] = $px . ',' . round($y((float)$r['median']), 1);
    $up[] = $px . ',' . round($y((float)$r['q3']), 1);
    array_unshift($down, $px . ',' . round($y((float)$r['q1']), 1));
}
$firstMed = (float)$trend[0]['median'];
$lastMed = (float)$trend[$n - 1]['median'];
$delta = $firstMed > 0 ? ($lastMed - $firstMed) / $firstMed * 100 : 0;
?>
<div class="mi-trend">
    <svg viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="กราฟราคากลางรายเดือน แถบสีคือช่วงราคาส่วนกลาง" preserveAspectRatio="xMidYMid meet">
        <?php foreach ([0, .5, 1] as $g): $gv = $lo + ($hi - $lo) * $g; ?>
            <line x1="<?= $padL ?>" x2="<?= $W - $padR ?>" y1="<?= round($y($gv), 1) ?>" y2="<?= round($y($gv), 1) ?>" class="mi-trend-grid"/>
            <text x="<?= $padL - 8 ?>" y="<?= round($y($gv), 1) + 4 ?>" text-anchor="end" class="mi-trend-axis">฿<?= number_format($gv) ?></text>
        <?php endforeach; ?>
        <?php if ($n > 1): ?>
            <polygon points="<?= implode(' ', array_merge($up, $down)) ?>" class="mi-trend-band"/>
            <polyline points="<?= implode(' ', $med) ?>" class="mi-trend-line"/>
        <?php endif; ?>
        <?php foreach ($trend as $r): $px = $x($r['period_start']); ?>
            <?php if ($n === 1): ?><line x1="<?= round($px, 1) ?>" x2="<?= round($px, 1) ?>" y1="<?= round($y((float)$r['q1']), 1) ?>" y2="<?= round($y((float)$r['q3']), 1) ?>" class="mi-trend-line"/><?php endif; ?>
            <circle cx="<?= round($px, 1) ?>" cy="<?= round($y((float)$r['median']), 1) ?>" r="4" class="mi-trend-dot"><title><?= $label($r['period_start']) ?> · กลาง ฿<?= number_format((float)$r['median']) ?> · <?= (int)$r['sample_size'] ?> ตัวอย่าง</title></circle>
            <text x="<?= round($px, 1) ?>" y="<?= $H - 10 ?>" text-anchor="middle" class="mi-trend-axis"><?= $label($r['period_start']) ?></text>
        <?php endforeach; ?>
    </svg>
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
