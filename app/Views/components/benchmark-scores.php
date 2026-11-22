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
    <dl class="mi-context-list">
        <?php foreach ($benchmarks as $b): ?>
            <div>
                <dt><?= htmlspecialchars($labels[$b['metric_key']] ?? $b['metric_key']) ?></dt>
                <dd><?= htmlspecialchars(rtrim(rtrim(number_format((float)$b['score_value'], 1), '0'), '.')) ?> <small class="muted">/ 100</small></dd>
            </div>
        <?php endforeach; ?>
    </dl>
    <p class="mi-trend-note">แหล่งที่มา: <a href="<?= htmlspecialchars($first['source_url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($first['source_name']) ?></a> (<?= htmlspecialchars($first['license_note']) ?><?= !empty($first['source_updated_on']) ? ', ข้อมูลอัปเดต ' . htmlspecialchars((string)$first['source_updated_on']) : '' ?>) เป็นคะแนนบรรณาธิการ 0-100 ที่คำนวณจากหลายปัจจัย ไม่ใช่ผลวัดดิบ ใช้เทียบเฉพาะรุ่นในหมวดเดียวกัน และไม่เกี่ยวกับการคำนวณราคา</p>
</section>
