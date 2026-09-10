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
            return implode(' · ', array_slice($reasons, 0, 2));
        }

        return 'พิจารณาจากจำนวนข้อมูล ความสด และความหลากหลายของแหล่งข้อมูล';
    }
}

$hasMarketSnapshot = !empty($product['median'])
    && (int) ($product['valid_sample_size'] ?? 0) > 0
    && (int) ($product['accepted_observation_count'] ?? 0) > 0;
$manifest = json_decode((string) ($product['calculation_manifest'] ?? ''), true) ?: [];
$confidence = $manifest['confidence'] ?? [];
?>
<article class="card product-card mi-product-card <?= $hasMarketSnapshot ? 'has-price' : 'no-price' ?>">
    <div class="product-card-meta">
        <span><?= htmlspecialchars($product['category_name'] ?? 'Hardware') ?></span>
        <span><?= htmlspecialchars($product['brand_name'] ?? '') ?></span>
    </div>
    <h3><a href="<?= $base ?>/price/<?= rawurlencode($product['slug']) ?>"><?= htmlspecialchars($product['full_name']) ?></a></h3>

    <?php if (!empty($product['spec_summary'])): ?>
        <p class="product-spec"><?= htmlspecialchars($product['spec_summary']) ?></p>
    <?php endif; ?>

    <?php if ($hasMarketSnapshot): ?>
        <div class="price-answer compact">
            <span>ช่วงราคาที่พบ</span>
            <strong>฿<?= number_format((float) $product['q1']) ?>-<?= number_format((float) $product['q3']) ?></strong>
        </div>
        <div class="card-meta-row">
            <span>ราคากลาง ฿<?= number_format((float) $product['median']) ?></span>
            <span><?= (int) $product['valid_sample_size'] ?> ตัวอย่าง</span>
        </div>
        <span class="confidence-badge confidence-<?= htmlspecialchars(strtolower((string) $product['confidence_label'])) ?>">
            <?= htmlspecialchars(mi_public_confidence_label($product['confidence_label'] ?? null)) ?>
        </span>
        <small class="confidence-reason"><?= htmlspecialchars(mi_public_confidence_reason($confidence)) ?></small>
    <?php else: ?>
        <span class="price-status-badge">ยังไม่มีราคาตลาด</span>
        <p class="compact-status">ข้อมูลราคายังไม่เพียงพอ</p>
    <?php endif; ?>
    <a class="product-card-action" href="<?= $base ?>/price/<?= rawurlencode($product['slug']) ?>">ดูรายละเอียด</a>
</article>
