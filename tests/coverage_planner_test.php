<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Models\CollectionPipeline;
use App\Services\Collection\CoveragePlanner;

function assert_coverage(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();

try {
    $mockSourceId = (new CollectionPipeline())->ensureMockSource();
    $db->exec("UPDATE collector_jobs SET status='completed' WHERE job_type='coverage_mock_collection' AND status IN ('queued','running')");
    $stableProductId = (int)$db->query("SELECT id FROM products WHERE slug='amd-ryzen-7-5700x3d'")->fetchColumn();
    $activeJobProductId = (int)$db->query("SELECT id FROM products WHERE slug='nvidia-geforce-rtx-3070'")->fetchColumn();

    assert_coverage($stableProductId > 0, 'Missing AMD Ryzen 7 5700X3D product');
    assert_coverage($activeJobProductId > 0, 'Missing NVIDIA GeForce RTX 3070 product');

    for ($i = 0; $i < 20; $i++) {
        $stmt = $db->prepare(
            "INSERT INTO price_observations (product_id,price_type,asking_price,currency,condition_level,listing_type,is_deposit,is_defective,is_duplicate,evidence_level,classification_confidence,verified_status,observed_at)
             VALUES (:product_id,'asking',:price,'THB','good','single_item',0,0,0,4,99,'approved',NOW())"
        );
        $stmt->execute(['product_id' => $stableProductId, 'price' => 7000 + ($i * 50)]);
    }

    $activeJob = $db->prepare("INSERT INTO collector_jobs (source_id,job_type,product_id,query_text,status,created_at,updated_at) VALUES (:source_id,'coverage_mock_collection',:product_id,'RTX 3070 มือสอง','queued',NOW(),NOW())");
    $activeJob->execute(['source_id' => $mockSourceId, 'product_id' => $activeJobProductId]);

    $planner = new CoveragePlanner($mockSourceId, 20, 14, 8, 'coverage_mock_collection', 0);
    $plan = $planner->plan();
    $itemsById = [];
    foreach ($plan['items'] as $item) {
        $itemsById[(int)$item['product_id']] = $item;
    }

    assert_coverage(isset($itemsById[$stableProductId]), 'Stable product missing from plan');
    assert_coverage(isset($itemsById[$activeJobProductId]), 'Active-job product missing from plan');
    assert_coverage($itemsById[$stableProductId]['due'] === false, 'Fully covered fresh product should not be due');
    assert_coverage($itemsById[$stableProductId]['sample_gap'] === 0, 'Fully covered product should have no sample gap');
    assert_coverage($itemsById[$activeJobProductId]['due'] === false, 'Product with queued job should not create duplicate work');
    assert_coverage(in_array('active_job_exists', $itemsById[$activeJobProductId]['due_reasons'], true), 'Active job reason should be recorded');
    assert_coverage($plan['summary']['products_scanned'] >= 20, 'Planner should scan active CPU/GPU POC products');
    assert_coverage($plan['summary']['due_products'] >= 1, 'Planner should identify at least one due product');

    $jobs = $planner->createJobs($plan, 3);
    assert_coverage(count($jobs['created']) === 3, 'Expected exactly 3 jobs with limit=3');
    foreach ($jobs['created'] as $job) {
        assert_coverage((int)$job['product_id'] !== $stableProductId, 'Not-due product should not receive a job');
        assert_coverage((int)$job['product_id'] !== $activeJobProductId, 'Product with active job should not receive duplicate job');
    }

    $freshPlan = $planner->plan();
    $secondJobs = $planner->createJobs($freshPlan, 3);
    assert_coverage(count($secondJobs['created']) === 3, 'Expected next 3 due products after first active jobs are considered');

    $activeCount = (int)$db->query(
        "SELECT COUNT(*) FROM collector_jobs WHERE source_id={$mockSourceId} AND job_type='coverage_mock_collection' AND status IN ('queued','running') GROUP BY product_id HAVING COUNT(*) > 1 LIMIT 1"
    )->fetchColumn();
    assert_coverage($activeCount === 0, 'Duplicate queued/running coverage jobs detected');

    echo "Coverage planner test passed\n";
    echo "Products scanned: {$plan['summary']['products_scanned']}\n";
    echo "Due products: {$plan['summary']['due_products']}\n";
    echo "Jobs created first pass: " . count($jobs['created']) . "\n";
    echo "Jobs created second pass: " . count($secondJobs['created']) . "\n";
} finally {
    $db->rollBack();
}
