<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Pricing\PriceSnapshotService;

function assert_snapshot(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();

try {
    $db->exec("UPDATE price_observations SET verified_status='excluded' WHERE product_id=1 AND verified_status='approved'");
    $db->exec("INSERT IGNORE INTO data_sources (id,name,domain,source_type,access_method,risk_level,is_active,last_success_at) VALUES (9001,'Snapshot fixture','fixture.snapshot','research','manual','low',1,NOW())");
    $prices = [7000, 7500, 8000, 12000];
    foreach ($prices as $index => $price) {
        $hash = hash('sha256', 'snapshot-fixture-' . $index);
        $raw = $db->prepare("INSERT IGNORE INTO raw_price_observations (source_id,external_reference_hash,raw_title,raw_price_text,captured_at,processing_status) VALUES (9001,:hash,:title,:price,NOW(),'processed')");
        $raw->execute(['hash' => $hash, 'title' => 'Snapshot fixture 5700X3D ' . $index, 'price' => (string)$price]);
        $rawId = (int)$db->lastInsertId();
        if ($rawId === 0) {
            $lookup = $db->prepare("SELECT id FROM raw_price_observations WHERE source_id=9001 AND external_reference_hash=:hash");
            $lookup->execute(['hash' => $hash]);
            $rawId = (int)$lookup->fetchColumn();
        }
        $obs = $db->prepare("INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,currency,condition_level,listing_type,is_deposit,is_defective,is_duplicate,evidence_level,classification_confidence,verified_status,observed_at) VALUES (:raw_id,1,'asking',:price,'THB','good','single_item',0,0,0,4,99,'approved',NOW())");
        $obs->execute(['raw_id' => $rawId, 'price' => $price]);
    }

    $snapshot = (new PriceSnapshotService())->recalculateProduct(1, 'asking');
    assert_snapshot($snapshot !== null, 'Expected a snapshot for approved fixture observations');
    assert_snapshot((float)$snapshot['median'] === 7750.0, 'Expected deterministic interpolated median of 7750');
    assert_snapshot((float)$snapshot['q1'] === 7375.0, 'Expected deterministic Q1 of 7375');
    assert_snapshot((float)$snapshot['q3'] === 9000.0, 'Expected deterministic Q3 of 9000');

    echo "Price snapshot test passed\n";
    echo "Q1: {$snapshot['q1']}, Median: {$snapshot['median']}, Q3: {$snapshot['q3']}\n";
} finally {
    $db->rollBack();
}
