<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Collection\ScheduledCollectionRunner;
use App\Services\Providers\MockSearchProvider;

function assert_runner(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function create_runner_job(PDO $db, int $sourceId, int $productId, string $query): int
{
    $stmt = $db->prepare("INSERT INTO collector_jobs (source_id,job_type,product_id,query_text,status,created_at,updated_at) VALUES (:source_id,'coverage_mock_collection',:product_id,:query,'queued',NOW(),NOW())");
    $stmt->execute(['source_id' => $sourceId, 'product_id' => $productId, 'query' => $query]);
    return (int)$db->lastInsertId();
}

function count_scalar(PDO $db, string $sql): int
{
    return (int)$db->query($sql)->fetchColumn();
}

global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();

try {
    $sourceId = 9101;
    $db->exec("INSERT IGNORE INTO data_sources (id,source_key,name,domain,source_type,access_method,allowed_collection_method,risk_level,reliability_score,evidence_quality,freshness_expectation_days,is_active,is_paused,last_success_at) VALUES ({$sourceId},'scheduled_runner_fixture','Scheduled runner fixture','fixture.runner','research','manual','manual','low',95,'high',14,1,0,NOW())");
    $db->exec("UPDATE collector_jobs SET status='completed' WHERE source_id={$sourceId} AND job_type='coverage_mock_collection'");

    $product5700x3d = (int)$db->query("SELECT id FROM products WHERE slug='amd-ryzen-7-5700x3d'")->fetchColumn();
    $product3070 = (int)$db->query("SELECT id FROM products WHERE slug='nvidia-geforce-rtx-3070'")->fetchColumn();
    assert_runner($product5700x3d > 0 && $product3070 > 0, 'Missing runner fixture products');

    $normalJob = create_runner_job($db, $sourceId, $product5700x3d, '5700x3d');
    $secondJob = create_runner_job($db, $sourceId, $product3070, 'rtx3070');
    $failJob = create_runner_job($db, $sourceId, $product5700x3d, '__fail__');
    $afterFailJob = create_runner_job($db, $sourceId, $product5700x3d, '5700x3d');

    $runner = new ScheduledCollectionRunner(new MockSearchProvider(ROOT_PATH . '/tests/fixtures/market_listings.json'), $sourceId);
    $summary = $runner->run(10);

    assert_runner($summary['jobs_scanned'] === 4, 'Expected 4 queued jobs scanned');
    assert_runner($summary['jobs_claimed'] === 4, 'Expected 4 jobs claimed');
    assert_runner($summary['jobs_completed'] === 3, 'Expected 3 completed jobs');
    assert_runner($summary['jobs_failed'] === 1, 'Expected 1 failed job');
    assert_runner($summary['candidates_created'] > 0, 'Expected created candidates');
    assert_runner($summary['green'] >= 1 && $summary['amber'] >= 1 && $summary['red'] >= 1, 'Expected lane distribution');

    $statuses = [];
    foreach ($db->query("SELECT id,status FROM collector_jobs WHERE id IN ({$normalJob},{$secondJob},{$failJob},{$afterFailJob})") as $row) {
        $statuses[(int)$row['id']] = (string)$row['status'];
    }
    assert_runner($statuses[$normalJob] === 'completed', 'Normal job should complete');
    assert_runner($statuses[$secondJob] === 'completed', 'Second job should complete');
    assert_runner($statuses[$failJob] === 'failed', 'Failure job should fail');
    assert_runner($statuses[$afterFailJob] === 'completed', 'Job after failure should still complete');

    $rawForNormalJob = count_scalar($db, "SELECT COUNT(*) FROM raw_price_observations WHERE collector_job_id={$normalJob}");
    $evidenceForNormalJob = count_scalar($db, "SELECT COUNT(*) FROM market_evidence e JOIN raw_price_observations r ON r.id=e.raw_observation_id WHERE r.collector_job_id={$normalJob}");
    $extractionsForNormalJob = count_scalar($db, "SELECT COUNT(*) FROM extraction_runs x JOIN raw_price_observations r ON r.id=x.raw_observation_id WHERE r.collector_job_id={$normalJob}");
    $reviewsForNormalJob = count_scalar($db, "SELECT COUNT(*) FROM observation_review_decisions d JOIN raw_price_observations r ON r.id=d.raw_observation_id WHERE r.collector_job_id={$normalJob}");

    assert_runner($rawForNormalJob > 0, 'Candidate rows should preserve collector_job_id');
    assert_runner($rawForNormalJob === $evidenceForNormalJob, 'Evidence should relate to originating job candidates');
    assert_runner($rawForNormalJob === $extractionsForNormalJob, 'Extractions should relate to originating job candidates');
    assert_runner($reviewsForNormalJob >= $rawForNormalJob, 'Reviews should relate to originating job candidates');

    $beforeSecondRun = [
        'raw' => count_scalar($db, "SELECT COUNT(*) FROM raw_price_observations WHERE source_id={$sourceId}"),
        'evidence' => count_scalar($db, "SELECT COUNT(*) FROM market_evidence WHERE source_id={$sourceId}"),
        'extractions' => count_scalar($db, "SELECT COUNT(*) FROM extraction_runs x JOIN raw_price_observations r ON r.id=x.raw_observation_id WHERE r.source_id={$sourceId}"),
        'reviews' => count_scalar($db, "SELECT COUNT(*) FROM observation_review_decisions d JOIN raw_price_observations r ON r.id=d.raw_observation_id WHERE r.source_id={$sourceId}"),
    ];
    $secondRun = $runner->run(10);
    $afterSecondRun = [
        'raw' => count_scalar($db, "SELECT COUNT(*) FROM raw_price_observations WHERE source_id={$sourceId}"),
        'evidence' => count_scalar($db, "SELECT COUNT(*) FROM market_evidence WHERE source_id={$sourceId}"),
        'extractions' => count_scalar($db, "SELECT COUNT(*) FROM extraction_runs x JOIN raw_price_observations r ON r.id=x.raw_observation_id WHERE r.source_id={$sourceId}"),
        'reviews' => count_scalar($db, "SELECT COUNT(*) FROM observation_review_decisions d JOIN raw_price_observations r ON r.id=d.raw_observation_id WHERE r.source_id={$sourceId}"),
    ];

    assert_runner($secondRun['jobs_claimed'] === 0, 'Completed/failed jobs should not be reprocessed');
    assert_runner($beforeSecondRun === $afterSecondRun, 'Second run should not duplicate pipeline records');

    echo "Scheduled runner test passed\n";
    echo "Jobs completed: {$summary['jobs_completed']}\n";
    echo "Jobs failed: {$summary['jobs_failed']}\n";
    echo "Candidates created: {$summary['candidates_created']}\n";
} finally {
    $db->rollBack();
}
