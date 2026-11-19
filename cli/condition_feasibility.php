<?php
declare(strict_types=1);

/**
 * LS-23 C3: READ-ONLY. Can the used price be split by warranty / box / receipt? For each fact: how many products have enough approved rows on both sides (at least 3
 * with and 3 without) for a median gap to mean anything. Changes no price or formula; for the owner's decision (docs/LS23_LONG_SPRINT_PLAN.md, decision 5).
 *
 *   MODINSPECT_ENV_FILE=.env.testing php cli/condition_feasibility.php [--json]
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Admin\ConditionFeasibility;

global $config;
$db = Database::connection($config['db']);
$rows = ConditionFeasibility::rows($db);
$s = ConditionFeasibility::summarize($rows);
if (in_array('--json', $argv, true)) { echo json_encode(['observations' => count($rows), 'facts' => $s], JSON_PRETTY_PRINT), "\n"; exit(0); }
echo 'Approved asking observations analysed: ' . count($rows) . "\n";
foreach ($s as $fact => $r) {
    echo sprintf("%-10s products with this fact recorded: %3d | with at least %d rows on both sides: %3d\n", ConditionFeasibility::FACTS[$fact], $r['products_with_data'], ConditionFeasibility::MIN_GROUP, $r['products_splittable']);
    foreach ($r['examples'] as $e) { echo sprintf("     product #%d: %d with / %d without, median %s vs %s (%+.1f%%)\n", $e['product_id'], $e['with'], $e['without'], number_format($e['median_with']), number_format($e['median_without']), $e['gap_percent']); }
}
echo "A gap between two medians describes the sample. It is not a rule and nothing here changes a price.\n";
exit(0);
