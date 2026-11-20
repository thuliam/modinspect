<?php
declare(strict_types=1);

/**
 * LS-24 M4: READ-ONLY. Pending asking observations grouped by model, so a reviewer starts where a little review makes a price appear: pending rows per lane,
 * the plain spread of the pending prices, approved rows already held, and whether the green rows alone would reach the three samples a snapshot needs.
 * Approves nothing and recommends no decision. Counts and catalog names only.
 *
 *   php cli/review_batches.php [--json] [--limit=30]
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Review\ReviewBatchReport;

$limit = 30; foreach (array_slice($argv, 1) as $a) { if (str_starts_with($a, '--limit=')) { $limit = max(1, (int)substr($a, 8)); } }
global $config;
$rows = ReviewBatchReport::build(Database::connection($config['db']));
if (in_array('--json', $argv, true)) { echo json_encode(array_slice($rows, 0, $limit), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n"; exit(0); }
echo sprintf("%d models have pending rows (showing %d)\n%-34s %4s %7s %5s %5s %4s  %-25s %s\n", count($rows), min($limit, count($rows)), 'model', 'have', 'pending', 'green', 'amber', 'red', 'pending prices (min / mid / max)', 'green alone reaches 3?');
foreach (array_slice($rows, 0, $limit) as $r) {
    echo sprintf("%-34s %4d %7d %5d %5d %4d  %-25s %s\n", mb_substr($r['name'], 0, 34), $r['approved'], $r['pending'], $r['green'], $r['amber'], $r['red'], number_format($r['min']) . ' / ' . number_format($r['median']) . ' / ' . number_format($r['max']), $r['reaches_with_green'] ? 'yes' : ($r['approved'] >= 3 ? 'already priced' : 'no'));
}
exit(0);
