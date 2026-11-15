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
<?php require_once ROOT_PATH . '/app/Views/components/hw-art.php'; ?>
<article class="card product-card mi-product-card <?= $hasMarketSnapshot ? 'has-price' : 'no-price' ?>">
    <?php $photo = mi_product_photo($product, $base); ?>
    <?php if (!empty($artType) || $photo): ?><div class="pc-art<?= $photo ? ' has-photo' : '' ?>"><?= $photo ?? mi_art_img($artType ?? 'ssd', $base) ?><span class="pc-badge <?= $hasMarketSnapshot ? 'on' : '' ?>"><?= $hasMarketSnapshot ? 'มีราคาตลาด' : 'ยังไม่มีราคาตลาด' ?></span></div><?php endif; ?>
    <div class="product-card-meta">
        <span><?= htmlspecialchars($product['category_name'] ?? 'Hardware') ?></span>
        <?= mi_brand_logo($product['brand_name'] ?? '', $base, 18) ?>
    </div>
    <h3><a href="<?= $base ?>/price/<?= rawurlencode($product['slug']) ?>"><?= htmlspecialchars($product['full_name']) ?></a></h3>

    <?php if (!empty($product['spec_summary'])): ?>
        <p class="product-spec"><?= htmlspecialchars($product['spec_summary']) ?></p>
    <?php endif; ?>

    <?php if ($hasMarketSnapshot): ?>
        <?php $rangeProduct = $product; $rangeSize = 'sm'; require ROOT_PATH . '/app/Views/components/range-answer.php'; ?>
        <div class="card-meta-row">
            <span><?= (int) $product['valid_sample_size'] ?> ตัวอย่าง</span>
        </div>
        <span class="confidence-badge confidence-<?= htmlspecialchars(strtolower((string) $product['confidence_label'])) ?>">
            <?= htmlspecialchars(mi_public_confidence_label($product['confidence_label'] ?? null)) ?>
        </span>
        <small class="confidence-reason"><?= htmlspecialchars(mi_public_confidence_reason($confidence)) ?></small>
    <?php else: ?>
        <p class="compact-status">ข้อมูลราคายังไม่เพียงพอ</p>
    <?php endif; ?>
    <a class="product-card-action" href="<?= $base ?>/price/<?= rawurlencode($product['slug']) ?>">ดูรายละเอียด <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
</article>
