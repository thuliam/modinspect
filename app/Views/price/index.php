<section class="container page mi-price-index">
    <div class="mi-page-heading">
        <span class="eyebrow">Price Index</span>
        <h1>เช็กราคาคอมมือสอง</h1>
        <p class="lead">ค้นหา CPU/GPU แล้วดูช่วงราคาที่พบ ราคากลาง ความน่าเชื่อถือ และข้อมูลล่าสุดเมื่อมีข้อมูลที่ผ่านการตรวจสอบพอ</p>
    </div>

    <form class="mi-filter-card" action="<?= $base ?>/price" method="get">
        <label for="price-search">ค้นหารุ่นสินค้า</label>
        <div class="mi-filter-row">
            <input id="price-search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="เช่น Ryzen 7 5700X3D, RTX 3070">
            <select name="category" aria-label="หมวดหมู่สินค้า">
                <option value="">ทุกหมวด</option>
                <?php foreach (['cpu' => 'CPU', 'gpu' => 'GPU', 'ram' => 'RAM', 'storage' => 'Storage'] as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $category === $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">ค้นหา</button>
        </div>
        <div class="mi-category-chips" aria-label="Quick category filters">
            <a href="<?= $base ?>/price?category=cpu" class="<?= $category === 'cpu' ? 'active' : '' ?>">CPU</a>
            <a href="<?= $base ?>/price?category=gpu" class="<?= $category === 'gpu' ? 'active' : '' ?>">GPU</a>
            <a href="<?= $base ?>/price" class="<?= $category === '' && $q === '' ? 'active' : '' ?>">ทั้งหมด</a>
        </div>
    </form>

    <div class="mi-result-summary">
        <strong>พบ <?= count($products) ?> รุ่น</strong>
        <span>ช่วงราคาคือราคาประกาศที่ผ่านการคัดกรอง ไม่ใช่ราคาปิดดีล</span>
    </div>

    <?php if ($products): ?>
        <div class="cards mi-card-grid">
            <?php foreach ($products as $product) require ROOT_PATH . '/app/Views/components/product-card.php'; ?>
        </div>
    <?php else: ?>
        <div class="mi-empty-state">
            <h2>ไม่พบสินค้าที่ค้นหา</h2>
            <p>ลองใช้ชื่อรุ่นแบบสั้น เช่น “3070”, “5700X3D” หรือเลือกหมวด CPU/GPU ก่อนค้นหาอีกครั้ง</p>
            <div class="mi-actions">
                <a class="button" href="<?= $base ?>/price">ล้างตัวกรอง</a>
                <a class="button ghost" href="<?= $base ?>/deal-checker">ใช้ Deal Checker</a>
            </div>
        </div>
    <?php endif; ?>
</section>
