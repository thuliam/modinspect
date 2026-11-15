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
            'WEAK_EVIDENCE' => 'หลักฐานอ่อน', 'WEAK_EVIDENCE_CAP' => 'จำกัดเพราะหลักฐานอ่อน',
        ];
        $reasons = array_map(static fn($c) => $th[$c] ?? (string) $c, array_values(array_filter($confidence['reasons'] ?? [])));
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
<article class="card product-card mi-product-card <?= $hasMarketSnapshot ? 'has-price' : 'no-price' ?><?= !empty($cardHorizontal) ? ' mi-card-wide' : '' ?>">
    <?php $photo = mi_product_photo($product, $base); ?>
    <?php if (!empty($artType) || $photo): ?><div class="pc-art<?= $photo ? ' has-photo' : '' ?>"><?= $photo ?? mi_art_img($artType ?? 'ssd', $base) ?><span class="pc-badge <?= $hasMarketSnapshot ? 'on' : '' ?>"><?= $hasMarketSnapshot ? 'มีราคาตลาด' : 'ยังไม่มีราคาตลาด' ?></span></div><?php endif; ?>
    <div class="product-card-meta">
        <span><?= htmlspecialchars($product['category_name'] ?? 'Hardware') ?></span>
        <?= mi_brand_logo($product['brand_name'] ?? '', $base, 18) ?>
    </div>
    <h3><a href="<?= $base ?>/price/<?= rawurlencode($product['slug']) ?>"><?= htmlspecialchars($product['full_name']) ?></a></h3>


    <?php if ($hasMarketSnapshot): ?>
        <?php $rangeProduct = $product; $rangeSize = 'sm'; require ROOT_PATH . '/app/Views/components/range-answer.php'; ?>
        <div class="card-meta-row">
            <span><?= (int) $product['valid_sample_size'] ?> ตัวอย่าง</span>
        </div>
        <span class="confidence-badge confidence-<?= htmlspecialchars(strtolower((string) $product['confidence_label'])) ?>">
            <?= htmlspecialchars(mi_public_confidence_label($product['confidence_label'] ?? null)) ?>
        </span>
    <?php else: ?>
        <p class="compact-status">ข้อมูลราคายังไม่เพียงพอ</p>
    <?php endif; ?>
    <a class="product-card-action" href="<?= $base ?>/price/<?= rawurlencode($product['slug']) ?>">ดูรายละเอียด <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
</article>
