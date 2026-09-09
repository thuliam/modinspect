<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Contracts\HttpClientInterface;
use App\Core\Database;
use App\Data\SearchRequest;
use App\Services\Collection\LiveCollectionPocService;
use App\Services\Collection\LiveQueryPlanner;
use App\Services\Collection\ProviderRegistry;
use App\Services\Collection\ScheduledCollectionRunner;
use App\Services\Collection\SourceOperationsService;
use App\Services\Providers\GeminiSearchProvider;

function assert_live_infra(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class FixtureHttpClient implements HttpClientInterface
{
    public int $requests = 0;

    public function __construct(private int $status, private string $body)
    {
    }

    public function postJson(string $url, array $payload, array $headers = [], int $timeoutSeconds = 10): array
    {
        $this->requests++;
        return ['status' => $this->status, 'body' => $this->body];
    }
}

final class ThrowingHttpClient implements HttpClientInterface
{
    public int $requests = 0;

    public function postJson(string $url, array $payload, array $headers = [], int $timeoutSeconds = 10): array
    {
        $this->requests++;
        throw new RuntimeException('Provider request failed or timed out');
    }
}

global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();

try {
    $db->exec("INSERT IGNORE INTO data_sources (source_key,name,domain,source_type,access_method,allowed_collection_method,risk_level,reliability_score,evidence_quality,freshness_expectation_days,request_budget_per_day,is_active,is_paused) VALUES ('gemini_google_search','Gemini Google Search POC','googleapis.com','research','api','api','medium',60,'medium',7,3,1,0)");
    $geminiSourceId = (int)$db->query("SELECT id FROM data_sources WHERE source_key='gemini_google_search'")->fetchColumn();
    $productId = (int)$db->query("SELECT id FROM products WHERE slug='amd-ryzen-7-5700x3d'")->fetchColumn();
    $secondProductId = (int)$db->query("SELECT id FROM products WHERE slug='nvidia-geforce-rtx-3070'")->fetchColumn();
    assert_live_infra($productId > 0, 'Missing live infra fixture product');
    assert_live_infra($secondProductId > 0, 'Missing second live infra fixture product');

    $fixtureBody = (string)file_get_contents(ROOT_PATH . '/tests/fixtures/gemini_response.json');
    $http = new FixtureHttpClient(200, $fixtureBody);
    $provider = new GeminiSearchProvider('test-key', 'gemini-fixture', 'https://example.test/gemini', 1, $http);
    $result = $provider->search(new SearchRequest('AMD Ryzen 7 5700X3D มือสอง', $productId, 3));
    assert_live_infra($http->requests === 1, 'Fixture HTTP should be called exactly once');
    assert_live_infra($result->provider === 'gemini', 'Provider result should identify gemini');
    assert_live_infra(count($result->candidates) === 3, 'Gemini fixture should parse 3 candidates');
    assert_live_infra($result->candidates[0]->provider === 'gemini', 'Candidate provider metadata missing');
    assert_live_infra($result->candidates[0]->model === 'gemini-fixture', 'Candidate model metadata missing');
    assert_live_infra($result->candidates[0]->query === 'AMD Ryzen 7 5700X3D มือสอง', 'Candidate query metadata missing');

    $malformed = new GeminiSearchProvider('test-key', 'gemini-fixture', 'https://example.test/gemini', 1, new FixtureHttpClient(200, '{"bad":true}'));
    try {
        $malformed->search(new SearchRequest('5700x3d', $productId, 3));
        assert_live_infra(false, 'Malformed provider response should throw');
    } catch (RuntimeException $e) {
        assert_live_infra($e->getMessage() === 'MALFORMED_RESPONSE', 'Malformed response should map to MALFORMED_RESPONSE');
    }

    $timeoutHttp = new ThrowingHttpClient();
    $timeoutProvider = new GeminiSearchProvider('test-key', 'gemini-fixture', 'https://example.test/gemini', 1, $timeoutHttp);
    try {
        $timeoutProvider->search(new SearchRequest('5700x3d', $productId, 3));
        assert_live_infra(false, 'Timeout provider should throw');
    } catch (RuntimeException $e) {
        assert_live_infra(str_contains($e->getMessage(), 'timed out'), 'Timeout should preserve useful error text');
    }

    $baseConfig = $config['collection'];
    $baseConfig['providers']['gemini']['source_key'] = 'gemini_google_search';
    $baseConfig['providers']['gemini']['max_requests_per_run'] = 3;
    $baseConfig['providers']['gemini']['enabled'] = false;
    $baseConfig['providers']['gemini']['api_key'] = '';
    $baseConfig['live_collection_enabled'] = false;
    $baseConfig['paid_provider_calls_enabled'] = false;

    $registry = new ProviderRegistry($baseConfig);
    assert_live_infra($registry->provider('gemini') instanceof GeminiSearchProvider, 'Registry should load Gemini adapter');
    assert_live_infra($registry->sourceId('gemini') === $geminiSourceId, 'Registry should resolve Gemini source');

    $planner = new LiveQueryPlanner();
    $queries = $planner->queriesForProduct($productId, 3);
    assert_live_infra(count($queries) === 3, 'Query planner should produce 3 deterministic queries');
    assert_live_infra($queries[0] === 'AMD Ryzen 7 5700X3D มือสอง', 'Primary query should use canonical Thai used intent');

    $poc = new LiveCollectionPocService($baseConfig);
    $dry = $poc->dryRun('gemini', [$productId], 3);
    assert_live_infra($dry['network_requests_made'] === 0, 'Dry-run must make zero network requests');
    assert_live_infra($dry['live_execution_allowed'] === false, 'Default config should block live execution');
    assert_live_infra(in_array('LIVE_COLLECTION_DISABLED', $dry['blockers'], true), 'Missing live-disabled blocker');
    assert_live_infra(in_array('PROVIDER_DISABLED', $dry['blockers'], true), 'Missing provider-disabled blocker');
    assert_live_infra(in_array('MISSING_API_KEY', $dry['blockers'], true), 'Missing API-key blocker');

    $capConfig = $baseConfig;
    $capConfig['live_collection_enabled'] = true;
    $capConfig['providers']['gemini']['enabled'] = true;
    $capConfig['providers']['gemini']['api_key'] = 'present';
    $cap = (new LiveCollectionPocService($capConfig))->preflight('gemini', [$productId, $secondProductId], 4);
    assert_live_infra(in_array('REQUEST_LIMIT_REACHED', $cap['blockers'], true), 'Request cap blocker missing');

    (new SourceOperationsService())->pauseSource($geminiSourceId, 'Paused source guard test', 'manually_paused');
    $paused = (new LiveCollectionPocService($capConfig))->preflight('gemini', [$productId], 1);
    assert_live_infra(in_array('SOURCE_PAUSED', $paused['blockers'], true), 'Paused source blocker missing');
    $db->exec("UPDATE data_sources SET is_paused=0,pause_reason=NULL WHERE id={$geminiSourceId}");

    $stmt = $db->prepare("INSERT INTO collector_jobs (source_id,job_type,product_id,query_text,status,created_at,updated_at) VALUES (:source_id,'live_provider_poc',:product_id,'AMD Ryzen 7 5700X3D มือสอง','queued',NOW(),NOW())");
    $stmt->execute(['source_id' => $geminiSourceId, 'product_id' => $productId]);
    $jobId = (int)$db->lastInsertId();

    $fixtureRunner = new ScheduledCollectionRunner($provider, $geminiSourceId, 'live_provider_poc', 'gemini');
    $summary = $fixtureRunner->run(1, $jobId);
    assert_live_infra($summary['provider'] === 'gemini', 'Runner should persist provider name');
    assert_live_infra($summary['jobs_completed'] === 1, 'Fixture live job should complete');
    assert_live_infra($summary['candidates_created'] === 3, 'Fixture live job should create 3 candidates');
    assert_live_infra($summary['green'] >= 1 && $summary['amber'] >= 1 && $summary['red'] >= 1, 'Fixture live candidates should route to review lanes');
    $approved = (int)$db->query("SELECT COUNT(*) FROM price_observations o JOIN raw_price_observations r ON r.id=o.raw_observation_id WHERE r.collector_job_id={$jobId} AND o.verified_status='approved'")->fetchColumn();
    assert_live_infra($approved === 0, 'Live fixture pipeline must not auto-accept observations');

    $failStmt = $db->prepare("INSERT INTO collector_jobs (source_id,job_type,product_id,query_text,status,created_at,updated_at) VALUES (:source_id,'live_provider_poc',:product_id,'AMD Ryzen 7 5700X3D มือสอง','queued',NOW(),NOW())");
    $failStmt->execute(['source_id' => $geminiSourceId, 'product_id' => $productId]);
    $failJobId = (int)$db->lastInsertId();
    $badProvider = new GeminiSearchProvider('test-key', 'gemini-fixture', 'https://example.test/gemini', 1, new FixtureHttpClient(429, '{}'));
    $failSummary = (new ScheduledCollectionRunner($badProvider, $geminiSourceId, 'live_provider_poc', 'gemini'))->run(1, $failJobId);
    assert_live_infra($failSummary['jobs_failed'] === 1, 'Provider failure should fail job');
    $incidentCount = (int)$db->query("SELECT COUNT(*) FROM source_incidents WHERE source_id={$geminiSourceId} AND incident_type='rate_limited'")->fetchColumn();
    assert_live_infra($incidentCount >= 1, 'Provider failure should map to source incident');

    $timeoutStmt = $db->prepare("INSERT INTO collector_jobs (source_id,job_type,product_id,query_text,status,created_at,updated_at) VALUES (:source_id,'live_provider_poc',:product_id,'AMD Ryzen 7 5700X3D มือสอง','queued',NOW(),NOW())");
    $timeoutStmt->execute(['source_id' => $geminiSourceId, 'product_id' => $productId]);
    $timeoutJobId = (int)$db->lastInsertId();
    $timeoutRunnerProvider = new GeminiSearchProvider('test-key', 'gemini-fixture', 'https://example.test/gemini', 1, new ThrowingHttpClient());
    $timeoutSummary = (new ScheduledCollectionRunner($timeoutRunnerProvider, $geminiSourceId, 'live_provider_poc', 'gemini'))->run(1, $timeoutJobId);
    assert_live_infra($timeoutSummary['jobs_failed'] === 1, 'Provider timeout should fail job');
    $timeoutIncidentCount = (int)$db->query("SELECT COUNT(*) FROM source_incidents WHERE source_id={$geminiSourceId} AND incident_type='other'")->fetchColumn();
    assert_live_infra($timeoutIncidentCount >= 1, 'Provider timeout should map to source incident');

    echo "Live provider infrastructure test passed\n";
    echo "Gemini fixture candidates: " . count($result->candidates) . "\n";
    echo "Dry-run blockers: " . implode(',', $dry['blockers']) . "\n";
    echo "Fixture runner lanes: " . json_encode(['green' => $summary['green'], 'amber' => $summary['amber'], 'red' => $summary['red']]) . "\n";
    echo "Network requests in CLI dry-run path: {$dry['network_requests_made']}\n";
} finally {
    $db->rollBack();
}
