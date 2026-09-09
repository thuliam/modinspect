<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Review\ReviewCalibrationService;

$filters = [];
foreach (array_slice($argv, 1) as $arg) {
    foreach (['category', 'source-id', 'provider', 'from', 'to', 'lane', 'outcome'] as $name) {
        if (str_starts_with($arg, '--' . $name . '=')) {
            $key = str_replace('-', '_', $name);
            $filters[$key] = substr($arg, strlen($name) + 3);
        }
    }
}

$report = (new ReviewCalibrationService())->report($filters);
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
