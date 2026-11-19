<?php // every category the catalog lists, one list for the dropdown and the chips
$categories = ['cpu' => 'CPU', 'gpu' => 'GPU', 'motherboard' => 'Motherboard', 'ram' => 'RAM', 'storage' => 'Storage', 'psu' => 'PSU', 'case' => 'Case', 'cooling' => 'Cooling']; ?>
<section class="container page mi-price-index">
    <div class="mi-page-heading">
        <span class="eyebrow">ดัชนีราคา</span>
        <h1>เช็กราคาคอมมือสอง</h1>
        <p class="lead">ค้นหาชิ้นส่วนคอมทั้ง 8 หมวด แล้วดูช่วงราคาที่พบ ราคากลาง ความน่าเชื่อถือ และข้อมูลล่าสุดเมื่อมีข้อมูลที่ผ่านการตรวจสอบพอ</p>
    </div>

    <form class="mi-filter-card" action="<?= $base ?>/price" method="get">
        <label for="price-search">ค้นหารุ่นสินค้า</label>
        <div class="mi-filter-row">
            <?php // LS-20: Select2 in tags mode (model names as suggestions, any text allowed); choosing or typing applies the search at once (data-autosubmit)
            $qKnown = false; foreach ($names ?? [] as $n) { if ((string) $n['full_name'] === $q) { $qKnown = true; break; } } ?>
            <select id="price-search" name="q" class="form-control h-auto" data-combo data-allow-clear="true" data-autosubmit data-placeholder="เช่น Ryzen 7 5700X3D, RTX 3070">
                <option value=""></option>
                <?php if ($q !== '' && !$qKnown): ?><option value="<?= htmlspecialchars($q) ?>" selected><?= htmlspecialchars($q) ?></option><?php endif; ?>
                <?php foreach ($names ?? [] as $n): ?><option value="<?= htmlspecialchars($n['full_name']) ?>"<?= (string) $n['full_name'] === $q ? ' selected' : '' ?>><?= htmlspecialchars($n['full_name']) ?></option><?php endforeach; ?>
            </select>
            <select name="category" aria-label="หมวดหมู่สินค้า">
                <option value="">ทุกหมวด</option>
                <?php foreach ($categories as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $category === $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">ค้นหา</button>
        </div>
        <?php $keep = array_filter(['sort' => $sort ?? '', 'priced' => !empty($pricedOnly) ? '1' : '']); ?>
        <div class="mi-category-chips" aria-label="Quick category filters">
            <?php foreach ($categories as $value => $label): ?>
                <a href="<?= $base ?>/price?<?= htmlspecialchars(http_build_query(array_filter(['q' => $q, 'category' => $value]) + $keep)) ?>" class="<?= $category === $value ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
            <a href="<?= $base ?>/price<?= ($q !== '' || $keep) ? '?' . htmlspecialchars(http_build_query(array_filter(['q' => $q]) + $keep)) : '' ?>" class="<?= $category === null && $q === '' ? 'active' : '' ?>">ทั้งหมด</a>
        </div>
        <div class="form-inline mt-3">
            <label class="mr-2 mb-0" for="price-sort">เรียงตาม</label>
            <select id="price-sort" name="sort" class="form-control h-auto mr-3 mb-2 mb-sm-0" data-autosubmit data-width="240px">
                <?php foreach (\App\Services\Catalog\PriceList::SORTS as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>"<?= ($sort ?? '') === $value ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="price-priced" name="priced" value="1"<?= !empty($pricedOnly) ? ' checked' : '' ?> data-autosubmit>
                <label class="custom-control-label" for="price-priced">เฉพาะรุ่นที่มีช่วงราคาแล้ว</label>
            </div>
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
            <p>ลองใช้ชื่อรุ่นแบบสั้น เช่น “3070”, “5700X3D” หรือเลือกหมวดสินค้าก่อนค้นหาอีกครั้ง</p>
            <?php if (!empty($didYouMean)): ?>
            <p class="mi-trend-note">คุณหมายถึงรุ่นเหล่านี้หรือไม่:</p>
            <div class="mi-category-chips" aria-label="คุณหมายถึง">
                <?php foreach ($didYouMean as $dm): ?><a href="<?= $base ?>/price/<?= rawurlencode($dm['slug']) ?>"><?= htmlspecialchars($dm['full_name']) ?></a><?php endforeach; ?>
            </div>
            <?php endif; ?>
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
