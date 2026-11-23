<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Catalog\ImageImport;

/**
 * LS-70: loads the pictures an admin approved in review.csv (from cli/commons_image_candidates.php). Rows with approve = yes are taken in file order, at most five
 * per model, into the free slots after the pictures the model already has. Each picture keeps its own source (Wikimedia Commons, author, page link) and licence.
 * Dry run by default; --apply writes public/assets/products/<slug>-<slot>.<ext> and the product_images rows. Reads local files only.
 * Usage: php cli/commons_image_apply.php --csv=storage/import/commons_candidates/review.csv [--apply]
 */
global $config;
$o = getopt('', ['csv:', 'apply']);
$csv = (string) ($o['csv'] ?? '');
if ($csv === '' || !is_file($csv)) { fwrite(STDERR, "Usage: php cli/commons_image_apply.php --csv=<review.csv> [--apply]\n"); exit(2); }
$db = Database::connection($config['db']);
if (!ImageImport::tableExists($db)) { fwrite(STDERR, "The product_images table is missing on this database.\n"); exit(3); }
$rows = \App\Services\Catalog\GameFpsImport::readFile($csv);
$dir = dirname($csv);
$used = []; foreach ($db->query('SELECT p.slug, MAX(i.slot) m FROM product_images i JOIN products p ON p.id = i.product_id GROUP BY p.slug')->fetchAll() as $r) { $used[(string) $r['slug']] = (int) $r['m']; }
$plan = []; $line = 0; $seen = [];
foreach ($rows as $r) {
    if (strtolower(trim((string) ($r['approve'] ?? ''))) !== 'yes') { continue; }
    $slug = trim((string) ($r['slug'] ?? '')); $file = $dir . '/' . str_replace(['..', '\\'], ['', '/'], trim((string) ($r['file'] ?? '')));
    $slot = ($used[$slug] ?? 0) + 1; $seen[$slug] = $slot;
    $used[$slug] = $slot;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $author = trim((string) ($r['author'] ?? ''));
    $meta = ['source_name' => mb_substr('Wikimedia Commons' . ($author !== '' ? ' · ' . $author : ''), 0, 120), 'license_note' => mb_substr(trim((string) ($r['licence'] ?? '')), 0, 120), 'source_url' => trim((string) ($r['page_url'] ?? '')), 'alt' => ''];
    $one = $slot > 5 ? [['line' => ++$line, 'action' => 'refused', 'slug' => $slug, 'name' => basename($file), 'error' => 'more than five pictures for this model', 'data' => null, 'product_id' => null]]
        : ImageImport::plan($db, [['name' => $slug . '_' . $slot . '.' . $ext, 'path' => $file]], $meta);
    foreach ($one as $x) { $x['name'] = basename($file) . ' → ' . $slug . ' #' . $slot; $plan[] = $x; }
}
$ready = array_filter($plan, static fn($p) => $p['data'] !== null);
$report = ['apply' => isset($o['apply']), 'approved' => count($plan), 'valid' => count($ready), 'refused' => array_values(array_map(static fn($p) => $p['name'] . ': ' . $p['error'], array_filter($plan, static fn($p) => $p['data'] === null)))];
if (isset($o['apply'])) {
    if ($ready === []) { echo json_encode($report + ['error' => 'nothing approved and valid'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n"; exit(1); }
    $report += ImageImport::apply($db, $plan, dirname(__DIR__) . '/public/assets/products');
}
echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
