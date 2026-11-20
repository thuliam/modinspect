<?php
// LS-20: the stored label is only the direction (below / in / above / insufficient); the wording and tone come from the severity tier and the saved row,
// so a price far above the range is no longer called "slightly above" (old saved results get the right wording too)
$labels = ['below_range' => 'ต่ำกว่าช่วงราคาที่พบ', 'in_range' => 'อยู่ในช่วงตลาด', 'above_range' => 'สูงกว่าช่วงที่พบ', 'insufficient' => 'ข้อมูลยังไม่เพียงพอ'];
$tones = ['below_range' => 'warn', 'in_range' => 'ok', 'above_range' => 'warn', 'insufficient' => 'neutral'];
$explain = \App\Services\DealExplanation::build($deal);
$resultLabel = ($explain['label'] ?? null) ?: ($labels[$deal['result_label']] ?? $deal['result_label']);
$tone = $explain['tone'] ?? ($tones[$deal['result_label']] ?? 'neutral');
$headline = ($explain['headline'] ?? null) ?: $deal['recommendation'];
// LS-21: an "insufficient data" result never shows a range (a stored range from a snapshot without accepted evidence would contradict the verdict, R-02)
$hasReferenceRange = $deal['result_label'] !== 'insufficient'
    && (float) ($deal['suggested_price_min'] ?? 0) > 0
    && (float) ($deal['suggested_price_max'] ?? 0) > 0;
?>
<section class="container page mi-deal-result-page">
    <div class="mi-page-heading">
        <span class="eyebrow">ผลเช็กดีล</span>
        <h1><?= htmlspecialchars($deal['full_name']) ?></h1>
    </div>

    <div class="mi-deal-layout">
        <article class="card result mi-result-panel">
            <div class="mi-result-head">
                <div>
                    <span class="mi-result-kicker">ราคาที่คุณพบ</span>
                    <div class="big-price">฿<?= number_format((float) $deal['user_price']) ?></div>
                </div>
                <span class="mi-verdict mi-verdict-<?= $tone ?>"><?= htmlspecialchars($resultLabel) ?></span>
            </div>

            <p class="mi-result-text"><?= htmlspecialchars($headline) ?></p>

            <?php if ($hasReferenceRange): ?>
                <div class="mi-compare">
                    <div><span>ช่วงราคาที่พบ</span><b>฿<?= number_format((float) $deal['suggested_price_min']) ?> – ฿<?= number_format((float) $deal['suggested_price_max']) ?></b></div>
                    <div><span>ราคากลาง</span><b>฿<?= number_format((float) $deal['median']) ?></b></div>
                </div>
            <?php else: ?>
                <div class="mi-result-empty">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    <div>
                        <strong>ยังไม่มีช่วงราคาตลาดของรุ่นนี้</strong>
                        <span>ระบบจะไม่สร้างช่วงราคาจากข้อมูลที่ยังไม่ผ่านการตรวจสอบ เมื่อมีข้อมูลที่ผ่านการตรวจสอบแล้ว ผลเช็กดีลจะแสดงตำแหน่งราคาของคุณที่นี่</span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($explain): ?>
                <div class="mi-deal-why mt-3" id="deal-why">
                    <h2 class="h5">ที่มาของผลนี้</h2>
                    <?php if ($explain['numbers']): ?>
                        <ul class="list-unstyled mb-3">
                            <?php foreach ($explain['numbers'] as $row): ?><li class="mb-1"><span class="text-muted"><?= htmlspecialchars($row['label']) ?></span> <b><?= htmlspecialchars($row['value']) ?></b></li><?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ($explain['have'] && $explain['have_heading']): ?>
                        <h3 class="h6 mb-2"><?= htmlspecialchars($explain['have_heading']) ?></h3>
                        <ul class="list-unstyled mb-3"><?php foreach ($explain['have'] as $t): ?><li class="mb-1"><i class="fas fa-check-circle text-success mr-2" aria-hidden="true"></i><?= htmlspecialchars($t) ?></li><?php endforeach; ?></ul>
                    <?php endif; ?>
                    <?php if ($explain['lack'] && $explain['lack_heading']): ?>
                        <h3 class="h6 mb-2"><?= htmlspecialchars($explain['lack_heading']) ?></h3>
                        <ul class="list-unstyled mb-3"><?php foreach ($explain['lack'] as $t): ?><li class="mb-1"><i class="fas fa-minus-circle text-warning mr-2" aria-hidden="true"></i><?= htmlspecialchars($t) ?></li><?php endforeach; ?></ul>
                    <?php endif; ?>
                    <?php foreach ($explain['notes'] as $t): ?><p class="mi-trend-note mb-2"><?= htmlspecialchars($t) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($deal['warning_note'])): ?>
                <div class="notice"><?= htmlspecialchars($deal['warning_note']) ?></div>
            <?php endif; ?>

            <div class="mi-actions">
                <a class="button" href="<?= $base ?>/price/<?= rawurlencode($deal['slug']) ?>">ดูหน้ารุ่นนี้</a>
                <a class="button ghost" href="<?= $base ?>/deal-checker">เช็กดีลอื่น</a>
                <?php if (!empty($cardUrl)): ?><a class="button ghost" href="<?= htmlspecialchars($cardUrl) ?>" download="modinpect-deal.png"><i class="fas fa-download mr-1" aria-hidden="true"></i>บันทึกเป็นภาพ</a><?php endif; ?>
                <a class="button ghost" href="<?= $base ?>/compare/<?= rawurlencode($deal['slug']) ?>">เทียบกับรุ่นอื่น</a>
                <?php $dealGuideCat = strtolower((string) ($product['category_slug'] ?? '')); if (\App\Services\Catalog\ConditionGuide::for($dealGuideCat) !== null): ?><a class="button ghost" href="<?= $base ?>/condition-guide/<?= rawurlencode($dealGuideCat) ?>">คู่มือเช็กสภาพ</a><?php endif; ?>
            </div>
            <div class="mi-field mt-3">
                <label for="deal-share">ลิงก์ผลนี้ (คัดลอกไปส่งต่อได้)</label>
                <input id="deal-share" type="url" readonly value="<?= htmlspecialchars(rtrim((string) $base, '/') . '/deal-checker/result/' . rawurlencode((string) ($deal['uuid'] ?? ''))) ?>">
            </div>
        </article>

        <aside class="mi-deal-help">
            <div class="card">
                <h2>ก่อนตัดสินใจซื้อ</h2>
                <ul>
                    <li>ดูสภาพจริงของสินค้า</li>
                    <li>ขอดูกล่อง ใบเสร็จ และประกันที่เหลือ</li>
                    <li>ขอผลทดสอบหรือทดสอบก่อนจ่าย</li>
                    <li>เทียบกับประกาศอื่นของรุ่นเดียวกัน</li>
                </ul>
            </div>
            <?php if (!empty($product)): $check = \App\Services\ConditionChecklist::for($product['category_slug'] ?? null, $deal); ?>
            <div class="card">
                <h2>เช็กลิสต์ก่อนจ่าย<?= !empty($product['category_name']) ? ' · ' . htmlspecialchars((string) $product['category_name']) : '' ?></h2>
                <ul>
                    <?php foreach ($check['items'] as $it): ?><li><?= htmlspecialchars($it['text']) ?></li><?php endforeach; ?>
                </ul>
                <?php if ($check['prompts']): ?>
                    <p class="mb-1"><strong>จากข้อมูลที่คุณกรอก</strong></p>
                    <ul>
                        <?php foreach ($check['prompts'] as $pr): ?><li><?= htmlspecialchars($pr['text']) ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p class="mi-hint"><?= htmlspecialchars($check['note']) ?></p>
            </div>
            <?php endif; ?>
            <div class="card">
                <h2>อ่านผลนี้อย่างไร</h2>
                <p>ผลนี้เทียบตำแหน่งราคาที่คุณพบกับราคาประกาศมือสองที่ผ่านการตรวจสอบเท่านั้น ไม่ใช่ราคาปิดดีล และไม่ได้ประเมินสภาพ ประกัน อุปกรณ์ หรือบริการของผู้ขาย</p>
            </div>
        </aside>
    </div>

    <?php if (!empty($product)): ?>
    <div class="mi-deal-more">
        <section class="card mi-history-card">
            <div class="mi-section-head compact"><div><span class="eyebrow">สินค้าที่เกี่ยวข้อง</span><h2>รายละเอียดของรุ่นนี้</h2></div></div>
            <dl class="mi-deal-facts">
                <div><dt>ยี่ห้อ</dt><dd><?= htmlspecialchars(strtolower((string) ($product['brand_name'] ?? '')) === 'generic' ? 'ทุกยี่ห้อ' : (string) ($product['brand_name'] ?? '-')) ?></dd></div>
                <div><dt>หมวด</dt><dd><?= htmlspecialchars((string) ($product['category_name'] ?? '-')) ?></dd></div>
                <?php if (!empty($product['spec_summary'])): ?><div><dt>สเปกย่อ</dt><dd><?= htmlspecialchars((string) $product['spec_summary']) ?></dd></div><?php endif; ?>
                <?php if (!empty($product['valid_sample_size'])): ?><div><dt>จำนวนตัวอย่าง</dt><dd><?= (int) $product['valid_sample_size'] ?> รายการ</dd></div><?php endif; ?>
                <?php if (!empty($product['median']) && (float) $deal['user_price'] > 0): $gap = ((float) $deal['user_price'] - (float) $product['median']) / (float) $product['median'] * 100; ?>
                    <div><dt>ราคาของคุณเทียบราคากลาง</dt><dd><?= $gap >= 0 ? '+' : '' ?><?= number_format($gap, 1) ?>%</dd></div>
                <?php endif; ?>
            </dl>
            <?php if (!empty($evidence_grades['owner_text'])): ?>
                <p class="mi-trend-note">ข้อมูล <?= (int) $evidence_grades['owner_text'] ?> จาก <?= (int) $evidence_grades['total'] ?> ตัวอย่างมาจากประกาศที่ทีมงานรวบรวมเป็นข้อความ ไม่มีลิงก์ประกาศต้นทาง จึงจัดความน่าเชื่อถืออยู่ในระดับต่ำ</p>
            <?php endif; ?>
            <div class="mi-actions"><a class="button ghost" href="<?= $base ?>/price/<?= rawurlencode($deal['slug']) ?>">ดูรายละเอียดทั้งหมด</a></div>
        </section>
        <?php if (!empty($trend)): ?>
        <section class="card mi-history-card">
            <div class="mi-section-head compact"><div><span class="eyebrow">แนวโน้ม</span><h2>ราคากลางรายเดือน</h2></div></div>
            <?php require ROOT_PATH . '/app/Views/components/trend-chart.php'; ?>
        </section>
        <?php endif; ?>
        <?php if (!empty($similar)): ?>
        <section class="card mi-history-card">
            <div class="mi-section-head compact"><div><span class="eyebrow">รุ่นใกล้เคียง</span><h2>เทียบกับรุ่นอื่นในหมวดเดียวกัน</h2></div></div>
            <p class="mi-trend-note">เรียงตามราคากลางที่ใกล้กับราคา ฿<?= number_format((float) $deal['user_price']) ?> ของคุณ ตัวเลขเปอร์เซ็นต์คือราคากลางของรุ่นนั้นเทียบกับราคาที่คุณพบ</p>
            <?php $ref = (float) $deal['user_price']; require ROOT_PATH . '/app/Views/components/similar-products.php'; ?>
        </section>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php // LS-24 A3: bargaining help from the same stored band the page shows
    $neg = $hasReferenceRange ? \App\Services\Deal\Negotiation::plan((string) $deal['full_name'], (float) $deal['user_price'], (float) $deal['suggested_price_min'], (float) $deal['suggested_price_max'], (float) ($deal['median'] ?? 0) ?: null, (int) ($deal['valid_sample_size'] ?? 0)) : null;
    require ROOT_PATH . '/app/Views/components/negotiation.php'; ?>
</section>
