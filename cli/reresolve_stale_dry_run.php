<?php
declare(strict_types=1);

// LS-4 R-1/R-2: READ-ONLY dry-run of the re-resolve design. Writes nothing. Counts and product names only, never a listing text.
//   php cli/reresolve_stale_dry_run.php
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\CollectionPipeline;
use App\Services\ProductResolver\ProductResolver;
use App\Services\Research\OwnerApprovalPlan;
use App\Services\Research\OwnerIntakePlanner;
use App\Services\Research\StaleReResolver;
use App\Services\Validation\ObservationValidator;

global $config;
$db = Database::connection($config['db']);
$rep = StaleReResolver::report($db, new ProductResolver((new CollectionPipeline())->catalog()), new ObservationValidator(false));
echo "undecided unresolved rows: {$rep['total']}\n";
foreach ($rep['outcomes'] as $k => $n) { printf("  %-28s %d\n", $k, $n); }
echo 'lanes of would_create: ' . json_encode($rep['lanes']) . "\n";

// effect on the pending products (same basis as cli/pending_gap_report.php)
$plan = OwnerApprovalPlan::build($db);
$approved = [];
foreach ($db->query("SELECT p.id pid, COUNT(*) c FROM price_observations o JOIN products p ON p.id=o.product_id WHERE o.verified_status='approved' AND o.price_type='asking' AND o.is_duplicate=0 AND o.is_defective=0 AND o.is_deposit=0 AND COALESCE(o.price_value,o.asking_price)>0 GROUP BY p.id") as $r) { $approved[(int)$r['pid']] = (int)$r['c']; }
$names = []; foreach ($db->query('SELECT id, full_name FROM products') as $r) { $names[(int)$r['id']] = (string)$r['full_name']; }
$byName = array_flip($names);
$reach = 0; $helps = 0; $lines = [];
foreach ($plan['left_pending'] as [$name, $usable, $status]) {
    $pid = $byName[$name] ?? 0;
    $have = $usable + ($approved[$pid] ?? 0);
    $need = max(0, OwnerIntakePlanner::MIN_ROWS - $have);
    $add = $rep['by_product'][$pid] ?? 0;
    if ($add > 0) { $helps++; }
    if ($status !== 'bimodal' && $add >= $need && $need > 0) { $reach++; }
    $lines[] = sprintf('  %-34s need %d, would add %d%s', $name, $need, $add, ($status !== 'bimodal' && $add >= $need) ? ' -> reaches the minimum' : '');
}
echo "pending products: " . count($plan['left_pending']) . ", helped: $helps, would reach the 3-row minimum: $reach\n" . implode("\n", $lines) . "\n";
echo "would_create rows on products that are not pending: " . (array_sum($rep['by_product']) - array_sum(array_map(static fn($n) => $rep['by_product'][$byName[$n[0]] ?? 0] ?? 0, $plan['left_pending']))) . "\n";
