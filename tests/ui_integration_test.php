<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\AdminController;
use App\Controllers\DealCheckerController;
use App\Controllers\HomeController;
use App\Controllers\ModuleController;
use App\Controllers\PriceController;
use App\Core\Database;
use App\Models\Dashboard;
use App\Services\Auth\AuthService;
use App\Services\Collection\SourceOperationsService;

function assert_ui(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function render_ui(callable $callable): string
{
    http_response_code(200);
    ob_start();
    $callable();
    return (string)ob_get_clean();
}

global $config;
$_SESSION = $_SESSION ?? [];
$db = Database::connection($config['db']);
$db->beginTransaction();

try {
    $productId = (int)$db->query("SELECT id FROM products WHERE slug='amd-ryzen-7-5700x3d'")->fetchColumn();
    $sourceId = (int)$db->query("SELECT id FROM data_sources WHERE source_key='mock_fixture_provider'")->fetchColumn();
    if ($sourceId <= 0) {
        $db->exec("INSERT INTO data_sources (source_key,name,domain,source_type,access_method,allowed_collection_method,risk_level,reliability_score,evidence_quality,freshness_expectation_days,is_active,is_paused) VALUES ('mock_fixture_provider','Mock Fixture Provider','mock.local','research','manual','manual','low',95,'high',14,1,0)");
        $sourceId = (int)$db->lastInsertId();
    }
    assert_ui($productId > 0 && $sourceId > 0, 'Missing UI integration fixture product/source');
    $auth = new AuthService();
    $admin = $auth->createUser('ui-admin-' . bin2hex(random_bytes(4)) . '@example.test', 'UI Admin', 'CorrectHorseBattery99', 'admin');
    assert_ui(($admin['ok'] ?? false) === true, 'Failed to create UI test admin');
    $_SESSION[AuthService::SESSION_USER_ID] = (int)$admin['user_id'];
    $_SESSION[AuthService::SESSION_LOGIN_AT] = time();
    $_SESSION[AuthService::SESSION_LAST_SEEN_AT] = time();

    $routes = [
        '/' => fn() => (new HomeController())->index(),
        '/price' => fn() => (new PriceController())->index(),
        '/price/amd-ryzen-7-5700x3d' => fn() => (new PriceController())->show('amd-ryzen-7-5700x3d'),
        '/deal-checker' => fn() => (new DealCheckerController())->form(),
        '/compare' => fn() => (new ModuleController())->compare(),
        '/build' => fn() => (new ModuleController())->build(),
        '/seller-price-advisor' => fn() => (new ModuleController())->sellerAdvisor(),
        '/methodology' => fn() => (new HomeController())->methodology(),
        '/local-match' => fn() => (new ModuleController())->localMatch(),
        '/market-report' => fn() => (new ModuleController())->marketReport(),
        '/seller' => fn() => (new ModuleController())->sellerDashboard(),
        '/shop' => fn() => (new ModuleController())->shop(),
        '/shop/inventory' => fn() => (new ModuleController())->inventory(),
        '/admin' => fn() => (new AdminController())->index(),
        '/admin/products' => fn() => (new AdminController())->products(),
        '/admin/product-aliases' => fn() => (new AdminController())->aliases(),
        '/admin/price-observations' => fn() => (new AdminController())->observations(),
        '/admin/review-queue' => fn() => (new AdminController())->review(),
        '/admin/review-analytics' => fn() => (new AdminController())->reviewAnalytics(),
        '/admin/price-indices' => fn() => (new AdminController())->indices(),
        '/admin/sources' => fn() => (new AdminController())->sources(),
        '/admin/collector-jobs' => fn() => (new AdminController())->jobs(),
        '/admin/articles' => fn() => (new AdminController())->articles(),
        '/admin/audit-logs' => fn() => (new AdminController())->audits(),
    ];

    foreach ($routes as $path => $handler) {
        $_GET = [];
        $_POST = [];
        $html = render_ui($handler);
        assert_ui(http_response_code() === 200, "Route {$path} did not return 200");
        assert_ui($html !== '' && str_contains($html, '<main>'), "Route {$path} did not render layout");
        assert_ui(!str_contains($html, 'href="#"'), "Route {$path} contains dead href");
    }

    $adminHtml = render_ui(fn() => (new AdminController())->index());
    $stats = (new Dashboard())->adminStats();
    assert_ui(str_contains($adminHtml, (string)$stats['cpu_products']), 'Admin dashboard missing real CPU count');
    assert_ui(str_contains($adminHtml, 'Gemini POC status'), 'Admin dashboard missing provider status');

    $failed = $db->prepare("INSERT INTO collector_jobs (source_id,job_type,product_id,query_text,status,attempt_count,error_message,created_at,updated_at) VALUES (:source_id,'coverage_mock_collection',:product_id,'ui-test','failed',1,'UI test failure',NOW(),NOW())");
    $failed->execute(['source_id' => $sourceId, 'product_id' => $productId]);
    $jobId = (int)$db->lastInsertId();
    $jobsHtml = render_ui(fn() => (new AdminController())->jobs());
    assert_ui(str_contains($jobsHtml, 'Requeue'), 'Collector jobs UI missing requeue action');
    $requeue = (new SourceOperationsService())->requeueFailedJob($jobId);
    assert_ui($requeue['requeued'] === true, 'Requeue service action failed');
    assert_ui((string)$db->query("SELECT status FROM collector_jobs WHERE id={$jobId}")->fetchColumn() === 'queued', 'Requeued job not queued');

    $ops = new SourceOperationsService();
    $pause = $ops->pauseSource($sourceId, 'UI integration pause', 'manually_paused');
    assert_ui($pause['status'] === 'paused', 'Source pause failed');
    $sourcesHtml = render_ui(fn() => (new AdminController())->sources());
    assert_ui(str_contains($sourcesHtml, 'Resume'), 'Sources UI missing resume action for paused source');
    $resume = $ops->resumeSource($sourceId, 'UI integration resume');
    assert_ui($resume['status'] === 'resumed', 'Source resume failed');

    $rawHash = hash('sha256', 'ui-integration|' . microtime(true));
    $db->prepare("INSERT INTO raw_price_observations (source_id,collector_job_id,external_reference_hash,raw_title,raw_price_text,captured_at,processing_status) VALUES (:source_id,:job_id,:hash,'MOCK UI Ryzen 7 5700X3D 7,500 บาท','7500',NOW(),'extracted')")
        ->execute(['source_id' => $sourceId, 'job_id' => $jobId, 'hash' => $rawHash]);
    $rawId = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO market_evidence (raw_observation_id,source_id,evidence_level,evidence_type,excerpt,content_hash,captured_at,is_public) VALUES (:raw_id,:source_id,'high','fixture','MOCK TEST DATA',:hash,NOW(),0)")
        ->execute(['raw_id' => $rawId, 'source_id' => $sourceId, 'hash' => hash('sha256', 'evidence'.$rawId)]);
    $db->prepare("INSERT INTO extraction_runs (raw_observation_id,provider_name,model_name,extracted_data,confidence,created_at) VALUES (:raw_id,'ui-test','fixture','{}',0.99,NOW())")
        ->execute(['raw_id' => $rawId]);
    $db->prepare("INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,price_value,currency,condition_level,listing_type,evidence_level,classification_confidence,verified_status,observed_at) VALUES (:raw_id,:product_id,'asking',7500,7500,'THB','good','single_item',3,0.99,'pending',NOW())")
        ->execute(['raw_id' => $rawId, 'product_id' => $productId]);
    $observationId = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO observation_review_decisions (price_observation_id,raw_observation_id,lane,decision,reason_codes) VALUES (:observation_id,:raw_id,'green','pending','[\"ui_test\"]')")
        ->execute(['observation_id' => $observationId, 'raw_id' => $rawId]);

    $reviewHtml = render_ui(fn() => (new AdminController())->review());
    assert_ui(str_contains($reviewHtml, 'MOCK TEST DATA'), 'Review UI must label mock/test data');
    assert_ui((new Dashboard())->decideObservation($observationId, 'approved', 'UI integration approval') === true, 'Review approval failed');
    assert_ui((string)$db->query("SELECT verified_status FROM price_observations WHERE id={$observationId}")->fetchColumn() === 'approved', 'Approved review did not update observation');

    $priceHtml = render_ui(fn() => (new PriceController())->show('amd-ryzen-7-5700x3d'));
    assert_ui(str_contains($priceHtml, 'ข้อมูลตลาดยังไม่เพียงพอ') || str_contains($priceHtml, 'ราคาตลาดโดยประมาณ'), 'Price detail missing valid snapshot or empty state');
    assert_ui(str_contains($priceHtml, 'กำลังพัฒนา'), 'Price detail missing disabled action labels');

    echo "UI integration test passed\n";
    echo "GET routes rendered: " . count($routes) . "\n";
    echo "Admin CPU count: {$stats['cpu_products']}\n";
    echo "Requeued job: {$jobId}\n";
    echo "Review observation approved: {$observationId}\n";
} finally {
    $db->rollBack();
}
