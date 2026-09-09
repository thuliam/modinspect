<section class="container page">
<span class="eyebrow">SELLER CENTER · PARTIAL</span><h1>แดชบอร์ดผู้ขาย</h1>
<div class="notice">Seller Center ยังเป็น prototype เฉพาะการดูราคาและ Seller Price Advisor ยังไม่มีบัญชีผู้ขายหรือรายการที่บันทึกจริง</div>
<div class="kpi-grid">
    <div class="card"><span>ราคาที่ประเมินเดือนนี้</span><b>ยังไม่เปิดใช้งาน</b></div>
    <div class="card"><span>รายการที่บันทึก</span><b>ยังไม่เปิดใช้งาน</b></div>
    <div class="card"><span>ข้อมูลที่พร้อมแสดง</span><b><?= count($products) ?></b></div>
    <div class="card"><span>Auto listing</span><b>OFF</b></div>
</div>
<div class="section-head"><h2>รุ่นที่น่าสนใจ</h2><a href="<?= $base ?>/seller-price-advisor">ประเมินราคาใหม่ →</a></div>
<div class="cards"><?php foreach($products as $product) require ROOT_PATH.'/app/Views/components/product-card.php'; ?></div>
</section>
