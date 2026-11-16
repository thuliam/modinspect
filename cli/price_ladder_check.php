<?php
declare(strict_types=1);

/**
 * Read-only: GPU, CPU and RAM snapshots whose price breaks the tier order inside a family and generation (LS-2 H-4). Flags only; nothing is changed.
 *   php cli/price_ladder_check.php [--json]
 * Exit code is 0 either way: a flag asks for a human look, it is not a failure.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Admin\PriceLadderCheck;

$check = new PriceLadderCheck();
$v = $check->forPublicSnapshots();
if (in_array('--json', $argv, true)) { echo json_encode(['checked_snapshots' => $check->checked, 'violations' => $v], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL; exit(0); }
printf("snapshots checked (GPU, CPU, RAM): %d | tier-order flags: %d (tolerance: higher tier >= %d%% of the lower tier median)\n", $check->checked, count($v), (int)(PriceLadderCheck::TOLERANCE * 100));
foreach ($v as $x) { printf("  %-30s %7.0f  <  %-30s %7.0f  (%.0f%%)\n", $x['higher'], $x['higher_median'], $x['lower'], $x['lower_median'], $x['ratio'] * 100); }
