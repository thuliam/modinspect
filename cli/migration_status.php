<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Ops\MigrationLedger;
use App\Services\Ops\MigrationStatus;

/**
 * LS-26: report of which migrations the connected database already has (information_schema) and, since LS-89, which are written down in schema_migrations.
 * It never runs a migration file. Read-only unless --record-applied, which writes a ledger row for every file whose effect is already there and has none.
 * Usage: php cli/migration_status.php [from_number] [--record-applied]   (default from 20)
 */
global $config;
$from = 20; $recordApplied = false;
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--record-applied') { $recordApplied = true; } elseif (ctype_digit($a)) { $from = (int) $a; } else { fwrite(STDERR, "Unknown option: $a\n"); exit(2); }
}
$db = Database::connection($config['db']);
$st = $db->prepare('SELECT LOWER(TABLE_NAME) t, LOWER(COLUMN_NAME) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()');
$st->execute();
$existing = [];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $existing[$r['t']][] = $r['c']; }
echo 'Database: ' . $config['db']['database'] . "\n";
$ledger = new MigrationLedger($db);
$recorded = $ledger->all();
$files = glob(ROOT_PATH . '/database/migrations/*.sql') ?: [];
sort($files);
$bad = 0; $written = 0;
foreach ($files as $f) {
    $name = basename($f);
    if ((int) $name < $from) { continue; }
    $sql = (string) file_get_contents($f);
    $r = MigrationStatus::evaluate($sql, $existing);
    if ($recordApplied && $r['verdict'] === 'applied' && !isset($recorded[$name]) && $ledger->record($name, $sql, 'recorded')) { $written++; $recorded = $ledger->all(); }
    $note = '';
    if (isset($recorded[$name])) { $note = 'recorded ' . substr($recorded[$name]['applied_at'], 0, 10) . ($recorded[$name]['checksum'] === MigrationLedger::checksum($sql) ? '' : ' (file changed since)'); }
    printf("%-52s %-8s %d/%d %s\n", $name, strtoupper($r['verdict']), $r['found'], $r['expected'], $note);
    foreach ($r['missing'] as $m) { echo "    missing: $m\n"; }
    if ($r['verdict'] === 'partial') { $bad++; }
}
if (!$ledger->available()) { echo "No schema_migrations table on this database (migration 038).\n"; }
if ($recordApplied) { echo "Ledger rows written: $written\n"; }
echo $bad > 0 ? "A PARTIAL file was applied only in part: do not rerun it whole, check the missing items by hand.\n" : "No partial files.\n";
exit($bad > 0 ? 1 : 0);
