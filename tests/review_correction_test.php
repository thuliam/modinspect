<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\Dashboard;
use App\Services\Pricing\PriceSnapshotService;
use App\Services\Review\ReviewCorrectionService;

function assert_correction(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function correction_source(PDO $db, string $suffix, bool $mock = false): int
{
    $stmt = $db->prepare(
        "INSERT INTO data_sources (source_key,name,domain,source_type,access_method,allowed_collection_method,risk_level,reliability_score,evidence_quality,freshness_expectation_days,is_active,is_paused)
         VALUES (:source_key,:name,:domain,'research','manual','manual','low',90,'high',14,1,0)"
    );
    $stmt->execute([
        'source_key' => ($mock ? 'mock_review_correction_' : 'review_correction_') . $suffix,
        'name' => $mock ? 'Mock Review Correction' : 'Review Correction Source',
        'domain' => $mock ? 'mock.review-correction' : 'fixture.review-correction',
    ]);
    return (int)$db->lastInsertId();
}

function correction_product(PDO $db, string $suffix, string $label): int
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
        'model_name' => "Correction {$label} {$suffix}",
        'slug' => "correction-{$label}-{$suffix}",
        'full_name' => "Correction {$label} {$suffix}",
    ]);
    return (int)$db->lastInsertId();
}

function correction_observation(PDO $db, int $sourceId, int $productId, int $price, string $suffix, array $overrides = []): array
{
    $raw = $db->prepare(
        "INSERT INTO raw_price_observations (source_id,external_reference_hash,raw_title,raw_price_text,source_url_encrypted,captured_at,processing_status)
         VALUES (:source_id,:hash,:title,:price,:url,'2026-09-09 10:00:00','review')"
    );
    $raw->execute([
        'source_id' => $sourceId,
        'hash' => hash('sha256', 'correction-' . $suffix . '-' . random_int(1, 1000000)),
        'title' => 'Correction fixture ' . $price,
        'price' => (string)$price,
        'url' => 'https://example.test/listing/' . $suffix,
    ]);
    $rawId = (int)$db->lastInsertId();
    $evidence = $db->prepare("INSERT INTO market_evidence (raw_observation_id,source_id,evidence_level,evidence_type,excerpt,content_hash,captured_at,is_public) VALUES (:raw_id,:source_id,'B','url','Correction evidence',:hash,'2026-09-09 10:00:00',0)");
    $evidence->execute(['raw_id' => $rawId, 'source_id' => $sourceId, 'hash' => hash('sha256', 'evidence-' . $rawId)]);
    $evidenceId = (int)$db->lastInsertId();
    $original = ['askingPrice' => $price, 'listingType' => 'single_item', 'conditionLevel' => 'good'];
    $extract = $db->prepare("INSERT INTO extraction_runs (raw_observation_id,evidence_id,provider_name,model_name,extracted_data,confidence,created_at) VALUES (:raw_id,:evidence_id,'correction-test','fixture',:data,0.88,NOW())");
    $extract->execute(['raw_id' => $rawId, 'evidence_id' => $evidenceId, 'data' => json_encode($original, JSON_UNESCAPED_UNICODE)]);
    $extractId = (int)$db->lastInsertId();

    $data = array_merge([
        'price_type' => 'asking',
        'asking_price' => $price,
        'price_value' => $price,
        'listing_type' => 'single_item',
        'condition_level' => 'good',
        'warranty_months' => null,
        'verified_status' => 'pending',
    ], $overrides);

    $obs = $db->prepare(
        "INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,price_value,currency,condition_level,warranty_months,listing_type,is_deposit,is_defective,is_duplicate,evidence_level,classification_confidence,verified_status,observed_at)
         VALUES (:raw_id,:product_id,:price_type,:asking_price,:price_value,'THB',:condition_level,:warranty_months,:listing_type,0,0,0,4,88,:verified_status,'2026-09-09 10:00:00')"
    );
    $obs->execute([
        'raw_id' => $rawId,
        'product_id' => $productId,
        'price_type' => $data['price_type'],
        'asking_price' => $data['asking_price'],
        'price_value' => $data['price_value'],
        'condition_level' => $data['condition_level'],
        'warranty_months' => $data['warranty_months'],
        'listing_type' => $data['listing_type'],
        'verified_status' => $data['verified_status'],
    ]);
    $observationId = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO observation_review_decisions (price_observation_id,raw_observation_id,extraction_run_id,lane,decision,reason_codes,ai_value) VALUES (:observation_id,:raw_id,:extraction_id,'amber','review_required','[\"correction_fixture\"]',:ai_value)")
        ->execute(['observation_id' => $observationId, 'raw_id' => $rawId, 'extraction_id' => $extractId, 'ai_value' => json_encode($original, JSON_UNESCAPED_UNICODE)]);

    return ['raw_id' => $rawId, 'extraction_id' => $extractId, 'observation_id' => $observationId];
}

global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(4));
    $sourceId = correction_source($db, $suffix);
    $mockSourceId = correction_source($db, $suffix, true);
    $productA = correction_product($db, $suffix, 'a');
    $productB = correction_product($db, $suffix, 'b');
    $service = new ReviewCorrectionService();
    $dashboard = new Dashboard();

    $fixture = correction_observation($db, $sourceId, $productA, 7000, $suffix . '-price');
    $originalExtraction = (string)$db->query("SELECT extracted_data FROM extraction_runs WHERE id={$fixture['extraction_id']}")->fetchColumn();
    assert_correction($service->correct($fixture['observation_id'], 'price', '7500', 'Correct price from listing') ['ok'] === true, 'Price correction should save');
    assert_correction((string)$db->query("SELECT extracted_data FROM extraction_runs WHERE id={$fixture['extraction_id']}")->fetchColumn() === $originalExtraction, 'Correction must not mutate original extraction');
    assert_correction($dashboard->decideObservation($fixture['observation_id'], 'approved', 'approve corrected price') === true, 'Corrected approval should succeed');
    $approved = $db->query("SELECT asking_price,price_value,verified_status FROM price_observations WHERE id={$fixture['observation_id']}")->fetch();
    assert_correction((float)$approved['asking_price'] === 7500.0 && (float)$approved['price_value'] === 7500.0 && $approved['verified_status'] === 'approved', 'Approved asking observation should use corrected price');
    assert_correction($dashboard->decideObservation($fixture['observation_id'], 'approved', 'replay') === false, 'Approve replay must remain blocked');

    $productFixture = correction_observation($db, $sourceId, $productA, 7100, $suffix . '-product');
    assert_correction($service->correct($productFixture['observation_id'], 'product_id', (string)$productB, 'Resolved to the correct canonical product')['ok'] === true, 'Product correction should save');
    assert_correction($dashboard->decideObservation($productFixture['observation_id'], 'approved', 'approve corrected product') === true, 'Corrected product approval should succeed');
    assert_correction((int)$db->query("SELECT product_id FROM price_observations WHERE id={$productFixture['observation_id']}")->fetchColumn() === $productB, 'Approved observation should use corrected product');

    $invalidFixture = correction_observation($db, $sourceId, $productA, 7200, $suffix . '-invalid');
    assert_correction($service->correct($invalidFixture['observation_id'], 'price', '-1', 'Invalid price')['ok'] === false, 'Invalid corrected price must be blocked');
    assert_correction($service->correct($invalidFixture['observation_id'], 'price_type', 'auction', 'Invalid enum')['ok'] === false, 'Invalid enum must be blocked');
    assert_correction($service->correct($invalidFixture['observation_id'], 'price', '7300', '')['ok'] === false, 'Correction reason is required');

    $rejectFixture = correction_observation($db, $sourceId, $productA, 7300, $suffix . '-reject');
    assert_correction($service->correct($rejectFixture['observation_id'], 'price', '7350', 'Correct but reject listing')['ok'] === true, 'Correction before reject should save');
    assert_correction($dashboard->decideObservation($rejectFixture['observation_id'], 'rejected', 'not acceptable') === true, 'Reject after correction should succeed');
    assert_correction((string)$db->query("SELECT verified_status FROM price_observations WHERE id={$rejectFixture['observation_id']}")->fetchColumn() === 'rejected', 'Rejected corrected observation must not be accepted');

    $duplicateFixture = correction_observation($db, $sourceId, $productA, 7400, $suffix . '-duplicate');
    $db->prepare("INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,price_value,currency,condition_level,listing_type,evidence_level,classification_confidence,verified_status,observed_at) VALUES (:raw_id,:product_id,'asking',7400,7400,'THB','good','single_item',4,99,'approved','2026-09-09 10:00:00')")
        ->execute(['raw_id' => $duplicateFixture['raw_id'], 'product_id' => $productA]);
    assert_correction($dashboard->decideObservation($duplicateFixture['observation_id'], 'approved', 'duplicate raw') === false, 'Duplicate accepted raw lineage must block approval');

    $soldFixture = correction_observation($db, $sourceId, $productA, 7600, $suffix . '-sold');
    assert_correction($service->correct($soldFixture['observation_id'], 'price_type', 'sold', 'Confirmed final sold price')['ok'] === true, 'Sold price_type correction should save');
    assert_correction($service->correct($soldFixture['observation_id'], 'price', '7550', 'Final sold price')['ok'] === true, 'Sold price correction should save');
    assert_correction($dashboard->decideObservation($soldFixture['observation_id'], 'approved', 'approve sold') === true, 'Sold approval should succeed');
    $sold = $db->query("SELECT price_type,asking_price,price_value FROM price_observations WHERE id={$soldFixture['observation_id']}")->fetch();
    assert_correction($sold['price_type'] === 'sold' && $sold['asking_price'] === null && (float)$sold['price_value'] === 7550.0, 'Sold observation must not require fake asking_price');

    $mockFixture = correction_observation($db, $mockSourceId, $productA, 5000, $suffix . '-mock');
    assert_correction($service->correct($mockFixture['observation_id'], 'price', '9000', 'Correct mock price')['ok'] === true, 'Mock correction can be audited');
    assert_correction($dashboard->decideObservation($mockFixture['observation_id'], 'approved', 'mock approval remains non-production') === true, 'Mock approval should remain review-auditable');
    assert_correction(str_contains((string)$db->query("SELECT quality_flags FROM price_observations WHERE id={$mockFixture['observation_id']}")->fetchColumn(), 'MOCK_SOURCE'), 'Mock source must remain flagged after correction');

    foreach ([8000, 8500, 9000] as $index => $price) {
        $snapshotFixture = correction_observation($db, $sourceId, $productA, $price - 100, $suffix . '-snapshot-' . $index);
        assert_correction($service->correct($snapshotFixture['observation_id'], 'price', (string)$price, 'Snapshot corrected price')['ok'] === true, 'Snapshot correction should save');
        assert_correction($dashboard->decideObservation($snapshotFixture['observation_id'], 'approved', 'snapshot corrected approval') === true, 'Snapshot corrected approval should succeed');
    }
    $snapshot = (new PriceSnapshotService())->recalculateProduct($productA, 'asking');
    assert_correction($snapshot !== null, 'Corrected approved observations should generate a snapshot');
    assert_correction((new PriceSnapshotService())->reproduceSnapshot((int)$snapshot['snapshot_id'])['result'] === 'MATCH', 'Corrected observation snapshot provenance should reproduce');
    $soldSnapshot = (new PriceSnapshotService())->recalculateProduct($productA, 'sold');
    assert_correction($soldSnapshot === null, 'Sold cohort must remain separate from asking cohort');

    echo "Review correction test passed\n";
    echo "Corrected price applied: 7500\n";
    echo "Corrected product applied: {$productB}\n";
    echo "Sold asking_price nullable: yes\n";
    echo "Corrected snapshot reproduction: MATCH\n";
} finally {
    $db->rollBack();
}
