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
    <?php if (!empty($mi_list_heading)): ?><h2 class="sr-only"><?= htmlspecialchars($mi_list_heading) ?></h2><?php endif; ?>
    <?php $miArtFor = static fn(string $n): string => match (strtolower(trim($n))) { 'cpu' => 'cpu', 'gpu' => 'gpu', 'ram' => 'ram', default => 'ssd' }; ?>
    <?php
    // adaptive layout: one answer is a wide featured card; 2-4 share the row; 5+ become a grid of at most four columns
    $mi_n = count($mi_ready);
    $mi_col = match (true) { $mi_n === 1 => 'col-12', $mi_n === 2 => 'col-md-6', $mi_n === 3 => 'col-md-6 col-lg-4', default => 'col-sm-6 col-lg-4 col-xl-3' };
    $mi_horizontal = $mi_n === 1;
?>
    <div class="row mi-featured mi-ready-grid">
        <?php foreach ($mi_ready as $product): ?>
            <div class="<?= $mi_col ?> mb-4">
                <?php $artType = $miArtFor((string) ($product['category_name'] ?? '')); $cardHorizontal = $mi_horizontal; require ROOT_PATH . '/app/Views/components/product-card.php'; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($mi_pending): ?>
    <div class="mi-pending-block">
        <?php if ($mi_ready): ?>
            <h2 class="mi-pending-title">ยังไม่มีราคาตลาด (<?= count($mi_pending) ?> รุ่น)</h2>
        <?php else: ?>
            <p class="mi-pending-note">ยังไม่มีรุ่นที่มีข้อมูลราคาผ่านการตรวจสอบเพียงพอ จึงไม่แสดงราคา คุณยังเปิดดูสเปกและใช้ Deal Checker ได้</p>
        <?php endif; ?>
        <?php $miArtFor = static fn(string $n): string => match (strtolower(trim($n))) { 'cpu' => 'cpu', 'gpu' => 'gpu', 'ram' => 'ram', default => 'ssd' }; ?>
        <?php require_once ROOT_PATH . '/app/Views/components/hw-art.php'; ?>
        <div class="mi-mini-grid mi-compact-list">
            <?php foreach ($mi_pending as $item): ?>
                <?php $miPhoto = mi_product_photo($item, $base); $miType = $miArtFor((string) ($item['category_name'] ?? '')); ?>
                <a class="mi-mini" href="<?= $base ?>/price/<?= rawurlencode($item['slug']) ?>">
                    <span class="mi-mini-art<?= $miPhoto ? ' has-photo' : '' ?>"><?= $miPhoto ?? mi_art_img($miType, $base) ?></span>
                    <span class="mi-mini-body">
                        <small><?= htmlspecialchars(trim(($item['category_name'] ?? '') . ' · ' . ($item['brand_name'] ?? ''), ' ·')) ?></small>
                        <strong><?= htmlspecialchars($item['full_name']) ?></strong>
                        <em>ยังไม่มีราคาตลาด</em>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
