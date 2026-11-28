<?php
declare(strict_types=1);

/**
 * LS-77..80: read-only follow-up report after the Facebook approval. Writes nothing to the database.
 *   LS-77  price sanity: spread and suspicious rows behind each new snapshot (RAM per-stick vs kit, branded RAM in a generic class)
 *   LS-78  pending rows a reviewer would not trust, grouped by reason
 *   LS-79  products still pending and how many usable rows each one lacks
 *   LS-80  products without a photo; Klevv products
 * Usage: php cli/archive/facebook_followup_report.php [--csv=storage/import/facebook_20261122]
 */
require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Research\OwnerApprovalPlan;

global $config;
$o = getopt('', ['csv::']);
$db = Database::connection($config['db']);
$like = 'offline_real_%facebook%';
$csv = [];

echo "== LS-77 price sanity (latest snapshot per product, Facebook-backed) ==\n";
$st = $db->prepare("SELECT p.id, p.full_name, h.q1, h.median, h.q3, h.sample_size
    FROM price_histories h JOIN products p ON p.id=h.product_id
    WHERE h.id IN (SELECT MAX(id) FROM price_histories GROUP BY product_id)
      AND p.id IN (SELECT o.product_id FROM price_observations o JOIN raw_price_observations r ON r.id=o.raw_observation_id JOIN data_sources s ON s.id=r.source_id WHERE s.source_key LIKE :s AND o.verified_status='approved')
    ORDER BY (h.q3/NULLIF(h.q1,0)) DESC");
$st->execute(['s' => $like]);
$rowSt = $db->prepare("SELECT o.id, COALESCE(o.price_value,o.asking_price) price, r.raw_title FROM price_observations o JOIN raw_price_observations r ON r.id=o.raw_observation_id JOIN data_sources s ON s.id=r.source_id
    WHERE s.source_key LIKE :s AND o.product_id=:p AND o.verified_status='approved' AND o.price_type='asking' ORDER BY price");
printf("%-34s %8s %8s %8s %4s %6s  flags\n", 'product', 'q1', 'median', 'q3', 'n', 'q3/q1');
$susp = 0;
foreach ($st->fetchAll() as $p) {
    $ratio = (float)$p['q1'] > 0 ? (float)$p['q3'] / (float)$p['q1'] : 0;
    $rowSt->execute(['s' => $like, 'p' => $p['id']]);
    $flags = [];
    foreach ($rowSt->fetchAll() as $r) {
        $t = (string)$r['raw_title'];
        $kit = preg_match('/(2\s*[x×]\s*\d|แพ็คคู่|แพคคู่|คู่|แถวละ|ตัวละ|ใบละ|ลูกละ|each|\bkit\b|4\s*[x×]\s*\d)/iu', $t) === 1;
        $off = (float)$p['median'] > 0 && ((float)$r['price'] < 0.7 * (float)$p['median'] || (float)$r['price'] > 1.4 * (float)$p['median']);
        if ($kit || $off) {
            $flags[] = 'row#' . $r['id'] . ($kit ? ' kit/each' : '') . ($off ? ' off-median' : '');
            $csv['ls77'][] = [$p['full_name'], $r['id'], $r['price'], $p['median'], $kit ? 'kit_or_each_wording' : '', $off ? 'off_median' : '', $t];
        }
    }
    if ($ratio > 1.6 || $p['sample_size'] < 4) { $flags[] = $ratio > 1.6 ? 'wide-spread' : 'small-sample'; }
    if ($flags) { $susp++; }
    printf("%-34s %8.0f %8.0f %8.0f %4d %6.2f  %s\n", mb_strimwidth((string)$p['full_name'], 0, 34), $p['q1'], $p['median'], $p['q3'], $p['sample_size'], $ratio, implode('; ', $flags));
}
echo "products with at least one flag: $susp\n";

echo "\n== LS-78 untrusted pending Facebook rows ==\n";
$st = $db->prepare("SELECT o.id, p.full_name, COALESCE(o.price_value,o.asking_price) price, o.is_deposit, o.is_defective, o.is_duplicate, o.listing_type, r.raw_title
    FROM price_observations o JOIN raw_price_observations r ON r.id=o.raw_observation_id JOIN data_sources s ON s.id=r.source_id JOIN products p ON p.id=o.product_id
    WHERE s.source_key LIKE :s AND o.verified_status='pending' AND o.price_type='asking' ORDER BY p.full_name, o.id");
$st->execute(['s' => $like]);
$groups = [];
foreach ($st->fetchAll() as $r) {
    if (!OwnerApprovalPlan::untrusted($r)) { continue; }
    $why = (int)$r['is_deposit'] ? 'deposit' : ((int)$r['is_defective'] ? 'defective' : ((int)$r['is_duplicate'] ? 'duplicate'
        : ($r['listing_type'] !== 'single_item' ? $r['listing_type'] : (preg_match('/(ขายแล้ว|\bsold\b|จองแล้ว|reserved|ติดจอง)/iu', (string)$r['raw_title']) ? 'sold_or_reserved' : 'new_item'))));
    $groups[$why][] = $r;
    $csv['ls78'][] = [$why, $r['id'], $r['full_name'], $r['price'], $r['raw_title']];
}
foreach ($groups as $why => $list) { printf("%-18s %d rows\n", $why, count($list)); }
echo 'total untrusted: ' . array_sum(array_map('count', $groups)) . "\n";

echo "\n== LS-79 products still pending (trusted rows only) ==\n";
$built = OwnerApprovalPlan::build($db, $like, true);
usort($built['left_pending'], static fn($a, $b) => $b[1] <=> $a[1]);
foreach ($built['left_pending'] as [$name, $usable, $status]) {
    $need = max(0, 3 - $usable);
    printf("%-40s usable=%d need=%d %s\n", $name, $usable, $need, $status);
    $csv['ls79'][] = [$name, $usable, $need, $status];
}
echo 'products pending: ' . count($built['left_pending']) . "\n";

echo "\n== LS-80 catalog gaps ==\n";
$st = $db->query("SELECT p.id, p.full_name, c.name cat FROM products p LEFT JOIN product_categories c ON c.id=p.category_id
    WHERE p.id NOT IN (SELECT product_id FROM product_images) AND p.image_path IS NULL ORDER BY c.name, p.full_name");
$noPhoto = $st->fetchAll();
$byCat = [];
foreach ($noPhoto as $r) { $byCat[(string)$r['cat']][] = $r['full_name']; $csv['ls80'][] = [$r['cat'], $r['id'], $r['full_name']]; }
foreach ($byCat as $c => $l) { printf("%-24s %d without photo\n", $c, count($l)); }
echo 'total without photo: ' . count($noPhoto) . "\n";
echo "Klevv products:\n";
foreach ($db->query("SELECT id, full_name FROM products WHERE full_name LIKE '%Klevv%' OR full_name LIKE '%CRAS%'")->fetchAll() as $r) { echo "  #{$r['id']} {$r['full_name']}\n"; }

if (isset($o['csv'])) {
    $dir = rtrim((string)$o['csv'], '/\\');
    if (!is_dir($dir)) { mkdir($dir, 0775, true); }
    foreach ($csv as $name => $rows) {
        $f = fopen("$dir/$name.csv", 'w'); fwrite($f, "\xEF\xBB\xBF");
        foreach ($rows as $r) { fputcsv($f, $r); }
        fclose($f);
    }
    echo "\nCSV written to $dir (" . implode(', ', array_keys($csv)) . ")\n";
}
