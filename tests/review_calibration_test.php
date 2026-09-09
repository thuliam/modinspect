<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\AdminController;
use App\Core\Database;
use App\Services\Auth\AuthService;
use App\Services\Auth\AuthorizationService;
use App\Services\Review\ReviewCalibrationService;

function assert_calibration(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function calibration_session(int $userId): void
{
    $_SESSION[AuthService::SESSION_USER_ID] = $userId;
    $_SESSION[AuthService::SESSION_LOGIN_AT] = time();
    $_SESSION[AuthService::SESSION_LAST_SEEN_AT] = time();
}

function calibration_render(callable $callable): string
{
    http_response_code(200);
    ob_start();
    $callable();
    return (string)ob_get_clean();
}

function calibration_source(PDO $db, string $key, string $name, string $domain): int
{
    $stmt = $db->prepare(
        "INSERT INTO data_sources (source_key,name,domain,source_type,access_method,allowed_collection_method,risk_level,reliability_score,evidence_quality,freshness_expectation_days,is_active,is_paused)
         VALUES (:source_key,:name,:domain,'research','manual','manual','low',90,'high',14,1,0)"
    );
    $stmt->execute(['source_key' => $key, 'name' => $name, 'domain' => $domain]);
    return (int)$db->lastInsertId();
}

function calibration_product(PDO $db, string $suffix): int
{
    $categoryId = (int)$db->query("SELECT id FROM product_categories WHERE slug='cpu' LIMIT 1")->fetchColumn();
    $brandId = (int)$db->query("SELECT id FROM brands WHERE slug='amd' LIMIT 1")->fetchColumn();
    $stmt = $db->prepare(
        "INSERT INTO products (category_id,brand_id,model_name,slug,full_name,is_active)
         VALUES (:category_id,:brand_id,:model_name,:slug,:full_name,1)"
    );
    $stmt->execute([
        'category_id' => $categoryId,
        'brand_id' => $brandId,
        'model_name' => 'Review Calibration Fixture',
        'slug' => 'review-calibration-fixture-' . $suffix,
        'full_name' => 'AMD Review Calibration Fixture ' . $suffix,
    ]);
    return (int)$db->lastInsertId();
}

function calibration_observation(PDO $db, int $productId, int $sourceId, string $suffix, string $lane, string $status, array $flags = [], ?string $correctedField = null): int
{
    $raw = $db->prepare(
        "INSERT INTO raw_price_observations (source_id,external_reference_hash,raw_title,raw_price_text,captured_at,processing_status)
         VALUES (:source_id,:hash,:title,'7500',NOW(),'review')"
    );
    $raw->execute([
        'source_id' => $sourceId,
        'hash' => hash('sha256', 'review-calibration-' . $suffix),
        'title' => 'Review calibration fixture ' . $suffix,
    ]);
    $rawId = (int)$db->lastInsertId();
    $obs = $db->prepare(
        "INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,price_value,currency,condition_level,listing_type,evidence_level,classification_confidence,quality_flags,verified_status,observed_at)
         VALUES (:raw_id,:product_id,'asking',7500,7500,'THB','good','single_item',4,99,:flags,:status,NOW())"
    );
    $obs->execute([
        'raw_id' => $rawId,
        'product_id' => $productId,
        'flags' => $flags ? json_encode($flags) : null,
        'status' => $status,
    ]);
    $observationId = (int)$db->lastInsertId();
    $initial = $db->prepare(
        "INSERT INTO observation_review_decisions (price_observation_id,raw_observation_id,lane,decision,reason_codes,created_at)
         VALUES (:observation_id,:raw_id,:lane,'review_required',:reasons,NOW() - INTERVAL 2 HOUR)"
    );
    $initial->execute([
        'observation_id' => $observationId,
        'raw_id' => $rawId,
        'lane' => $lane,
        'reasons' => json_encode(['generic-validation-v1', 'cpu-validation-v1']),
    ]);
    if ($status !== 'pending') {
        $decision = $status === 'approved' ? 'approved' : 'rejected';
        $db->prepare(
            "INSERT INTO observation_review_decisions (price_observation_id,raw_observation_id,lane,decision,reason_codes,human_value,created_at)
             VALUES (:observation_id,:raw_id,:lane,:decision,:reasons,'{}',NOW())"
        )->execute([
            'observation_id' => $observationId,
            'raw_id' => $rawId,
            'lane' => $lane,
            'decision' => $decision,
            'reasons' => json_encode(['human_' . $status]),
        ]);
    }
    if ($correctedField !== null) {
        $db->prepare(
            "INSERT INTO review_corrections (price_observation_id,field_name,original_value,corrected_value,reason,corrected_at)
             VALUES (:observation_id,:field,'old','new','Calibration test correction',NOW())"
        )->execute(['observation_id' => $observationId, 'field' => $correctedField]);
    }
    return $observationId;
}

global $config;
$_SESSION = [];
$db = Database::connection($config['db']);
$db->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(4));
    $productId = calibration_product($db, $suffix);
    $mockSource = calibration_source($db, 'mock_review_calibration_' . $suffix, 'Mock Review Calibration', 'mock.review-calibration.test');
    $realSource = calibration_source($db, 'marketplace_rc_' . $suffix, 'Marketplace RC', 'marketplace.local');

    calibration_observation($db, $productId, $mockSource, $suffix . '-mock-green-clean', 'green', 'approved', ['WEAK_EVIDENCE']);
    calibration_observation($db, $productId, $mockSource, $suffix . '-mock-green-corrected', 'green', 'approved', ['MULTIPLE_PRICES'], 'price');
    calibration_observation($db, $productId, $mockSource, $suffix . '-mock-amber-rejected', 'amber', 'rejected', ['VARIANT_AMBIGUOUS']);
    calibration_observation($db, $productId, $mockSource, $suffix . '-mock-red-excluded', 'red', 'excluded', ['BUNDLE']);
    calibration_observation($db, $productId, $mockSource, $suffix . '-mock-amber-pending', 'amber', 'pending', ['WEAK_EVIDENCE']);

    calibration_observation($db, $productId, $realSource, $suffix . '-real-green-clean', 'green', 'approved');
    calibration_observation($db, $productId, $realSource, $suffix . '-real-green-rejected', 'green', 'rejected', ['WRONG_PRODUCT']);
    calibration_observation($db, $productId, $realSource, $suffix . '-real-amber-approved-corrected', 'amber', 'approved', ['WEAK_EVIDENCE'], 'condition_level');

    $service = new ReviewCalibrationService();
    $report = $service->report(['category' => 'cpu']);
    assert_calibration($report['sample_size'] >= 8, 'Report missing fixture observations');
    assert_calibration($report['lifecycle']['pending_reviews'] >= 1, 'Pending count missing');
    assert_calibration($report['lifecycle']['reviewed_count'] >= 7, 'Reviewed count missing');
    assert_calibration($report['lanes']['green']['approved_clean'] >= 2, 'Clean Green approvals missing');
    assert_calibration($report['lanes']['green']['approved_after_correction'] >= 1, 'Corrected Green approval missing');
    assert_calibration((float)$report['green_precision']['clean_green_precision'] < 100.0, 'Clean Green precision should separate corrected Green');
    assert_calibration(($report['corrected_fields']['price'] ?? 0) >= 1, 'Corrected price frequency missing');
    assert_calibration(($report['corrected_fields']['condition_level'] ?? 0) >= 1, 'Corrected condition frequency missing');
    assert_calibration(isset($report['quality_flags']['WEAK_EVIDENCE']), 'Quality flag frequency missing');
    assert_calibration(isset($report['quality_flags']['BUNDLE']), 'Bundle flag missing');
    assert_calibration($report['valid_observation_yield']['mock_test_excluded_from_live_kpi'] === true, 'Mock/test must be excluded from live KPI');
    assert_calibration($report['valid_observation_yield']['real_reviewed_candidates'] >= 3, 'Real reviewed denominator missing');
    assert_calibration($report['calibration_status'] === 'LIVE_SAMPLE_INSUFFICIENT', 'Mixed fixture with tiny real sample should not be ready');

    $mockOnly = $service->report(['source_id' => $mockSource]);
    assert_calibration($mockOnly['dataset_label'] === 'MOCK_TEST', 'Mock source must be labeled MOCK_TEST');
    assert_calibration($mockOnly['calibration_status'] === 'FIXTURE_BASELINE', 'Mock-only analytics cannot be READY');
    assert_calibration($mockOnly['valid_observation_yield']['yield_percent'] === null, 'Mock-only live yield must be null');

    $laneFilter = $service->report(['source_id' => $realSource, 'lane' => 'green']);
    assert_calibration($laneFilter['lanes']['amber']['total'] === 0, 'Lane filter leaked Amber data');
    assert_calibration($laneFilter['lanes']['green']['total'] === 2, 'Lane filter Green count mismatch');

    $auth = new AuthService();
    $reviewer = $auth->createUser('review-calibration-reviewer-' . $suffix . '@example.test', 'Calibration Reviewer', 'CorrectHorseBattery99', 'reviewer');
    $operator = $auth->createUser('review-calibration-operator-' . $suffix . '@example.test', 'Calibration Operator', 'CorrectHorseBattery99', 'operator');
    assert_calibration($reviewer['ok'] && $operator['ok'], 'Failed to create auth fixtures');
    $authz = new AuthorizationService();
    assert_calibration($authz->can(['role' => 'reviewer', 'is_active' => 1], 'review.analytics.view'), 'Reviewer should view analytics');
    assert_calibration($authz->can(['role' => 'operator', 'is_active' => 1], 'review.analytics.view'), 'Operator should view analytics read-only');

    calibration_session((int)$reviewer['user_id']);
    $html = calibration_render(fn() => (new AdminController())->reviewAnalytics());
    assert_calibration(http_response_code() === 200, 'Review analytics route should render for reviewer');
    assert_calibration(str_contains($html, 'Review Analytics'), 'Admin analytics view missing title');
    assert_calibration(str_contains($html, 'MOCK/TEST metrics are not live-market proof'), 'Admin analytics view missing fixture warning');

    echo "Review calibration test passed\n";
    echo "Sample size: {$report['sample_size']}\n";
    echo "Clean Green precision: " . $report['green_precision']['clean_green_precision'] . "%\n";
    echo "Correction rate: " . $report['lifecycle']['correction_rate'] . "%\n";
    echo "Calibration status: " . $report['calibration_status'] . "\n";
    echo "Mock-only status: " . $mockOnly['calibration_status'] . "\n";
} finally {
    $db->rollBack();
}
