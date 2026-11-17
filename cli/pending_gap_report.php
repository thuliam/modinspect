<?php
declare(strict_types=1);

// LS-4 K-2: read-only. For every product that stays pending, how many more usable asking rows it needs to reach the snapshot minimum.
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Research\OwnerApprovalPlan;
use App\Services\Research\OwnerIntakePlanner;

global $config;
$db = Database::connection($config['db']);
$plan = OwnerApprovalPlan::build($db);
$approved = [];
foreach ($db->query("SELECT p.full_name n, COUNT(*) c FROM price_observations o JOIN products p ON p.id=o.product_id WHERE o.verified_status='approved' AND o.price_type='asking' AND o.is_duplicate=0 AND o.is_defective=0 AND o.is_deposit=0 AND COALESCE(o.price_value,o.asking_price)>0 GROUP BY p.id") as $r) { $approved[$r['n']] = (int)$r['c']; }
$rows = [];
foreach ($plan['left_pending'] as [$name, $usable, $status]) {
    $have = $usable + ($approved[$name] ?? 0);
    $rows[] = [$status === 'bimodal' ? 'bimodal' : 'too_few', max(0, OwnerIntakePlanner::MIN_ROWS - $have), $have, $name];
}
usort($rows, static fn($a, $b) => [$a[0] === 'bimodal', $a[1]] <=> [$b[0] === 'bimodal', $b[1]]);
printf("pending products: %d (approvable now: %d)\n%-8s %-6s %-5s %s\n", count($rows), count($plan['plan']), 'status', 'need', 'have', 'product');
foreach ($rows as $r) { printf("%-8s %-6d %-5d %s\n", ...$r); }
printf("total extra rows needed to reach %d each: %d\n", OwnerIntakePlanner::MIN_ROWS, array_sum(array_column($rows, 1)));
