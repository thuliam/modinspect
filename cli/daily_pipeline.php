<?php
declare(strict_types=1);

/**
 * LS-24 M2: one cron-safe command for the daily data routine. DRY RUN unless --apply (which needs --actor=<admin user id>).
 *   1. if a batch folder is given and it exists: check and import it (IntakeDailyRun: a dataset with a problem is skipped whole, rows land as PENDING for a person)
 *   2. what a recalculation would change in the used prices (SnapshotRecompute); with --apply only new or changed prices get a snapshot
 *   3. catalog coverage totals (CoverageReport)
 * Appends ONE line of counts to storage/logs/daily_pipeline.log (no title, price or link) and prints the same summary. Nothing is approved, no migration is applied.
 *
 *   php cli/daily_pipeline.php [<batch-folder>] [--apply --actor=ID] [--json]
 * Exit: 0 ran clean, 1 a dataset had problems, 2 usage.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Admin\CoverageReport;
use App\Services\Intake\IntakeDailyRun;
use App\Services\Pricing\PriceSnapshotService;
use App\Services\Pricing\SnapshotRecompute;

$dir = null; $apply = false; $actor = 0; $json = false;
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--apply') { $apply = true; } elseif ($a === '--json') { $json = true; } elseif (str_starts_with($a, '--actor=')) { $actor = (int)substr($a, 8); } elseif (!str_starts_with($a, '--')) { $dir = $a; }
}
global $config;
$db = Database::connection($config['db']);
if ($apply) {
    $u = $db->prepare("SELECT 1 FROM users WHERE id = :id AND is_active = 1 AND role = 'admin'"); $u->execute(['id' => $actor]);
    if ($actor < 1 || !$u->fetchColumn()) { fwrite(STDERR, "--apply needs --actor=<id of an active admin user>.\n"); exit(2); }
}
$out = ['at' => date('c'), 'mode' => $apply ? 'apply' : 'dry run', 'batch' => null, 'snapshot_plan' => null, 'stored_snapshots' => 0, 'coverage' => null];
$problems = false;
if ($dir !== null && is_dir($dir)) {
    $b = IntakeDailyRun::run($db, $dir, $apply, $actor, ROOT_PATH . '/storage/import');
    $out['batch'] = ['files' => array_map(static fn(array $f): array => ['rows' => $f['rows'], 'valid_rows' => $f['valid_rows']], $b['files']), 'skipped' => array_keys($b['skipped']), 'steps' => array_map(static fn(array $s): array => array_filter($s, 'is_scalar'), $b['steps'])];
    $problems = $b['skipped'] !== [];
}
$job = new SnapshotRecompute($db, new PriceSnapshotService());
$plan = $job->plan('asking');
$out['snapshot_plan'] = SnapshotRecompute::counts($plan);
if ($apply) { $out['stored_snapshots'] = $job->apply($plan, 'asking'); }
$cov = CoverageReport::build($db);
$out['coverage'] = ['models' => array_sum(array_column($cov, 'models')), 'priced' => array_sum(array_column($cov, 'with_price')), 'two_sources' => array_sum(array_column($cov, 'with_two_sources')), 'specs_complete' => array_sum(array_column($cov, 'specs_complete')), 'new_price' => array_sum(array_column($cov, 'with_new_price'))];
$logDir = ROOT_PATH . '/storage/logs';
if (is_dir($logDir) || @mkdir($logDir, 0775, true)) { @file_put_contents($logDir . '/daily_pipeline.log', json_encode($out, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX); }
echo $json ? json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n" : sprintf("%s | snapshots %s%s | coverage %s\n", $out['mode'], json_encode($out['snapshot_plan']), $apply ? ' stored=' . $out['stored_snapshots'] : '', json_encode($out['coverage']));
exit($problems ? 1 : 0);
