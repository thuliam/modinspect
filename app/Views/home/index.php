<section class="draft-hero"><div class="container hero-center">
<span class="live-chip"><span class="material-symbols-outlined">monitoring</span> REVIEWED PRICE DATA</span>
<h1>เช็กราคาคอมมือสอง <em>ก่อนจ่ายเงินจริง</em></h1>
<p>ดูช่วงราคาตลาดเมื่อมีข้อมูลที่ผ่านการตรวจแล้วเพียงพอ และบอกตรงเมื่อข้อมูลยังไม่พร้อม</p>
<form class="draft-search" action="<?= $base ?>/price" method="get"><span class="material-symbols-outlined">search</span><input name="q" placeholder="ค้นหา CPU, GPU หรือรุ่นสินค้า" required><button>เช็กราคา</button><span class="button ghost disabled-control">อัปโหลดรูป — ยังไม่เปิดใช้งาน</span></form>
<div class="suggestions">แนะนำตอนนี้: <a href="<?= $base ?>/price?q=5700X3D">5700X3D</a><a href="<?= $base ?>/price?q=RTX+3070">RTX 3070</a><a href="<?= $base ?>/price?q=RX+6700">RX 6700</a></div>
</div></section>
<section class="container market-strip">
    <div><small>รุ่นติดตาม</small><b><?= (int)$stats['products'] ?></b><span>Product Master</span></div>
    <div><small>REVIEW QUEUE</small><b><?= (int)$stats['pending'] ?></b><span>Human-controlled</span></div>
    <div><small>REVIEWED DATA</small><b><?= (int)$stats['accepted_observations'] ?></b><span>แหล่งคำนวณราคา</span></div>
</section>
<section class="container category-section"><h2>หมวดหมู่ยอดนิยม</h2><div class="category-grid"><?php foreach([['memory','CPU',$stats['cpu_products'].' รุ่น'],['developer_board','GPU',$stats['gpu_products'].' รุ่น'],['view_module','RAM','กำลังพัฒนา'],['database','Storage','กำลังพัฒนา'],['settings_input_component','Board','กำลังพัฒนา'],['bolt','PSU','กำลังพัฒนา'],['monitor','Set PC','กำลังพัฒนา']] as $c): ?><a href="<?= $base ?>/price?category=<?= urlencode(strtolower($c[1])) ?>"><i class="material-symbols-outlined"><?= $c[0] ?></i><b><?= $c[1] ?></b><small><?= htmlspecialchars((string)$c[2]) ?></small></a><?php endforeach; ?></div></section>
<section class="how-section"><div class="container"><h2>วิธีใช้งานใน 3 ขั้นตอน</h2><div class="how-grid"><div><i>1</i><h3>ค้นหา</h3><p>พิมพ์ชื่อรุ่นอุปกรณ์ที่คุณสนใจ</p></div><div><i>2</i><h3>ดูข้อมูลราคา</h3><p>ระบบจะแสดงช่วงราคาเฉพาะเมื่อมีข้อมูลที่ผ่านการตรวจแล้ว</p></div><div><i>3</i><h3>ตัดสินใจ</h3><p>ใช้ Deal Checker และข้อจำกัดของข้อมูลประกอบการตรวจสินค้าเอง</p></div></div></div></section>
<section class="container deal-banner"><div><h2>เจอดีลที่น่าสงสัย?</h2><p>ลองใช้ <b>Deal Checker</b> ประเมินตำแหน่งราคาเมื่อสินค้านั้นมีข้อมูลตลาดเพียงพอ</p></div><a class="button" href="<?= $base ?>/deal-checker"><span class="material-symbols-outlined">fact_check</span> ใช้ Deal Checker</a></section>
<section class="container partner-section"><div class="section-head"><h2>สินค้าแนะนำจากพาร์ทเนอร์</h2><span class="meta">ยังไม่เปิดใช้งาน</span></div><div class="empty">พื้นที่โฆษณา/พาร์ทเนอร์ยังไม่เชื่อม backend จึงไม่แสดงข้อเสนอปลอม</div></section>
