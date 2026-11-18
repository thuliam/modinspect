<?php
declare(strict_types=1);

/**
 * F-2: generates database/migrations/021_cpu_gpu_reference_specs.sql from database/reference/official_cpu_specs.csv and official_gpu_specs.csv.
 * Reads the CSVs and writes the SQL file only: it never touches the database. Apply the SQL with: php cli/migrate.php database/migrations/021_cpu_gpu_reference_specs.sql
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Catalog\CpuGpuSpecMigration;
use App\Services\Collection\OwnerRowPreparer;

$root = dirname(__DIR__);
$sql = CpuGpuSpecMigration::generate(
    OwnerRowPreparer::readCsv($root . '/database/reference/official_cpu_specs.csv'),
    OwnerRowPreparer::readCsv($root . '/database/reference/official_gpu_specs.csv')
);
$file = $root . '/database/migrations/021_cpu_gpu_reference_specs.sql';
file_put_contents($file, $sql);
echo "wrote $file (" . strlen($sql) . " bytes, " . substr_count($sql, 'INSERT IGNORE INTO product_reference_specs') . " reference specs)\n";
