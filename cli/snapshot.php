<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Pricing\PriceSnapshotService;

$results = (new PriceSnapshotService())->recalculateAll('asking');

echo json_encode([
    'status' => 'ok',
    'snapshots_created' => count($results),
    'snapshots' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
