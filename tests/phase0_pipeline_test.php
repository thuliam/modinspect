<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Data\SearchRequest;
use App\Services\Extraction\RuleBasedListingExtractor;
use App\Services\ProductResolver\ProductResolver;
use App\Services\Providers\MockSearchProvider;
use App\Services\Validation\ObservationValidator;

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$catalog = json_decode((string)file_get_contents(ROOT_PATH . '/tests/fixtures/product_catalog.json'), true, 512, JSON_THROW_ON_ERROR);
$resolver = new ProductResolver($catalog);
$resolverCases = json_decode((string)file_get_contents(ROOT_PATH . '/tests/fixtures/resolver_titles.json'), true, 512, JSON_THROW_ON_ERROR);
$correct = 0;

foreach ($resolverCases as $case) {
    $resolution = $resolver->resolve((string)$case['title']);
    if ($resolution->productId === (int)$case['expected_product_id']) {
        $correct++;
    }
}

$accuracy = $correct / count($resolverCases);
assert_true($accuracy >= 0.95, 'Resolver fixture accuracy below 95%');

$provider = new MockSearchProvider(ROOT_PATH . '/tests/fixtures/market_listings.json');
$extractor = new RuleBasedListingExtractor();
$validator = new ObservationValidator(false);
$resultSet = $provider->search(new SearchRequest('5700x3d', null, 20));

assert_true(count($resultSet->candidates) >= 3, 'Mock provider did not return expected candidates');

$lanes = ['green' => 0, 'amber' => 0, 'red' => 0];
foreach ($resultSet->candidates as $candidate) {
    $extraction = $extractor->extract($candidate);
    $resolution = $resolver->resolve($candidate->title);
    $validation = $validator->validate($extraction, $resolution);
    $lanes[$validation->lane]++;

    if ($validation->lane === 'green') {
        assert_true($validation->reviewState === 'review_required', 'Green lane must remain review-gated during calibration');
    }
}

assert_true($lanes['green'] >= 1, 'Expected at least one green fixture');
assert_true($lanes['amber'] >= 1, 'Expected at least one amber fixture');
assert_true($lanes['red'] >= 1, 'Expected at least one red fixture');

echo "Phase 0 pipeline fixture test passed\n";
echo "Resolver accuracy: " . number_format($accuracy * 100, 2) . "%\n";
echo "Lanes: " . json_encode($lanes) . "\n";
