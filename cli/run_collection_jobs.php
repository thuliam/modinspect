<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Models\CollectionPipeline;
use App\Services\Collection\ScheduledCollectionRunner;
use App\Services\Providers\MockSearchProvider;

$args = $argv ?? [];
$dryRun = in_array('--dry-run', $args, true);
$limit = 5;
$jobId = null;

foreach ($args as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int)substr($arg, 8));
    } elseif (str_starts_with($arg, '--job-id=')) {
        $jobId = max(1, (int)substr($arg, 9));
    }
}

$sourceId = (new CollectionPipeline())->ensureMockSource();
$runner = new ScheduledCollectionRunner(
    new MockSearchProvider(ROOT_PATH . '/tests/fixtures/market_listings.json'),
    $sourceId
);

echo json_encode($runner->run($limit, $jobId, $dryRun), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
