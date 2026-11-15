<section class="container page">
<span class="eyebrow">เปรียบเทียบสินค้า</span><h1>เปรียบเทียบราคาและความคุ้มค่า</h1><p class="lead">เทียบข้อมูลเฉพาะรุ่นที่มีชุดข้อมูลราคาที่ผ่านการตรวจแล้ว</p>
<div class="compare-grid">
<?php
$isReady=fn(array $p):bool=>!empty($p['median']) && (int)($p['valid_sample_size']??0)>0 && (int)($p['accepted_observation_count']??0)>0;
usort($products,fn($a,$b)=>(int)$isReady($b)<=>(int)$isReady($a));
$confTh=['high'=>'สูง','medium'=>'ปานกลาง','low'=>'จำกัด'];
if(!array_filter(array_slice($products,0,4),$isReady)): ?><p class="mi-note mi-featured-note"><i class="fas fa-info-circle" aria-hidden="true"></i> ยังไม่มีรุ่นที่มีราคาตลาดผ่านการตรวจสอบ จึงยังเปรียบเทียบราคาไม่ได้ ระบบจะแสดงเมื่อมีข้อมูลที่ผ่านการตรวจสอบแล้ว</p><?php endif;
foreach(array_slice($products,0,4) as $p): $ready=!empty($p['median']) && (int)($p['valid_sample_size']??0)>0 && (int)($p['accepted_observation_count']??0)>0; ?>
<article class="card"><span class="meta"><?= htmlspecialchars($p['category_name']) ?></span><h2><?= htmlspecialchars($p['full_name']) ?></h2>
<?php if($ready): ?><div class="range">฿<?= number_format((float)$p['q1']) ?>–<?= number_format((float)$p['q3']) ?></div><dl><dt>ค่ากลาง</dt><dd>฿<?= number_format((float)$p['median']) ?></dd><dt>ตัวอย่าง</dt><dd><?= (int)$p['valid_sample_size'] ?></dd><dt>ความมั่นใจ</dt><dd><?= htmlspecialchars($confTh[strtolower((string)($p['confidence_label']??''))]??'ข้อมูลยังไม่พอ') ?></dd><dt>รายละเอียด</dt><dd><?= htmlspecialchars($p['spec_summary']??'-') ?></dd></dl><?php else: ?><div class="empty">ข้อมูลตลาดยังไม่เพียงพอ</div><?php endif; ?>
<a class="button ghost" href="<?= $base ?>/price/<?= $p['slug'] ?>">ดูรุ่นนี้</a></article>
<?php endforeach; ?>
</div>
</section>
