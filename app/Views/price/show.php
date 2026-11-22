<?php
if (!function_exists('mi_public_confidence_label')) {
    function mi_public_confidence_label(?string $label): string
    {
        return match (strtolower((string) $label)) {
            'high' => 'น่าเชื่อถือสูง',
            'medium' => 'น่าเชื่อถือปานกลาง · ใช้ประกอบการตัดสินใจ',
            'low' => 'น่าเชื่อถือต่ำ · ข้อมูลยังจำกัด',
            default => 'ข้อมูลยังไม่พอ',
        };
    }
}

if (!function_exists('mi_public_confidence_reason')) {
    function mi_public_confidence_reason(array $confidence): string
    {
        $th = [
            'FRESH_OBSERVATIONS' => 'ข้อมูลใหม่', 'GOOD_SOURCE_DIVERSITY' => 'แหล่งข้อมูลหลากหลาย', 'HIGH_SOURCE_CONCENTRATION' => 'ข้อมูลกระจุกอยู่ในไม่กี่แหล่ง',
            'INSUFFICIENT_SAMPLE' => 'ตัวอย่างไม่พอ', 'LIMITED_FRESHNESS' => 'ข้อมูลเริ่มเก่า', 'LOW_SAMPLE_SIZE' => 'ตัวอย่างน้อย', 'MIXED_EVIDENCE_QUALITY' => 'คุณภาพหลักฐานปะปนกัน',
            'MIXED_FRESHNESS' => 'อายุข้อมูลปะปนกัน', 'MODERATE_SAMPLE_SIZE' => 'ตัวอย่างพอสมควร', 'MODERATE_SOURCE_DIVERSITY' => 'แหล่งข้อมูลพอสมควร', 'SINGLE_SOURCE' => 'มาจากแหล่งเดียว',
            'SINGLE_SOURCE_CAP' => 'จำกัดเพราะมาจากแหล่งเดียว', 'OWNER_TEXT_ONLY_CAP' => 'จำกัดเพราะเป็นข้อความที่ทีมงานรวบรวมทั้งหมด ไม่มีลิงก์ต้นทาง', 'SOURCE_CONCENTRATION_CAP' => 'จำกัดเพราะข้อมูลกระจุกตัว', 'STALE_COHORT_CAP' => 'จำกัดเพราะข้อมูลเก่า', 'STALE_DATA' => 'ข้อมูลเก่า',
            'STRONG_EVIDENCE' => 'หลักฐานแข็งแรง', 'STRONG_SAMPLE_SIZE' => 'ตัวอย่างมาก', 'SUFFICIENT_SAMPLE' => 'ตัวอย่างเพียงพอ', 'TINY_SAMPLE_CAP' => 'จำกัดเพราะตัวอย่างน้อยมาก',
            'WEAK_EVIDENCE' => 'หลักฐานอ่อน', 'WEAK_EVIDENCE_CAP' => 'จำกัดเพราะหลักฐานอ่อน', 'HUMAN_RECORDED_EVIDENCE_CAP' => 'จำกัดเพราะข้อมูลทั้งหมดเป็นข้อมูลที่ทีมงานบันทึกเอง ยังไม่มีการเก็บหน้าประกาศโดยระบบ',
        ];
        $reasons = array_map(static fn($c) => $th[$c] ?? (string) $c, array_values(array_filter($confidence['reasons'] ?? [])));
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
    <header class="mi-product-hero row align-items-stretch" id="sec-price" data-nav="ราคา">
        <div class="col-lg-6 mb-3">
            <div class="mi-gallery">
                <span class="mi-gallery-badge"><i class="fas fa-<?= $hasMarketSnapshot ? 'chart-line' : 'info-circle' ?>" aria-hidden="true"></i> <?= $hasMarketSnapshot ? 'มีข้อมูลราคาตลาด' : 'ยังไม่มีราคาตลาด' ?></span>
                <div class="mi-gallery-art<?= ($photo = mi_product_photo($product, $base, 'mi-photo', true)) ? ' has-photo' : '' ?>"><?= $photo ?? mi_art_img($artType, $base) ?></div>
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
                <?php if (strtolower((string) ($product['brand_name'] ?? '')) === 'generic'): ?><p class="mi-trend-note">ราคานี้เป็นราคากลางของสเปกนี้ ไม่แยกยี่ห้อและรุ่นย่อย</p><?php endif; ?>
                <?php if ($hasMarketSnapshot): ?>
                    <?php $rangeProduct = $product; $rangeSize = 'lg'; require ROOT_PATH . '/app/Views/components/range-answer.php'; ?>
                    <?php $eg = $evidence_grades ?? ['total' => 0, 'owner_text' => 0]; ?>
                    <?php if (($eg['owner_text'] ?? 0) > 0): ?>
                        <p class="mi-grade-note" role="note"><i class="fas fa-info-circle" aria-hidden="true"></i> <?= (int) $eg['owner_text'] ?> จาก <?= (int) $eg['total'] ?> ตัวอย่างมาจากข้อความที่เจ้าของรวบรวมเอง ไม่มีลิงก์ประกาศต้นทางให้ตรวจ จึงเป็นหลักฐานเกรดต่ำกว่า ใช้ประกอบการตัดสินใจเท่านั้น</p>
                    <?php endif; ?>
                    <?php if (($eg['transcribed'] ?? 0) > 0): ?>
                        <p class="mi-grade-note" role="note"><i class="fas fa-info-circle" aria-hidden="true"></i> <?= (int) $eg['transcribed'] ?> จาก <?= (int) $eg['total'] ?> ตัวอย่างทีมงานบันทึกจากประกาศที่มีลิงก์เอง ระบบยังไม่ได้เก็บหน้าประกาศไว้ และประกาศอาจถูกลบไปแล้ว จึงใช้ประกอบการตัดสินใจเท่านั้น</p>
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
                    <a class="btn btn-ghost" href="<?= $base ?>/compare/<?= rawurlencode((string) $product['slug']) ?>">เทียบกับรุ่นอื่น</a>
                    <?php if (\App\Services\Catalog\UpgradeFinder::supports((string) ($product['category_slug'] ?? ''))): ?><a class="btn btn-ghost" href="<?= $base ?>/upgrade/<?= rawurlencode((string) $product['slug']) ?>">ใช้รุ่นนี้อยู่ ขยับไปรุ่นไหน</a><?php endif; ?>
                    <?php $wl = \App\Services\Catalog\WatchList::parse(is_string($_COOKIE[\App\Services\Catalog\WatchList::COOKIE] ?? null) ? $_COOKIE[\App\Services\Catalog\WatchList::COOKIE] : null); $watching = \App\Services\Catalog\WatchList::has($wl, (string) $product['slug']); $wFlash = $_SESSION['watch_flash'] ?? null; unset($_SESSION['watch_flash']); ?>
                    <form method="post" action="<?= $base ?>/watch" class="d-inline"><input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>"><input type="hidden" name="slug" value="<?= htmlspecialchars((string) $product['slug']) ?>"><button class="btn btn-ghost" type="submit"><i class="<?= $watching ? 'fas' : 'far' ?> fa-star" aria-hidden="true"></i> <?= $watching ? 'เลิกติดตามรุ่นนี้' : 'ติดตามรุ่นนี้' ?></button></form>
                    <?php if ($wl || $wFlash): ?><small class="text-muted align-self-center"><?= $wFlash ? htmlspecialchars((string) $wFlash) . ' · ' : '' ?><a href="<?= $base ?>/watching">รุ่นที่ติดตาม (<?= count($wl) ?>)</a></small><?php endif; ?>
                    <?php if (!empty($shareText)): ?><button type="button" class="btn btn-ghost" data-copy-text="<?= htmlspecialchars($shareText) ?>"><i class="fas fa-share-alt" aria-hidden="true"></i> <span>คัดลอกข้อความแชร์</span></button><?php endif; ?>
                    <?php if (!empty($cardUrl)): ?><a class="btn btn-ghost" href="<?= htmlspecialchars($cardUrl) ?>" download="modinpect-price.png"><i class="fas fa-image" aria-hidden="true"></i> บันทึกการ์ดราคา</a><?php endif; ?>
                    <a class="btn btn-ghost" href="<?= $base ?>/price">ค้นหารุ่นอื่น</a>
                    <a class="btn btn-ghost" href="<?= $base ?>/methodology">วิธีคำนวณ</a>
                </div>
            </div>
        </div>
    </header>

    <?php // LS-19 B1: section jump bar, built by site.js from the data-nav sections below (Bootstrap scrollspy marks where the reader is) ?>
    <nav id="product-nav" class="nav mi-section-nav" aria-label="ไปยังส่วนของหน้านี้" hidden></nav>

    <?php $facts = $facts ?? []; require ROOT_PATH . '/app/Views/components/product-facts.php'; require ROOT_PATH . '/app/Views/components/benchmark-scores.php'; ?>
    <?php if (\App\Services\Catalog\ConditionGuide::for($product['category_slug'] ?? null) !== null): ?>
        <p class="mi-trend-note"><a href="<?= $base ?>/condition-guide/<?= htmlspecialchars(strtolower((string) $product['category_slug'])) ?>">ดูคู่มือเช็กสภาพก่อนซื้อ<?= ' ' . htmlspecialchars((string) $product['category_name']) ?></a></p>
    <?php endif; ?>

    <?php if (!empty($communityOn)): // LS-21 (R-11): only when the owner has switched community reports on ?>
        <p class="mi-trend-note"><i class="fas fa-flag mr-1" aria-hidden="true"></i><a href="<?= $base ?>/report/<?= rawurlencode((string) $product['slug']) ?>">พบข้อมูลไม่ถูกต้อง หรืออยากแจ้งราคาที่ขายได้? แจ้งเรา</a></p>
    <?php endif; ?>

    <?php if (!$hasMarketSnapshot): ?>
        <div class="mi-detail-grid no-data-detail" id="sec-market" data-nav="ข้อมูลตลาด">
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
                    <a class="button ghost" href="<?= $base ?>/compare/<?= rawurlencode((string) $product['slug']) ?>">เทียบกับรุ่นอื่น</a>
                    <a class="button ghost" href="<?= $base ?>/price">ค้นหารุ่นอื่น</a>
                    <a class="button ghost" href="<?= $base ?>/methodology">อ่านวิธีคำนวณ</a>
                </div>
            </aside>
        </div>
    <?php else: ?>
        <div class="mi-detail-grid mi-detail-single" id="sec-market" data-nav="ข้อมูลตลาด">


            <aside class="card mi-confidence-card">
                <span class="confidence-badge confidence-<?= htmlspecialchars($confidenceClass) ?>">
                    <?= htmlspecialchars(mi_public_confidence_label($product['confidence_label'] ?? null)) ?>
                </span>
                <p><?= htmlspecialchars(mi_public_confidence_reason($confidence)) ?></p>
                <p class="mi-trend-note">ราคานี้มาจากราคาประกาศมือสองที่ผ่านการตรวจสอบ ไม่ใช่ราคาปิดดีลจริง และอาจต่างตามสภาพ ประกัน อุปกรณ์ และบริการของผู้ขาย</p>
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

        <?php require ROOT_PATH . '/app/Views/components/evidence-explorer.php'; ?>
        <section class="card mi-history-card" id="sec-trend" data-nav="แนวโน้ม">
            <div class="mi-section-head compact">
                <div>
                    <span class="eyebrow">ประวัติราคา</span>
                    <h2>แนวโน้มราคากลางรายเดือน</h2>
                </div>
                <span class="muted">จากวันที่ของประกาศที่ผ่านการตรวจ</span>
            </div>
            <?php if ($trend): ?>
                <?php require ROOT_PATH . '/app/Views/components/trend-chart.php'; ?>
            <?php else: ?>
                <div class="mini-empty">
                    <strong>ยังไม่มีข้อมูลย้อนหลังเพียงพอ</strong>
                    <span>แนวโน้มรายเดือนจะแสดงเมื่อมีเดือนที่มีตัวอย่างที่ผ่านการตรวจอย่างน้อย 3 รายการ</span>
                </div>
            <?php endif; ?>
        </section>
        <?php if (!empty($similar)): ?>
        <section class="card mi-history-card" id="sec-similar" data-nav="รุ่นใกล้เคียง">
            <div class="mi-section-head compact"><div><span class="eyebrow">เทียบรุ่นใกล้เคียง</span><h2>รุ่นอื่นที่ราคาใกล้กัน</h2></div></div>
            <?php $ref = (float)($product['median'] ?? 0); require ROOT_PATH . '/app/Views/components/similar-products.php'; ?>
        </section>
        <?php endif; ?>
    <?php endif; ?>
    <section class="mi-trust" aria-label="อ่านราคานี้อย่างไร">
        <button class="btn btn-ghost align-self-start" type="button" data-toggle="collapse" data-target="#howToRead" aria-expanded="false" aria-controls="howToRead"><i class="fas fa-book mr-2" aria-hidden="true"></i>อ่านราคานี้อย่างไร</button>
        <div class="collapse mt-3" id="howToRead"><div class="row">
            <?php foreach ([['fa-tag', 'ราคาประกาศ', 'เป็นราคาที่ผู้ขายตั้งไว้ ไม่ใช่ราคาปิดดีล'], ['fa-microchip', 'แยกรุ่นชัดเจน', 'ไม่เฉลี่ยข้ามรุ่นหรือข้ามเจเนอเรชัน'], ['fa-file-alt', 'บอกที่มา', 'ดูจำนวนตัวอย่างและความสดของข้อมูลได้'], ['fa-search', 'ตรวจสภาพเอง', 'ต้องดูสภาพ ประกัน และอุปกรณ์ประกอบก่อนซื้อเสมอ']] as [$ic, $tt, $tx]): ?>
                <div class="col-md-6 col-xl-3 mb-3"><div class="mi-why"><span class="mi-why-ico"><i class="fas <?= $ic ?>" aria-hidden="true"></i></span><div><h3><?= $tt ?></h3><p><?= $tx ?></p></div></div></div>
            <?php endforeach; ?>
        </div></div>
    </section>
    <?php if (!empty($uvn)) { require ROOT_PATH . '/app/Views/components/used-vs-new.php'; } // LS-24 A5, only when both prices exist ?>
    <?php if (!empty($cm_on)) { require ROOT_PATH . '/app/Views/components/community-product.php'; } // LS-24 M9, only while the forum is on ?>
</section>