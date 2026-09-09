<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Data\SearchCandidate;
use App\Services\Extraction\RuleBasedListingExtractor;
use App\Services\ProductResolver\ProductResolver;
use App\Services\Validation\ModelMarker;
use App\Services\Validation\ObservationValidator;

global $config;
$db = Database::connection($config['db']);

$catalogRows = $db->query(
    "SELECT p.id,p.full_name,GROUP_CONCAT(pa.alias_text SEPARATOR '||') aliases
     FROM products p
     JOIN product_categories c ON c.id=p.category_id
     LEFT JOIN product_aliases pa ON pa.product_id=p.id
     WHERE p.is_active=1 AND c.slug IN ('cpu','gpu')
     GROUP BY p.id
     ORDER BY p.id"
)->fetchAll();

$catalog = [];
foreach ($catalogRows as $row) {
    $catalog[] = [
        'id' => (int)$row['id'],
        'full_name' => (string)$row['full_name'],
        'aliases' => $row['aliases'] ? explode('||', (string)$row['aliases']) : [],
    ];
}

$productsById = [];
foreach ($catalog as $product) {
    $productsById[(int)$product['id']] = (string)$product['full_name'];
}

$fixtures = require dirname(__DIR__) . '/tests/fixtures/validation_golden_dataset.php';
$resolver = new ProductResolver($catalog);
$extractor = new RuleBasedListingExtractor();
$validator = new ObservationValidator(false);

$stats = [
    'fixture_count' => count($fixtures),
    'by_category' => [
        'cpu' => ['count' => 0, 'resolution_checked' => 0, 'resolution_correct' => 0],
        'gpu' => ['count' => 0, 'resolution_checked' => 0, 'resolution_correct' => 0],
    ],
    'classification_correct' => 0,
    'green_count' => 0,
    'green_valid_count' => 0,
    'critical_invalid_count' => 0,
    'critical_invalid_false_green' => 0,
    'wrong_nearby_false_match' => 0,
    'rule_triggers' => [],
    'failures' => [],
];

foreach ($fixtures as $fixture) {
    $category = (string)$fixture['category'];
    $stats['by_category'][$category]['count']++;

    $candidate = new SearchCandidate(
        'validation-golden-dataset',
        (string)$fixture['title'],
        (string)$fixture['price_text'],
        'https://example.test/golden/' . $fixture['id'],
        '2026-09-09 12:00:00',
        'C'
    );
    $extraction = $extractor->extract($candidate);
    $resolution = $resolver->resolve($candidate->title);
    $validation = $validator->validate($extraction, $resolution);

    $matchedMarker = $resolution->matchedName === null ? null : ModelMarker::primary($resolution->matchedName);
    $expectedMarker = $fixture['expected_product_marker'];
    $flags = array_values(array_filter($validation->reasonCodes, static fn(string $reason): bool => strtoupper($reason) === $reason && $reason !== 'PASSES_RULES'));
    $skipResolutionMetric = $fixture['critical_invalid'] && in_array(true, [
        in_array('WHOLE_PC', (array)$fixture['expected_flags'], true),
        in_array('MOBILE_GPU', (array)$fixture['expected_flags'], true),
        in_array('WANTED', (array)$fixture['expected_flags'], true),
        in_array('DEPOSIT', (array)$fixture['expected_flags'], true),
        in_array('DEFECTIVE', (array)$fixture['expected_flags'], true),
    ], true);
    $resolutionCorrect = $expectedMarker === null
        ? ($resolution->productId === null || in_array('WRONG_PRODUCT', $flags, true) || in_array('UNRESOLVED_PRODUCT', $flags, true))
        : $matchedMarker === $expectedMarker;
    if (!$skipResolutionMetric) {
        $stats['by_category'][$category]['resolution_checked']++;
        if ($resolutionCorrect) {
            $stats['by_category'][$category]['resolution_correct']++;
        } elseif ($expectedMarker === null && $resolution->productId !== null && !in_array('WRONG_PRODUCT', $flags ?? [], true)) {
            $stats['wrong_nearby_false_match']++;
        }
    }

    foreach ($flags as $flag) {
        $stats['rule_triggers'][$flag] = ($stats['rule_triggers'][$flag] ?? 0) + 1;
    }

    $expectedFlags = $fixture['expected_flags'];
    $flagsPresent = count(array_diff($expectedFlags, $flags)) === 0;
    $classificationCorrect = $validation->lane === $fixture['expected_lane']
        && $extraction->listingType === $fixture['expected_listing_type']
        && $flagsPresent;
    if ($classificationCorrect) {
        $stats['classification_correct']++;
    }
    if ($validation->lane === 'green') {
        $stats['green_count']++;
        if (!$fixture['critical_invalid']) {
            $stats['green_valid_count']++;
        }
    }
    if ($fixture['critical_invalid']) {
        $stats['critical_invalid_count']++;
        if ($validation->lane === 'green') {
            $stats['critical_invalid_false_green']++;
        }
    }

    if ((!$resolutionCorrect && !$skipResolutionMetric) || !$classificationCorrect) {
        $stats['failures'][] = [
            'id' => $fixture['id'],
            'title' => $fixture['title'],
            'expected_marker' => $expectedMarker,
            'matched_marker' => $matchedMarker,
            'expected_lane' => $fixture['expected_lane'],
            'lane' => $validation->lane,
            'expected_listing_type' => $fixture['expected_listing_type'],
            'listing_type' => $extraction->listingType,
            'expected_flags' => $expectedFlags,
            'flags' => $flags,
            'resolution_method' => $resolution->method,
        ];
    }
}

ksort($stats['rule_triggers']);
$cpu = $stats['by_category']['cpu'];
$gpu = $stats['by_category']['gpu'];
$summary = [
    'fixture_count' => $stats['fixture_count'],
    'cpu' => [
        'fixture_count' => $cpu['count'],
        'product_resolution_accuracy' => percentage($cpu['resolution_correct'], $cpu['resolution_checked']),
    ],
    'gpu' => [
        'fixture_count' => $gpu['count'],
        'product_resolution_accuracy' => percentage($gpu['resolution_correct'], $gpu['resolution_checked']),
    ],
    'overall_classification_accuracy' => percentage($stats['classification_correct'], $stats['fixture_count']),
    'green_coverage' => percentage($stats['green_count'], $stats['fixture_count']),
    'green_precision' => percentage($stats['green_valid_count'], max($stats['green_count'], 1)),
    'critical_invalid_false_green_count' => $stats['critical_invalid_false_green'],
    'critical_invalid_false_green_rate' => percentage($stats['critical_invalid_false_green'], max($stats['critical_invalid_count'], 1)),
    'wrong_nearby_false_match_count' => $stats['wrong_nearby_false_match'],
    'wrong_nearby_false_match_rate' => percentage($stats['wrong_nearby_false_match'], $stats['fixture_count']),
    'rule_triggers' => $stats['rule_triggers'],
    'passes_targets' => (
        percentage($cpu['resolution_correct'], $cpu['resolution_checked']) >= 95.0
        && percentage($gpu['resolution_correct'], $gpu['resolution_checked']) >= 95.0
        && percentage($stats['critical_invalid_false_green'], max($stats['critical_invalid_count'], 1)) <= 2.0
        && percentage($stats['wrong_nearby_false_match'], $stats['fixture_count']) <= 2.0
        && percentage($stats['green_count'], $stats['fixture_count']) >= 25.0
        && percentage($stats['green_valid_count'], max($stats['green_count'], 1)) >= 95.0
    ),
    'failure_count' => count($stats['failures']),
    'failures' => array_slice($stats['failures'], 0, 20),
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($summary['passes_targets'] ? 0 : 1);

function percentage(int $numerator, int $denominator): float
{
    if ($denominator <= 0) {
        return 0.0;
    }
    return round(($numerator / $denominator) * 100, 2);
}
