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
        <?php $minPrice = (int) ($minPrice ?? 0); $maxPrice = (int) ($maxPrice ?? 0); $keep = array_filter(['sort' => $sort ?? '', 'priced' => !empty($pricedOnly) ? '1' : '', 'min' => $minPrice ?: '', 'max' => $maxPrice ?: '']); ?>
        <?php // LS-47: category filter as a dock (icon above its label, RGB tiles, macOS-style bounce on hover); same icons as the main menu
        $catIcons = ['cpu' => 'fa-microchip', 'gpu' => 'fa-tv', 'motherboard' => 'fa-project-diagram', 'ram' => 'fa-memory', 'storage' => 'fa-hdd', 'psu' => 'fa-plug', 'case' => 'fa-server', 'cooling' => 'fa-fan']; $catI = 0; ?>
        <nav class="mi-cat-dock" aria-label="Quick category filters">
            <?php foreach ($categories as $value => $label): ?>
                <a class="mi-cat-item<?= $category === $value ? ' active' : '' ?>"<?= $category === $value ? ' aria-current="true"' : '' ?> style="--h:<?= ($catI++) * 40 ?>" href="<?= $base ?>/price?<?= htmlspecialchars(http_build_query(array_filter(['q' => $q, 'category' => $value]) + $keep)) ?>"><span class="mi-cat-ico"><i class="fas <?= $catIcons[$value] ?? 'fa-microchip' ?>" aria-hidden="true"></i></span><span class="mi-cat-lbl"><?= $label ?></span></a>
            <?php endforeach; ?>
            <a class="mi-cat-item<?= $category === null && $q === '' ? ' active' : '' ?>"<?= $category === null && $q === '' ? ' aria-current="true"' : '' ?> style="--h:<?= $catI * 40 ?>" href="<?= $base ?>/price<?= ($q !== '' || $keep) ? '?' . htmlspecialchars(http_build_query(array_filter(['q' => $q]) + $keep)) : '' ?>"><span class="mi-cat-ico"><i class="fas fa-th-large" aria-hidden="true"></i></span><span class="mi-cat-lbl">ทั้งหมด</span></a>
        </nav>
        <?php // LS-21: special filters of the chosen category; the choices are only values that exist in that category's models
        $specOptions = $specOptions ?? []; $specFilters = $specFilters ?? [];
        $speedOpts = $specOptions['speed'] ?? []; ?>
        <?php if ($specOptions): ?>
        <div class="form-inline mt-3" aria-label="ตัวกรองเฉพาะหมวด">
            <?php foreach ($specOptions as $key => $values): if ($key === 'speed') { continue; } ?>
                <div class="d-flex align-items-center mr-3 mb-2">
                <label class="mr-2 mb-0" for="spec-<?= $key ?>"><?= htmlspecialchars(\App\Services\Catalog\SpecFilter::LABELS[$key] ?? $key) ?></label>
                <select id="spec-<?= $key ?>" name="<?= $key ?>" class="form-control h-auto" data-autosubmit data-width="150px">
                    <option value="">ทั้งหมด</option>
                    <?php foreach ($values as $v): $text = in_array($key, ['cap'], true) ? ((int) $v >= 1000 ? ((int) $v / 1000) . ' TB' : $v . ' GB') : ($key === 'vram' ? $v . ' GB' : ($key === 'watt' ? $v . ' W' : $v)); ?>
                        <option value="<?= htmlspecialchars($v) ?>"<?= ($specFilters[$key] ?? '') === $v ? ' selected' : '' ?>><?= htmlspecialchars($text) ?></option>
                    <?php endforeach; ?>
                </select>
                </div>
            <?php endforeach; ?>
            <?php if ($speedOpts): ?>
                <div class="d-flex align-items-center mr-3 mb-2">
                <label class="mr-2 mb-0" for="spec-speed_min"><?= htmlspecialchars(\App\Services\Catalog\SpecFilter::LABELS['speed']) ?></label>
                <select id="spec-speed_min" name="speed_min" class="form-control h-auto mr-1" data-autosubmit data-width="110px" aria-label="ความเร็วต่ำสุด">
                    <option value="">ต่ำสุด</option>
                    <?php if (!empty($specFilters['speed_min']) && !in_array($specFilters['speed_min'], $speedOpts, true)): ?><option value="<?= htmlspecialchars($specFilters['speed_min']) ?>" selected><?= htmlspecialchars($specFilters['speed_min']) ?></option><?php endif; ?>
                    <?php foreach ($speedOpts as $v): ?><option value="<?= htmlspecialchars($v) ?>"<?= ($specFilters['speed_min'] ?? '') === $v ? ' selected' : '' ?>><?= htmlspecialchars($v) ?></option><?php endforeach; ?>
                </select>
                <span class="mr-1">–</span>
                <select id="spec-speed_max" name="speed_max" class="form-control h-auto" data-autosubmit data-width="110px" aria-label="ความเร็วสูงสุด">
                    <option value="">สูงสุด</option>
                    <?php if (!empty($specFilters['speed_max']) && !in_array($specFilters['speed_max'], $speedOpts, true)): ?><option value="<?= htmlspecialchars($specFilters['speed_max']) ?>" selected><?= htmlspecialchars($specFilters['speed_max']) ?></option><?php endif; ?>
                    <?php foreach ($speedOpts as $v): ?><option value="<?= htmlspecialchars($v) ?>"<?= ($specFilters['speed_max'] ?? '') === $v ? ' selected' : '' ?>><?= htmlspecialchars($v) ?></option><?php endforeach; ?>
                </select>
                </div>
            <?php endif; ?>
            <?php if ($specFilters): ?><a class="btn btn-ghost btn-sm mb-2" href="<?= $base ?>/price?<?= htmlspecialchars(http_build_query(array_filter(['q' => $q, 'category' => $category]) + $keep)) ?>">ล้างตัวกรอง</a><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php // LS-51: budget first: a range in baht and one-tap bands; only models with a public price can match, so a budget hides unpriced models
        $bands = [[0, 3000, 'ไม่เกิน ฿3,000'], [3000, 6000, '฿3,000–6,000'], [6000, 10000, '฿6,000–10,000'], [10000, 20000, '฿10,000–20,000'], [20000, 0, '฿20,000 ขึ้นไป']];
        $bandBase = array_filter(['q' => $q, 'category' => $category, 'sort' => $sort ?? '', 'priced' => !empty($pricedOnly) ? '1' : '']); ?>
        <div class="mi-budget mt-3">
            <div class="mi-budget-row">
                <label class="mi-budget-lbl" for="price-min">งบประมาณ (บาท)</label>
                <input id="price-min" class="mi-budget-in" name="min" type="number" inputmode="numeric" min="0" max="10000000" step="100" placeholder="ต่ำสุด" value="<?= $minPrice ?: '' ?>">
                <span aria-hidden="true">–</span>
                <input id="price-max" class="mi-budget-in" name="max" type="number" inputmode="numeric" min="0" max="10000000" step="100" placeholder="สูงสุด" aria-label="งบสูงสุด (บาท)" value="<?= $maxPrice ?: '' ?>">
                <button type="submit" class="btn btn-ghost btn-sm">ใช้งบนี้</button>
            </div>
            <div class="mi-budget-chips" aria-label="ช่วงงบยอดนิยม">
                <?php foreach ($bands as [$bMin, $bMax, $bLabel]): $on = $minPrice === $bMin && $maxPrice === $bMax; ?>
                    <a class="mi-budget-chip<?= $on ? ' active' : '' ?>"<?= $on ? ' aria-current="true"' : '' ?> href="<?= $base ?>/price?<?= htmlspecialchars(http_build_query($bandBase + array_filter(['min' => $bMin ?: '', 'max' => $bMax ?: '']))) ?>"><?= $bLabel ?></a>
                <?php endforeach; ?>
                <?php if ($minPrice || $maxPrice): ?><a class="mi-budget-chip" href="<?= $base ?>/price<?= $bandBase ? '?' . htmlspecialchars(http_build_query($bandBase)) : '' ?>">ล้างงบ</a><?php endif; ?>
            </div>
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
            <?php // LS-22: take one filter off at a time instead of starting over
            $base_params = array_filter(['q' => $q, 'category' => $category, 'sort' => $sort ?? '', 'priced' => !empty($pricedOnly) ? '1' : ''] + $specFilters);
            $names_th = ['q' => 'คำค้น', 'category' => 'หมวด', 'sort' => 'การเรียง', 'priced' => 'เฉพาะรุ่นที่มีราคา'] + \App\Services\Catalog\SpecFilter::LABELS + ['speed_min' => 'ความเร็วต่ำสุด', 'speed_max' => 'ความเร็วสูงสุด'];
            $removable = array_diff_key($base_params, ['sort' => 1]); ?>
            <?php if (count($removable) > 1): ?>
            <p class="mi-trend-note">เอาตัวกรองออกทีละตัว:</p>
            <div class="mi-category-chips" aria-label="เอาตัวกรองออก">
                <?php foreach ($removable as $k => $v): ?><a href="<?= $base ?>/price?<?= htmlspecialchars(http_build_query(array_diff_key($base_params, [$k => 1]))) ?>">ไม่เอา <?= htmlspecialchars((string) ($names_th[$k] ?? $k)) ?>: <?= htmlspecialchars((string) $v) ?></a><?php endforeach; ?>
            </div>
            <?php endif; ?>
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
