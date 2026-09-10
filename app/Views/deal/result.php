<?php
$labels = [
    'below_range' => 'ต่ำกว่าช่วงราคาที่พบ',
    'in_range' => 'อยู่ในช่วงตลาด',
    'above_range' => 'สูงกว่าช่วงที่พบเล็กน้อย',
    'premium' => 'Premium asking price',
    'insufficient' => 'ข้อมูลไม่เพียงพอ',
];
$resultLabel = $labels[$deal['result_label']] ?? $deal['result_label'];
$hasReferenceRange = (float) ($deal['suggested_price_min'] ?? 0) > 0
    && (float) ($deal['suggested_price_max'] ?? 0) > 0;
?>
<section class="container page mi-deal-result-page">
    <div class="mi-page-heading narrow-copy">
        <span class="eyebrow">Deal Result</span>
        <h1><?= htmlspecialchars($deal['full_name']) ?></h1>
    </div>

    <article class="card result mi-result-panel">
        <span class="confidence-badge deal-label"><?= htmlspecialchars($resultLabel) ?></span>
        <div class="big-price">฿<?= number_format((float) $deal['user_price']) ?></div>
        <p class="lead"><?= htmlspecialchars($deal['recommendation']) ?></p>

        <?php if ($hasReferenceRange): ?>
            <div class="compare-row">
                <span>ช่วงราคาที่พบ</span>
                <b>฿<?= number_format((float) $deal['suggested_price_min']) ?>-<?= number_format((float) $deal['suggested_price_max']) ?></b>
            </div>
            <div class="compare-row">
                <span>ราคากลาง</span>
                <b>฿<?= number_format((float) $deal['median']) ?></b>
            </div>
        <?php else: ?>
            <div class="mini-empty">
                <strong>ข้อมูลตลาดยังไม่เพียงพอ</strong>
                <span>ระบบไม่สร้างช่วงราคาอ้างอิงเมื่อข้อมูลที่ผ่านการตรวจสอบยังไม่พอ</span>
            </div>
        <?php endif; ?>

        <?php if (!empty($deal['warning_note'])): ?>
            <div class="notice"><?= htmlspecialchars($deal['warning_note']) ?></div>
        <?php endif; ?>

        <p class="muted">ผลนี้เป็นการเทียบตำแหน่งราคาเท่านั้น สภาพสินค้า ประกัน อุปกรณ์ และบริการของผู้ขายอาจทำให้ราคาสูงหรือต่ำกว่าช่วงที่พบได้</p>
        <div class="mi-actions">
            <a class="button" href="<?= $base ?>/price/<?= rawurlencode($deal['slug']) ?>">ดูหน้ารุ่นนี้</a>
            <a class="button ghost" href="<?= $base ?>/deal-checker">เช็กดีลอื่น</a>
        </div>
    </article>
</section>
