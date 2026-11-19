<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Controllers\AdminController;
use App\Core\Database;
use App\Services\Auth\AuthService;

/**
 * LS-14 J-0: a reviewer or operator can look at products and aliases but the pages must not offer buttons that end in a 403. The server still
 * refuses the write (tests/rbac_master_data_write_test.php); this test covers what the page shows. In-process, isolated test DB, rolled back.
 */
function ok_ro(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
function ro_page(callable $fn): string { http_response_code(200); ob_start(); try { $fn(); } catch (\Throwable $e) { ob_end_clean(); throw $e; } return (string)ob_get_clean(); }

global $config;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['REQUEST_URI'] = '/admin/products';
$db = Database::connection($config['db']);
ok_ro($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$db->beginTransaction();
try {
    $mk = static fn(string $role): int => (int)(new AuthService())->createUser("ro-$role-" . bin2hex(random_bytes(4)) . '@example.test', $role, 'CorrectHorseBattery99', $role)['user_id'];
    $as = static function (int $id): void { $_SESSION = [AuthService::SESSION_USER_ID => $id, AuthService::SESSION_LOGIN_AT => time(), AuthService::SESSION_LAST_SEEN_AT => time()]; };
    $admin = $mk('admin'); $reviewer = $mk('reviewer');
    $_GET = ['draw' => 1, 'start' => 0, 'length' => 25];

    foreach (['products' => ['page' => 'products', 'data' => 'productsData', 'form' => 'id="product-form"', 'add' => 'js-product-new', 'row' => 'js-product-edit'],
              'aliases' => ['page' => 'aliases', 'data' => 'aliasesData', 'form' => 'id="alias-form"', 'add' => 'Save Alias', 'row' => 'js-alias-edit']] as $name => $c) {
        $as($admin);
        $page = ro_page(fn() => (new AdminController())->{$c['page']}());
        ok_ro(str_contains($page, $c['form']), "An admin sees the $name form");
        $rows = json_decode(ro_page(fn() => (new AdminController())->{$c['data']}()), true);
        ok_ro(!empty($rows['data']) && str_contains(json_encode($rows['data']), $c['row']), "An admin gets edit buttons in the $name rows");

        $as($reviewer);
        $page = ro_page(fn() => (new AdminController())->{$c['page']}());
        ok_ro(!str_contains($page, $c['form']) && !str_contains($page, $c['add']) && !str_contains($page, '/save"'), "A reviewer sees no $name form and no add button");
        $rows = json_decode(ro_page(fn() => (new AdminController())->{$c['data']}()), true);
        $json = json_encode($rows['data']);
        ok_ro(!empty($rows['data']) && !str_contains($json, $c['row']) && !str_contains($json, '/toggle') && !str_contains($json, '/delete') && !str_contains($json, '_token'), "A reviewer gets the $name rows without any edit, toggle, delete button or token");
        ok_ro(count($rows['data'][0]) === count(json_decode(ro_page(fn() => (function () use ($as, $admin, $c) { $as($admin); (new AdminController())->{$c['data']}(); })()), true)['data'][0]), "The $name table keeps the same number of columns");
        $as($reviewer);
    }
} finally {
    $db->rollBack();
}
echo "admin_readonly_controls_test: PASS\n";
