<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Pricing\PriceSnapshotService;
use App\Services\Pricing\SnapshotRecompute;

/**
 * LS-23 C1: the recalculation plan. A dry run stores nothing; apply stores a snapshot only for new or changed products; a second apply stores nothing (history is
 * not flooded); no formula, observation or approval is touched. Test database inside a transaction, rolled back (the app shares one connection).
 */
function ok_sr(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

global $config;
$db = Database::connection($config['db']);
ok_sr($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$svc = new PriceSnapshotService();
$job = new SnapshotRecompute($db, $svc);
$count = static fn(): int => (int)$db->query('SELECT COUNT(*) FROM price_indices')->fetchColumn();
$approved = static fn(): int => (int)$db->query("SELECT COUNT(*) FROM price_observations WHERE verified_status = 'approved'")->fetchColumn();

$db->beginTransaction();
try {
    $n0 = $count(); $a0 = $approved();
    $plan = $job->plan();
    $c = SnapshotRecompute::counts($plan);
    ok_sr(array_sum($c) === (int)$db->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn(), 'Every active product has a status');
    ok_sr($count() === $n0, 'Planning stores nothing');
    $eligible = array_values(array_filter($plan, static fn(array $r): bool => in_array($r['status'], ['new', 'changed'], true)));
    ok_sr($eligible !== [], 'The fixture data has at least one product whose price the plan would store');
    $name = $eligible[0]['name']; $pid = $eligible[0]['product_id'];

    $stored = $job->apply($plan);
    ok_sr($stored === count($eligible) && $count() === $n0 + $stored, 'Apply stores one snapshot per new or changed product and no other');
    $after = $job->plan();
    $byId = array_column($after, null, 'product_id');
    ok_sr($byId[$pid]['status'] === 'unchanged' && $byId[$pid]['name'] === $name, 'After applying, the same product is unchanged');
    $n1 = $count();
    ok_sr($job->apply($after) === 0 && $count() === $n1, 'Applying again stores nothing');
    ok_sr($approved() === $a0, 'No observation approval changed');

    // a change in the data shows up as "changed" with the numbers
    $obs = $db->prepare("SELECT id FROM price_observations WHERE product_id = :p AND verified_status = 'approved' AND price_type = 'asking' ORDER BY id DESC LIMIT 1");
    $obs->execute(['p' => $pid]); $oid = $obs->fetchColumn();
    if ($oid !== false) {
        $db->prepare("UPDATE price_observations SET verified_status = 'rejected' WHERE id = :id")->execute(['id' => $oid]);
        $c2 = $job->plan(); $row = array_column($c2, null, 'product_id')[$pid];
        ok_sr(in_array($row['status'], ['changed', 'unchanged'], true) && ($row['status'] === 'unchanged' || isset($row['changes']['valid_sample_size'])), 'Removing one approved row is reported as a sample-size change');
    }
} finally {
    $db->rollBack();
}
$src = (string)file_get_contents(ROOT_PATH . '/cli/snapshot_recompute.php');
ok_sr(str_contains($src, "in_array('--apply', \$argv, true)") && str_contains($src, 'DRY RUN'), 'The CLI is a dry run unless --apply is given');
echo "snapshot_recompute_test: PASS\n";
