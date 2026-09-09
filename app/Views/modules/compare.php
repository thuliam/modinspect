<section class="container page">
<span class="eyebrow">PRODUCT COMPARISON</span><h1>เปรียบเทียบราคาและความคุ้มค่า</h1><p class="lead">เทียบข้อมูลเฉพาะรุ่นที่มีชุดข้อมูลราคาที่ผ่านการตรวจแล้ว</p>
<div class="compare-grid">
<?php foreach(array_slice($products,0,4) as $p): $ready=!empty($p['median']) && (int)($p['valid_sample_size']??0)>0 && (int)($p['accepted_observation_count']??0)>0; ?>
<article class="card"><span class="meta"><?= htmlspecialchars($p['category_name']) ?></span><h2><?= htmlspecialchars($p['full_name']) ?></h2>
<?php if($ready): ?><div class="range">฿<?= number_format((float)$p['q1']) ?>–<?= number_format((float)$p['q3']) ?></div><dl><dt>ค่ากลาง</dt><dd>฿<?= number_format((float)$p['median']) ?></dd><dt>ตัวอย่าง</dt><dd><?= (int)$p['valid_sample_size'] ?></dd><dt>ความมั่นใจ</dt><dd><?= htmlspecialchars($p['confidence_label']??'insufficient') ?></dd><dt>รายละเอียด</dt><dd><?= htmlspecialchars($p['spec_summary']??'-') ?></dd></dl><?php else: ?><div class="empty">ข้อมูลตลาดยังไม่เพียงพอ</div><?php endif; ?>
<a class="button ghost" href="<?= $base ?>/price/<?= $p['slug'] ?>">ดูรุ่นนี้</a></article>
<?php endforeach; ?>
</div>
</section>
