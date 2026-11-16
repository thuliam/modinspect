<?php
$labels = [
    'below_range' => 'ต่ำกว่าช่วงราคาที่พบ',
    'in_range' => 'อยู่ในช่วงตลาด',
    'above_range' => 'สูงกว่าช่วงที่พบเล็กน้อย',
    'premium' => 'ตั้งราคาสูงกว่าช่วงตลาดมาก',
    'insufficient' => 'ข้อมูลยังไม่เพียงพอ',
];
$tones = ['below_range' => 'warn', 'in_range' => 'ok', 'above_range' => 'warn', 'premium' => 'bad', 'insufficient' => 'neutral'];
$resultLabel = $labels[$deal['result_label']] ?? $deal['result_label'];
$tone = $tones[$deal['result_label']] ?? 'neutral';
$hasReferenceRange = (float) ($deal['suggested_price_min'] ?? 0) > 0
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

            <p class="mi-result-text"><?= htmlspecialchars($deal['recommendation']) ?></p>

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

            <?php if (!empty($deal['warning_note'])): ?>
                <div class="notice"><?= htmlspecialchars($deal['warning_note']) ?></div>
            <?php endif; ?>

            <div class="mi-actions">
                <a class="button" href="<?= $base ?>/price/<?= rawurlencode($deal['slug']) ?>">ดูหน้ารุ่นนี้</a>
                <a class="button ghost" href="<?= $base ?>/deal-checker">เช็กดีลอื่น</a>
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
</section>
