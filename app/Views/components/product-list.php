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
// LS-52: with $mi_group_by_category the list is split under one headline per category (catalog order); one category needs no headline
$mi_order = ['cpu', 'gpu', 'motherboard', 'ram', 'storage', 'psu', 'case', 'cooling'];
$mi_icons = ['cpu' => 'fa-microchip', 'gpu' => 'fa-tv', 'motherboard' => 'fa-project-diagram', 'ram' => 'fa-memory', 'storage' => 'fa-hdd', 'psu' => 'fa-plug', 'case' => 'fa-server', 'cooling' => 'fa-fan'];
$mi_groups = ['' => $products];
if (!empty($mi_group_by_category)) {
    $mi_groups = [];
    foreach ($products as $p) { $mi_groups[trim((string) ($p['category_name'] ?? ''))][] = $p; }
    uksort($mi_groups, static function (string $a, string $b) use ($mi_order): int {
        $ia = array_search(strtolower($a), $mi_order, true); $ib = array_search(strtolower($b), $mi_order, true);
        return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
    });
}
$mi_headed = !empty($mi_group_by_category) && count($mi_groups) > 1;
$mi_h2_done = false;
foreach ($mi_groups as $mi_gname => $mi_gitems):
$mi_ready = array_values(array_filter($mi_gitems, $mi_has_price));
$mi_pending = array_values(array_filter($mi_gitems, static fn(array $p): bool => !$mi_has_price($p)));
?>
<?php if ($mi_headed): ?>
<section class="mi-cat-group" aria-labelledby="mi-grp-<?= htmlspecialchars(strtolower($mi_gname)) ?>">
    <header class="mi-cat-head">
        <span class="mi-cat-head-ico"><i class="fas <?= $mi_icons[strtolower($mi_gname)] ?? 'fa-microchip' ?>" aria-hidden="true"></i></span>
        <h2 id="mi-grp-<?= htmlspecialchars(strtolower($mi_gname)) ?>"><?= htmlspecialchars($mi_gname) ?></h2>
        <span class="mi-cat-head-n"><?= count($mi_gitems) ?> รุ่น<?= $mi_ready ? ' · มีราคา ' . count($mi_ready) : '' ?></span>
    </header>
<?php endif; ?>
<?php if ($mi_ready): ?>
    <?php if (!empty($mi_list_heading) && !$mi_headed && !$mi_h2_done): $mi_h2_done = true; ?><h2 class="sr-only"><?= htmlspecialchars($mi_list_heading) ?></h2><?php endif; ?>
    <?php $miArtFor = static fn(string $n): string => match (strtolower(trim($n))) { 'cpu' => 'cpu', 'gpu' => 'gpu', 'ram' => 'ram', default => 'ssd' }; ?>
    <?php
    // adaptive layout: one answer is a wide featured card; 2-4 share the row; 5+ become a grid of at most four columns
    $mi_n = count($mi_ready);
    $mi_col = !empty($mi_group_by_category)
        ? ($mi_n === 1 ? 'col-12' : 'col-sm-6 col-xl-4')
        : match (true) { $mi_n === 1 => 'col-12', $mi_n === 2 => 'col-md-6', $mi_n === 3 => 'col-md-6 col-lg-4', default => 'col-sm-6 col-lg-4 col-xl-3' };
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
<?php if ($mi_headed): ?></section><?php endif; ?>
<?php endforeach; ?>
