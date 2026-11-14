<?php
/**
 * Product listing used by Home and Price Index.
 * Products with an eligible market snapshot get a full card. Products without one are a compact row each:
 * dozens of identical "no price yet" cards bury the answer and look broken.
 * Expects: $products (array), $base (string).
 */
$mi_has_price = static fn(array $p): bool => !empty($p['median'])
    && (int) ($p['valid_sample_size'] ?? 0) > 0
    && (int) ($p['accepted_observation_count'] ?? 0) > 0;
$mi_ready = array_values(array_filter($products, $mi_has_price));
$mi_pending = array_values(array_filter($products, static fn(array $p): bool => !$mi_has_price($p)));
?>
<?php if ($mi_ready): ?>
    <div class="cards mi-card-grid">
        <?php foreach ($mi_ready as $product) require ROOT_PATH . '/app/Views/components/product-card.php'; ?>
    </div>
<?php endif; ?>

<?php if ($mi_pending): ?>
    <div class="mi-pending-block">
        <?php if ($mi_ready): ?>
            <h2 class="mi-pending-title">ยังไม่มีราคาตลาด (<?= count($mi_pending) ?> รุ่น)</h2>
        <?php else: ?>
            <p class="mi-pending-note">ยังไม่มีรุ่นที่มีข้อมูลราคาผ่านการตรวจสอบเพียงพอ จึงไม่แสดงราคา คุณยังเปิดดูสเปกและใช้ Deal Checker ได้</p>
        <?php endif; ?>
        <ul class="mi-compact-list">
            <?php foreach ($mi_pending as $item): ?>
                <li>
                    <a href="<?= $base ?>/price/<?= rawurlencode($item['slug']) ?>"><?= htmlspecialchars($item['full_name']) ?></a>
                    <small><?= htmlspecialchars(trim(($item['category_name'] ?? '') . ' · ' . ($item['brand_name'] ?? ''), ' ·')) ?></small>
                    <span class="price-status-badge">ข้อมูลยังไม่พอ</span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
