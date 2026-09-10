<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Pricing\PriceSnapshotService;

function assert_loop(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();

try {
    $productId = (int)$db->query("SELECT id FROM products WHERE slug='amd-ryzen-7-5700x3d'")->fetchColumn();
    assert_loop($productId > 0, 'Missing snapshot loop fixture product');
    $db->prepare("UPDATE price_observations SET verified_status='excluded' WHERE product_id=:product_id AND verified_status='approved'")->execute(['product_id' => $productId]);

    $prices = [7000, 7500, 8000, 12000];
    $insertedIds = [];
    foreach ($prices as $price) {
        $stmt = $db->prepare(
            "INSERT INTO price_observations (product_id,price_type,asking_price,currency,condition_level,listing_type,is_deposit,is_defective,is_duplicate,evidence_level,classification_confidence,verified_status,observed_at)
             VALUES (:product_id,'asking',:price,'THB','good','single_item',0,0,0,4,99,'pending',NOW())"
        );
        $stmt->execute(['product_id' => $productId, 'price' => $price]);
        $insertedIds[] = (int)$db->lastInsertId();
    }

    $service = new PriceSnapshotService();
    $pendingSnapshot = $service->recalculateProduct($productId, 'asking');
    assert_loop($pendingSnapshot === null, 'Pending observations must not create a snapshot');

    $db->exec("UPDATE price_observations SET verified_status='approved' WHERE id IN (" . implode(',', $insertedIds) . ")");
    $approvedSnapshot = $service->recalculateProduct($productId, 'asking');

    assert_loop($approvedSnapshot !== null, 'Approved observations should create a snapshot');
    assert_loop((float)$approvedSnapshot['q1'] === 7375.0, 'Expected Q1 7375');
    assert_loop((float)$approvedSnapshot['median'] === 7750.0, 'Expected median 7750');
    assert_loop((float)$approvedSnapshot['q3'] === 9000.0, 'Expected Q3 9000');

    echo "Snapshot loop test passed\n";
    echo "Pending snapshot: 0\n";
    echo "Approved snapshot Q1/Median/Q3: {$approvedSnapshot['q1']}/{$approvedSnapshot['median']}/{$approvedSnapshot['q3']}\n";
} finally {
    $db->rollBack();
}
