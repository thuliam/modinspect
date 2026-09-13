<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Collection\PricezaProbeCollector;

$limit = 40;
$import = false;

foreach (array_slice($argv ?? [], 1) as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $value = filter_var(substr($arg, 8), FILTER_VALIDATE_INT);
        if ($value !== false) {
            $limit = (int)$value;
        }
    } elseif ($arg === '--import') {
        $import = true;
    }
}

if (PHP_SAPI !== 'cli') {
    $queryLimit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT);
    if ($queryLimit !== false && $queryLimit !== null) {
        $limit = (int)$queryLimit;
    }
    $import = (string)($_GET['import'] ?? '') === '1';
}

echo json_encode((new PricezaProbeCollector())->collectAuthoritativeBatch($limit, $import), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
