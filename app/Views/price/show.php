<?php
if (!function_exists('mi_public_confidence_label')) {
    function mi_public_confidence_label(?string $label): string
    {
        return match (strtolower((string) $label)) {
            'high' => 'HIGH · น่าเชื่อถือสูง',
            'medium' => 'MEDIUM · ใช้ประกอบการตัดสินใจ',
            'low' => 'LOW · ข้อมูลยังจำกัด',
            default => 'ข้อมูลยังไม่พอ',
        };
    }
}

if (!function_exists('mi_public_confidence_reason')) {
    function mi_public_confidence_reason(array $confidence): string
    {
        $reasons = array_values(array_filter($confidence['reasons'] ?? []));
        if ($reasons) {
            return implode(' · ', array_slice($reasons, 0, 3));
        }

        return 'พิจารณาจากจำนวนข้อมูล ความสด และความหลากหลายของแหล่งข้อมูล';
    }
}

$hasMarketSnapshot = !empty($product['median'])
    && (int) ($product['valid_sample_size'] ?? 0) > 0
    && (int) ($product['accepted_observation_count'] ?? 0) > 0;
$manifest = json_decode((string) ($product['calculation_manifest'] ?? ''), true) ?: [];
$confidence = $manifest['confidence'] ?? [];
$confidenceClass = strtolower((string) ($product['confidence_label'] ?? 'insufficient'));
?>
<section class="container product-page mi-product-detail">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="<?= $base ?>/price">เช็กราคา</a>
        <span>›</span>
        <span><?= htmlspecialchars($product['category_name']) ?></span>
        <span>›</span>
        <span><?= htmlspecialchars($product['brand_name']) ?></span>
    </nav>

    <?php
    require_once ROOT_PATH . '/app/Views/components/hw-art.php';
    $artType = match (strtolower(trim((string) ($product['category_name'] ?? '')))) { 'cpu' => 'cpu', 'gpu' => 'gpu', 'ram' => 'ram', default => 'ssd' };
    ?>
    <header class="mi-product-hero row align-items-stretch">
        <div class="col-lg-6 mb-3">
            <div class="mi-gallery">
                <span class="mi-gallery-badge"><i class="fas fa-<?= $hasMarketSnapshot ? 'chart-line' : 'info-circle' ?>" aria-hidden="true"></i> <?= $hasMarketSnapshot ? 'มีข้อมูลราคาตลาด' : 'ยังไม่มีราคาตลาด' ?></span>
                <div class="mi-gallery-art<?= ($photo = mi_product_photo($product, $base, 'mi-photo')) ? ' has-photo' : '' ?>"><?= $photo ?? mi_art_img($artType, $base) ?></div>
                <div class="mi-gallery-meta">
                    <span><?= htmlspecialchars($product['category_name']) ?></span>
                    <?php if (!empty($product['brand_name'])): ?><?= mi_brand_logo($product['brand_name'], $base, 20) ?><?php endif; ?>
                    <?php if (!empty($product['generation'])): ?><span><?= htmlspecialchars($product['generation']) ?></span><?php endif; ?>
                </div>
                <small><?= !empty($photo) ? 'ภาพถ่ายตัวอย่างของรุ่นนี้ (ไม่ใช่ชิ้นที่ประกาศขาย) · ดูเครดิตในหน้าวิธีคำนวณ' : 'ภาพประกอบเป็นภาพวาดประเภทสินค้า ไม่ใช่ภาพถ่ายสินค้าจริง' ?></small>
            </div>
        </div>
        <div class="col-lg-6 mb-3">
            <div class="mi-product-sum">
                <span class="eyebrow"><?= htmlspecialchars($product['category_name']) ?></span>
                <h1><?= htmlspecialchars($product['full_name']) ?></h1>
                <?php if (!empty($product['spec_summary'])): ?><p class="mi-product-spec"><?= htmlspecialchars($product['spec_summary']) ?></p><?php endif; ?>
                <?php if ($hasMarketSnapshot): ?>
                    <?php $rangeProduct = $product; $rangeSize = 'lg'; require ROOT_PATH . '/app/Views/components/range-answer.php'; ?>
                    <?php $eg = $evidence_grades ?? ['total' => 0, 'owner_text' => 0]; ?>
                    <?php if (($eg['owner_text'] ?? 0) > 0): ?>
                        <p class="mi-grade-note" role="note"><i class="fas fa-info-circle" aria-hidden="true"></i> <?= (int) $eg['owner_text'] ?> จาก <?= (int) $eg['total'] ?> ตัวอย่างมาจากข้อความที่เจ้าของรวบรวมเอง ไม่มีลิงก์ประกาศต้นทางให้ตรวจ จึงเป็นหลักฐานเกรดต่ำกว่า ใช้ประกอบการตัดสินใจเท่านั้น</p>
                    <?php endif; ?>
                    <div class="mi-sum-meta">
                        <span class="confidence-badge confidence-<?= htmlspecialchars($confidenceClass) ?>"><?= htmlspecialchars(mi_public_confidence_label($product['confidence_label'] ?? null)) ?></span>
                        <span><i class="fas fa-database" aria-hidden="true"></i> <?= (int) $product['valid_sample_size'] ?> ตัวอย่าง</span>
                    </div>
                <?php else: ?>
                    <p class="mi-sum-empty"><i class="fas fa-info-circle" aria-hidden="true"></i> ข้อมูลที่ผ่านการตรวจสอบยังไม่พอ จึงไม่แสดงช่วงราคา ระบบไม่สร้างราคาตลาดปลอม</p>
                <?php endif; ?>
                <div class="mi-actions">
                    <a class="btn btn-lime" href="<?= $base ?>/deal-checker?product=<?= (int) $product['id'] ?>"><i class="fas fa-balance-scale" aria-hidden="true"></i> เช็กดีลรุ่นนี้</a>
                    <a class="btn btn-ghost" href="<?= $base ?>/price">ค้นหารุ่นอื่น</a>
                    <a class="btn btn-ghost" href="<?= $base ?>/methodology">วิธีคำนวณ</a>
                </div>
            </div>
        </div>
    </header>

    <?php if (!$hasMarketSnapshot): ?>
        <div class="mi-detail-grid no-data-detail">
            <article class="card mi-product-summary-card">
                <h2>ข้อมูลสินค้า</h2>
                <dl class="mi-context-list">
                    <div><dt>หมวดหมู่</dt><dd><?= htmlspecialchars($product['category_name'] ?? '-') ?></dd></div>
                    <div><dt>แบรนด์</dt><dd><?= htmlspecialchars($product['brand_name'] ?? '-') ?></dd></div>
                    <div><dt>รุ่น</dt><dd><?= htmlspecialchars($product['model_name'] ?? $product['full_name']) ?></dd></div>
                    <div><dt>เจเนอเรชัน/บริบท</dt><dd><?= htmlspecialchars($product['generation'] ?? '-') ?></dd></div>
                    <div><dt>สเปกย่อ</dt><dd><?= htmlspecialchars($product['spec_summary'] ?? '-') ?></dd></div>
                </dl>
            </article>

            <aside class="mi-empty-state product-empty">
                <span class="price-status-badge">ยังไม่มีราคาตลาด</span>
                <h2>ข้อมูลตลาดยังไม่เพียงพอ</h2>
                <p>ModInspect ยังไม่มีข้อมูลที่ผ่านการตรวจสอบมากพอสำหรับรุ่นนี้ จึงไม่สร้างช่วงราคาหรือราคากลางให้ดูเหมือนเป็นราคาตลาดจริง</p>
                <p>คุณยังสามารถใช้ Deal Checker เพื่อบันทึกราคาที่เจอได้ แต่ผลลัพธ์จะแจ้งอย่างตรงไปตรงมาว่ายังเทียบตลาดไม่ได้</p>
                <div class="mi-actions">
                    <a class="button" href="<?= $base ?>/deal-checker?product=<?= (int) $product['id'] ?>">ใช้ Deal Checker</a>
                    <a class="button ghost" href="<?= $base ?>/price">ค้นหารุ่นอื่น</a>
                    <a class="button ghost" href="<?= $base ?>/methodology">อ่านวิธีคำนวณ</a>
                </div>
            </aside>
        </div>
    <?php else: ?>
        <div class="mi-detail-grid">
            <article class="card mi-price-answer-card">
                <?php $rangeProduct = $product; $rangeSize = 'lg'; require ROOT_PATH . '/app/Views/components/range-answer.php'; ?>
                <p>ราคานี้มาจากราคาประกาศมือสองที่ผ่านการตรวจสอบ ไม่ใช่ราคาปิดดีลจริง และอาจต่างตามสภาพ ประกัน อุปกรณ์ และบริการของผู้ขาย</p>
            </article>

            <aside class="card mi-confidence-card">
                <span class="confidence-badge confidence-<?= htmlspecialchars($confidenceClass) ?>">
                    <?= htmlspecialchars(mi_public_confidence_label($product['confidence_label'] ?? null)) ?>
                </span>
                <p><?= htmlspecialchars(mi_public_confidence_reason($confidence)) ?></p>
                <dl class="mi-context-list">
                    <div><dt>จำนวนตัวอย่าง</dt><dd><?= (int) $product['valid_sample_size'] ?> รายการ</dd></div>
                    <div><dt>ข้อมูลล่าสุด</dt><dd><?= htmlspecialchars($product['last_calculated_at'] ?? '-') ?></dd></div>
                    <div><dt>ความสดของข้อมูล</dt><dd><?= number_format((float) ($product['fresh_sample_ratio'] ?? 0), 1) ?>%</dd></div>
                    <div><dt>ประเภทข้อมูล</dt><dd>ราคาประกาศ</dd></div>
                </dl>
            </aside>
        </div>

        <div class="mi-secondary-grid">
            <form class="card mi-deal-cta" action="<?= $base ?>/deal-checker" method="get">
                <h2>เจอราคาประกาศของรุ่นนี้?</h2>
                <p>ใส่ราคาที่คุณเจอเพื่อดูว่าอยู่ต่ำกว่า อยู่ในช่วง หรือสูงกว่าช่วงราคาที่พบ</p>
                <input type="hidden" name="product" value="<?= (int) $product['id'] ?>">
                <label for="detail-user-price">ราคาที่พบ (บาท)</label>
                <div class="mi-inline-form">
                    <input id="detail-user-price" name="user_price" inputmode="numeric" placeholder="เช่น 7900">
                    <button type="submit">เปิด Deal Checker</button>
                </div>
            </form>

            <article class="card mi-method-card">
                <h2>เข้าใจข้อมูลนี้</h2>
                <p>ช่วงราคาที่พบช่วยให้เห็นกรอบตลาดโดยประมาณ ส่วนความน่าเชื่อถือบอกคุณภาพของข้อมูล ไม่ใช่คำตัดสินว่าดีลนั้นดีหรือไม่ดี</p>
                <a class="button ghost" href="<?= $base ?>/methodology">อ่านวิธีคำนวณ</a>
            </article>
        </div>

        <section class="card mi-history-card">
            <div class="mi-section-head compact">
                <div>
                    <span class="eyebrow">ประวัติราคา</span>
                    <h2>ประวัติราคากลาง</h2>
                </div>
                <span class="muted">แสดงเมื่อมีข้อมูลย้อนหลัง</span>
            </div>
            <?php if ($history): ?>
                <div class="history mi-history-list">
                    <?php foreach ($history as $point): ?>
                        <div>
                            <span><?= htmlspecialchars($point['snapshot_date'] ?? $point['created_at'] ?? '-') ?></span>
                            <strong>฿<?= number_format((float) $point['median']) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="mini-empty">
                    <strong>ยังไม่มีข้อมูลย้อนหลังเพียงพอ</strong>
                    <span>หน้ารุ่นนี้จะแสดงประวัติเมื่อมีชุดข้อมูลราคาหลายช่วงเวลา</span>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <section class="mi-trust" aria-label="อ่านราคานี้อย่างไร">
        <h2 class="mi-trust-title"><span class="mi-slash"></span>อ่านราคานี้อย่างไร</h2>
        <div class="row">
            <?php foreach ([['fa-tag', 'ราคาประกาศ', 'เป็นราคาที่ผู้ขายตั้งไว้ ไม่ใช่ราคาปิดดีล'], ['fa-microchip', 'แยกรุ่นชัดเจน', 'ไม่เฉลี่ยข้ามรุ่นหรือข้ามเจเนอเรชัน'], ['fa-file-alt', 'บอกที่มา', 'ดูจำนวนตัวอย่างและความสดของข้อมูลได้'], ['fa-search', 'ตรวจสภาพเอง', 'ต้องดูสภาพ ประกัน และอุปกรณ์ประกอบก่อนซื้อเสมอ']] as [$ic, $tt, $tx]): ?>
                <div class="col-md-6 col-xl-3 mb-3"><div class="mi-why"><span class="mi-why-ico"><i class="fas <?= $ic ?>" aria-hidden="true"></i></span><div><h3><?= $tt ?></h3><p><?= $tx ?></p></div></div></div>
            <?php endforeach; ?>
        </div>
    </section>
</section>