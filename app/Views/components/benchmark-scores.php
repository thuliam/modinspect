<?php
/** @var list<array{metric_key:string,score_value:string,scale_note:string,source_name:string,source_url:string,license_note:string,source_updated_on:?string}> $benchmarks rows from Product::benchmarks() */
use App\Services\Benchmark\BenchmarkMatcher;
$benchmarks = $benchmarks ?? [];
if ($benchmarks === []) { return; }
$labels = BenchmarkMatcher::METRICS[strtolower((string)($product['category_slug'] ?? ''))] ?? [];
$order = array_flip(array_keys($labels));
usort($benchmarks, static fn(array $a, array $b): int => ($order[$a["metric_key"]] ?? 99) <=> ($order[$b["metric_key"]] ?? 99));
$first = $benchmarks[0];
?>
<section class="card mi-facts-card" id="sec-bench" aria-labelledby="mi-bench-title">
    <div class="mi-section-head compact"><div><span class="eyebrow">ข้อมูลอ้างอิง</span><h2 id="mi-bench-title">คะแนนประสิทธิภาพจากผู้ทดสอบ</h2></div></div>
    <?php // LS-36: stat bars (Bootstrap .progress) and a radar (Chart.js, drawn by mi-charts.js from a JSON data block), gamer-style; the numbers are the published scores, nothing is derived
    $fmt = static fn(float $v): string => rtrim(rtrim(number_format($v, 1), '0'), '.');
    $radar = []; foreach ($benchmarks as $b) { if ($b['metric_key'] !== 'inno_score') { $radar[] = ['l' => $labels[$b['metric_key']] ?? $b['metric_key'], 'v' => (float)$b['score_value']]; } }
    if (count($radar) >= 3): $radarId = 'bench' . bin2hex(random_bytes(4)); ?>
        <div class="mi-stat-radar"><canvas id="<?= $radarId ?>" data-chart="radar" data-source="<?= $radarId ?>-data" role="img" aria-label="เรดาร์คะแนน <?= htmlspecialchars(implode(', ', array_map(static fn($r) => $r['l'] . ' ' . $fmt($r['v']), $radar))) ?>"></canvas></div>
        <?php if (empty($GLOBALS['mi_chartjs_loaded'])): $GLOBALS['mi_chartjs_loaded'] = true; ?>
            <script src="<?= $base ?>/assets/vendor/chartjs/chart.umd.min.js"></script>
            <script src="<?= $base ?>/assets/mi-charts.js?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/mi-charts.js') ?>" defer></script>
        <?php endif; ?>
        <script type="application/json" id="<?= $radarId ?>-data"><?= json_encode($radar, 271) ?></script>
    <?php endif; ?>
    <ul class="list-unstyled mi-stats">
        <?php foreach ($benchmarks as $b): $v = max(0.0, min(100.0, (float)$b['score_value'])); $name = $labels[$b['metric_key']] ?? $b['metric_key']; ?>
            <li class="mi-stat<?= $b['metric_key'] === 'inno_score' ? ' mi-stat-main' : '' ?>">
                <span class="mi-stat-l"><?= htmlspecialchars($name) ?></span>
                <div class="progress mi-stat-bar"><div class="progress-bar" role="progressbar" style="width: <?= $fmt($v) ?>%" aria-valuenow="<?= $fmt($v) ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= htmlspecialchars($name) ?>"></div></div>
                <b class="mi-stat-v"><?= $fmt($v) ?></b>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="mi-trend-note">แหล่งที่มา: <a href="<?= htmlspecialchars($first['source_url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($first['source_name']) ?></a> (<?= htmlspecialchars($first['license_note']) ?><?= !empty($first['source_updated_on']) ? ', ข้อมูลอัปเดต ' . htmlspecialchars((string)$first['source_updated_on']) : '' ?>) เป็นคะแนนบรรณาธิการ 0-100 ที่คำนวณจากหลายปัจจัย ไม่ใช่ผลวัดดิบ ใช้เทียบเฉพาะรุ่นในหมวดเดียวกัน และไม่เกี่ยวกับการคำนวณราคา</p>
</section>
