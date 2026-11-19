<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Admin\IntakeTargets;

/** LS-23 A3: the target files are read-only exports; the CSV cannot carry a spreadsheet formula and lists what it should. */
function ok_it(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$csv = IntakeTargets::csv(['a', 'b'], [['=SUM(1)', 'ปกติ'], ['+1', '-2'], ['ok', '@x']]);
ok_it(str_starts_with($csv, "\xEF\xBB\xBF"), 'The CSV starts with a BOM so Excel reads Thai');
ok_it(!str_contains($csv, "\n=") && str_contains($csv, "'=SUM(1)") && str_contains($csv, "'+1") && str_contains($csv, "'-2") && str_contains($csv, "'@x"), 'A cell that starts with = + - @ is neutralised');

global $config;
$db = \App\Core\Database::connection($config['db']);
$before = (int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn();
$dir = sys_get_temp_dir() . '/mi_targets_' . bin2hex(random_bytes(4));
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(ROOT_PATH . '/cli/export_collection_targets.php') . ' --out-dir=' . escapeshellarg($dir) . ' 2>&1', $out, $code);
ok_it($code === 0, 'The export CLI exits 0: ' . implode(' ', array_slice($out, 0, 3)));
foreach (['A_targets.csv', 'B_targets.csv', 'D_existing_names.csv'] as $f) {
    ok_it(is_file($dir . '/' . $f) && str_starts_with((string)file_get_contents($dir . '/' . $f), "\xEF\xBB\xBF"), "$f is written");
    @unlink($dir . '/' . $f);
}
@rmdir($dir);
ok_it((int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn() === $before, 'The export writes no row');
ok_it(!preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP)\b/', (string)file_get_contents(ROOT_PATH . '/app/Services/Admin/IntakeTargets.php') . (string)file_get_contents(ROOT_PATH . '/cli/export_collection_targets.php')), 'The export is SELECT-only');
$names = IntakeTargets::existingNames($db);
ok_it(array_reduce($names, static fn(bool $c, array $r): bool => $c && count($r) === 4, true), 'Every existing-name row has category, brand, name, aliases');
echo "intake_targets_test: PASS\n";
