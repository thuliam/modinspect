<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Data\SearchRequest;
use App\Models\CollectionPipeline;
use App\Services\Extraction\RuleBasedListingExtractor;
use App\Services\ProductResolver\ProductResolver;
use App\Services\Providers\MockSearchProvider;
use App\Services\Validation\ObservationValidator;

$args = $argv ?? [];
$dryRun = in_array('--dry-run', $args, true);
$mock = in_array('--mock', $args, true);
$query = '5700x3d';

foreach ($args as $arg) {
    if (str_starts_with($arg, '--query=')) {
        $query = substr($arg, 8);
    }
}

if (!$mock) {
    fwrite(STDERR, "Only --mock mode is available. Live providers are disabled.\n");
    exit(2);
}

$pipeline = null;
$jobId = null;
$sourceId = null;
$provider = new MockSearchProvider(ROOT_PATH . '/tests/fixtures/market_listings.json');
$extractor = new RuleBasedListingExtractor();

if ($dryRun) {
    $catalog = json_decode((string)file_get_contents(ROOT_PATH . '/tests/fixtures/product_catalog.json'), true, 512, JSON_THROW_ON_ERROR);
} else {
    $pipeline = new CollectionPipeline();
    $sourceId = $pipeline->ensureMockSource();
    $jobId = $pipeline->startJob($sourceId, $query);
    $catalog = $pipeline->catalog();
}

$resolver = new ProductResolver($catalog);
$validator = new ObservationValidator(false);

$resultSet = $provider->search(new SearchRequest($query, null, 20));
$summary = ['provider' => $resultSet->provider, 'dry_run' => $dryRun, 'query' => $query, 'green' => 0, 'amber' => 0, 'red' => 0, 'records' => []];

foreach ($resultSet->candidates as $candidate) {
    $extraction = $extractor->extract($candidate);
    $resolution = $resolver->resolve($candidate->title);
    $validation = $validator->validate($extraction, $resolution);
    $summary[$validation->lane]++;
    if (!$dryRun && $pipeline !== null && $sourceId !== null && $jobId !== null) {
        $pipeline->persistCandidate($sourceId, $jobId, $candidate, $extraction, $resolution, $validation);
    }
    $summary['records'][] = [
        'title' => $candidate->title,
        'price' => $extraction->askingPrice,
        'product_id' => $resolution->productId,
        'product' => $resolution->matchedName,
        'lane' => $validation->lane,
        'review_state' => $validation->reviewState,
        'reasons' => $validation->reasonCodes,
    ];
}

if (!$dryRun && $pipeline !== null && $jobId !== null) {
    $pipeline->finishJob($jobId, count($resultSet->candidates), $summary['green'] + $summary['amber']);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
