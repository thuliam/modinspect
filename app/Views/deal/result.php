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
            <div class="card">
                <h2>อ่านผลนี้อย่างไร</h2>
                <p>ผลนี้เทียบตำแหน่งราคาที่คุณพบกับราคาประกาศมือสองที่ผ่านการตรวจสอบเท่านั้น ไม่ใช่ราคาปิดดีล และไม่ได้ประเมินสภาพ ประกัน อุปกรณ์ หรือบริการของผู้ขาย</p>
            </div>
        </aside>
    </div>
</section>
