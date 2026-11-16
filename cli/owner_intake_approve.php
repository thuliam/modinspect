<?php
declare(strict_types=1);

/**
 * Approve pending owner-collected observations (grade OWNER_TEXT_NO_URL) on the owner's instruction, then rebuild price snapshots.
 *
 * It does NOT decide quality by itself: every row still goes through Dashboard::decideObservation (same gates as the Admin button,
 * same audit log, same review-decision record). The only judgement it applies is a transparent outlier rule per product:
 * a row priced below 60% or above 160% of that product's median (pending rows plus rows already approved) is EXCLUDED (not approved) with a note, because one
 * bundle or new-in-box post can drag a small sample. Products with fewer than 3 usable rows are left pending (the snapshot needs 3).
 *
 * Usage: php cli/owner_intake_approve.php --actor=<admin user id> [--apply]
 *        without --apply it only prints the plan and writes nothing.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\Dashboard;
use App\Services\Research\OwnerApprovalPlan;
use App\Services\Pricing\PriceSnapshotService;

$actor = null; $apply = false; $low = 0.6; $high = 1.6;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--actor=')) { $actor = (int)substr($arg, 8); }
    elseif ($arg === '--apply') { $apply = true; }
}
if (!$actor) { fwrite(STDERR, "--actor=<admin user id> is required (the approval is recorded under that person)\n"); exit(2); }

global $config;
$db = Database::connection($config['db']);
$exists = $db->prepare("SELECT role FROM users WHERE id=:id AND is_active=1");
$exists->execute(['id' => $actor]);
if (!in_array((string)$exists->fetchColumn(), ['admin', 'reviewer'], true)) { fwrite(STDERR, "Actor must be an active admin or reviewer\n"); exit(2); }

$built = OwnerApprovalPlan::build($db);   // shared with the admin intake screen
$plan = $built['plan']; $leftPending = $built['left_pending'];
printf("%-34s %6s %6s %8s %8s\n", 'product', 'approve', 'excl.', 'median', 'already');
foreach ($plan as $p) { printf("%-34s %6d %6d %8.0f %8d\n", $p['name'], count($p['keep']), count($p['drop']), $p['median'], $p['already']); }
echo "left pending (fewer than 3 usable rows, or bimodal): " . count($leftPending) . "\n";
foreach ($leftPending as [$name, $k, $why]) { echo "  - $name ($k usable" . ($why === 'bimodal' ? ', BIMODAL: needs a human' : '') . ")\n"; }
if (!$apply) { echo "\nDry run: nothing written. Add --apply to record the decisions.\n"; exit(0); }

$dashboard = new Dashboard();
$note = 'Approved at provenance grade OWNER_TEXT_NO_URL on the owner\'s instruction (2026-11-15); recorded by the agent under the owner\'s account.';
$excludeNote = 'Excluded by the batch outlier rule (below 60% or above 160% of the product median in this batch); not a quality verdict on the seller.';
$ok = 0; $ex = 0; $failed = 0;
foreach ($plan as $p) {
    foreach ($p['keep'] as $r) { $dashboard->decideObservation((int)$r['id'], 'approved', $note, $actor) ? $ok++ : $failed++; }
    foreach ($p['drop'] as $r) { $dashboard->decideObservation((int)$r['id'], 'excluded', $excludeNote, $actor) ? $ex++ : $failed++; }
}
echo "\napproved=$ok excluded=$ex failed=$failed\n";

$snapshots = new PriceSnapshotService();
foreach (array_keys($plan) as $pid) {
    $snap = $snapshots->recalculateProduct($pid, 'asking');
    printf("%-34s %s\n", $plan[$pid]['name'], $snap ? 'snapshot #' . $snap['snapshot_id'] : 'no snapshot (not enough approved rows)');
}
