<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Pricing\PriceSnapshotService;

function assert_provenance(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function insert_source(PDO $db, string $key, string $name, string $domain): int
{
    $stmt = $db->prepare(
        "INSERT INTO data_sources (source_key,name,domain,source_type,access_method,allowed_collection_method,risk_level,reliability_score,evidence_quality,freshness_expectation_days,is_active,is_paused)
         VALUES (:source_key,:name,:domain,'research','manual','manual','low',90,'high',14,1,0)"
    );
    $stmt->execute(['source_key' => $key, 'name' => $name, 'domain' => $domain]);
    return (int)$db->lastInsertId();
}

function insert_product(PDO $db, string $suffix): int
{
    $categoryId = (int)$db->query("SELECT id FROM product_categories WHERE slug='cpu' LIMIT 1")->fetchColumn();
    $brandId = (int)$db->query("SELECT id FROM brands WHERE slug='amd' LIMIT 1")->fetchColumn();
    assert_provenance($categoryId > 0 && $brandId > 0, 'Missing CPU/AMD fixture category or brand');
    $stmt = $db->prepare(
        "INSERT INTO products (category_id,brand_id,model_name,slug,full_name,is_active)
         VALUES (:category_id,:brand_id,:model_name,:slug,:full_name,1)"
    );
    $stmt->execute([
        'category_id' => $categoryId,
        'brand_id' => $brandId,
        'model_name' => 'Snapshot Provenance Fixture ' . $suffix,
        'slug' => 'snapshot-provenance-fixture-' . $suffix,
        'full_name' => 'AMD Snapshot Provenance Fixture ' . $suffix,
    ]);
    return (int)$db->lastInsertId();
}

function insert_raw(PDO $db, int $sourceId, string $hashSeed, int $price): int
{
    $stmt = $db->prepare(
        "INSERT INTO raw_price_observations (source_id,external_reference_hash,raw_title,raw_price_text,captured_at,processing_status)
         VALUES (:source_id,:hash,:title,:price,'2026-09-09 12:00:00','processed')"
    );
    $stmt->execute([
        'source_id' => $sourceId,
        'hash' => hash('sha256', $hashSeed),
        'title' => 'Snapshot provenance fixture ' . $price,
        'price' => (string)$price,
    ]);
    return (int)$db->lastInsertId();
}

function insert_observation(PDO $db, int $productId, ?int $rawId, int $price, array $overrides = []): int
{
    $data = array_merge([
        'price_type' => 'asking',
        'listing_type' => 'single_item',
        'is_deposit' => 0,
        'is_defective' => 0,
        'is_duplicate' => 0,
        'verified_status' => 'approved',
        'observed_at' => '2026-09-09 12:00:00',
    ], $overrides);

    $stmt = $db->prepare(
        "INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,currency,condition_level,listing_type,is_deposit,is_defective,is_duplicate,evidence_level,classification_confidence,verified_status,observed_at)
         VALUES (:raw_id,:product_id,:price_type,:price,'THB','good',:listing_type,:is_deposit,:is_defective,:is_duplicate,4,99,:verified_status,:observed_at)"
    );
    $stmt->execute([
        'raw_id' => $rawId,
        'product_id' => $productId,
        'price_type' => $data['price_type'],
        'price' => $price,
        'listing_type' => $data['listing_type'],
        'is_deposit' => $data['is_deposit'],
        'is_defective' => $data['is_defective'],
        'is_duplicate' => $data['is_duplicate'],
        'verified_status' => $data['verified_status'],
        'observed_at' => $data['observed_at'],
    ]);
    return (int)$db->lastInsertId();
}

global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(4));
    $productId = insert_product($db, $suffix);
    $sourceId = insert_source($db, 'snapshot_provenance_source_' . $suffix, 'Snapshot Provenance Source', 'fixture.provenance');
    $mockSourceId = insert_source($db, 'mock_snapshot_provenance_' . $suffix, 'Mock Snapshot Provenance', 'mock.provenance');

    $includedIds = [];
    foreach ([7000, 7500, 8000, 12000] as $index => $price) {
        $includedIds[] = insert_observation($db, $productId, insert_raw($db, $sourceId, 'included-' . $suffix . '-' . $index, $price), $price);
    }

    $pendingId = insert_observation($db, $productId, insert_raw($db, $sourceId, 'pending-' . $suffix, 7300), 7300, ['verified_status' => 'pending']);
    $rejectedId = insert_observation($db, $productId, insert_raw($db, $sourceId, 'rejected-' . $suffix, 7400), 7400, ['verified_status' => 'rejected']);
    $soldId = insert_observation($db, $productId, insert_raw($db, $sourceId, 'sold-' . $suffix, 7200), 7200, ['price_type' => 'sold']);
    $mockId = insert_observation($db, $productId, insert_raw($db, $mockSourceId, 'mock-' . $suffix, 5000), 5000);
    $duplicateId = insert_observation($db, $productId, insert_raw($db, $sourceId, 'duplicate-' . $suffix, 7600), 7600, ['is_duplicate' => 1]);
    $depositId = insert_observation($db, $productId, insert_raw($db, $sourceId, 'deposit-' . $suffix, 2000), 2000, ['is_deposit' => 1]);
    $defectiveId = insert_observation($db, $productId, insert_raw($db, $sourceId, 'defective-' . $suffix, 3000), 3000, ['is_defective' => 1]);
    $bundleId = insert_observation($db, $productId, insert_raw($db, $sourceId, 'bundle-' . $suffix, 9000), 9000, ['listing_type' => 'bundle']);

    $service = new PriceSnapshotService();
    $snapshotA = $service->recalculateProduct($productId, 'asking');
    assert_provenance($snapshotA !== null, 'Expected snapshot A');
    $snapshotAId = (int)$snapshotA['snapshot_id'];
    assert_provenance($snapshotA['formula_version'] === PriceSnapshotService::FORMULA_VERSION, 'Formula version missing');
    assert_provenance($snapshotA['cohort_version'] === PriceSnapshotService::COHORT_VERSION, 'Cohort version missing');
    assert_provenance(strlen((string)$snapshotA['calculation_hash']) === 64, 'Calculation hash missing');

    $includedCount = (int)$db->query("SELECT COUNT(*) FROM price_snapshot_observations WHERE snapshot_id={$snapshotAId} AND inclusion_status='included'")->fetchColumn();
    $excludedCount = (int)$db->query("SELECT COUNT(*) FROM price_snapshot_observations WHERE snapshot_id={$snapshotAId} AND inclusion_status='excluded'")->fetchColumn();
    assert_provenance($includedCount === 4, 'Expected 4 included observations');
    assert_provenance($excludedCount === 8, 'Expected 8 excluded observations');

    $reasons = [];
    foreach ($db->query("SELECT observation_id,exclusion_reason FROM price_snapshot_observations WHERE snapshot_id={$snapshotAId} AND inclusion_status='excluded'") as $row) {
        $reasons[(int)$row['observation_id']] = (string)$row['exclusion_reason'];
    }
    assert_provenance($reasons[$pendingId] === 'NOT_ACCEPTED', 'Pending exclusion reason mismatch');
    assert_provenance($reasons[$rejectedId] === 'NOT_ACCEPTED', 'Rejected exclusion reason mismatch');
    assert_provenance($reasons[$soldId] === 'WRONG_PRICE_TYPE', 'Sold price must stay outside asking cohort');
    assert_provenance($reasons[$mockId] === 'MOCK_SOURCE', 'Mock exclusion reason mismatch');
    assert_provenance($reasons[$duplicateId] === 'DUPLICATE', 'Duplicate exclusion reason mismatch');
    assert_provenance($reasons[$depositId] === 'DEPOSIT', 'Deposit exclusion reason mismatch');
    assert_provenance($reasons[$defectiveId] === 'DEFECTIVE', 'Defective exclusion reason mismatch');
    assert_provenance($reasons[$bundleId] === 'OUTSIDE_COHORT', 'Bundle exclusion reason mismatch');

    $manifest = json_decode((string)$db->query("SELECT calculation_manifest FROM price_indices WHERE id={$snapshotAId}")->fetchColumn(), true);
    assert_provenance(count($manifest['considered_observation_ids']) === 12, 'Manifest should store considered IDs');
    assert_provenance(count($manifest['included_observation_ids']) === 4, 'Manifest should store included IDs');

    $reproductionA = $service->reproduceSnapshot($snapshotAId);
    assert_provenance($reproductionA['result'] === 'MATCH', 'Snapshot A reproduction should match');

    $snapshotARepeat = $service->recalculateProduct($productId, 'asking');
    assert_provenance($snapshotARepeat !== null, 'Expected repeated snapshot');
    assert_provenance($snapshotARepeat['calculation_hash'] === $snapshotA['calculation_hash'], 'Same input and formula should have same hash');

    $db->prepare("UPDATE price_observations SET verified_status='rejected',asking_price=123 WHERE id=:id")->execute(['id' => $includedIds[0]]);
    $reproductionAfterMutation = $service->reproduceSnapshot($snapshotAId);
    assert_provenance($reproductionAfterMutation['result'] === 'MATCH', 'Historical snapshot should remain reproducible after observation mutation');

    $snapshotB = $service->recalculateProduct($productId, 'asking');
    assert_provenance($snapshotB !== null, 'Expected newer snapshot after input change');
    assert_provenance($snapshotB['calculation_hash'] !== $snapshotA['calculation_hash'], 'Changed input should change hash');
    assert_provenance($service->reproduceSnapshot((int)$snapshotB['snapshot_id'])['result'] === 'MATCH', 'Snapshot B reproduction should match');

    $db->prepare("UPDATE price_snapshot_observations SET calculation_price=calculation_price+1 WHERE snapshot_id=:snapshot_id AND inclusion_status='included' LIMIT 1")
        ->execute(['snapshot_id' => (int)$snapshotB['snapshot_id']]);
    assert_provenance($service->reproduceSnapshot((int)$snapshotB['snapshot_id'])['result'] === 'MISMATCH', 'Corrupted membership should produce mismatch');

    $legacy = $db->prepare(
        "INSERT INTO price_indices (product_id,price_type,price_low,q1,median,q3,price_high,sample_size,valid_sample_size,fresh_sample_ratio,confidence_score,confidence_label,last_calculated_at)
         VALUES (:product_id,'asking',1,1,1,1,1,0,0,0,0,'insufficient',NOW())"
    );
    $legacy->execute(['product_id' => $productId]);
    $legacyId = (int)$db->lastInsertId();
    assert_provenance($service->reproduceSnapshot($legacyId)['result'] === 'LEGACY_PROVENANCE_UNAVAILABLE', 'Legacy snapshots must not receive fabricated provenance');

    $soldSnapshot = $service->recalculateProduct($productId, 'sold');
    assert_provenance($soldSnapshot === null, 'One sold observation is insufficient and must not mix with asking cohort');

    echo "Snapshot provenance test passed\n";
    echo "Snapshot A: {$snapshotAId}\n";
    echo "Included/excluded: {$includedCount}/{$excludedCount}\n";
    echo "Formula/cohort: " . PriceSnapshotService::FORMULA_VERSION . "/" . PriceSnapshotService::COHORT_VERSION . "\n";
    echo "Reproduction: MATCH\n";
    echo "Corruption check: MISMATCH\n";
} finally {
    $db->rollBack();
}
