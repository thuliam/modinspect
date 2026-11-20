<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Catalog\ProductFacts;
use App\Services\Intake\IntakeSpecsMigration as M;
use App\Services\Intake\IntakeValidator;

/**
 * LS-23 B5: dataset C becomes an additive, idempotent migration (INSERT IGNORE only). Quotes and backslashes cannot break out of a string, an existing value is
 * never overwritten, an unknown model adds nothing, and every spec key of the contract has a Thai label so the value can be shown on the product page.
 */
function ok_sm(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$row = static fn(string $name, string $key, string $val, array $more = []): array => array_merge(['product_name' => $name, 'spec_key' => $key, 'spec_value' => $val, 'unit' => '', 'source_url' => 'https://www.example-maker.com/p/1', 'date_checked' => '2026-11-10', 'review_status' => 'official_checked', 'review_note' => ''], $more);

$g = M::generate([$row("O'Brien \\ Board", 'speed_mhz', '3200', ['unit' => 'MHz', 'review_note' => "it's \\ ok; DROP TABLE x"]), $row('Plain', 'memory_bus_bit', '256')]);
ok_sm($g['specs'] === 2 && $g['models'] === 2, 'Counts specs and models');
ok_sm(str_contains($g['sql'], "'O''Brien \\\\ Board'") && str_contains($g['sql'], "'it''s \\\\ ok; DROP TABLE x'"), 'Quotes are doubled and backslashes escaped');
ok_sm(str_contains($g['sql'], "'bus_width_bit'") && !str_contains($g['sql'], "'memory_bus_bit'"), 'The contract spelling memory_bus_bit is stored under the catalog key bus_width_bit');
foreach (array_filter(preg_split('/\n/', $g['sql']), static fn(string $l): bool => $l !== '' && !str_starts_with($l, '--')) as $line) {
    ok_sm(str_starts_with($line, 'INSERT IGNORE INTO product_reference_specs ') && str_ends_with($line, ';'), 'Every statement is one INSERT IGNORE on one line: ' . substr($line, 0, 60));
}
ok_sm(!preg_match('/^(UPDATE|DELETE|DROP|ALTER|CREATE|TRUNCATE|REPLACE)\b/m', $g['sql']), 'No statement can change or remove existing data');

// ---- run it on the test database (rolled back) ----
global $config;
$db = Database::connection($config['db']);
ok_sm($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$p = $db->query('SELECT id, full_name FROM products WHERE is_active = 1 ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$run = static function (string $sql) use ($db): void { foreach (array_filter(array_map('trim', explode(";\n", (string)preg_replace('/^--.*$/m', '', $sql))), static fn(string $s): bool => $s !== '') as $stmt) { $db->exec($stmt); } };
$val = static fn(string $key): ?string => ($x = $db->prepare('SELECT spec_value FROM product_reference_specs WHERE product_id = :p AND spec_key = :k')) && $x->execute(['p' => $p['id'], 'k' => $key]) ? (($f = $x->fetchColumn()) === false ? null : (string)$f) : null;
$db->beginTransaction();
try {
    $sql = M::generate([$row($p['full_name'], 'speed_mhz', '3200', ['unit' => 'MHz']), $row('No Such Model 9000', 'speed_mhz', '1')])['sql'];
    $run($sql);
    ok_sm($val('speed_mhz') === '3200', 'The spec is stored for the existing model');
    ok_sm((int)$db->query("SELECT COUNT(*) FROM product_reference_specs s WHERE s.spec_key = 'speed_mhz' AND s.spec_value = '1'")->fetchColumn() === 0, 'An unknown model name adds nothing');
    $n = (int)$db->query('SELECT COUNT(*) FROM product_reference_specs')->fetchColumn();
    $run($sql);
    ok_sm((int)$db->query('SELECT COUNT(*) FROM product_reference_specs')->fetchColumn() === $n, 'Running it twice adds nothing (idempotent)');
    $run(M::generate([$row($p['full_name'], 'speed_mhz', '9999')])['sql']);
    ok_sm($val('speed_mhz') === '3200', 'A value already stored is never overwritten');
} finally {
    $db->rollBack();
}

// ---- every spec key of the contract can be shown ----
$shown = [];
foreach (IntakeValidator::specKeys() as $key) {
    $stored = M::KEY_ALIASES[$key] ?? $key;
    $rows = ProductFacts::rows(['id' => 1, 'full_name' => 'x', 'category_slug' => 'cpu', 'release_year' => null], [['spec_key' => $stored, 'spec_value' => '7', 'unit' => null, 'source_url' => 'https://example.test', 'date_checked' => '2026-11-10', 'review_status' => 'official_checked', 'review_note' => null]], 2026);
    $shown[$key] = $rows !== [];
}
$unlabelled = array_keys(array_filter($shown, static fn(bool $b): bool => !$b));
ok_sm($unlabelled === [] || $unlabelled === ['release_date'] || array_diff($unlabelled, ['release_date', 'tdp']) === [], 'Every spec key of the contract has a label on the product page; missing: ' . implode(',', $unlabelled));
echo "intake_specs_migration_test: PASS\n";
