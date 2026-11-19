<?php
declare(strict_types=1);

/**
 * LS-23 B5: validates C_reference_specs.csv with the intake contract and writes an additive migration (INSERT IGNORE only) into database/migrations/.
 * It reads the catalog (SELECTs only) and writes ONE file: it never touches the database. Applying the migration is a separate, deliberate step
 * (`php cli/migrate.php <file>`), against the shared DEV database only on the owner's instruction.
 *
 *   MODINSPECT_ENV_FILE=.env.testing php cli/intake_generate_specs_migration.php <C_reference_specs.csv> [--out=database/migrations/NNN_name.sql]
 *
 * Exit: 0 written, 1 the file has problems (nothing written), 2 usage.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Intake\IntakeSpecsMigration;
use App\Services\Intake\IntakeValidator;

$file = null; $out = null;
foreach (array_slice($argv, 1) as $a) { if (str_starts_with($a, '--out=')) { $out = substr($a, 6); } elseif (!str_starts_with($a, '--')) { $file = $a; } }
if ($file === null || !is_file($file)) { fwrite(STDERR, "Usage: php cli/intake_generate_specs_migration.php <C_reference_specs.csv> [--out=file]\n"); exit(2); }

global $config;
$db = Database::connection($config['db']);
$ctx = ['now' => new DateTimeImmutable('now'), 'products' => [], 'names' => []];
foreach ($db->query('SELECT p.full_name n, c.slug cat FROM products p JOIN product_categories c ON c.id = p.category_id WHERE p.is_active = 1')->fetchAll(PDO::FETCH_ASSOC) as $p) { $ctx['products'][IntakeValidator::norm((string)$p['n'])] = (string)$p['cat']; }
$v = IntakeValidator::validateFile('C', $file, $ctx);
if ($v['header_errors'] !== [] || $v['row_errors'] !== []) {
    echo json_encode(['result' => 'file has problems, nothing written', 'header_errors' => $v['header_errors'], 'error_codes' => $v['codes'], 'first_errors' => array_slice($v['row_errors'], 0, 20)], JSON_PRETTY_PRINT), "\n";
    exit(1);
}
$g = IntakeSpecsMigration::generate($v['valid']);
if ($out === null) {
    $max = 0; foreach (glob(ROOT_PATH . '/database/migrations/*.sql') ?: [] as $m) { if (preg_match('/^(\d+)_/', basename($m), $mm)) { $max = max($max, (int)$mm[1]); } }
    $out = ROOT_PATH . '/database/migrations/' . str_pad((string)($max + 1), 3, '0', STR_PAD_LEFT) . '_collected_reference_specs.sql';
}
if (is_file($out)) { fwrite(STDERR, "Refusing to overwrite an existing file.\n"); exit(2); }
file_put_contents($out, $g['sql']);
echo json_encode(['result' => 'written', 'file' => $out, 'specs' => $g['specs'], 'models' => $g['models'], 'next' => 'review the file, then php cli/migrate.php ' . $out . ' (test database first)'], JSON_PRETTY_PRINT), "\n";
exit(0);
