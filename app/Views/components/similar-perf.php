<?php
/** LS-51: neighbours by performance. @var array $similarPerf rows from Product::similarByScore(); @var ?float $myInno this model's overall published score; @var string $base
 *  Reference only: the InnoReviews overall score (editorial, 0-100), shown with the price when the neighbour has one. */
if (empty($similarPerf)) { return; }
?>
<ul class="mi-similar">
    <?php foreach ($similarPerf as $s): $d = (float) $s['inno_diff']; $priced = \App\Services\Catalog\PriceList::isPriced($s); ?>
        <li>
            <a href="<?= $base ?>/price/<?= rawurlencode($s['slug']) ?>">
                <span class="mi-similar-name"><?= htmlspecialchars($s['full_name']) ?></span>
                <span class="mi-similar-why">คะแนนรวม <?= rtrim(rtrim(number_format((float) $s['inno'], 1), '0'), '.') ?><?= $priced ? ' · ฿' . number_format((float) $s['median']) : ' · ยังไม่มีราคาตลาด' ?></span>
            </a>
            <span class="mi-similar-price"><b><?= rtrim(rtrim(number_format((float) $s['inno'], 1), '0'), '.') ?></b><small class="<?= $d >= 0 ? 'up' : 'down' ?>"><?= $d >= 0 ? '+' : '−' ?><?= number_format(abs($d), 1) ?> จุด</small></span>
        </li>
    <?php endforeach; ?>
</ul>
<p class="mi-trend-note mb-0">คะแนนรวมบรรณาธิการ 0-100 จาก InnoReviews (CC BY 4.0) ไม่ใช่ผลวัดดิบ ใช้เทียบเฉพาะรุ่นในหมวดเดียวกัน</p>
