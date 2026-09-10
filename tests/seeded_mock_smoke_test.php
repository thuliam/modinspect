<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Models\CollectionPipeline;
use App\Services\Collection\CoveragePlanner;
use App\Services\Collection\ScheduledCollectionRunner;
use App\Services\Providers\MockSearchProvider;

function assert_seeded_smoke(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();

try {
    $sourceId = (new CollectionPipeline())->ensureMockSource();
    $db->exec("UPDATE data_sources SET is_active=1,is_paused=0,pause_reason=NULL,disabled_reason=NULL WHERE id={$sourceId}");
    $db->exec("UPDATE collector_jobs SET status='completed',created_at=DATE_SUB(NOW(), INTERVAL 2 HOUR),completed_at=DATE_SUB(NOW(), INTERVAL 2 HOUR) WHERE source_id={$sourceId} AND job_type='coverage_mock_collection' AND status IN ('queued','running','completed')");
    $db->exec("DELETE r FROM raw_price_observations r WHERE r.source_id={$sourceId}");

    $planner = new CoveragePlanner($sourceId);
    $plan = $planner->plan();
    $jobs = $planner->createJobs($plan, 3);
    assert_seeded_smoke(count($jobs['created']) === 3, 'Expected planner to create 3 seeded smoke jobs');

    $runner = new ScheduledCollectionRunner(new MockSearchProvider(ROOT_PATH . '/tests/fixtures/market_listings.json'), $sourceId);
    $summary = $runner->run(3);

    assert_seeded_smoke($summary['jobs_claimed'] === 3, 'Runner should claim 3 seeded smoke jobs');
    assert_seeded_smoke($summary['jobs_completed'] === 3, 'Runner should complete 3 seeded smoke jobs');
    assert_seeded_smoke($summary['candidates_created'] >= 9, 'Seeded smoke should create non-zero candidates');
    assert_seeded_smoke($summary['evidence_created'] === $summary['candidates_created'], 'Evidence count should match created candidates');
    assert_seeded_smoke($summary['extractions_created'] === $summary['candidates_created'], 'Extraction count should match created candidates');
    assert_seeded_smoke($summary['green'] >= 3, 'Expected green fixture records');
    assert_seeded_smoke($summary['amber'] >= 3, 'Expected amber fixture records');
    assert_seeded_smoke($summary['red'] >= 3, 'Expected red fixture records');

    $again = $planner->createJobs($planner->plan(), 3);
    $firstProductIds = array_map(static fn(array $job): int => (int)$job['product_id'], $jobs['created']);
    foreach ($again['created'] as $created) {
        assert_seeded_smoke(!in_array((int)$created['product_id'], $firstProductIds, true), 'Immediate repeated planning should not duplicate just-run product jobs');
    }

    $runCount = (int)$db->query("SELECT COUNT(*) FROM collection_runs WHERE run_id='{$summary['run_id']}'")->fetchColumn();
    assert_seeded_smoke($runCount === 1, 'Seeded smoke runner summary should persist');

    echo "Seeded mock smoke test passed\n";
    echo "Products/jobs: " . count($jobs['created']) . "\n";
    echo "Candidates: {$summary['candidates_created']}\n";
    echo "Evidence: {$summary['evidence_created']}\n";
    echo "Extractions: {$summary['extractions_created']}\n";
    echo "Lanes: " . json_encode(['green' => $summary['green'], 'amber' => $summary['amber'], 'red' => $summary['red']]) . "\n";
} finally {
    $db->rollBack();
}
