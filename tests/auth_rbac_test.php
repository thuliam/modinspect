<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\RedirectException;
use App\Models\Dashboard;
use App\Services\Auth\AuthService;
use App\Services\Auth\AuthorizationService;

function assert_auth(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function auth_session(int $userId): void
{
    $_SESSION[AuthService::SESSION_USER_ID] = $userId;
    $_SESSION[AuthService::SESSION_LOGIN_AT] = time();
    $_SESSION[AuthService::SESSION_LAST_SEEN_AT] = time();
}

function expect_redirect(callable $callable, string $path): void
{
    try {
        $callable();
    } catch (RedirectException $e) {
        assert_auth($e->path === $path, 'Expected redirect to ' . $path . ', got ' . $e->path);
        return;
    }
    throw new RuntimeException('Expected redirect to ' . $path);
}

function render_auth(callable $callable): string
{
    http_response_code(200);
    ob_start();
    try {
        $callable();
    } catch (RedirectException $e) {
        ob_end_clean();
        throw $e;
    }
    return (string)ob_get_clean();
}

function auth_fixture_observation(PDO $db, int $sourceId, int $productId, string $suffix): int
{
    $raw = $db->prepare(
        "INSERT INTO raw_price_observations (source_id,external_reference_hash,raw_title,raw_price_text,source_url_encrypted,captured_at,processing_status)
         VALUES (:source_id,:hash,'Auth RBAC Ryzen 7 5700X3D','7500',:url,NOW(),'review')"
    );
    $raw->execute([
        'source_id' => $sourceId,
        'hash' => hash('sha256', 'auth-rbac-' . $suffix),
        'url' => 'https://example.test/auth-rbac/' . $suffix,
    ]);
    $rawId = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO price_observations (raw_observation_id,product_id,price_type,asking_price,price_value,currency,condition_level,listing_type,evidence_level,classification_confidence,verified_status,observed_at) VALUES (:raw_id,:product_id,'asking',7500,7500,'THB','good','single_item',4,99,'pending',NOW())")
        ->execute(['raw_id' => $rawId, 'product_id' => $productId]);
    $observationId = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO observation_review_decisions (price_observation_id,raw_observation_id,lane,decision,reason_codes) VALUES (:observation_id,:raw_id,'green','review_required','[\"auth_rbac\"]')")
        ->execute(['observation_id' => $observationId, 'raw_id' => $rawId]);
    return $observationId;
}

global $config;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/admin';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SESSION = [];

$db = Database::connection($config['db']);
$db->beginTransaction();

try {
    $auth = new AuthService();
    $suffix = bin2hex(random_bytes(4));
    $password = 'CorrectHorseBattery99';
    $admin = $auth->createUser("admin-{$suffix}@example.test", 'Auth Admin', $password, 'admin');
    $reviewer = $auth->createUser("reviewer-{$suffix}@example.test", 'Auth Reviewer', $password, 'reviewer');
    $operator = $auth->createUser("operator-{$suffix}@example.test", 'Auth Operator', $password, 'operator');
    $disabled = $auth->createUser("disabled-{$suffix}@example.test", 'Disabled User', $password, 'admin', false);
    assert_auth($admin['ok'] && $reviewer['ok'] && $operator['ok'] && $disabled['ok'], 'Auth fixture users should be created');

    $hash = (string)$db->query("SELECT password_hash FROM users WHERE id={$admin['user_id']}")->fetchColumn();
    assert_auth($hash !== $password && password_verify($password, $hash), 'Password must be securely hashed');

    expect_redirect(fn() => (new AdminController())->index(), '/login');
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/admin/review-queue/decision';
    expect_redirect(fn() => (new AdminController())->reviewDecision(), '/login');

    $login = $auth->login("admin-{$suffix}@example.test", $password, '127.0.0.1');
    assert_auth(($login['ok'] ?? false) === true && (int)$_SESSION[AuthService::SESSION_USER_ID] === (int)$admin['user_id'], 'Admin login should succeed');
    assert_auth((new AuthService())->currentUser()['email'] === "admin-{$suffix}@example.test", 'Current user should load without password hash');
    assert_auth(!array_key_exists('password_hash', (new AuthService())->currentUser()), 'Password hash must not be exposed by currentUser');
    $auth->logout(true);
    assert_auth((new AuthService())->currentUser() === null, 'Logout must invalidate access');

    assert_auth($auth->login("admin-{$suffix}@example.test", 'wrong password', '127.0.0.1')['error'] === 'INVALID_CREDENTIALS', 'Invalid password must be rejected');
    assert_auth($auth->login("unknown-{$suffix}@example.test", 'wrong password', '127.0.0.1')['error'] === 'INVALID_CREDENTIALS', 'Unknown account must use generic error');
    assert_auth($auth->login("disabled-{$suffix}@example.test", $password, '127.0.0.1')['error'] === 'INVALID_CREDENTIALS', 'Disabled account cannot login');
    for ($i = 0; $i < 5; $i++) {
        $auth->login("throttle-{$suffix}@example.test", 'wrong password', '10.0.0.1');
    }
    assert_auth($auth->login("throttle-{$suffix}@example.test", 'wrong password', '10.0.0.1')['error'] === 'TOO_MANY_ATTEMPTS', 'Repeated login attempts should be throttled');

    $authz = new AuthorizationService();
    $adminUser = $db->query("SELECT id,name,email,role,is_active FROM users WHERE id={$admin['user_id']}")->fetch();
    $reviewerUser = $db->query("SELECT id,name,email,role,is_active FROM users WHERE id={$reviewer['user_id']}")->fetch();
    $operatorUser = $db->query("SELECT id,name,email,role,is_active FROM users WHERE id={$operator['user_id']}")->fetch();
    assert_auth($authz->can($adminUser, 'sources.control'), 'ADMIN should have full access');
    assert_auth($authz->can($reviewerUser, 'review.decide'), 'REVIEWER should decide reviews');
    assert_auth(!$authz->can($reviewerUser, 'sources.control'), 'REVIEWER must not control sources');
    assert_auth($authz->can($operatorUser, 'sources.control'), 'OPERATOR should control sources');
    assert_auth(!$authz->can($operatorUser, 'review.decide'), 'OPERATOR must not approve observations');

    auth_session((int)$admin['user_id']);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/admin';
    assert_auth(str_contains(render_auth(fn() => (new AdminController())->index()), 'Auth Admin'), 'ADMIN dashboard should render authenticated identity');

    auth_session((int)$reviewer['user_id']);
    assert_auth(str_contains(render_auth(fn() => (new AdminController())->review()), 'HUMAN REVIEW'), 'REVIEWER should view review queue');
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/admin/sources/action';
    $_POST = ['_token' => Csrf::token(), 'source_id' => 1, 'action' => 'pause', 'reason' => 'denied'];
    render_auth(fn() => (new AdminController())->sourceAction());
    assert_auth(http_response_code() === 403, 'Direct unauthorized REVIEWER source POST must be rejected');

    auth_session((int)$operator['user_id']);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/admin/collector-jobs';
    assert_auth(str_contains(render_auth(fn() => (new AdminController())->jobs()), 'COLLECTOR JOBS'), 'OPERATOR should view jobs');
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/admin/review-queue/decision';
    $_POST = ['_token' => Csrf::token(), 'observation_id' => 1, 'decision' => 'approved'];
    render_auth(fn() => (new AdminController())->reviewDecision());
    assert_auth(http_response_code() === 403, 'Direct unauthorized OPERATOR review POST must be rejected');

    auth_session((int)$admin['user_id']);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/admin/review-queue/decision';
    $_POST = ['_token' => 'bad-token', 'observation_id' => 1, 'decision' => 'approved'];
    render_auth(fn() => (new AdminController())->reviewDecision());
    assert_auth(http_response_code() === 419, 'CSRF failure must be rejected');

    $sourceId = (int)$db->query("SELECT id FROM data_sources WHERE source_key='mock_fixture_provider' LIMIT 1")->fetchColumn();
    $productId = (int)$db->query("SELECT id FROM products WHERE slug='amd-ryzen-7-5700x3d' LIMIT 1")->fetchColumn();
    $observationId = auth_fixture_observation($db, $sourceId, $productId, $suffix);
    auth_session((int)$reviewer['user_id']);
    assert_auth((new Dashboard())->correctObservation($observationId, 'price', '7600', 'Authenticated correction', (int)$reviewer['user_id'])['ok'] === true, 'Authenticated correction should save');
    assert_auth((int)$db->query("SELECT corrected_by FROM review_corrections WHERE price_observation_id={$observationId}")->fetchColumn() === (int)$reviewer['user_id'], 'Correction actor identity missing');
    assert_auth((new Dashboard())->decideObservation($observationId, 'approved', 'Authenticated approval', (int)$reviewer['user_id']) === true, 'Authenticated reviewer approval should succeed');
    assert_auth((int)$db->query("SELECT user_id FROM audit_logs WHERE entity_type='price_observation' AND entity_id={$observationId} AND action='review_approved' ORDER BY id DESC LIMIT 1")->fetchColumn() === (int)$reviewer['user_id'], 'Review audit actor identity missing');

    echo "Auth RBAC test passed\n";
    echo "Admin/reviewer/operator boundaries: ok\n";
    echo "Login throttling: ok\n";
    echo "Actor lineage: ok\n";
} finally {
    $db->rollBack();
}
