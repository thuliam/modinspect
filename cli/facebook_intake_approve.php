<?php
declare(strict_types=1);

/**
 * LS-76: approve pending owner-transcribed Facebook observations (REAL dataset), then rebuild price snapshots. Same gates as cli/owner_intake_approve.php:
 * every row goes through Dashboard::decideObservation (same audit log and review-decision record, recorded under --actor).
 * Rows a reviewer would not trust (bundle, sold or reserved, new item, deposit, defective, duplicate) are never touched: they stay pending for a person.
 * Per product, a row below 60% or above 160% of the median (pending plus already approved) is EXCLUDED with a note; a product with fewer than 3 usable rows
 * or a bimodal sample stays pending.
 *
 * Usage: php cli/facebook_intake_approve.php --actor=<admin user id> [--apply --expect=<rows to approve>]
 *        without --apply it only prints the plan and writes nothing; --apply refuses unless --expect equals the number of rows the plan approves.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\Dashboard;
use App\Services\Pricing\PriceSnapshotService;
use App\Services\Research\OwnerApprovalPlan;

$o = getopt('', ['actor:', 'apply', 'expect::']);
$actor = (int)($o['actor'] ?? 0);
if ($actor <= 0) { fwrite(STDERR, "--actor=<admin user id> is required (the approval is recorded under that person)\n"); exit(2); }

global $config;
$db = Database::connection($config['db']);
$exists = $db->prepare('SELECT role FROM users WHERE id=:id AND is_active=1');
$exists->execute(['id' => $actor]);
if (!in_array((string)$exists->fetchColumn(), ['admin', 'reviewer'], true)) { fwrite(STDERR, "Actor must be an active admin or reviewer\n"); exit(2); }

$built = OwnerApprovalPlan::build($db, 'offline_real_%facebook%', true);
$plan = $built['plan'];
$toApprove = array_sum(array_map(static fn($p) => count($p['keep']), $plan));
$toExclude = array_sum(array_map(static fn($p) => count($p['drop']), $plan));
printf("%-40s %7s %6s %8s %8s\n", 'product', 'approve', 'excl.', 'median', 'already');
foreach ($plan as $p) { printf("%-40s %7d %6d %8.0f %8d\n", $p['name'], count($p['keep']), count($p['drop']), $p['median'], $p['already']); }
echo "\nproducts approved: " . count($plan) . ", rows to approve: $toApprove, rows to exclude: $toExclude, products left pending: " . count($built['left_pending']) . "\n";
if (!isset($o['apply'])) { echo "Dry run: nothing written. Add --apply --expect=$toApprove to record the decisions.\n"; exit(0); }
if (!isset($o['expect']) || (int)$o['expect'] !== $toApprove) { fwrite(STDERR, "Refused: --expect must equal the number of rows the plan approves ($toApprove).\n"); exit(2); }

$dashboard = new Dashboard();
$note = 'Approved at the owner\'s instruction (owner-transcribed Facebook listing, asking price, single used item); recorded by the agent under the owner\'s account.';
$excludeNote = 'Excluded by the batch outlier rule (below 60% or above 160% of the product median in this batch); not a quality verdict on the seller.';
$ok = 0; $ex = 0; $failed = 0;
foreach ($plan as $p) {
    foreach ($p['keep'] as $r) { $dashboard->decideObservation((int)$r['id'], 'approved', $note, $actor) ? $ok++ : $failed++; }
    foreach ($p['drop'] as $r) { $dashboard->decideObservation((int)$r['id'], 'excluded', $excludeNote, $actor) ? $ex++ : $failed++; }
}
echo "\napproved=$ok excluded=$ex failed=$failed\n";
$snapshots = new PriceSnapshotService();
$made = 0;
foreach (array_keys($plan) as $pid) {
    $snap = $snapshots->recalculateProduct($pid, 'asking');
    if ($snap) { $made++; }
    printf("%-40s %s\n", $plan[$pid]['name'], $snap ? 'snapshot #' . $snap['snapshot_id'] : 'no snapshot (not enough approved rows)');
}
echo "snapshots created: $made\n";
