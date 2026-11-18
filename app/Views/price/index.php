<?php // every category the catalog lists, one list for the dropdown and the chips
$categories = ['cpu' => 'CPU', 'gpu' => 'GPU', 'motherboard' => 'Motherboard', 'ram' => 'RAM', 'storage' => 'Storage', 'psu' => 'PSU', 'case' => 'Case', 'cooling' => 'Cooling']; ?>
<section class="container page mi-price-index">
    <div class="mi-page-heading">
        <span class="eyebrow">ดัชนีราคา</span>
        <h1>เช็กราคาคอมมือสอง</h1>
        <p class="lead">ค้นหา CPU/GPU แล้วดูช่วงราคาที่พบ ราคากลาง ความน่าเชื่อถือ และข้อมูลล่าสุดเมื่อมีข้อมูลที่ผ่านการตรวจสอบพอ</p>
    </div>

    <form class="mi-filter-card" action="<?= $base ?>/price" method="get">
        <label for="price-search">ค้นหารุ่นสินค้า</label>
        <div class="mi-filter-row">
            <input id="price-search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="เช่น Ryzen 7 5700X3D, RTX 3070">
            <select name="category" aria-label="หมวดหมู่สินค้า">
                <option value="">ทุกหมวด</option>
                <?php foreach ($categories as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $category === $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">ค้นหา</button>
        </div>
        <div class="mi-category-chips" aria-label="Quick category filters">
            <?php foreach ($categories as $value => $label): ?>
                <a href="<?= $base ?>/price?<?= http_build_query(array_filter(['q' => $q, 'category' => $value])) ?>" class="<?= $category === $value ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
            <a href="<?= $base ?>/price<?= $q !== '' ? '?' . http_build_query(['q' => $q]) : '' ?>" class="<?= $category === null && $q === '' ? 'active' : '' ?>">ทั้งหมด</a>
        </div>
    </form>

    <div class="mi-result-summary" role="status" aria-live="polite">
        <strong>พบ <?= count($products) ?> รุ่น<?php $pricedCount = count(array_filter($products, static fn(array $p): bool => !empty($p['median']) && (int) ($p['valid_sample_size'] ?? 0) > 0 && (int) ($p['accepted_observation_count'] ?? 0) > 0)); ?><?= $products ? ' · มีช่วงราคา ' . $pricedCount . ' รุ่น' : '' ?></strong>
        <span>ช่วงราคาคือราคาประกาศที่ผ่านการคัดกรอง ไม่ใช่ราคาปิดดีล</span>
    </div>

    <?php if ($products): ?>
        <?php $mi_list_heading = 'รุ่นที่มีราคาตลาดแล้ว'; require ROOT_PATH . '/app/Views/components/product-list.php'; ?>
    <?php else: ?>
        <div class="mi-empty-state">
            <h2>ไม่พบสินค้าที่ค้นหา</h2>
            <p>ลองใช้ชื่อรุ่นแบบสั้น เช่น “3070”, “5700X3D” หรือเลือกหมวด CPU/GPU ก่อนค้นหาอีกครั้ง</p>
            <?php if (!empty($suggestions)): ?>
            <p class="mi-trend-note">รุ่นที่คนดูบ่อย:</p>
            <div class="mi-category-chips" aria-label="รุ่นแนะนำ">
                <?php foreach ($suggestions as $sg): ?><a href="<?= $base ?>/price/<?= rawurlencode($sg['slug']) ?>"><?= htmlspecialchars($sg['full_name']) ?></a><?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="mi-actions">
                <a class="button" href="<?= $base ?>/price">ล้างตัวกรอง</a>
                <a class="button ghost" href="<?= $base ?>/deal-checker">ใช้ Deal Checker</a>
            </div>
        </div>
    <?php endif; ?>
</section>
