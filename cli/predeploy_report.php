<?php
declare(strict_types=1);

/**
 * LS-29 A3: one report before a deploy. Read-only: it runs the secret scan, reads the git state and plans the migrations (a dry run), and optionally smoke-checks a live URL (GET only).
 *   php cli/predeploy_report.php [--smoke=https://host]
 * fail: secrets found, a partly applied migration, the smoke check failing. warn: uncommitted changes, branch not pushed, migrations still to run (the owner decides when).
 * It never deploys, never applies a migration and never prints a secret value. Exit 0 for PASS or PASS_WITH_WARNINGS, 1 for FAIL.
 * Run the database check with the environment you mean to ship to: the first line of the output names the database.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Ops\MigrationPlan;
use App\Services\Ops\PredeployReport;

global $config;
$smoke = null;
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--smoke=')) { $smoke = substr($a, 8); }
    else { fwrite(STDERR, "Unknown option: $a\n"); exit(2); }
}
$php = escapeshellarg(PHP_BINARY);
$run = static function (string $cmd) : array { exec($cmd . ' 2>&1', $out, $code); return [$code, implode("\n", $out)]; };
$checks = [];

[$code] = $run($php . ' ' . escapeshellarg(ROOT_PATH . '/cli/secret_scan.php'));
$checks[] = ['name' => 'Secret scan', 'level' => $code === 0 ? 'pass' : 'fail', 'note' => $code === 0 ? 'no findings' : 'findings: run php cli/secret_scan.php and fix them'];

[$c1, $dirty] = $run('git -C ' . escapeshellarg(ROOT_PATH) . ' status --porcelain');
$checks[] = ['name' => 'Working tree', 'level' => $c1 !== 0 ? 'warn' : (trim($dirty) === '' ? 'pass' : 'warn'), 'note' => $c1 !== 0 ? 'git not available' : (trim($dirty) === '' ? 'clean' : count(array_filter(explode("\n", $dirty))) . ' uncommitted path(s)')];
[$c2, $ahead] = $run('git -C ' . escapeshellarg(ROOT_PATH) . ' rev-list --count @{upstream}..HEAD');
$checks[] = ['name' => 'Pushed', 'level' => $c2 !== 0 ? 'warn' : ((int)$ahead === 0 ? 'pass' : 'warn'), 'note' => $c2 !== 0 ? 'no upstream branch' : ((int)$ahead === 0 ? 'nothing unpushed' : "$ahead commit(s) not pushed")];

try {
    $db = Database::connection($config['db']);
    $st = $db->prepare('SELECT LOWER(TABLE_NAME) t, LOWER(COLUMN_NAME) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()');
    $st->execute(); $existing = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $existing[$r['t']][] = $r['c']; }
    $files = [];
    foreach (glob(ROOT_PATH . '/database/migrations/*.sql') ?: [] as $f) { $files[basename($f)] = (string)file_get_contents($f); }
    $plan = MigrationPlan::build($files, $existing, 22);
    $level = !$plan['safe'] ? 'fail' : ($plan['to_run'] !== [] ? 'warn' : 'pass');
    $checks[] = ['name' => 'Migrations on ' . $config['db']['database'], 'level' => $level,
        'note' => !$plan['safe'] ? 'a partly applied file: check by hand' : ($plan['to_run'] !== [] ? count($plan['to_run']) . ' to run: ' . implode(', ', $plan['to_run']) : 'all applied')];
} catch (Throwable $e) {
    $checks[] = ['name' => 'Migrations', 'level' => 'fail', 'note' => 'database not reachable'];
}

if ($smoke !== null) {
    [$code] = $run($php . ' ' . escapeshellarg(ROOT_PATH . '/cli/smoke_check.php') . ' ' . escapeshellarg($smoke));
    $checks[] = ['name' => 'Smoke check', 'level' => $code === 0 ? 'pass' : 'fail', 'note' => $code === 0 ? 'all checks passed' : 'failed: run php cli/smoke_check.php for the list'];
}
$r = PredeployReport::summarize($checks);
echo 'Database: ' . $config['db']['database'] . "\n";
foreach ($r['checks'] as $c) { printf("%-5s %-34s %s\n", strtoupper($c['level']), $c['name'], $c['note']); }
echo 'Verdict: ' . $r['verdict'] . "\n";
exit($r['verdict'] === 'FAIL' ? 1 : 0);
