<?php
declare(strict_types=1);

/**
 * LS-29 A1: plan, and only with --apply run, the migrations a database does not have yet.
 *   php cli/migrate_pending.php [--from=22]          dry run: prints the plan, writes nothing
 *   php cli/migrate_pending.php [--from=22] --apply  backs the database up first, then runs each "run" file in order with cli/migrate.php
 * Refuses to apply when any file is partial, when the backup fails, or when there is nothing to run. Stops at the first file that fails and re-reads the status after
 * every file. Files whose effect cannot be seen (data only) are listed as "manual" and never run here. Always check which database the first line names.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Ops\DatabaseBackup;
use App\Services\Ops\MigrationPlan;

global $config;
$from = 22; $apply = false;
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--from=')) { $from = (int)substr($a, 7); }
    elseif ($a === '--apply') { $apply = true; }
    else { fwrite(STDERR, "Unknown option: $a\n"); exit(2); }
}
$db = Database::connection($config['db']);
$existing = static function () use ($db): array {
    $st = $db->prepare('SELECT LOWER(TABLE_NAME) t, LOWER(COLUMN_NAME) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()');
    $st->execute(); $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $out[$r['t']][] = $r['c']; }
    return $out;
};
$files = [];
foreach (glob(ROOT_PATH . '/database/migrations/*.sql') ?: [] as $f) { $files[basename($f)] = (string)file_get_contents($f); }
$plan = MigrationPlan::build($files, $existing(), $from, (new App\Services\Ops\MigrationLedger($db))->all());
echo 'Database: ' . $config['db']['database'] . ' (' . ($apply ? 'APPLY' : 'dry run') . ")\n";
foreach ($plan['items'] as $i) {
    printf("%-44s %-8s %s\n", $i['file'], strtoupper($i['action']), $i['verdict']);
    foreach ($i['missing'] as $m) { echo "    missing: $m\n"; }
}
if (!$plan['safe']) { echo "BLOCKED: a partial file exists; check it by hand before anything is applied.\n"; exit(1); }
if ($plan['to_run'] === []) { echo "Nothing to run.\n"; exit(0); }
echo 'To run, in order: ' . implode(', ', $plan['to_run']) . "\n";
if (!$apply) { echo "Dry run only. Add --apply to back up and run them.\n"; exit(0); }

$backup = DatabaseBackup::backup($config['db'], ROOT_PATH . '/storage/backups/' . $config['db']['database'] . '-pre-migrate-' . date('Ymd-His') . '.sql');
if (!$backup['ok']) { fwrite(STDERR, 'Backup failed, nothing applied: ' . $backup['error'] . "\n"); exit(1); }
echo "Backup written ({$backup['bytes']} bytes).\n";
foreach ($plan['to_run'] as $file) {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(ROOT_PATH . '/cli/migrate.php') . ' ' . escapeshellarg('database/migrations/' . $file), $code);
    $after = MigrationPlan::build([$file => $files[$file]], $existing(), 0)['items'][0];
    if ($code !== 0 || $after['verdict'] !== 'applied') { fwrite(STDERR, "Stopped: $file did not apply cleanly ({$after['verdict']}). Check it by hand; the backup above is the way back.\n"); exit(1); }
    echo "applied $file\n";
}
echo "Done.\n";
