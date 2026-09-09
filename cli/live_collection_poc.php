<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Collection\LiveCollectionPocService;

$args = $argv ?? [];
$provider = 'gemini';
$productIds = [];
$maxRequests = 1;
$dryRun = in_array('--dry-run', $args, true);
$preflightOnly = in_array('--preflight', $args, true);

foreach ($args as $arg) {
    if (str_starts_with($arg, '--provider=')) {
        $provider = substr($arg, 11);
    } elseif (str_starts_with($arg, '--product-id=')) {
        $productIds[] = max(1, (int)substr($arg, 13));
    } elseif (str_starts_with($arg, '--max-requests=')) {
        $maxRequests = max(1, (int)substr($arg, 15));
    }
}

if ($productIds === []) {
    fwrite(STDERR, "Usage: php cli/live_collection_poc.php --provider=gemini --product-id=1 [--product-id=2] --max-requests=1 --dry-run|--preflight\n");
    exit(2);
}

global $config;
$service = new LiveCollectionPocService($config['collection']);
$output = ($dryRun || $preflightOnly)
    ? ($dryRun ? $service->dryRun($provider, $productIds, $maxRequests) : $service->preflight($provider, $productIds, $maxRequests))
    : $service->execute($provider, $productIds, $maxRequests);

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit(($output['mode'] ?? '') === 'refused' ? 1 : 0);
