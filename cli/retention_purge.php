<?php
declare(strict_types=1);

/**
 * LS-8 O-2: retention for the public tools' data. NO default period: nothing is touched unless you give a period. Dry run unless --apply.
 * Usage: php cli/retention_purge.php [--deal-checks-days=N] [--builds-days=N] [--community-days=N] [--ratelimit-hours=N] [--apply]
 * --community-days (LS-21, R-11): deletes REJECTED and SPAM community reports decided more than N days ago (ADR-006 proposes 90); new and accepted ones are never touched.
 * Floors: 7 days for a database table, 1 hour for the cache. Prints counts only. Run a dry run first and keep a backup (php cli/db_backup.php).
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Ops\RetentionPurge;

$opts = [];
$apply = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') { $apply = true; }
    elseif (preg_match('/^--(deal-checks-days|builds-days|community-days|ratelimit-hours)=(\d+)$/', $arg, $m)) { $opts[str_replace('-', '_', $m[1])] = (int)$m[2]; }
    else { fwrite(STDERR, "Unknown or malformed argument: " . preg_replace('/=.*/', '=...', $arg) . "\n"); exit(2); }
}
global $config;
try {
    $r = RetentionPurge::run(Database::connection($config['db']), $opts, ROOT_PATH . '/storage/cache/ratelimit', $apply);
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(2);
}
echo json_encode($r + ['note' => $apply ? 'applied' : 'dry run: nothing was deleted; add --apply to delete'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
