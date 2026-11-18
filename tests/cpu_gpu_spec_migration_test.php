<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Catalog\CpuGpuSpecMigration;
use App\Services\Collection\OwnerRowPreparer;

/**
 * F-2: the official CPU and GPU specification CSVs (read page by page from AMD, Intel and NVIDIA, source and date on every row) become one additive migration:
 *  - only facts a CSV cell states are written, into product_reference_specs (never into products, prices, observations or snapshots);
 *  - every row keeps its manufacturer URL and check date; a row that the CSV marks VARIANTS is stored as needs_variant_review;
 *  - the migration never inserts a product: a model that is not in the catalog is simply not written (no invented product);
 *  - the committed migration equals what the generator produces from the committed CSVs, so the SQL is never edited by hand. Pure (no database).
 */
function ok_cg(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$CH = 'category,catalog_slug,official_name,source_url,date_checked,release_date,release_quarter,architecture,cores,threads,p_cores,e_cores,base_ghz,boost_ghz,l2_mb,l3_mb,memory,socket,note';
$GH = 'category,catalog_slug,official_name,source_url,date_checked,release_date,architecture,gpu_cores,boost_mhz,vram_gb,memory_type,bus_width_bit,board_power_w,power_connector,psu_recommend_w,interface,note';
$csv = static function (string $h, array $rows): array {
    $f = tempnam(sys_get_temp_dir(), 'cg'); file_put_contents($f, $h . "\n" . implode("\n", $rows) . "\n");
    $r = OwnerRowPreparer::readCsv($f); unlink($f); return $r;
};
$cpu = $csv($CH, [
    "cpu,acme-cpu-1,Acme CPU 1,https://example.com/cpu1,2026-11-18,2022-04-04,,Zen 3,6,12,,,3.5,4.4,3,32,\"DDR4, up to 3200 MT/s\",AM4,\"it's a note, with comma\"",
    "cpu,acme-cpu-2,Acme CPU 2,https://example.com/cpu2,2026-11-18,,2024-Q1,,10,16,6,4,2.5,4.7,,20,DDR5,LGA1700,",
]);
$gpu = $csv($GH, [
    "gpu,acme-gpu-1,Acme GPU 1,https://example.com/gpu1,2026-11-18,,Ada Lovelace,3072,2460,8,GDDR6,128,115,,550,PCIe Gen 4,",
    "gpu,acme-gpu-2,Acme GPU 2,https://example.com/gpu2,2026-11-18,2021-03-01,,2560,2581,\"12 / 8\",GDDR6,\"192 / 128\",230,1x8-Pin + 1x6-Pin,650,,VARIANTS: two versions listed",
]);
$sql = CpuGpuSpecMigration::generate($cpu, $gpu);

$ins = static fn(string $s): int => substr_count($s, 'INSERT IGNORE INTO product_reference_specs');
// one reference row per non-empty cell: cpu1 = date,arch,cores,threads,base,boost,l2,l3,memory,socket = 10; cpu2 = quarter,cores,threads,p,e,base,boost,l3,memory,socket = 10;
// gpu1 = arch,cores,boost,vram,memtype,bus,power,psu,interface = 9; gpu2 = date,cores,boost,vram,memtype,bus,power,connector,psu = 9
ok_cg($ins($sql) === 38, 'One reference row per non-empty cell (10+10+9+9): ' . $ins($sql));
ok_cg(preg_match("/'release_date','2022-04-04',NULL,'https:\\/\\/example.com\\/cpu1','2026-11-18','official_checked'/", $sql) === 1, 'A date is stored as release_date with its source, check date and status');
ok_cg(str_contains($sql, "'release_quarter','2024-Q1'"), 'A launch quarter is stored as release_quarter');
ok_cg(preg_match("/'memory_support','DDR4, up to 3200 MT\\/s'/", $sql) === 1, 'CPU memory support is stored under memory_support, not memory_type (020 owns memory_type)');
ok_cg(str_contains($sql, "'boost_ghz','4.4','GHz'") && str_contains($sql, "'l3_mb','32','MB'") && str_contains($sql, "'boost_mhz','2460','MHz'") && str_contains($sql, "'vram_gb','8','GB'") && str_contains($sql, "'bus_width_bit','128','bit'"), 'Units are attached to clocks, cache, VRAM and bus width');
ok_cg(str_contains($sql, "'board_power_watt','115','W'") && str_contains($sql, "'system_psu_recommendation_watt','550','W'"), 'GPU board power and PSU use the keys already used by 020');
ok_cg(!str_contains($sql, "'gpu_cores','',") && !str_contains($sql, "'e_cores','',") && !str_contains($sql, "'l2_mb','',"), 'An empty cell writes nothing');
ok_cg(str_contains($sql, "'it''s a note, with comma'"), 'Single quotes are doubled and a quoted field with a comma survives');
ok_cg(preg_match("/'socket','LGA1700',NULL,'https:\\/\\/example.com\\/cpu2'/", $sql) === 1, 'Each row carries its own source URL');
ok_cg(preg_match("/'vram_gb','12 \\/ 8','GB','https:\\/\\/example.com\\/gpu2','2026-11-18','needs_variant_review','VARIANTS: two versions listed'/", $sql) === 1, 'A VARIANTS row is stored as needs_variant_review with its note');
ok_cg(preg_match("/'cores','10',NULL,'https:\\/\\/example.com\\/cpu2','2026-11-18','official_checked',NULL/", $sql) === 1, 'A plain row is official_checked with no note');

// safety: reference table only; never a product, a price, an observation or a snapshot; no destructive statement
$noComments = (string)preg_replace('/^--.*$/m', '', $sql);
ok_cg(!str_contains($noComments, 'INSERT INTO products') && !str_contains($noComments, 'INSERT INTO brands'), 'The migration never inserts a product or a brand');
ok_cg(preg_match('/\b(UPDATE|DELETE|DROP|TRUNCATE|ALTER|REPLACE|CREATE)\b/i', $noComments) !== 1, 'The generated SQL contains no UPDATE, DELETE, DROP, TRUNCATE, ALTER, REPLACE or CREATE');
foreach (['raw_price_observations', 'price_snapshots', 'price_indices', 'market_', 'verified_status'] as $price) { ok_cg(!str_contains($sql, $price), "The generated SQL never touches prices ($price)"); }
ok_cg(preg_match_all('/FROM products WHERE slug=\'(acme-[a-z0-9-]+)\'/', $sql) === 38, 'Every statement selects the product by its slug (a missing slug writes nothing)');
ok_cg(str_contains($sql, 'INSERT IGNORE'), 'Idempotent: running it twice adds no duplicate');

// a CSV with a wrong category is refused, so a CPU can never be written from the GPU file by accident
$threw = false;
try { CpuGpuSpecMigration::generate($gpu, $cpu); } catch (InvalidArgumentException $e) { $threw = true; }
ok_cg($threw, 'Rows of the wrong category are refused');

// the committed migration is exactly the generator's output for the committed CSVs
$root = dirname(__DIR__);
$cpuRows = OwnerRowPreparer::readCsv($root . '/database/reference/official_cpu_specs.csv');
$gpuRows = OwnerRowPreparer::readCsv($root . '/database/reference/official_gpu_specs.csv');
$committed = (string)file_get_contents($root . '/database/migrations/021_cpu_gpu_reference_specs.sql');
ok_cg($committed === CpuGpuSpecMigration::generate($cpuRows, $gpuRows), 'database/migrations/021_cpu_gpu_reference_specs.sql equals the generator output (run php cli/gen_cpu_gpu_spec_migration.php)');

// facts about the real files
ok_cg(count($cpuRows) === 28 && count($gpuRows) === 29, 'All 28 CPUs and 29 GPUs of the catalog are covered: ' . count($cpuRows) . '/' . count($gpuRows));
foreach (array_merge($cpuRows, $gpuRows) as $r) {
    ok_cg(str_starts_with($r['source_url'], 'https://') && $r['date_checked'] === '2026-11-18', 'Source is https and dated: ' . $r['catalog_slug']);
    ok_cg(strlen($r['note']) <= 500, 'Note fits the column: ' . $r['catalog_slug']);
    ok_cg(strlen($r['source_url']) <= 500, 'URL fits the column: ' . $r['catalog_slug']);
}
foreach ($cpuRows as $r) { ok_cg($r['release_date'] !== '' || $r['release_quarter'] !== '', 'Every CPU has a launch date or quarter: ' . $r['catalog_slug']); }
$slugs = array_column(array_merge($cpuRows, $gpuRows), 'catalog_slug');
ok_cg(count($slugs) === count(array_unique($slugs)), 'No catalog slug appears twice');
ok_cg(!str_contains($committed, 'passmark') && !str_contains($committed, 'geekbench') && !str_contains($committed, 'techpowerup'), 'No restricted benchmark source appears');
foreach (array_merge($cpuRows, $gpuRows) as $r) { ok_cg(preg_match('#^https://(www\.amd\.com|www\.intel\.com|www\.nvidia\.com)/#', $r['source_url']) === 1, 'Only a manufacturer page is a source: ' . $r['catalog_slug']); }
$variantGpus = array_filter($gpuRows, static fn(array $r): bool => str_starts_with($r['note'], 'VARIANTS:'));
ok_cg(count($variantGpus) === 11 && substr_count($committed, "'needs_variant_review'") > 0, 'The variant warnings of the GPU file reach the migration');
echo "cpu_gpu_spec_migration_test: PASS\n";
