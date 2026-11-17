<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Catalog\HardwareReferenceMigration;
use App\Services\Collection\OwnerRowPreparer;

/**
 * LS-12: the official-hardware-sources CSV (manufacturer spec pages, no prices) becomes one additive migration:
 *  - candidate parts (CANDIDATE_ADD) are inserted into the catalog with the facts the CSV states and nothing else (no price, no observation);
 *  - every part's official specs go into product_reference_specs with their source URL, check date and a review status;
 *  - monitors are skipped (no monitor category exists yet);
 *  - an exact-variant warning in the CSV is kept as review_status = needs_variant_review.
 * The committed migration must equal what the generator produces from the committed CSV, so the SQL is never edited by hand. Pure (no database).
 */
function ok_hr(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$H = 'category,brand,model_name,catalog_slug,model_or_part_number,catalog_disposition,cpu_socket,memory_type,form_factor,capacity_gb,board_power_watt,system_psu_recommendation_watt,psu_capacity_watt,max_gpu_length_mm,max_cpu_cooler_height_mm,cooler_height_mm,monitor_resolution,monitor_refresh_hz,source_url,date_checked,price_status,review_note';
$csv = static function (array $rows) use ($H): array {
    $f = tempnam(sys_get_temp_dir(), 'hr'); file_put_contents($f, $H . "\n" . implode("\n", $rows) . "\n");
    $r = OwnerRowPreparer::readCsv($f); unlink($f); return $r;
};
$sql = HardwareReferenceMigration::generate($csv([
    "motherboard,Acme,Board O'Neil,acme-board,,CANDIDATE_ADD,AM5,DDR5,Micro-ATX,,,,,,,,,,https://example.com/board,2026-11-17,NOT_VERIFIED_FOR_MARKET_PRICING,\"note, with comma\"",
    "psu,Acme,Power 750,acme-power-750,AP-750,REVIEW_EXACT_VARIANT,,,ATX,,,,750,,,,,,https://example.com/psu,2026-11-17,NOT_VERIFIED_FOR_MARKET_PRICING,revisions differ",
    "cpu,AMD,Ryzen 5 5600,amd-ryzen-5-5600,,CHECK_EXISTING,AM4,DDR4,,,,,,,,,,,https://example.com/cpu,2026-11-17,NOT_VERIFIED_FOR_MARKET_PRICING,",
    "monitor,AOC,24G4,aoc-24g4,24G4,NEW_CATEGORY_REQUIRED,,,,,,,,,,,1920x1080,180,https://example.com/mon,2026-11-17,NOT_VERIFIED_FOR_MARKET_PRICING,",
]));
ok_hr(str_contains($sql, "'Board O''Neil'") && !str_contains($sql, "Board O'Neil'"), 'Single quotes in a value are doubled');
ok_hr(substr_count($sql, 'INSERT INTO products') === 1 && str_contains($sql, "'acme-board'") && !str_contains($sql, "'Power 750'"), 'Only the CANDIDATE_ADD row becomes a product; the exact-variant and existing rows do not');
ok_hr(str_contains($sql, "NOT EXISTS (SELECT 1 FROM products WHERE slug='acme-board')"), 'A product is inserted only when its slug is absent (idempotent)');
$noComments = (string)preg_replace('/^--.*$/m', '', $sql);   // the header comment names the skipped monitors; the statements must not
ok_hr(!str_contains($noComments, 'aoc-24g4'), 'Monitors are skipped: no statement mentions them');
ok_hr(str_contains($sql, '-- Skipped') && str_contains($sql, 'aoc-24g4'), 'The skipped monitors are named in the header comment');
ok_hr(str_contains($sql, "'needs_variant_review'") && str_contains($sql, "'official_checked'"), 'Both review statuses appear');
ok_hr(preg_match("/'psu_capacity_watt','750','W','https:\\/\\/example.com\\/psu','2026-11-17','needs_variant_review'/", $sql) === 1, 'An exact-variant row keeps its spec with the needs_variant_review status, unit and source');
ok_hr(preg_match("/'socket','AM4',NULL,'https:\\/\\/example.com\\/cpu','2026-11-17','official_checked'/", $sql) === 1, 'A plain row is official_checked, with the CSV column cpu_socket stored as socket');
ok_hr(str_contains($sql, "'note, with comma'"), 'A quoted CSV field with a comma survives');
ok_hr(substr_count($sql, 'INSERT IGNORE INTO product_reference_specs') === 7, 'One reference row per non-empty spec cell of the non-monitor rows (board 3, psu 2, cpu 2): ' . substr_count($sql, 'INSERT IGNORE INTO product_reference_specs'));
$stmts = str_replace('ON DELETE CASCADE', '', $noComments);   // the only DELETE is the foreign key's cascade
foreach (['UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'REPLACE'] as $bad) { ok_hr(preg_match('/\b' . $bad . '\b/i', $stmts) !== 1, "The generated SQL never contains $bad"); }
foreach (['raw_price_observations', 'price_snapshots', 'market_', 'verified_status'] as $price) { ok_hr(!str_contains($sql, $price), "The generated SQL never touches prices ($price)"); }

// the committed migration is exactly the generator's output for the committed CSV, so it is never hand-edited
$root = dirname(__DIR__);
$rows = OwnerRowPreparer::readCsv($root . '/database/reference/official_hardware_sources.csv');
$committed = (string)file_get_contents($root . '/database/migrations/020_hardware_reference_specs.sql');
ok_hr($committed === HardwareReferenceMigration::generate($rows), 'database/migrations/020_hardware_reference_specs.sql equals the generator output (run php cli/gen_hardware_reference_migration.php)');

// facts about the real file
$nonMonitor = array_values(array_filter($rows, static fn(array $r): bool => $r['category'] !== 'monitor'));
$candidates = array_values(array_filter($nonMonitor, static fn(array $r): bool => $r['catalog_disposition'] === 'CANDIDATE_ADD'));
ok_hr(count($rows) === 22 && count($nonMonitor) === 19 && count($candidates) === 7, 'The file has 22 rows: 19 parts (7 candidates) and 3 monitors: ' . count($rows) . '/' . count($nonMonitor) . '/' . count($candidates));
ok_hr(substr_count($committed, 'INSERT INTO products') === 7, 'Seven candidate products are inserted');
$cells = 0;
foreach ($nonMonitor as $r) { foreach (HardwareReferenceMigration::SPEC_COLUMNS as $col => $key) { if (trim($r[$col]) !== '') { $cells++; } } }
ok_hr(substr_count($committed, 'INSERT IGNORE INTO product_reference_specs') === $cells, "One reference row per non-empty spec cell ($cells): " . substr_count($committed, 'INSERT IGNORE INTO product_reference_specs'));
$variantSlugs = array_column(array_filter($nonMonitor, static fn(array $r): bool => $r['catalog_disposition'] === 'REVIEW_EXACT_VARIANT'), 'catalog_slug');
ok_hr(count($variantSlugs) === 4, 'Four rows warn about the exact variant');
foreach ($candidates as $c) { ok_hr(str_contains($committed, "'" . $c['catalog_slug'] . "'"), 'Candidate present: ' . $c['catalog_slug']); }
foreach ($rows as $r) { if ($r['category'] === 'monitor') { ok_hr(!str_contains($committed, "'" . $r['catalog_slug'] . "'"), 'Monitor not inserted: ' . $r['catalog_slug']); } }
ok_hr(str_contains($committed, 'CREATE TABLE IF NOT EXISTS product_reference_specs'), 'The table is created idempotently');
// the new brands exist before products reference them
ok_hr(str_contains($committed, "INSERT IGNORE INTO brands (name,slug) VALUES ('DeepCool','deepcool')") && str_contains($committed, "'Montech','montech'"), 'The two new brands (DeepCool, Montech) are added before use');
// every source is an official page over https
foreach ($rows as $r) { ok_hr(str_starts_with($r['source_url'], 'https://') && $r['date_checked'] === '2026-11-17', 'Source is https and dated: ' . $r['catalog_slug']); }
echo "hardware_reference_migration_test: PASS\n";
