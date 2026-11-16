<?php
declare(strict_types=1);

/**
 * Ranks the rows the owner could fill in (owner_fill_needed.csv: usually a GPU whose VRAM the listing does not state) by how much a
 * fill would help the catalog: first products that would reach the 3-row snapshot minimum, then products with the thinnest samples.
 * Read-only on the database and the CSV files. Writes one CSV with row id, model, price and the field to fill: never listing text.
 *
 *   php cli/owner_fill_priority.php [--dir=storage/research/line_facebook_2026_loc]
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Research\OwnerIntakeDeduper;

$dir = dirname(__DIR__) . '/storage/research/line_facebook_2026_loc';
foreach (array_slice($argv, 1) as $a) { if (str_starts_with($a, '--dir=')) { $dir = substr($a, 6); } }

$readCsv = static function (string $file): array {
    $fh = fopen($file, 'rb'); $h = fgetcsv($fh, 0, ',', '"', ''); $h[0] = ltrim((string)$h[0], "\xEF\xBB\xBF"); $rows = [];
    while (($c = fgetcsv($fh, 0, ',', '"', '')) !== false) { $c = array_pad($c, count($h), ''); $rows[] = array_combine($h, array_slice($c, 0, count($h))); }
    fclose($fh);
    return $rows;
};
$listings = []; foreach ($readCsv($dir . '/research_listings_sanitized.csv') as $r) { $listings[$r['row_id']] = $r; }
$fill = $readCsv($dir . '/owner_fill_needed.csv');

global $config;
$db = Database::connection($config['db']);
$existing = [];
foreach ($db->query("SELECT r.raw_title, COALESCE(o.price_value, o.asking_price) price FROM price_observations o JOIN raw_price_observations r ON r.id=o.raw_observation_id JOIN data_sources s ON s.id=r.source_id WHERE s.source_key LIKE 'offline_owner_text_%'")->fetchAll() as $r) {
    $existing[OwnerIntakeDeduper::keyFor(OwnerIntakeDeduper::undouble((string)$r['raw_title']), (int)$r['price'])] = true;
}
// product -> approved rows and pending owner-text rows
$products = $db->query("SELECT p.id, p.full_name,
    SUM(o.verified_status='approved') approved, SUM(o.verified_status='pending' AND s.source_key LIKE 'offline_owner_text_%') pending
    FROM products p LEFT JOIN price_observations o ON o.product_id=p.id AND o.price_type='asking' LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id
    WHERE p.is_active=1 GROUP BY p.id, p.full_name")->fetchAll();
$byModel = [];
foreach ($products as $p) {
    foreach (array_unique(array_map(static fn($r) => $r['model_key'], $listings)) as $mk) {
        if ($mk !== '' && str_ends_with(strtolower($p['full_name']), strtolower($mk))) { $byModel[$mk][] = $p; }
    }
}

$groups = [];
foreach ($fill as $f) {
    $l = $listings[$f['row_id']] ?? null;
    if (!$l || $l['model_key'] === '' || ($l['listing_type'] ?? '') !== 'single_item' || ($l['duplicate_of_row'] ?? '') !== '' || ($l['wanted_or_trade'] ?? '') === 'yes'
        || ($l['defective_hint'] ?? '') === 'yes' || ($l['bundle_total_line'] ?? '') === 'yes' || ($l['price_anomaly'] ?? '') !== '' || !is_numeric($l['asking_price_thb']) || (float)$l['asking_price_thb'] <= 0) { continue; }
    if (isset($existing[OwnerIntakeDeduper::keyFor((string)$l['title'], (int)$l['asking_price_thb'])])) { continue; }
    $groups[$l['model_key']][] = ['row_id' => $f['row_id'], 'price' => (int)$l['asking_price_thb'], 'fill' => $f['missing_fields']];
}

$ranked = [];
foreach ($groups as $mk => $rows) {
    $prod = $byModel[$mk][0] ?? null;
    if (!$prod) { continue; }
    $have = (int)$prod['approved'] + (int)$prod['pending'];
    $need = max(0, 3 - $have);
    $ranked[] = ['model' => $mk, 'product' => $prod['full_name'], 'have' => $have, 'need' => $need, 'rows' => $rows, 'unlocks' => $need > 0 && count($rows) >= $need];
}
usort($ranked, static fn($a, $b) => [$b['unlocks'], $a['have'], -count($a['rows'])] <=> [$a['unlocks'], $b['have'], -count($b['rows'])]);

$out = $dir . '/owner_fill_priority.csv';
$fh = fopen($out, 'wb'); fwrite($fh, "\xEF\xBB\xBF");
fputcsv($fh, ['rank', 'model', 'product_rows_now', 'rows_needed_for_snapshot', 'effect', 'row_id', 'asking_price_thb', 'fill_field', 'owner_fill_value'], ',', '"', '');
$rank = 0; $unlock = 0; $totalRows = 0;
foreach ($ranked as $g) {
    $rank++; if ($g['unlocks']) { $unlock++; }
    foreach ($g['rows'] as $r) {
        fputcsv($fh, [$rank, $g['model'], $g['have'], $g['need'], $g['unlocks'] ? 'reaches snapshot minimum' : ($g['need'] > 0 ? 'still short' : 'adds samples'), $r['row_id'], $r['price'], $r['fill'], ''], ',', '"', '');
        $totalRows++;
    }
}
fclose($fh);
echo json_encode(['models_ranked' => count($ranked), 'models_that_reach_snapshot_minimum' => $unlock, 'rows_listed' => $totalRows, 'file' => $out], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
foreach (array_slice($ranked, 0, 12) as $g) { printf("%-14s now %2d need %d rows %2d %s\n", $g['model'], $g['have'], $g['need'], count($g['rows']), $g['unlocks'] ? 'UNLOCKS' : ''); }
