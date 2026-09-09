<article class="card product-card">
<div class="meta"><?= htmlspecialchars($product['category_name']) ?> · <?= htmlspecialchars($product['brand_name']) ?></div>
<h3><a href="<?= $base ?>/price/<?= rawurlencode($product['slug']) ?>"><?= htmlspecialchars($product['full_name']) ?></a></h3>
<?php $hasMarketSnapshot=!empty($product['median']) && (int)($product['valid_sample_size']??0)>0 && (int)($product['accepted_observation_count']??0)>0; ?>
<?php if ($hasMarketSnapshot): ?><?php $manifest=json_decode((string)($product['calculation_manifest'] ?? ''),true) ?: []; $confidence=$manifest['confidence'] ?? []; ?>
<div class="range">฿<?= number_format((float)$product['q1']) ?>–<?= number_format((float)$product['q3']) ?></div>
<p>ค่ากลาง ฿<?= number_format((float)$product['median']) ?> · <?= (int)$product['valid_sample_size'] ?> ตัวอย่าง</p>
<span class="badge">ความมั่นใจ <?= htmlspecialchars(str_replace(['high','medium','low','insufficient'],['สูง','ปานกลาง','ต่ำ','ไม่พอ'],$product['confidence_label'])) ?></span>
<?php if(!empty($confidence['reasons'])): ?><small><?= htmlspecialchars(implode(' · ',array_slice($confidence['reasons'],0,2))) ?></small><?php endif; ?>
<?php else: ?><p class="muted">ข้อมูลตลาดยังไม่เพียงพอ</p><?php endif; ?>
</article>
