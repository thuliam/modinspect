<section class="container page">
<span class="eyebrow">LOCAL MARKET MATCH · NOT ENABLED</span><h1>จับคู่ข้อมูลราคากับตลาดใกล้คุณ</h1>
<div class="card filter-strip disabled-panel">
    <label>จังหวัด<select disabled><option>กรุงเทพมหานคร</option></select></label>
    <label>ระยะทาง<select disabled><option>25 กม.</option></select></label>
    <button class="disabled-control" type="button" disabled><span class="material-symbols-outlined">near_me</span> ค้นหาข้อมูลใกล้ฉัน — ยังไม่เปิดใช้งาน</button>
</div>
<div class="empty">Local Match ยังไม่มี backend สำหรับ location, consent, หรือ source policy เฉพาะพื้นที่ จึงไม่แสดงระยะทางหรือประกาศตัวอย่างปลอม</div>
<div class="cards"><?php foreach(array_slice($products,0,3) as $product) require ROOT_PATH.'/app/Views/components/product-card.php'; ?></div>
</section>
