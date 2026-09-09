<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Collection\SourceHealthService;

$sourceId = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--source-id=')) {
        $sourceId = max(1, (int)substr($arg, 12));
    }
}

echo json_encode((new SourceHealthService())->report($sourceId), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
