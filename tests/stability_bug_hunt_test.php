<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\Dashboard;
use App\Models\Product;
use App\Services\Pricing\PriceSnapshotService;

function assert_stability(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function stability_count(PDO $db, string $sql): int
{
    return (int)$db->query($sql)->fetchColumn();
}

function stability_approved_observation(PDO $db, int $sourceId, int $productId, int $price, string $hashSeed): int
{
    $hash = hash('sha256', $hashSeed);
    $raw = $db->prepare(
        "INSERT INTO raw_price_observations (source_id,external_reference_hash,raw_title,raw_price_text,captured_at,processing_status)
         VALUES (:source_id,:hash,:title,:price,NOW(),'processed')"
    );
    $raw->execute([
        'source_id' => $sourceId,
        'hash' => $hash,
        'title' => 'Stability fixture AMD Ryzen 7 5700X3D ' . $price,
        'price' => (string)$price,
    ]);
    $rawId = (int)$db->lastInsertId();

    $obs = $db->prepare(
        "INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,currency,condition_level,listing_type,is_deposit,is_defective,is_duplicate,evidence_level,classification_confidence,verified_status,observed_at)
         VALUES (:raw_id,:product_id,'asking',:price,'THB','good','single_item',0,0,0,4,99,'approved',NOW())"
    );
    $obs->execute(['raw_id' => $rawId, 'product_id' => $productId, 'price' => $price]);
    return (int)$db->lastInsertId();
}

global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();

try {
    $productId = (int)$db->query("SELECT id FROM products WHERE slug='amd-ryzen-7-5700x3d'")->fetchColumn();
    assert_stability($productId > 0, 'Missing AMD Ryzen 7 5700X3D product');

    $suffix = bin2hex(random_bytes(4));
    $db->prepare("INSERT INTO data_sources (source_key,name,domain,source_type,access_method,allowed_collection_method,risk_level,reliability_score,evidence_quality,freshness_expectation_days,is_active,is_paused)
                  VALUES (:source_key,'Stability real fixture','fixture.stability','research','manual','manual','low',90,'high',14,1,0)")
        ->execute(['source_key' => 'stability_real_fixture_' . $suffix]);
    $sourceId = (int)$db->lastInsertId();

    $db->prepare("INSERT INTO data_sources (source_key,name,domain,source_type,access_method,allowed_collection_method,risk_level,reliability_score,evidence_quality,freshness_expectation_days,is_active,is_paused)
                  VALUES (:source_key,'Mock stability fixture','mock.stability','research','manual','manual','low',90,'high',14,1,0)")
        ->execute(['source_key' => 'mock_stability_fixture_' . $suffix]);
    $mockSourceId = (int)$db->lastInsertId();

    $db->prepare("UPDATE price_observations SET verified_status='excluded' WHERE product_id=:product_id AND verified_status='approved'")
        ->execute(['product_id' => $productId]);

    $hash = hash('sha256', 'review-replay-' . microtime(true));
    $db->prepare("INSERT INTO raw_price_observations (source_id,external_reference_hash,raw_title,raw_price_text,captured_at,processing_status) VALUES (:source_id,:hash,'Review replay fixture','7500',NOW(),'review')")
        ->execute(['source_id' => $sourceId, 'hash' => $hash]);
    $rawId = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,currency,condition_level,listing_type,is_deposit,is_defective,is_duplicate,evidence_level,classification_confidence,verified_status,observed_at) VALUES (:raw_id,:product_id,'asking',7500,'THB','good','single_item',0,0,0,4,99,'pending',NOW())")
        ->execute(['raw_id' => $rawId, 'product_id' => $productId]);
    $observationId = (int)$db->lastInsertId();

    $dashboard = new Dashboard();
    assert_stability($dashboard->decideObservation($observationId, 'approved', 'first approval') === true, 'Initial approval should succeed');
    $decisionCountAfterFirst = stability_count($db, "SELECT COUNT(*) FROM observation_review_decisions WHERE price_observation_id={$observationId}");
    $auditCountAfterFirst = stability_count($db, "SELECT COUNT(*) FROM audit_logs WHERE entity_type='price_observation' AND entity_id={$observationId}");
    assert_stability($dashboard->decideObservation($observationId, 'approved', 'duplicate approval') === false, 'Duplicate approval must be rejected');
    assert_stability($dashboard->decideObservation($observationId, 'rejected', 'stale reject') === false, 'Reject after approve must be rejected');
    assert_stability($dashboard->decideObservation(999999999, 'approved', 'missing') === false, 'Missing review ID must be rejected');
    assert_stability(stability_count($db, "SELECT COUNT(*) FROM observation_review_decisions WHERE price_observation_id={$observationId}") === $decisionCountAfterFirst, 'Replay must not add review decisions');
    assert_stability(stability_count($db, "SELECT COUNT(*) FROM audit_logs WHERE entity_type='price_observation' AND entity_id={$observationId}") === $auditCountAfterFirst, 'Replay must not add audit rows');

    foreach ([5000, 5200, 5400] as $i => $price) {
        stability_approved_observation($db, $mockSourceId, $productId, $price, 'mock-contamination-' . $i . microtime(true));
    }
    $mockOnly = (new PriceSnapshotService())->recalculateProduct($productId, 'asking');
    assert_stability($mockOnly === null, 'Approved mock observations must not create a public price snapshot');
    $productAfterMock = (new Product())->find($productId);
    assert_stability((int)$productAfterMock['accepted_observation_count'] === 1, 'Public accepted count must exclude mock observations');

    foreach ([7000, 7500, 8000] as $i => $price) {
        stability_approved_observation($db, $sourceId, $productId, $price, 'real-fixture-' . $i . microtime(true));
    }
    $snapshot = (new PriceSnapshotService())->recalculateProduct($productId, 'asking');
    assert_stability($snapshot !== null, 'Eligible non-mock observations should create a snapshot');
    assert_stability((int)$snapshot['valid_sample_size'] === 4, 'Snapshot should count only eligible approved observations');

    $job = $db->prepare("INSERT INTO collector_jobs (source_id,job_type,product_id,query_text,status,attempt_count,created_at,updated_at) VALUES (:source_id,'coverage_mock_collection',:product_id,'atomic-claim','queued',0,NOW(),NOW())");
    $job->execute(['source_id' => $sourceId, 'product_id' => $productId]);
    $jobId = (int)$db->lastInsertId();
    $claimA = $db->prepare("UPDATE collector_jobs SET status='running',run_id='claim-a',attempt_count=attempt_count+1,started_at=NOW(),updated_at=NOW() WHERE id=:id AND status='queued'");
    $claimA->execute(['id' => $jobId]);
    $claimB = $db->prepare("UPDATE collector_jobs SET status='running',run_id='claim-b',attempt_count=attempt_count+1,started_at=NOW(),updated_at=NOW() WHERE id=:id AND status='queued'");
    $claimB->execute(['id' => $jobId]);
    $claimed = $db->query("SELECT status,run_id,attempt_count FROM collector_jobs WHERE id={$jobId}")->fetch();
    assert_stability($claimA->rowCount() === 1 && $claimB->rowCount() === 0, 'Only one conditional claim should win');
    assert_stability($claimed['status'] === 'running' && $claimed['run_id'] === 'claim-a' && (int)$claimed['attempt_count'] === 1, 'Atomic claim must preserve winner run_id and attempt count');

    $xss = '<script>alert(1)</script>';
    $db->prepare("INSERT INTO raw_price_observations (source_id,external_reference_hash,raw_title,raw_price_text,captured_at,processing_status) VALUES (:source_id,:hash,:title,'7500',NOW(),'review')")
        ->execute(['source_id' => $sourceId, 'hash' => hash('sha256', 'xss-' . microtime(true)), 'title' => $xss]);
    $rawXssId = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,currency,condition_level,listing_type,is_deposit,is_defective,is_duplicate,evidence_level,classification_confidence,verified_status,observed_at) VALUES (:raw_id,:product_id,'asking',7500,'THB','good','single_item',0,0,0,4,99,'pending',NOW())")
        ->execute(['raw_id' => $rawXssId, 'product_id' => $productId]);
    ob_start();
    (new App\Controllers\AdminController())->review();
    $reviewHtml = (string)ob_get_clean();
    assert_stability(!str_contains($reviewHtml, $xss), 'Review UI must escape raw listing titles');
    assert_stability(str_contains($reviewHtml, htmlspecialchars($xss, ENT_QUOTES, 'UTF-8')), 'Review UI should render escaped listing title');

    echo "Stability bug hunt test passed\n";
    echo "Duplicate review replay blocked: yes\n";
    echo "Approved mock observations excluded from snapshots: yes\n";
    echo "Atomic claim winners: 1\n";
} finally {
    $db->rollBack();
}
