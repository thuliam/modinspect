<?php
declare(strict_types=1);

/**
 * LS-89 BE-3: one cron-safe command for the daily routine: backup, prices, trend, health. Each job is recorded (table job_runs) and never runs twice at once.
 *   php cli/daily_jobs.php                                  back up, then only report what prices and trend would change
 *   php cli/daily_jobs.php --apply --actor=<admin user id>  back up, then update prices and the trend (nothing is written if the backup fails)
 * Options: --keep=14 (backup files to keep)  --json
 * Exit: 0 every job ran clean, 1 a job failed or was already running, 2 usage. Importing listings stays in cli/daily_pipeline.php.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Ops\BackupRetention;
use App\Services\Ops\DailyJobs;
use App\Services\Ops\DatabaseBackup;

$apply = false; $actor = 0; $keep = 14; $json = false;
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--apply') { $apply = true; }
    elseif ($a === '--json') { $json = true; }
    elseif (str_starts_with($a, '--actor=')) { $actor = (int)substr($a, 8); }
    elseif (str_starts_with($a, '--keep=')) { $keep = (int)substr($a, 7); }
    else { fwrite(STDERR, "Unknown option: $a\n"); exit(2); }
}
if ($keep < 1) { fwrite(STDERR, "--keep must be at least 1\n"); exit(2); }
global $config;
$db = Database::connection($config['db']);
if ($apply) {
    $u = $db->prepare("SELECT 1 FROM users WHERE id = :id AND is_active = 1 AND role = 'admin'"); $u->execute(['id' => $actor]);
    if ($actor < 1 || !$u->fetchColumn()) { fwrite(STDERR, "--apply needs --actor=<id of an active admin user>.\n"); exit(2); }
}
$backup = static function () use ($config, $keep): array {
    $dir = ROOT_PATH . '/storage/backups';
    $result = DatabaseBackup::backup($config['db'], $dir . '/' . $config['db']['database'] . '-' . date('Ymd-His') . '.sql');
    if ($result['ok']) { BackupRetention::prune($dir, $keep); }
    return $result;
};
$results = (new DailyJobs($db, $backup))->run($apply, $actor > 0 ? $actor : null);
if ($json) {
    echo json_encode(['database' => $config['db']['database'], 'mode' => $apply ? 'apply' : 'report', 'jobs' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
} else {
    echo 'Database: ' . $config['db']['database'] . ' (' . ($apply ? 'APPLY' : 'report only') . ")\n";
    foreach ($results as $name => $r) { printf("%-8s %-8s %s %s\n", $name, $r['status'], (string)($r['error'] ?? ''), json_encode($r['summary'], JSON_UNESCAPED_UNICODE)); }
}
exit(array_filter($results, static fn(array $r): bool => $r['status'] !== 'ok') === [] ? 0 : 1);
