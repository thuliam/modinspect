<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Ops\MigrationStatus;

/**
 * LS-26: read-only report of which migrations the connected database already has. Only SELECTs on information_schema; it never runs a migration file.
 * Usage: php cli/migration_status.php [from_number]   (default 20)
 */
global $config;
$from = (int) ($argv[1] ?? 20);
$db = Database::connection($config['db']);
$st = $db->prepare('SELECT LOWER(TABLE_NAME) t, LOWER(COLUMN_NAME) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()');
$st->execute();
$existing = [];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $existing[$r['t']][] = $r['c']; }
echo 'Database: ' . $config['db']['database'] . "\n";
$files = glob(ROOT_PATH . '/database/migrations/*.sql') ?: [];
sort($files);
$bad = 0;
foreach ($files as $f) {
    $name = basename($f);
    if ((int) $name < $from) { continue; }
    $r = MigrationStatus::evaluate((string) file_get_contents($f), $existing);
    printf("%-52s %-8s %d/%d\n", $name, strtoupper($r['verdict']), $r['found'], $r['expected']);
    foreach ($r['missing'] as $m) { echo "    missing: $m\n"; }
    if ($r['verdict'] === 'partial') { $bad++; }
}
echo $bad > 0 ? "A PARTIAL file was applied only in part: do not rerun it whole, check the missing items by hand.\n" : "No partial files.\n";
exit($bad > 0 ? 1 : 0);
