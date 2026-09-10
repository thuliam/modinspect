<section class="mi-hero">
    <div class="container mi-hero-grid">
        <div class="mi-hero-copy">
            <span class="eyebrow">ModInspect</span>
            <h1>เช็กราคา เช็กสภาพ ก่อนซื้อขายคอมมือสอง</h1>
            <p class="lead">ค้นหารุ่น CPU/GPU มือสอง ดูช่วงราคาที่พบ ราคากลาง ความน่าเชื่อถือ และใช้ Deal Checker เปรียบเทียบราคาที่คุณเจออย่างเป็นกลาง</p>
            <form class="mi-search" action="<?= $base ?>/price" method="get">
                <label for="home-search">ค้นหารุ่นสินค้า</label>
                <div>
                    <input id="home-search" name="q" placeholder="เช่น Ryzen 7 5700X3D หรือ RTX 3070" required>
                    <button type="submit">เช็กราคา</button>
                </div>
            </form>
            <div class="mi-quick-links" aria-label="Search examples">
                <span>ตัวอย่าง:</span>
                <a href="<?= $base ?>/price?q=5700X3D">5700X3D</a>
                <a href="<?= $base ?>/price?q=RTX+3070">RTX 3070</a>
                <a href="<?= $base ?>/price?q=RX+6700">RX 6700</a>
            </div>
            <p class="mi-data-note">ยังเป็น POC: ถ้าข้อมูลราคาที่ผ่านการตรวจสอบยังไม่พอ ระบบจะแสดงหน้ารุ่นสินค้าโดยไม่สร้างราคาตลาดปลอม</p>
        </div>
    </div>
</section>

<section class="container mi-section">
    <div class="mi-section-head">
        <div>
            <span class="eyebrow">Price Index</span>
            <h2>เลือกดูรุ่นสินค้า</h2>
            <p>เปิดหน้ารุ่นสินค้าได้แม้ยังไม่มีราคาตลาด เพื่อดูสเปก สถานะข้อมูล และทางเลือกถัดไป</p>
        </div>
        <a class="button ghost" href="<?= $base ?>/price">ดูทั้งหมด</a>
    </div>
    <?php if ($products): ?>
        <div class="cards mi-card-grid">
            <?php foreach ($products as $product) require ROOT_PATH . '/app/Views/components/product-card.php'; ?>
        </div>
    <?php else: ?>
        <div class="mi-empty-state">
            <h3>ข้อมูลตลาดยังไม่เพียงพอ</h3>
            <p>ยังไม่มีสินค้าที่พร้อมแสดงเป็นรายการยอดนิยม ลองค้นหารุ่นที่ต้องการหรืออ่านวิธีคำนวณราคา</p>
            <div class="mi-actions">
                <a class="button" href="<?= $base ?>/price">ค้นหาสินค้า</a>
                <a class="button ghost" href="<?= $base ?>/methodology">วิธีคำนวณ</a>
            </div>
        </div>
    <?php endif; ?>
</section>

<section class="mi-process-band">
    <div class="container mi-process-grid">
        <div>
            <span>1</span>
            <h3>ค้นหารุ่น</h3>
            <p>เริ่มจากชื่อรุ่นหรือรหัสยอดนิยม เช่น 5700X3D, RTX 3070</p>
        </div>
        <div>
            <span>2</span>
            <h3>ดูช่วงราคา</h3>
            <p>ถ้ามีข้อมูลเพียงพอ ระบบจะแสดงช่วงราคาที่พบ ราคากลาง และความน่าเชื่อถือ</p>
        </div>
        <div>
            <span>3</span>
            <h3>เช็กดีล</h3>
            <p>ใส่ราคาที่เจอเพื่อเทียบกับตลาด โดยยังต้องดูสภาพ ประกัน และอุปกรณ์ประกอบเอง</p>
        </div>
    </div>
</section>

<section class="container mi-cta-band">
    <div>
        <span class="eyebrow">Deal Checker</span>
        <h2>เจอราคาประกาศแล้วไม่แน่ใจ?</h2>
        <p>เปรียบเทียบราคาที่คุณเจอกับช่วงราคาตลาดอย่างเป็นกลาง ไม่ตัดสินผู้ขาย และไม่แทนการตรวจสภาพจริง</p>
    </div>
    <a class="button" href="<?= $base ?>/deal-checker">เปิด Deal Checker</a>
</section>
