<?php
declare(strict_types=1);

/**
 * LS-23 A3: writes the three target files for the data-collection master prompt (docs/DATA_CONTRACT.md). Reads the database (SELECTs only) and
 * writes CSV files into a folder (default storage/import/, git-ignored). Prints counts only.
 *
 *   MODINSPECT_ENV_FILE=.env.testing php cli/export_collection_targets.php [--out-dir=storage/import]
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Admin\IntakeTargets;

$dir = ROOT_PATH . '/storage/import';
foreach (array_slice($argv, 1) as $arg) { if (str_starts_with($arg, '--out-dir=')) { $dir = rtrim(substr($arg, 10), '/\\'); } }
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) { fwrite(STDERR, "Cannot create $dir\n"); exit(2); }
global $config;
$db = Database::connection($config['db']);

$files = [
    'B_targets.csv' => [['category', 'brand', 'product_name'], IntakeTargets::newPriceTargets($db)],
    'A_targets.csv' => [['status', 'need_more_rows', 'have_rows', 'product_name'], IntakeTargets::usedListingTargets($db)],
    'D_existing_names.csv' => [['category', 'brand', 'product_name', 'aliases'], IntakeTargets::existingNames($db)],
];
$counts = [];
foreach ($files as $name => [$header, $rows]) {
    file_put_contents($dir . '/' . $name, IntakeTargets::csv($header, $rows));
    $counts[$name] = count($rows);
}
echo json_encode(['out_dir' => $dir, 'rows' => $counts], JSON_PRETTY_PRINT), "\n";
exit(0);
