<?php
$counts = $counts ?? ['models' => 0, 'aliases' => 0, 'priced' => 0, 'categories' => 0];
$ico = static fn(string $paths): string => '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
$tools = [
    ['href' => '/price?category=cpu', 'title' => 'เช็กราคา CPU', 'text' => 'ค้นรุ่นซีพียูมือสอง ดูช่วงราคาที่พบเมื่อมีข้อมูลที่ผ่านการตรวจสอบพอ', 'icon' => $ico('<rect x="6" y="6" width="12" height="12" rx="2"/><rect x="10" y="10" width="4" height="4"/><path d="M9 3v3m6-3v3M9 18v3m6-3v3M3 9h3m-3 6h3m12-6h3m-3 6h3"/>')],
    ['href' => '/price?category=gpu', 'title' => 'เช็กราคา GPU', 'text' => 'ค้นรุ่นการ์ดจอมือสอง แยกรุ่นให้ชัด เช่น 3060 กับ 3060 Ti ไม่ปนกัน', 'icon' => $ico('<rect x="3" y="7" width="18" height="10" rx="2"/><circle cx="9" cy="12" r="2.5"/><path d="M15 10v4M18 10v4M5 17v2"/>')],
    ['href' => '/deal-checker', 'title' => 'Deal Checker', 'text' => 'ใส่ราคาที่เจอ ระบบเทียบกับช่วงตลาด และบอกตรงๆ เมื่อข้อมูลยังไม่พอ', 'icon' => $ico('<path d="M4 18V9m6 9V5m6 13v-6m4 6H3"/>')],
    ['href' => '/methodology', 'title' => 'วิธีคำนวณ', 'text' => 'ดูว่าราคามาจากไหน ราคาประกาศต่างจากราคาปิดดีลอย่างไร และข้อจำกัดของข้อมูล', 'icon' => $ico('<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>')],
];
?>
<section class="mi-hero">
    <canvas id="cloud" aria-hidden="true"></canvas>
    <div class="container mi-hero-grid">
        <div class="mi-hero-copy">
            <span class="eyebrow">ModInspect</span>
            <h1><span class="nowrap">เช็กราคา</span> <span class="nowrap">เช็กสภาพ</span> <span class="nowrap">ก่อนซื้อขายคอมมือสอง</span></h1>
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

<section class="vs-services" aria-labelledby="vs-tools-title">
    <div class="container vs-services-grid">
        <div class="vs-intro">
            <h2 id="vs-tools-title" class="reveal">เช็กราคา ก่อนตัดสินใจซื้อขาย</h2>
            <p class="vs-bar reveal" style="--d:120ms">เริ่มจากรุ่นที่สนใจ แล้วดูช่วงราคาที่พบ ที่มาของตัวเลข และความน่าเชื่อถือ ไม่มีราคาลอยที่อธิบายที่มาไม่ได้</p>
        </div>
        <div class="vs-cards" data-parallax>
            <?php foreach ($tools as $i => $tool): ?>
                <a class="vs-card reveal" style="--d:<?= $i * 90 ?>ms" href="<?= $base . $tool['href'] ?>">
                    <span class="vs-ico"><?= $tool['icon'] ?></span>
                    <h3><?= htmlspecialchars($tool['title']) ?></h3>
                    <p><?= htmlspecialchars($tool['text']) ?></p>
                </a>
            <?php endforeach; ?>
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
        <?php require ROOT_PATH . '/app/Views/components/product-list.php'; ?>
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

<section class="vs-statement">
    <div class="container vs-two">
        <h2 class="reveal">ค้นหา.<br>เทียบ.<br>ตัดสินใจ.</h2>
        <div class="reveal" style="--d:120ms">
            <p class="vs-bar">ราคาที่แสดงคือราคาประกาศมือสองที่ผ่านการคัดกรอง ไม่ใช่ราคาปิดดีล ต้องดูสภาพ ประกัน และอุปกรณ์ประกอบเองเสมอ</p>
            <ol class="vs-steps">
                <li><b>ค้นหารุ่น</b><span>เริ่มจากชื่อรุ่นหรือรหัสยอดนิยม เช่น 5700X3D, RTX 3070</span></li>
                <li><b>ดูช่วงราคา</b><span>ถ้ามีข้อมูลเพียงพอ ระบบจะแสดงช่วงราคาที่พบ ราคากลาง และความน่าเชื่อถือ</span></li>
                <li><b>เช็กดีล</b><span>ใส่ราคาที่เจอเพื่อเทียบกับตลาด โดยยังต้องดูสภาพ ประกัน และอุปกรณ์ประกอบเอง</span></li>
            </ol>
            <a class="button" href="<?= $base ?>/methodology">อ่านวิธีคำนวณ</a>
        </div>
    </div>
</section>

<section class="vs-numbers" aria-labelledby="vs-num-title">
    <div class="container">
        <h2 id="vs-num-title" class="reveal">ตัวเลขของระบบวันนี้</h2>
        <div class="vs-num-grid">
            <div class="reveal"><b data-count="<?= (int) $counts['models'] ?>"><?= (int) $counts['models'] ?></b><span>รุ่นในฐานข้อมูล</span></div>
            <div class="reveal" style="--d:90ms"><b data-count="<?= (int) $counts['aliases'] ?>"><?= (int) $counts['aliases'] ?></b><span>ชื่อเรียกที่ระบบรู้จัก</span></div>
            <div class="reveal" style="--d:180ms"><b data-count="<?= (int) $counts['categories'] ?>"><?= (int) $counts['categories'] ?></b><span>หมวดสินค้า</span></div>
            <div class="reveal" style="--d:270ms"><b data-count="<?= (int) $counts['priced'] ?>"><?= (int) $counts['priced'] ?></b><span>รุ่นที่มีราคาผ่านการตรวจสอบ</span></div>
        </div>
        <p class="vs-num-note reveal">ตัวเลขนับจากฐานข้อมูลจริง ไม่ปัดขึ้น ถ้ายังเป็น 0 คือยังไม่มีข้อมูลที่ผ่านการตรวจสอบพอจะแสดงราคา</p>
    </div>
</section>

<section class="vs-cta">
    <div class="container vs-two">
        <div class="reveal">
            <span class="eyebrow">Deal Checker</span>
            <h2>เจอราคาประกาศแล้วไม่แน่ใจ?</h2>
        </div>
        <div class="reveal" style="--d:120ms">
            <p class="vs-bar">เปรียบเทียบราคาที่คุณเจอกับช่วงราคาตลาดอย่างเป็นกลาง ไม่ตัดสินผู้ขาย และไม่แทนการตรวจสภาพจริง</p>
            <a class="button" href="<?= $base ?>/deal-checker">เปิด Deal Checker</a>
        </div>
    </div>
</section>
