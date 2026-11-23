<?php
/**
 * LS-69: picture gallery of a model (up to five pictures from product_images). A Bootstrap carousel (no script of ours), each slide carries its own credit.
 * Inputs: $images (rows: slot, path, alt_text, source_name, source_url, license_note), $product, $base. Nothing is drawn when no row has a usable local path.
 */
$slides = [];
foreach (array_slice((array) ($images ?? []), 0, 5) as $img) {
    $p = (string) ($img['path'] ?? '');
    if (!preg_match('~^assets/products/[A-Za-z0-9._-]+$~', $p)) { continue; }
    $file = ROOT_PATH . '/public/' . $p;
    $slides[] = ['src' => $base . '/' . $p . (is_file($file) ? '?v=' . (int) @filemtime($file) : ''), 'alt' => trim((string) ($img['alt_text'] ?? '')) !== '' ? (string) $img['alt_text'] : (string) ($product['full_name'] ?? ''),
        'credit' => trim((string) ($img['source_name'] ?? '')), 'licence' => trim((string) ($img['license_note'] ?? '')), 'url' => (string) ($img['source_url'] ?? '')];
}
if ($slides === []) { return; }
$n = count($slides); $cid = 'mi-gallery-' . (int) ($product['id'] ?? 0);
?>
<div id="<?= $cid ?>" class="carousel slide mi-carousel" data-interval="false" data-ride="false" aria-label="<?= htmlspecialchars((string) ($product['full_name'] ?? '')) ?>">
    <div class="carousel-inner">
        <?php foreach ($slides as $i => $sl): ?>
            <figure class="carousel-item<?= $i === 0 ? ' active' : '' ?> mb-0">
                <img class="mi-photo d-block w-100" src="<?= htmlspecialchars($sl['src']) ?>" alt="<?= htmlspecialchars($sl['alt']) ?>"<?= $i === 0 ? '' : ' loading="lazy"' ?>>
                <figcaption class="mi-photo-credit"><?= htmlspecialchars(($n > 1 ? ($i + 1) . '/' . $n . ' · ' : '') . $sl['credit'] . ($sl['licence'] !== '' ? ' · ' . $sl['licence'] : '')) ?><?php if (preg_match('~^https?://~i', $sl['url'])): ?> · <a href="<?= htmlspecialchars($sl['url']) ?>" target="_blank" rel="noopener noreferrer">ที่มา</a><?php endif; ?></figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
    <?php if ($n > 1): ?>
        <ol class="carousel-indicators">
            <?php foreach ($slides as $i => $sl): ?><li data-target="#<?= $cid ?>" data-slide-to="<?= $i ?>"<?= $i === 0 ? ' class="active"' : '' ?>></li><?php endforeach; ?>
        </ol>
        <a class="carousel-control-prev" href="#<?= $cid ?>" role="button" data-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="sr-only">ก่อนหน้า</span></a>
        <a class="carousel-control-next" href="#<?= $cid ?>" role="button" data-slide="next"><span class="carousel-control-next-icon" aria-hidden="true"></span><span class="sr-only">ถัดไป</span></a>
    <?php endif; ?>
</div>
