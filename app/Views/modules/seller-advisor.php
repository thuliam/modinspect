<section class="container page narrow">
<span class="eyebrow">SELLER PRICE ADVISOR · PARTIAL</span><h1>ตั้งราคาอย่างมีเหตุผล โดยไม่ถูกกดราคา</h1>
<form class="card form" method="get">
    <label>สินค้า<select name="product_id" required><?php foreach($products as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (string)($_GET['product_id']??'')===(string)$p['id']?'selected':'' ?>><?= htmlspecialchars($p['full_name']) ?></option><?php endforeach; ?></select></label>
    <div class="two"><label>สภาพ<select name="condition"><option value="like_new">เหมือนใหม่</option><option value="good">ดี</option><option value="fair">พอใช้</option><option value="poor">มีตำหนิ</option></select></label><label>ต้องการขายภายใน<select disabled><option>ยังไม่เปิดใช้งาน</option></select></label></div>
    <button>ประเมินราคาขาย</button>
</form>
<?php if(isset($_GET['product_id']) && !$result): ?><div class="empty">ข้อมูลตลาดยังไม่เพียงพอสำหรับรุ่นนี้ เพราะยังไม่มีชุดข้อมูลราคาที่ผ่านการตรวจแล้วพร้อมใช้กับคำแนะนำสาธารณะ</div><?php endif; ?>
<?php if($result): ?><div class="zone-grid"><div class="card"><span class="meta">ขายไว</span><div class="range">฿<?= number_format($result['fast']) ?></div><p>เหมาะเมื่อต้องการลดเวลาถือสินค้า</p></div><div class="card featured"><span class="meta">ราคาตลาด</span><div class="range">฿<?= number_format($result['market']) ?></div><p>อ้างอิงค่ากลางของข้อมูลล่าสุด</p></div><div class="card"><span class="meta">พรีเมียม</span><div class="range">฿<?= number_format($result['premium']) ?></div><p>ควรมีประกัน กล่อง และผลทดสอบรองรับ</p></div></div><?php endif; ?>
</section>
