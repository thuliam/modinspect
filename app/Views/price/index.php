<section class="container page"><span class="eyebrow">PRICE INDEX</span><h1>ดัชนีราคาคอมมือสอง</h1>
<form class="filters"><input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="ค้นหารุ่น"><select name="category"><option value="">ทุกหมวด</option><?php foreach(['cpu'=>'CPU','gpu'=>'GPU','ram'=>'RAM','storage'=>'Storage'] as $v=>$l): ?><option value="<?= $v ?>" <?= $category===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select><button>ค้นหา</button></form>
<p class="muted">พบ <?= count($products) ?> รุ่น · ช่วงราคาเป็นราคาประกาศที่ผ่านการคัดกรอง</p>
<div class="cards"><?php foreach($products as $product) require ROOT_PATH.'/app/Views/components/product-card.php'; ?></div>
<?php if(!$products): ?><div class="empty">ไม่พบสินค้าที่ค้นหา ลองใช้ชื่อรุ่นแบบสั้น เช่น “3070”</div><?php endif; ?></section>

