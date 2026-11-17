<?php
declare(strict_types=1);

/**
 * LS-12: generates database/migrations/020_hardware_reference_specs.sql from database/reference/official_hardware_sources.csv.
 * Reads the CSV and writes the SQL file only: it never touches the database. Apply the SQL with: php cli/migrate.php database/migrations/020_hardware_reference_specs.sql
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Catalog\HardwareReferenceMigration;
use App\Services\Collection\OwnerRowPreparer;

$rows = OwnerRowPreparer::readCsv(dirname(__DIR__) . '/database/reference/official_hardware_sources.csv');
$sql = HardwareReferenceMigration::generate($rows);
$file = dirname(__DIR__) . '/database/migrations/020_hardware_reference_specs.sql';
file_put_contents($file, $sql);
echo "wrote $file (" . strlen($sql) . " bytes, " . substr_count($sql, 'INSERT INTO products') . " products, " . substr_count($sql, 'INSERT IGNORE INTO product_reference_specs') . " reference specs)\n";
