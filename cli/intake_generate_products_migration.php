<?php
declare(strict_types=1);

/**
 * LS-23 B6: validates D_new_products.csv (and D_aliases.csv if present) with the intake contract and writes an additive migration (INSERT only) into
 * database/migrations/. Reads the catalog (SELECTs only), writes ONE file, never touches the database. Apply it as a separate, deliberate step
 * (`php cli/migrate.php <file>`), on the shared DEV database only on the owner's instruction. Run it BEFORE the reference-spec migration of the same batch so the
 * models exist when their specs are inserted.
 *
 *   MODINSPECT_ENV_FILE=.env.testing php cli/intake_generate_products_migration.php <folder> [--out=database/migrations/NNN_name.sql]
 *
 * Exit: 0 written, 1 problems (nothing written), 2 usage.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Intake\IntakeProductsMigration;
use App\Services\Intake\IntakeValidator;

$dir = null; $out = null;
foreach (array_slice($argv, 1) as $a) { if (str_starts_with($a, '--out=')) { $out = substr($a, 6); } elseif (!str_starts_with($a, '--')) { $dir = $a; } }
$c = IntakeValidator::contract();
$fp = ($dir ?? '') . '/' . $c['datasets']['D_products']['file'];
if ($dir === null || !is_file($fp)) { fwrite(STDERR, "Usage: php cli/intake_generate_products_migration.php <folder with D_new_products.csv> [--out=file]\n"); exit(2); }

global $config;
$db = Database::connection($config['db']);
$ctx = ['now' => new DateTimeImmutable('now'), 'products' => [], 'names' => [], 'aliases' => []];
foreach ($db->query('SELECT p.full_name n, c.slug cat FROM products p JOIN product_categories c ON c.id = p.category_id WHERE p.is_active = 1')->fetchAll(PDO::FETCH_ASSOC) as $p) { $ctx['products'][IntakeValidator::norm((string)$p['n'])] = (string)$p['cat']; }
foreach ($db->query('SELECT normalized_alias a FROM product_aliases')->fetchAll(PDO::FETCH_COLUMN) as $a) { $ctx['aliases'][(string)$a] = true; }
$vp = IntakeValidator::validateFile('D_products', $fp, $ctx);
foreach ($vp['valid'] as $r) { $ctx['batch_products'][IntakeValidator::norm($r['full_name'])] = true; }
$fa = $dir . '/' . $c['datasets']['D_aliases']['file'];
$va = is_file($fa) ? IntakeValidator::validateFile('D_aliases', $fa, $ctx) : ['valid' => [], 'header_errors' => [], 'row_errors' => [], 'codes' => []];
if ($vp['header_errors'] || $vp['row_errors'] || $va['header_errors'] || $va['row_errors']) {
    echo json_encode(['result' => 'files have problems, nothing written', 'products' => ['header_errors' => $vp['header_errors'], 'error_codes' => $vp['codes']], 'aliases' => ['header_errors' => $va['header_errors'], 'error_codes' => $va['codes']]], JSON_PRETTY_PRINT), "\n";
    exit(1);
}
$g = IntakeProductsMigration::generate($vp['valid'], $va['valid']);
if ($out === null) {
    $max = 0; foreach (glob(ROOT_PATH . '/database/migrations/*.sql') ?: [] as $m) { if (preg_match('/^(\d+)_/', basename($m), $mm)) { $max = max($max, (int)$mm[1]); } }
    $out = ROOT_PATH . '/database/migrations/' . str_pad((string)($max + 1), 3, '0', STR_PAD_LEFT) . '_collected_models_and_aliases.sql';
}
if (is_file($out)) { fwrite(STDERR, "Refusing to overwrite an existing file.\n"); exit(2); }
file_put_contents($out, $g['sql']);
echo json_encode(['result' => 'written', 'file' => $out, 'models' => $g['models'], 'aliases' => $g['aliases'], 'aliases_dropped' => count($g['dropped']), 'dropped_reasons' => array_count_values(array_column($g['dropped'], 'reason')), 'bare_number_aliases' => count($g['risky']),
    'next' => 'review the file, then php cli/migrate.php ' . $out . ' (test database first)'], JSON_PRETTY_PRINT), "\n";
exit(0);
