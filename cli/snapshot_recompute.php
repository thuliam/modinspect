<?php
declare(strict_types=1);

/**
 * LS-23 C1: shows what a recalculation of the used-price snapshots would change, then (only with --apply) stores a snapshot for the products that are new or
 * changed. DRY RUN by default. It uses the existing engine unchanged (no formula edit) and approves nothing. Shared DEV database only on the owner's instruction.
 *
 *   MODINSPECT_ENV_FILE=.env.testing php cli/snapshot_recompute.php [--apply] [--json]
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Pricing\PriceSnapshotService;
use App\Services\Pricing\SnapshotRecompute;

$apply = in_array('--apply', $argv, true); $json = in_array('--json', $argv, true);
global $config;
$db = Database::connection($config['db']);
$job = new SnapshotRecompute($db, new PriceSnapshotService());
$plan = $job->plan('asking');
$counts = SnapshotRecompute::counts($plan);
$stored = $apply ? $job->apply($plan, 'asking') : 0;
$changes = array_values(array_filter($plan, static fn(array $r): bool => in_array($r['status'], ['new', 'changed'], true)));
if ($json) { echo json_encode(['mode' => $apply ? 'applied' : 'dry run', 'counts' => $counts, 'stored' => $stored, 'changes' => $changes], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n"; exit(0); }
echo ($apply ? "APPLIED" : "DRY RUN (nothing stored; add --apply)") . ': ' . json_encode($counts) . ($apply ? " stored=$stored" : '') . "\n";
foreach ($changes as $r) {
    echo sprintf("  %-8s %s\n", $r['status'], $r['name']);
    foreach ($r['changes'] as $k => $c) { echo sprintf("      %s: %s -> %s\n", $k, $c['from'] ?? '-', $c['to']); }
}
exit(0);
