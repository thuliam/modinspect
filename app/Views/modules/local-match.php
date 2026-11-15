<section class="container page">
<span class="eyebrow">ตลาดใกล้คุณ · ยังไม่เปิดใช้</span><h1>จับคู่ข้อมูลราคากับตลาดใกล้คุณ</h1>
<div class="card filter-strip disabled-panel">
    <label>จังหวัด<select disabled><option>กรุงเทพมหานคร</option></select></label>
    <label>ระยะทาง<select disabled><option>25 กม.</option></select></label>
    <button class="disabled-control" type="button" disabled><i class="fas fa-location-arrow" aria-hidden="true"></i> ค้นหาข้อมูลใกล้ฉัน — ยังไม่เปิดใช้งาน</button>
</div>
<div class="empty">ฟีเจอร์นี้ยังไม่มีระบบรองรับตำแหน่งที่ตั้ง การขอความยินยอม และนโยบายแหล่งข้อมูลเฉพาะพื้นที่ จึงไม่แสดงระยะทางหรือประกาศตัวอย่างปลอม</div>
<div class="cards"><?php foreach(array_slice($products,0,3) as $product) require ROOT_PATH.'/app/Views/components/product-card.php'; ?></div>
</section>
