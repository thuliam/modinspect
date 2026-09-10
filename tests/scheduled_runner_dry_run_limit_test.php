<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Collection\ScheduledCollectionRunner;
use App\Services\Providers\MockSearchProvider;

function assert_runner_limit(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();

try {
    $sourceId = 9102;
    $db->exec("INSERT IGNORE INTO data_sources (id,source_key,name,domain,source_type,access_method,allowed_collection_method,risk_level,reliability_score,evidence_quality,freshness_expectation_days,is_active,is_paused,last_success_at) VALUES ({$sourceId},'scheduled_runner_dry_fixture','Scheduled runner dry fixture','fixture.runner.dry','research','manual','manual','low',95,'high',14,1,0,NOW())");
    $productId = (int)$db->query("SELECT id FROM products WHERE slug='amd-ryzen-7-5700x3d'")->fetchColumn();
    assert_runner_limit($productId > 0, 'Missing dry-run fixture product');

    for ($i = 0; $i < 5; $i++) {
        $stmt = $db->prepare("INSERT INTO collector_jobs (source_id,job_type,product_id,query_text,status,created_at,updated_at) VALUES (:source_id,'coverage_mock_collection',:product_id,'5700x3d','queued',NOW(),NOW())");
        $stmt->execute(['source_id' => $sourceId, 'product_id' => $productId]);
    }

    $before = [
        'queued' => (int)$db->query("SELECT COUNT(*) FROM collector_jobs WHERE source_id={$sourceId} AND status='queued'")->fetchColumn(),
        'raw' => (int)$db->query("SELECT COUNT(*) FROM raw_price_observations WHERE source_id={$sourceId}")->fetchColumn(),
    ];

    $runner = new ScheduledCollectionRunner(new MockSearchProvider(ROOT_PATH . '/tests/fixtures/market_listings.json'), $sourceId);
    $dry = $runner->run(3, null, true);
    assert_runner_limit($dry['jobs_scanned'] === 3, 'Dry run should scan up to limit');
    assert_runner_limit($dry['jobs_claimed'] === 0, 'Dry run must not claim jobs');
    assert_runner_limit($dry['jobs_skipped'] === 3, 'Dry run should report skipped jobs');

    $afterDry = [
        'queued' => (int)$db->query("SELECT COUNT(*) FROM collector_jobs WHERE source_id={$sourceId} AND status='queued'")->fetchColumn(),
        'raw' => (int)$db->query("SELECT COUNT(*) FROM raw_price_observations WHERE source_id={$sourceId}")->fetchColumn(),
    ];
    assert_runner_limit($before === $afterDry, 'Dry run must not mutate job or pipeline state');

    $normal = $runner->run(3);
    assert_runner_limit($normal['jobs_claimed'] === 3, 'Normal run with limit=3 should claim exactly 3 jobs');
    $remainingQueued = (int)$db->query("SELECT COUNT(*) FROM collector_jobs WHERE source_id={$sourceId} AND status='queued'")->fetchColumn();
    assert_runner_limit($remainingQueued === 2, 'Limit should leave unprocessed queued jobs');

    echo "Scheduled runner dry-run/limit test passed\n";
    echo "Dry-run scanned: {$dry['jobs_scanned']}\n";
    echo "Normal claimed: {$normal['jobs_claimed']}\n";
    echo "Remaining queued: {$remainingQueued}\n";
} finally {
    $db->rollBack();
}
