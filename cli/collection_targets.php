<?php
declare(strict_types=1);

/**
 * LS-8 O-1: Thai list of the products to collect listings for next, closest-to-a-price first. Read-only (SELECTs only).
 * Usage: php cli/collection_targets.php [--format=text|csv]
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Admin\CollectionTargets;

$format = 'text';
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--format=')) { $format = substr($arg, 9); }
}
global $config;
$b = CollectionTargets::build(CollectionTargets::gapRows(Database::connection($config['db'])));
echo $format === 'csv' ? CollectionTargets::renderCsv($b) : CollectionTargets::renderText($b);
