<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Models\Dashboard;

function assert_review(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();

try {
    $db->exec("INSERT IGNORE INTO data_sources (id,name,domain,source_type,access_method,risk_level,is_active,last_success_at) VALUES (9002,'Review fixture','fixture.review','research','manual','low',1,NOW())");
    $hash = hash('sha256', 'review-fixture');
    $raw = $db->prepare("INSERT IGNORE INTO raw_price_observations (source_id,external_reference_hash,raw_title,raw_price_text,captured_at,processing_status) VALUES (9002,:hash,'Review fixture 5700X3D','7500',NOW(),'review')");
    $raw->execute(['hash' => $hash]);
    $rawId = (int)$db->lastInsertId();
    if ($rawId === 0) {
        $lookup = $db->prepare("SELECT id FROM raw_price_observations WHERE source_id=9002 AND external_reference_hash=:hash");
        $lookup->execute(['hash' => $hash]);
        $rawId = (int)$lookup->fetchColumn();
    }

    $obs = $db->prepare("INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,currency,condition_level,listing_type,is_deposit,is_defective,is_duplicate,evidence_level,classification_confidence,verified_status,observed_at) VALUES (:raw_id,1,'asking',7500,'THB','good','single_item',0,0,0,4,99,'pending',NOW())");
    $obs->execute(['raw_id' => $rawId]);
    $observationId = (int)$db->lastInsertId();

    $ok = (new Dashboard())->decideObservation($observationId, 'approved', 'fixture approval');
    assert_review($ok, 'Expected review decision to succeed');

    $status = $db->query("SELECT verified_status FROM price_observations WHERE id={$observationId}")->fetchColumn();
    assert_review($status === 'approved', 'Expected observation to be approved');

    $auditCount = (int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE entity_type='price_observation' AND entity_id={$observationId} AND action='review_approved'")->fetchColumn();
    assert_review($auditCount === 1, 'Expected one audit record for review approval');

    echo "Review decision test passed\n";
} finally {
    $db->rollBack();
}
