<section class="container page">
<span class="eyebrow">ศูนย์ผู้ขาย · ใช้ได้บางส่วน</span><h1>แดชบอร์ดผู้ขาย</h1>
<div class="notice">ศูนย์ผู้ขายยังเป็นต้นแบบ ใช้ดูราคาและใช้ผู้ช่วยตั้งราคาขายได้เท่านั้น ยังไม่มีบัญชีผู้ขายหรือรายการที่บันทึกจริง</div>
<div class="kpi-grid">
    <div class="card"><span>ราคาที่ประเมินเดือนนี้</span><b>ยังไม่เปิดใช้งาน</b></div>
    <div class="card"><span>รายการที่บันทึก</span><b>ยังไม่เปิดใช้งาน</b></div>
    <div class="card"><span>ข้อมูลที่พร้อมแสดง</span><b><?= count($products) ?></b></div>
    <div class="card"><span>ลงประกาศอัตโนมัติ</span><b>ปิดอยู่</b></div>
</div>
<div class="section-head"><h2>รุ่นที่น่าสนใจ</h2><?php if (!empty($config['seller_advisor']['enabled'])): ?><a href="<?= $base ?>/seller-price-advisor">ประเมินราคาใหม่ →</a><?php endif; ?></div>
<div class="cards"><?php foreach($products as $product) require ROOT_PATH.'/app/Views/components/product-card.php'; ?></div>
</section>
