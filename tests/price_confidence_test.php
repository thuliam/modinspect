<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Pricing\PriceSnapshotService;
use App\Services\Pricing\SnapshotConfidenceService;

function assert_confidence(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function confidence_source(PDO $db, string $suffix, string $key, string $quality = 'high'): int
{
    $stmt = $db->prepare(
        "INSERT INTO data_sources (source_key,name,domain,source_type,access_method,allowed_collection_method,risk_level,reliability_score,evidence_quality,freshness_expectation_days,is_active,is_paused)
         VALUES (:source_key,:name,:domain,'research','manual','manual','low',90,:quality,14,1,0)"
    );
    $stmt->execute([
        'source_key' => 'confidence_' . $key . '_' . $suffix,
        'name' => 'Confidence ' . $key,
        'domain' => $key . '.confidence.test',
        'quality' => $quality,
    ]);
    return (int)$db->lastInsertId();
}

function confidence_product(PDO $db, string $suffix, string $name): int
{
    $categoryId = (int)$db->query("SELECT id FROM product_categories WHERE slug='cpu' LIMIT 1")->fetchColumn();
    $brandId = (int)$db->query("SELECT id FROM brands WHERE slug='amd' LIMIT 1")->fetchColumn();
    $stmt = $db->prepare(
        "INSERT INTO products (category_id,brand_id,model_name,slug,full_name,is_active)
         VALUES (:category_id,:brand_id,:model_name,:slug,:full_name,1)"
    );
    $stmt->execute([
        'category_id' => $categoryId,
        'brand_id' => $brandId,
        'model_name' => 'Confidence Fixture ' . $name,
        'slug' => 'confidence-fixture-' . $name . '-' . $suffix,
        'full_name' => 'AMD Confidence Fixture ' . $name,
    ]);
    return (int)$db->lastInsertId();
}

function confidence_raw(PDO $db, int $sourceId, string $seed, int $price): int
{
    $stmt = $db->prepare(
        "INSERT INTO raw_price_observations (source_id,external_reference_hash,raw_title,raw_price_text,captured_at,processing_status)
         VALUES (:source_id,:hash,:title,:price,NOW(),'processed')"
    );
    $stmt->execute([
        'source_id' => $sourceId,
        'hash' => hash('sha256', $seed),
        'title' => 'Confidence fixture ' . $seed,
        'price' => (string)$price,
    ]);
    return (int)$db->lastInsertId();
}

function confidence_observation(PDO $db, int $productId, int $sourceId, string $seed, int $price, array $overrides = []): int
{
    $data = array_merge([
        'price_type' => 'asking',
        'listing_type' => 'single_item',
        'verified_status' => 'approved',
        'observed_at' => date('Y-m-d 12:00:00'),
        'evidence_level' => 5,
        'quality_flags' => null,
    ], $overrides);
    $rawId = confidence_raw($db, $sourceId, $seed, $price);
    $stmt = $db->prepare(
        "INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,price_value,currency,condition_level,listing_type,evidence_level,classification_confidence,quality_flags,verified_status,observed_at)
         VALUES (:raw_id,:product_id,:price_type,:asking_price,:price_value,'THB','good',:listing_type,:evidence_level,99,:quality_flags,:verified_status,:observed_at)"
    );
    $stmt->execute([
        'raw_id' => $rawId,
        'product_id' => $productId,
        'price_type' => $data['price_type'],
        'asking_price' => $data['price_type'] === 'asking' ? $price : null,
        'price_value' => $price,
        'listing_type' => $data['listing_type'],
        'evidence_level' => $data['evidence_level'],
        'quality_flags' => $data['quality_flags'] === null ? null : json_encode($data['quality_flags']),
        'verified_status' => $data['verified_status'],
        'observed_at' => $data['observed_at'],
    ]);
    return (int)$db->lastInsertId();
}

function confidence_manifest(PDO $db, int $snapshotId): array
{
    $json = (string)$db->query("SELECT calculation_manifest FROM price_indices WHERE id={$snapshotId}")->fetchColumn();
    return json_decode($json, true) ?: [];
}

global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(4));
    $sources = [
        confidence_source($db, $suffix, 'alpha', 'high'),
        confidence_source($db, $suffix, 'beta', 'high'),
        confidence_source($db, $suffix, 'gamma', 'high'),
        confidence_source($db, $suffix, 'delta', 'high'),
    ];
    $weakSource = confidence_source($db, $suffix, 'weak', 'low');
    $mockSource = confidence_source($db, $suffix, 'mock_source', 'high');

    $service = new PriceSnapshotService();

    $highProduct = confidence_product($db, $suffix, 'high');
    foreach ([7000, 7100, 7200, 7300, 7400, 7500, 7600, 7700] as $i => $price) {
        confidence_observation($db, $highProduct, $sources[$i % 4], 'high-' . $suffix . '-' . $i, $price);
    }
    confidence_observation($db, $highProduct, $mockSource, 'mock-' . $suffix, 1, ['quality_flags' => ['MOCK_FIXTURE']]);
    $high = $service->recalculateProduct($highProduct, 'asking');
    assert_confidence($high !== null, 'Expected high-confidence snapshot');
    assert_confidence($high['confidence_method_version'] === SnapshotConfidenceService::METHOD_VERSION, 'Confidence method version mismatch');
    assert_confidence($high['confidence_label'] === 'high', 'Expected HIGH label for diverse fresh strong cohort');
    assert_confidence($high['confidence']['components']['source_diversity']['unique_source_count'] === 4, 'Expected four independent sources');
    assert_confidence(in_array('GOOD_SOURCE_DIVERSITY', $high['confidence']['reasons'], true), 'Expected diversity reason');
    $highReproduction = $service->reproduceSnapshot((int)$high['snapshot_id']);
    if ($highReproduction['result'] !== 'MATCH') {
        echo json_encode($highReproduction, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
    assert_confidence($highReproduction['result'] === 'MATCH', 'High confidence reproduction must match');

    $highRepeat = $service->recalculateProduct($highProduct, 'asking');
    assert_confidence($highRepeat !== null && $highRepeat['calculation_hash'] === $high['calculation_hash'], 'Same inputs must reproduce same confidence hash');

    $manifest = confidence_manifest($db, (int)$high['snapshot_id']);
    assert_confidence(isset($manifest['confidence']['components']['sample']), 'Manifest missing sample component');
    assert_confidence(isset($manifest['confidence']['components']['freshness']), 'Manifest missing freshness component');
    assert_confidence(isset($manifest['confidence']['components']['source_diversity']), 'Manifest missing source diversity component');
    assert_confidence(isset($manifest['confidence']['components']['evidence_quality']), 'Manifest missing evidence component');
    $mockExcluded = (int)$db->query("SELECT COUNT(*) FROM price_snapshot_observations WHERE snapshot_id=" . (int)$high['snapshot_id'] . " AND exclusion_reason='MOCK_SOURCE'")->fetchColumn();
    assert_confidence($mockExcluded === 1, 'Mock source must be excluded and not raise confidence');

    $singleProduct = confidence_product($db, $suffix, 'single-source');
    foreach ([7000, 7100, 7200, 7300, 7400, 7500, 7600, 7700] as $i => $price) {
        confidence_observation($db, $singleProduct, $sources[0], 'single-' . $suffix . '-' . $i, $price);
    }
    $single = $service->recalculateProduct($singleProduct, 'asking');
    assert_confidence($single !== null && $single['confidence_label'] !== 'high', 'Single source cohort cannot be HIGH');
    assert_confidence(in_array('SINGLE_SOURCE_CAP', $single['confidence']['policy_caps'], true), 'Expected single-source cap');

    $lowProduct = confidence_product($db, $suffix, 'low');
    foreach ([7000, 7100, 7200] as $i => $price) {
        confidence_observation($db, $lowProduct, $weakSource, 'low-' . $suffix . '-' . $i, $price, [
            'observed_at' => '2025-01-01 12:00:00',
            'evidence_level' => 1,
        ]);
    }
    $low = $service->recalculateProduct($lowProduct, 'asking');
    assert_confidence($low !== null && $low['confidence_label'] === 'low', 'Expected LOW for tiny stale weak single-source cohort');
    assert_confidence(in_array('STALE_DATA', $low['confidence']['reasons'], true), 'Expected stale reason');
    assert_confidence(in_array('WEAK_EVIDENCE_CAP', $low['confidence']['policy_caps'], true), 'Expected weak-evidence cap');

    $dominantProduct = confidence_product($db, $suffix, 'dominant');
    for ($i = 0; $i < 9; $i++) {
        confidence_observation($db, $dominantProduct, $sources[0], 'dominant-a-' . $suffix . '-' . $i, 7000 + $i * 100);
    }
    confidence_observation($db, $dominantProduct, $sources[1], 'dominant-b-' . $suffix, 8000);
    $dominant = $service->recalculateProduct($dominantProduct, 'asking');
    assert_confidence($dominant !== null && $dominant['confidence_label'] !== 'high', 'Dominant-source concentration cannot be HIGH');
    assert_confidence(in_array('SOURCE_CONCENTRATION_CAP', $dominant['confidence']['policy_caps'], true), 'Expected concentration cap');

    $soldProduct = confidence_product($db, $suffix, 'sold');
    foreach ([7000, 7100, 7200, 7300] as $i => $price) {
        confidence_observation($db, $soldProduct, $sources[$i % 2], 'asking-' . $suffix . '-' . $i, $price);
        confidence_observation($db, $soldProduct, $sources[($i + 2) % 4], 'sold-' . $suffix . '-' . $i, $price - 500, ['price_type' => 'sold']);
    }
    $asking = $service->recalculateProduct($soldProduct, 'asking');
    $sold = $service->recalculateProduct($soldProduct, 'sold');
    assert_confidence($asking !== null && $sold !== null, 'Expected separate asking and sold snapshots');
    assert_confidence($asking['price_type'] === 'asking' && $sold['price_type'] === 'sold', 'Asking/sold snapshot type mismatch');
    assert_confidence($asking['median'] !== $sold['median'], 'Asking and sold cohorts must not be mixed');

    $invalidProduct = confidence_product($db, $suffix, 'invalid');
    foreach ([7000, 7100, 7200] as $i => $price) {
        confidence_observation($db, $invalidProduct, $sources[$i], 'valid-invalid-' . $suffix . '-' . $i, $price);
    }
    confidence_observation($db, $invalidProduct, $sources[3], 'whole-pc-' . $suffix, 25000, ['listing_type' => 'whole_pc', 'quality_flags' => ['WHOLE_PC']]);
    $invalid = $service->recalculateProduct($invalidProduct, 'asking');
    assert_confidence($invalid !== null, 'Expected invalid-fixture snapshot');
    $wholePcExcluded = (int)$db->query("SELECT COUNT(*) FROM price_snapshot_observations WHERE snapshot_id=" . (int)$invalid['snapshot_id'] . " AND exclusion_reason='OUTSIDE_COHORT'")->fetchColumn();
    assert_confidence($wholePcExcluded >= 1, 'Whole-PC/category-invalid row must be excluded from snapshot');

    $direct = new SnapshotConfidenceService();
    $freshRows = [
        ['observed_at' => date('Y-m-d 12:00:00'), 'source_domain' => 'a.test', 'evidence_level' => 5, 'source_evidence_quality' => 'high'],
        ['observed_at' => date('Y-m-d 12:00:00'), 'source_domain' => 'b.test', 'evidence_level' => 5, 'source_evidence_quality' => 'high'],
        ['observed_at' => date('Y-m-d 12:00:00'), 'source_domain' => 'c.test', 'evidence_level' => 5, 'source_evidence_quality' => 'high'],
    ];
    $oldRows = array_map(static fn(array $row): array => array_merge($row, ['observed_at' => '2025-01-01 12:00:00']), $freshRows);
    assert_confidence($direct->calculate($freshRows, 'asking', date('Y-m-d'))['components']['freshness']['score'] > $direct->calculate($oldRows, 'asking', date('Y-m-d'))['components']['freshness']['score'], 'Fresher observations should improve freshness component');

    $db->prepare("UPDATE price_indices SET calculation_manifest=JSON_SET(calculation_manifest,'$.confidence.overall_score',1) WHERE id=:id")
        ->execute(['id' => (int)$high['snapshot_id']]);
    assert_confidence($service->reproduceSnapshot((int)$high['snapshot_id'])['result'] === 'MISMATCH', 'Confidence manifest corruption must produce mismatch');

    echo "Price confidence test passed\n";
    echo "High snapshot: " . (int)$high['snapshot_id'] . " label " . strtoupper($high['confidence_label']) . " score " . number_format((float)$high['confidence_score'], 2) . "\n";
    echo "Medium/single-source label: " . strtoupper($single['confidence_label']) . "\n";
    echo "Low snapshot label: " . strtoupper($low['confidence_label']) . "\n";
    echo "Asking/sold medians: " . $asking['median'] . "/" . $sold['median'] . "\n";
    echo "Confidence corruption check: MISMATCH\n";
} finally {
    $db->rollBack();
}
