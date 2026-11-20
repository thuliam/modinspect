<?php
declare(strict_types=1);

/**
 * LS-23 E3: READ-ONLY catalog coverage per category: models with a used price, with two approved sources, with every required manufacturer spec (the contract's
 * list), and with a new-price reference. Run before and after an import to see what changed. Writes nothing.
 *
 *   MODINSPECT_ENV_FILE=.env.testing php cli/coverage_report.php [--json] [--missing]
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Admin\CoverageReport;

global $config;
$rows = CoverageReport::build(Database::connection($config['db']));
if (in_array('--json', $argv, true)) { echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n"; exit(0); }
echo sprintf("%-12s %6s %8s %12s %14s %10s\n", 'category', 'models', 'priced', '2 sources', 'specs complete', 'new price');
$tot = ['models' => 0, 'with_price' => 0, 'with_two_sources' => 0, 'specs_complete' => 0, 'with_new_price' => 0];
foreach ($rows as $r) {
    echo sprintf("%-12s %6d %8d %12d %14d %10d\n", $r['category'], $r['models'], $r['with_price'], $r['with_two_sources'], $r['specs_complete'], $r['with_new_price']);
    foreach ($tot as $k => $_) { $tot[$k] += $r[$k]; }
    if (in_array('--missing', $argv, true)) { foreach ($r['missing_specs'] as $name => $keys) { echo "      $name lacks: " . implode(', ', $keys) . "\n"; } }
}
echo sprintf("%-12s %6d %8d %12d %14d %10d\n", 'TOTAL', $tot['models'], $tot['with_price'], $tot['with_two_sources'], $tot['specs_complete'], $tot['with_new_price']);
exit(0);
