<?php
declare(strict_types=1);

/**
 * LS-23 E1: one command for a collection batch. Checks every file against the intake contract, then takes each CLEAN dataset through its importer and reports
 * counts only. DRY RUN unless --apply (which needs --actor=<admin user id>). A dataset with any problem is skipped whole. Imported rows are PENDING for a person to
 * review; nothing is approved, no migration is applied (C and D report what a migration would hold), no formula changes. Shared DEV database only on the
 * owner's instruction.
 *
 *   MODINSPECT_ENV_FILE=.env.testing php cli/intake_daily_run.php <folder> [--apply --actor=ID] [--json]
 *
 * Exit: 0 ran, 1 a dataset had problems, 2 usage.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Intake\IntakeDailyRun;

$dir = null; $apply = false; $actor = 0; $json = false;
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--apply') { $apply = true; } elseif ($a === '--json') { $json = true; } elseif (str_starts_with($a, '--actor=')) { $actor = (int)substr($a, 8); } elseif (!str_starts_with($a, '--')) { $dir = $a; }
}
if ($dir === null || !is_dir($dir)) { fwrite(STDERR, "Usage: php cli/intake_daily_run.php <folder> [--apply --actor=ID] [--json]\n"); exit(2); }
global $config;
$db = Database::connection($config['db']);
if ($apply) {
    $u = $db->prepare("SELECT 1 FROM users WHERE id = :id AND is_active = 1 AND role = 'admin'"); $u->execute(['id' => $actor]);
    if ($actor < 1 || !$u->fetchColumn()) { fwrite(STDERR, "--apply needs --actor=<id of an active admin user>.\n"); exit(2); }
}
$report = IntakeDailyRun::run($db, $dir, $apply, $actor, ROOT_PATH . '/storage/import');
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
exit($report['skipped'] === [] ? 0 : 1);
