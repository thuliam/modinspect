<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Collection\ScheduledCollectionRunner;
use App\Services\Collection\SourceHealthService;
use App\Services\Collection\SourceOperationsService;
use App\Services\Providers\MockSearchProvider;

function assert_ops(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();

try {
    $sourceId = 9201;
    $db->exec("INSERT IGNORE INTO data_sources (id,source_key,name,domain,source_type,access_method,allowed_collection_method,risk_level,reliability_score,evidence_quality,freshness_expectation_days,is_active,is_paused,last_success_at) VALUES ({$sourceId},'ops_fixture','Ops fixture source','fixture.ops','research','manual','manual','low',95,'high',14,1,0,NOW())");
    $productId = (int)$db->query("SELECT id FROM products WHERE slug='amd-ryzen-7-5700x3d'")->fetchColumn();
    assert_ops($productId > 0, 'Missing operation fixture product');

    $failed = $db->prepare("INSERT INTO collector_jobs (source_id,job_type,product_id,query_text,status,attempt_count,run_id,started_at,completed_at,error_message,created_at,updated_at) VALUES (:source_id,'coverage_mock_collection',:product_id,'__fail__','failed',1,'prior-run',NOW(),NOW(),'Controlled failure',NOW(),NOW())");
    $failed->execute(['source_id' => $sourceId, 'product_id' => $productId]);
    $failedJobId = (int)$db->lastInsertId();

    $completed = $db->prepare("INSERT INTO collector_jobs (source_id,job_type,product_id,query_text,status,attempt_count,created_at,updated_at) VALUES (:source_id,'coverage_mock_collection',:product_id,'5700x3d','completed',1,NOW(),NOW())");
    $completed->execute(['source_id' => $sourceId, 'product_id' => $productId]);
    $completedJobId = (int)$db->lastInsertId();

    $ops = new SourceOperationsService();
    $dry = $ops->requeueFailedJob($failedJobId, true);
    assert_ops($dry['status'] === 'dry_run' && $dry['requeued'] === false, 'Dry-run requeue should not mutate');
    $stillFailed = (string)$db->query("SELECT status FROM collector_jobs WHERE id={$failedJobId}")->fetchColumn();
    assert_ops($stillFailed === 'failed', 'Dry-run requeue changed job status');

    $badRequeue = $ops->requeueFailedJob($completedJobId);
    assert_ops($badRequeue['requeued'] === false, 'Completed job must not requeue');
    assert_ops(in_array('only_failed_jobs_can_be_requeued', $badRequeue['errors'], true), 'Invalid requeue reason missing');

    $requeue = $ops->requeueFailedJob($failedJobId);
    assert_ops($requeue['requeued'] === true, 'Failed job should requeue explicitly');
    $requeued = $db->query("SELECT status,attempt_count,error_message,run_id FROM collector_jobs WHERE id={$failedJobId}")->fetch();
    assert_ops($requeued['status'] === 'queued', 'Requeued job should return to queued');
    assert_ops((int)$requeued['attempt_count'] === 1, 'Requeue should preserve attempt history');
    assert_ops($requeued['error_message'] === null && $requeued['run_id'] === null, 'Requeue should clear failure/run state');

    $runner = new ScheduledCollectionRunner(new MockSearchProvider(ROOT_PATH . '/tests/fixtures/market_listings.json'), $sourceId);
    $summary = $runner->run(1, $failedJobId);
    assert_ops($summary['jobs_claimed'] === 1 && $summary['jobs_failed'] === 1, 'Requeued controlled failure should run and fail again');
    $attemptedAgain = (int)$db->query("SELECT attempt_count FROM collector_jobs WHERE id={$failedJobId}")->fetchColumn();
    assert_ops($attemptedAgain === 2, 'Runner claim should increment attempt count');
    $runCount = (int)$db->query("SELECT COUNT(*) FROM collection_runs WHERE run_id='{$summary['run_id']}'")->fetchColumn();
    assert_ops($runCount === 1, 'Runner summary should persist to collection_runs');

    $incident = $ops->recordIncident($sourceId, 'blocked', 'Fixture source blocked collection in controlled test', 'paused');
    assert_ops($incident['incident_id'] > 0, 'Incident should be recorded');

    $pause = $ops->pauseSource($sourceId, 'Manual test pause', 'manually_paused');
    assert_ops($pause['status'] === 'paused', 'Source should pause');
    $source = $db->query("SELECT is_paused FROM data_sources WHERE id={$sourceId}")->fetch();
    assert_ops((int)$source['is_paused'] === 1, 'Source paused flag missing');

    $queued = $db->prepare("INSERT INTO collector_jobs (source_id,job_type,product_id,query_text,status,created_at,updated_at) VALUES (:source_id,'coverage_mock_collection',:product_id,'5700x3d','queued',NOW(),NOW())");
    $queued->execute(['source_id' => $sourceId, 'product_id' => $productId]);
    $pausedRun = $runner->run(5);
    assert_ops($pausedRun['source_status'] === 'paused_or_disabled', 'Paused source should block runner');
    assert_ops($pausedRun['jobs_claimed'] === 0, 'Paused source should not claim jobs');

    $health = (new SourceHealthService())->report($sourceId);
    $item = $health['items'][0] ?? null;
    assert_ops($item !== null, 'Health report missing source');
    assert_ops($item['status'] === 'paused', 'Health status should reflect paused source');
    assert_ops($item['jobs_attempted'] >= 2, 'Health report should count attempted jobs');
    assert_ops($item['jobs_failed'] >= 1, 'Health report should count failed jobs');

    echo "Collection operations test passed\n";
    echo "Requeued job: {$failedJobId}\n";
    echo "Persisted run: {$summary['run_id']}\n";
    echo "Health status: {$item['status']}\n";
} finally {
    $db->rollBack();
}
