<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Controllers\AdminController;
use App\Controllers\AdminCommunityController;
use App\Controllers\SellerController;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\RedirectException;
use App\Services\Auth\AuthService;
use App\Services\Listing\SellerListings;
use App\Services\Member\MemberAuth;

/**
 * LS-14 Module 9 through the controllers (in process, isolated test DB, rolled back): /seller needs a member; a member creates a listing through
 * the form handler; another member gets 404 on it; photos are refused to strangers; the public listing page is 404 while
 * SELLER_LISTINGS_PUBLIC is off; moderation is open to admin and reviewer, not operator; the CSRF token is required.
 */
function ok_sc(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
/** @return array{redirect:?string,code:int,body:string} */
function sc_call(callable $fn): array
{
    http_response_code(200); ob_start();
    try { $fn(); } catch (RedirectException $e) { ob_end_clean(); return ['redirect' => $e->path, 'code' => 302, 'body' => '']; }
    return ['redirect' => null, 'code' => http_response_code(), 'body' => (string)ob_get_clean()];
}

global $config;
$_SERVER['REMOTE_ADDR'] = '10.250.' . random_int(0, 255) . '.' . random_int(1, 254);   // a fresh rate-limit bucket
$db = Database::connection($config['db']);
ok_sc($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$config['seller']['listings_public'] = false;
$db->beginTransaction();
try {
    $_SESSION = [];
    ok_sc(sc_call(fn() => (new SellerController())->index())['redirect'] === '/account/login', '/seller sends a visitor without an account to the login page');

    $db->exec("INSERT INTO members (display_name, terms_accepted_at) VALUES ('Alice', NOW())"); $alice = (int)$db->lastInsertId();
    $db->exec("INSERT INTO members (display_name, terms_accepted_at) VALUES ('Bob', NOW())"); $bob = (int)$db->lastInsertId();
    $pid = (int)$db->query('SELECT id FROM products WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();

    $_SESSION = [MemberAuth::SESSION_MEMBER_ID => $alice];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_token' => 'forged', 'product_id' => (string)$pid, 'asking_price' => '5000'];
    ok_sc(sc_call(fn() => (new SellerController())->save())['redirect'] === '/seller' && count((new SellerListings($db))->forMember($alice)) === 0, 'A forged token creates nothing');
    $_POST = ['_token' => Csrf::token(), 'product_id' => (string)$pid, 'asking_price' => '5000', 'condition_percent' => '88', 'defect_note' => 'โทร 0812345678'];
    $r = sc_call(fn() => (new SellerController())->save());
    ok_sc($r['redirect'] === null && str_contains($r['body'], 'ห้ามใส่เบอร์โทร') && count((new SellerListings($db))->forMember($alice)) === 0, 'A phone number in free text is refused on the form and nothing is saved');
    $_POST['defect_note'] = 'รอยเล็กน้อยที่มุม';
    $r = sc_call(fn() => (new SellerController())->save());
    $mine = (new SellerListings($db))->forMember($alice);
    ok_sc(count($mine) === 1 && $r['redirect'] === '/seller/listings/' . $mine[0]['uuid'], 'The form handler creates the listing and opens it');
    $uuid = (string)$mine[0]['uuid'];
    $_SERVER['REQUEST_METHOD'] = 'GET'; $_POST = [];
    $page = sc_call(fn() => (new SellerController())->form($uuid));
    ok_sc($page['code'] === 200 && str_contains($page['body'], 'รูปสินค้า (0/10)') && str_contains($page['body'], 'ส่งให้ทีมงานตรวจ'), 'The owner sees the edit page with the photo section and the submit button');

    $_SESSION = [MemberAuth::SESSION_MEMBER_ID => $bob];
    ok_sc(sc_call(fn() => (new SellerController())->form($uuid))['code'] === 404, 'Another member gets 404 on someone else\'s listing');
    ok_sc(sc_call(fn() => (new SellerController())->photo($uuid, str_repeat('a', 24) . '.jpg'))['code'] === 404, 'Photos are refused to another member');
    $_SESSION = [];
    ok_sc(sc_call(fn() => (new SellerController())->show($uuid))['code'] === 404, 'The public listing page is 404 while public listings are off');

    // moderation: submit, then reviewer may moderate, operator may not
    $_SESSION = [MemberAuth::SESSION_MEMBER_ID => $alice];
    (new SellerListings($db))->act($alice, $uuid, 'submit');
    $mkUser = static function (string $role): int { $u = (new AuthService())->createUser("sc-$role-" . bin2hex(random_bytes(4)) . '@example.test', $role, 'CorrectHorseBattery99', $role); return (int)$u['user_id']; };
    $asUser = static function (int $id): void { $_SESSION = [AuthService::SESSION_USER_ID => $id, AuthService::SESSION_LOGIN_AT => time(), AuthService::SESSION_LAST_SEEN_AT => time()]; };
    $lid = (int)$mine[0]['id'];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $asUser($mkUser('operator'));
    $_POST = ['_token' => Csrf::token(), 'id' => (string)$lid, 'decision' => 'approved'];
    ok_sc(sc_call(fn() => (new AdminCommunityController())->sellerListingAction())['code'] === 403 && (new SellerListings($db))->find($uuid)['status'] === 'submitted', 'An operator cannot moderate listings');
    $asUser($mkUser('reviewer'));
    $_POST = ['_token' => Csrf::token(), 'id' => (string)$lid, 'decision' => 'approved'];
    ok_sc(sc_call(fn() => (new AdminCommunityController())->sellerListingAction())['redirect'] === '/admin/seller-listings' && (new SellerListings($db))->find($uuid)['status'] === 'approved', 'A reviewer can approve a submitted listing');

    $config['seller']['listings_public'] = true; $_SESSION = [];
    $pub = sc_call(fn() => (new SellerController())->show($uuid));
    ok_sc($pub['code'] === 200 && str_contains($pub['body'], 'ผู้ขายประเมินเอง') && !str_contains($pub['body'], 'Alice'), 'With public listings on, an approved listing is shown without the seller\'s name');
    $config['seller']['listings_public'] = false;
} finally {
    $db->rollBack();
    $config['seller']['listings_public'] = false;
}
echo "seller_controller_test: PASS\n";
