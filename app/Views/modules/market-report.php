<?php $readyProducts=array_values(array_filter($products,fn($p)=>!empty($p['median']) && (int)($p['valid_sample_size']??0)>0 && (int)($p['accepted_observation_count']??0)>0)); ?>
<section class="container page">
<span class="eyebrow">MARKET REPORT · PARTIAL</span><h1>ภาพรวมตลาดคอมมือสอง</h1>
<div class="notice">รายงานนี้ใช้เฉพาะข้อมูลราคาที่ผ่านการตรวจแล้วเท่านั้น ข้อมูล mock/test หรือรายการที่ยังรอตรวจไม่ถูกนับเป็นราคาตลาดจริง</div>
<div class="kpi-grid"><div class="card"><span>รุ่นที่ติดตาม</span><b><?= count($products) ?></b></div><div class="card"><span>พร้อมรายงาน</span><b><?= count($readyProducts) ?></b></div><div class="card"><span>Live provider</span><b>OFF</b></div><div class="card"><span>Auto-accept</span><b>OFF</b></div></div>
<div class="card table-card"><h2>การเคลื่อนไหวรายรุ่น</h2><table><thead><tr><th>สินค้า</th><th>ช่วงตลาด</th><th>ค่ากลาง</th><th>ตัวอย่าง</th><th>สถานะ</th></tr></thead><tbody>
<?php foreach($products as $p): $ready=!empty($p['median']) && (int)($p['valid_sample_size']??0)>0 && (int)($p['accepted_observation_count']??0)>0; ?><tr><td><?= htmlspecialchars($p['full_name']) ?></td><td><?= $ready?'฿'.number_format((float)$p['q1']).'–฿'.number_format((float)$p['q3']):'ข้อมูลตลาดยังไม่เพียงพอ' ?></td><td><?= $ready?'฿'.number_format((float)$p['median']):'-' ?></td><td><?= $ready?(int)$p['valid_sample_size']:0 ?></td><td><span class="badge"><?= $ready?'พร้อมแสดง':'รอข้อมูลที่ตรวจแล้ว' ?></span></td></tr><?php endforeach; ?>
<?php if(!$products): ?><tr><td colspan="5" class="empty-cell">ยังไม่มีสินค้าใน Product Master</td></tr><?php endif; ?>
</tbody></table></div>
</section>
