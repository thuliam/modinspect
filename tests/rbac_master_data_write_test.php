<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Controllers\AdminController;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\RedirectException;
use App\Services\Auth\AuthService;
use App\Services\Auth\AuthorizationService;

/**
 * Master-data writes must need a WRITE permission, not the view permission (LS-3 J-1 finding).
 * Product save/toggle, alias save/delete and article save/archive change what the public site shows and how listings resolve to products:
 * reviewers and operators may look at them (products.view) but must not change them. The requests below carry a valid CSRF token and
 * deliberately empty or unknown ids, so even an allowed call writes nothing.
 */
function ok_md(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

function md_session(int $userId): void
{
    $_SESSION[AuthService::SESSION_USER_ID] = $userId;
    $_SESSION[AuthService::SESSION_LOGIN_AT] = time();
    $_SESSION[AuthService::SESSION_LAST_SEEN_AT] = time();
}

/** @return string 'blocked' (403), 'allowed' (redirect or normal output) */
function md_call(callable $handler, string $uri): string
{
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = $uri;
    $_POST = ['_token' => Csrf::token()];
    http_response_code(200);
    ob_start();
    try { $handler(); } catch (RedirectException $e) { ob_end_clean(); return 'allowed'; }
    ob_end_clean();
    return http_response_code() === 403 ? 'blocked' : 'allowed';
}

global $config;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SESSION = [];
$db = Database::connection($config['db']);
$db->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(4));
    $auth = new AuthService();
    $pw = 'CorrectHorseBattery99';
    $admin = $auth->createUser("md-admin-$suffix@example.test", 'MD Admin', $pw, 'admin');
    $reviewer = $auth->createUser("md-reviewer-$suffix@example.test", 'MD Reviewer', $pw, 'reviewer');
    $operator = $auth->createUser("md-operator-$suffix@example.test", 'MD Operator', $pw, 'operator');
    ok_md($admin['ok'] && $reviewer['ok'] && $operator['ok'], 'Fixture users are created');

    $users = [];
    foreach (['admin' => $admin, 'reviewer' => $reviewer, 'operator' => $operator] as $role => $u) {
        $users[$role] = $db->query('SELECT id,name,email,role,is_active FROM users WHERE id=' . (int)$u['user_id'])->fetch();
    }
    $authz = new AuthorizationService();
    ok_md($authz->can($users['admin'], 'products.manage'), 'An admin can manage master data');
    ok_md(!$authz->can($users['reviewer'], 'products.manage') && !$authz->can($users['operator'], 'products.manage'), 'Reviewers and operators cannot manage master data');
    ok_md($authz->can($users['reviewer'], 'products.view') && $authz->can($users['operator'], 'products.view'), 'Reviewers and operators can still view products');

    $writes = [
        'productSave' => '/admin/products/save', 'productToggle' => '/admin/products/toggle',
        'aliasSave' => '/admin/product-aliases/save', 'aliasDelete' => '/admin/product-aliases/delete',
        'articleSave' => '/admin/articles/save', 'articleArchive' => '/admin/articles/archive',
        'unresolvedAction' => '/admin/unresolved-listings/action',
    ];
    $before = [
        'products' => (int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn(),
        'aliases' => (int)$db->query('SELECT COUNT(*) FROM product_aliases')->fetchColumn(),
        'articles' => (int)$db->query('SELECT COUNT(*) FROM articles')->fetchColumn(),
    ];
    foreach ($writes as $method => $uri) {
        foreach (['reviewer', 'operator'] as $role) {
            md_session((int)$users[$role]['id']);
            $r = md_call(fn() => (new AdminController())->$method(), $uri);
            ok_md($r === 'blocked', "$role must be refused (403) on POST $uri, got $r");
        }
        md_session((int)$users['admin']['id']);
        $r = md_call(fn() => (new AdminController())->$method(), $uri);
        ok_md($r === 'allowed', "admin must not be refused on POST $uri, got $r");
    }
    $after = [
        'products' => (int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn(),
        'aliases' => (int)$db->query('SELECT COUNT(*) FROM product_aliases')->fetchColumn(),
        'articles' => (int)$db->query('SELECT COUNT(*) FROM articles')->fetchColumn(),
    ];
    ok_md($before === $after, 'The probes with empty ids wrote nothing: ' . json_encode([$before, $after]));
} finally {
    $db->rollBack();
}

echo "rbac_master_data_write_test: PASS\n";
