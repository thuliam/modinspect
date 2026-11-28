<?php
declare(strict_types=1);

/**
 * LS-90 part 2 (D1-D6): seller profiles and verification, the life of a listing, browsing, saving and reporting, the moderation queue,
 * the content moderator capability and the log-only notifications. Closed switch means 404 and no write. Isolated test DB, rolled back.
 */
define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Controllers\AdminController;
use App\Controllers\AdminModerationController;
use App\Controllers\MarketController;
use App\Controllers\SellerController;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\RedirectException;
use App\Services\Admin\ModerationQueue;
use App\Services\Auth\AuthorizationService;
use App\Services\Auth\AuthService;
use App\Services\Auth\UserCapabilities;
use App\Services\Listing\SellerListings;
use App\Services\Market\ListingBrowse;
use App\Services\Market\ListingLifecycle;
use App\Services\Market\NotificationQueue;
use App\Services\Market\SellerProfiles;
use App\Services\Member\MemberAuth;

function ok_mk(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
/** @return array{redirect:?string,code:int,body:string} */
function mk_call(callable $fn, string $method = 'GET', array $post = [], array $get = []): array
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = '/listings';
    $_SERVER['REMOTE_ADDR'] = '10.60.' . random_int(0, 255) . '.' . random_int(1, 254);
    $_POST = $method === 'POST' ? $post + ['_token' => Csrf::token()] : [];
    $_GET = $get;
    http_response_code(200);
    ob_start();
    try { $fn(); } catch (RedirectException $e) { ob_end_clean(); return ['redirect' => $e->path, 'code' => 302, 'body' => '']; }
    return ['redirect' => null, 'code' => http_response_code(), 'body' => (string)ob_get_clean()];
}

global $config;
ok_mk($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$db = Database::connection($config['db']);
ok_mk(($config['seller']['listings_public'] ?? null) === false, 'public listings are off unless configured');
$tables = ['seller_profiles', 'seller_verification_events', 'listing_status_events', 'saved_listings', 'saved_searches', 'listing_reports', 'notification_queue', 'user_capabilities', 'seller_listings'];
$counts = static function () use ($db, $tables): array { $out = []; foreach ($tables as $t) { $out[$t] = (int)$db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn(); } return $out; };
$db->beginTransaction();
try {
    $_SESSION = [];
    $member = static function (string $name) use ($db): int { $db->prepare('INSERT INTO members (display_name, terms_accepted_at) VALUES (?, NOW())')->execute([$name]); return (int)$db->lastInsertId(); };
    $alice = $member('Alice');
    $bob = $member('Bob');
    $auth = new AuthService();
    $user = static fn(string $role): int => (int)$auth->createUser("mk-$role-" . bin2hex(random_bytes(4)) . '@example.test', "MK $role", bin2hex(random_bytes(12)), $role)['user_id'];
    $admin = $user('admin');
    $operator = $user('operator');
    $pid = (int)$db->query('SELECT id FROM products WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
    $productName = (string)$db->query("SELECT full_name FROM products WHERE id = $pid")->fetchColumn();
    $svc = new SellerListings($db);
    $browse = new ListingBrowse($db);
    $profiles = new SellerProfiles($db);
    $uuid = $svc->save($alice, null, $pid, '5000', ['quantity' => '3', 'province' => 'ขอนแก่น'])['uuid'];
    $listing = static fn(): array => $svc->find($uuid);

    // ---- switch off: 404 and nothing written ----
    $_SESSION = [MemberAuth::SESSION_MEMBER_ID => $bob];
    $before = $counts();
    foreach ([
        'browse' => [fn() => (new MarketController())->browse(), 'GET'],
        'saved' => [fn() => (new MarketController())->saved(), 'GET'],
        'save' => [fn() => (new MarketController())->toggleSaved($uuid), 'POST'],
        'report' => [fn() => (new MarketController())->report($uuid), 'POST'],
        'search save' => [fn() => (new MarketController())->saveSearch(), 'POST'],
        'search delete' => [fn() => (new MarketController())->deleteSearch('1'), 'POST'],
        'profile' => [fn() => (new SellerController())->profile(), 'GET'],
        'profile save' => [fn() => (new SellerController())->saveProfile(), 'POST'],
        'verify' => [fn() => (new SellerController())->verifyProfile(), 'POST'],
        'sale' => [fn() => (new SellerController())->sale($uuid), 'POST'],
        'show' => [fn() => (new SellerController())->show($uuid), 'GET'],
    ] as $name => [$action, $method]) {
        $r = mk_call($action, $method, ['reason' => 'suspicious', 'display_name' => 'Shop', 'province' => 'ขอนแก่น', 'to' => 'sold', 'category' => 'cpu']);
        ok_mk($r['code'] === 404 && $r['redirect'] === null, "closed switch: $name answers 404, got {$r['code']} {$r['redirect']}");
    }
    ok_mk($counts() === $before, 'closed switch writes nothing');
    $home = mk_call(fn() => (new SellerController())->index());
    $linkPattern = '~<a\b[^>]*href="[^"]*(?:(?<!seller)/listings"|/seller/profile")[^>]*>[^<]*~';
    ok_mk(preg_match($linkPattern, '<a class="x" href="/base/listings">y') === 1 && preg_match($linkPattern, '<a href="/seller/profile">') === 1 && preg_match($linkPattern, '<a href="/seller/listings/new">') === 0, 'the link check itself works');
    preg_match_all($linkPattern, $home['body'], $closed);
    ok_mk($closed[0] === [], 'no links to the closed pages: ' . implode(' | ', $closed[0]));

    // ---- D1 profiles ----
    foreach (['โทร 0812345678', 'แอด @myshop', 'ดู https://example.com', 'mail me a@b.co'] as $bad) {
        ok_mk(isset($profiles->save($alice, ['display_name' => $bad])['errors']['display_name']), "contact details refused in the name: $bad");
        ok_mk(isset($profiles->save($alice, ['display_name' => 'ร้านทดสอบ', 'about' => $bad])['errors']['about']), "contact details refused in the text: $bad");
    }
    ok_mk(isset($profiles->save($alice, ['display_name' => 'ร้านทดสอบ', 'province' => 'Atlantis'])['errors']['province']), 'unknown province refused');
    ok_mk($profiles->get($alice) === null && $profiles->requestVerification($alice)['error'] === 'NO_PROFILE', 'nothing saved yet');
    ok_mk($profiles->save($alice, ['display_name' => 'ร้านอลิซ', 'province' => 'ขอนแก่น', 'about' => 'ขายของใช้เอง'])['ok'] && $profiles->get($alice)['verification'] === 'unverified', 'saved');
    ok_mk($profiles->decide($admin, $alice, 'verify')['error'] === 'BAD_STATE', 'cannot verify what was not requested');
    ok_mk($profiles->requestVerification($alice)['ok'] && count($profiles->waiting()) >= 1, 'requested');
    ok_mk($profiles->decide($admin, $alice, 'refuse')['error'] === 'NOTE_REQUIRED' && $profiles->decide($admin, $alice, 'verify')['ok'] && $profiles->get($alice)['verification'] === 'verified', 'verified by a person');
    ok_mk($profiles->save($alice, ['display_name' => 'ร้านอลิซ', 'province' => 'ขอนแก่น', 'about' => 'ขายของใช้เอง'])['ok'] && $profiles->get($alice)['verification'] === 'verified', 'saving the same profile keeps it');
    $profiles->save($alice, ['display_name' => 'ร้านใหม่', 'province' => 'ขอนแก่น']);
    ok_mk($profiles->get($alice)['verification'] === 'unverified' && $profiles->get($alice)['verified_by'] === null, 'a changed profile loses the verification');
    $profiles->requestVerification($alice);
    $profiles->decide($admin, $alice, 'verify');
    ok_mk(array_column($profiles->events($alice), 'to_state') === ['pending', 'verified', 'unverified', 'pending', 'verified'], 'history: ' . json_encode(array_column($profiles->events($alice), 'to_state')));
    ok_mk((int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE user_id = $admin AND action = 'seller_verification_verified'")->fetchColumn() === 2, 'audited');

    // ---- D2 lifecycle, never market evidence ----
    ok_mk((int)$listing()['quantity'] === 3 && isset($svc->save($alice, $uuid, $pid, '5000', ['quantity' => '0'])['errors']['quantity']), 'quantity 1 to 999');
    $evidence = static fn(): array => [(int)$db->query('SELECT COUNT(*) FROM price_observations')->fetchColumn(), (int)$db->query('SELECT COUNT(*) FROM raw_price_observations')->fetchColumn(), (int)$db->query('SELECT COUNT(*) FROM price_indices')->fetchColumn()];
    $prices = $evidence();
    $life = new ListingLifecycle($db, 60);
    ok_mk($life->changeSale($listing(), 'sold')['error'] === 'BAD_STATE', 'a draft cannot be sold');
    $svc->act($alice, $uuid, 'submit');
    $svc->moderate($admin, (int)$listing()['id'], 'approved');
    $l = $listing();
    ok_mk($l['sale_state'] === 'available' && abs(strtotime((string)$l['expires_at']) - strtotime('+60 days')) < 3600, 'approval starts the clock');
    ok_mk($life->renew($l)['error'] === 'TOO_EARLY', 'too early to renew');
    ok_mk($life->changeSale($l, 'reserved')['ok'] && $svc->publicListing($uuid, true) !== null && $browse->one($uuid)['sale_state'] === 'reserved', 'reserved stays visible');
    ok_mk($life->changeSale($listing(), 'available')['ok'], 'available again');
    $db->exec("UPDATE seller_listings SET expires_at = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id = " . (int)$l['id']);
    ok_mk($svc->publicListing($uuid, true) === null && $browse->one($uuid) === null, 'expired is not shown');
    ok_mk($life->renew($listing())['ok'] && $browse->one($uuid) !== null, 'renewed');

    // ---- D3 browse, save, report ----
    $config['seller']['listings_public'] = true;
    ok_mk(ListingBrowse::filters(['q' => str_repeat('x', 200), 'category' => 'cpu;drop', 'province' => 'Atlantis', 'max_price' => '-1', 'verified' => 'yes', 'sort' => 'evil', 'extra' => '1']) === ['q' => str_repeat('x', 80)], 'only usable filters survive');
    $found = $browse->search(ListingBrowse::filters(['q' => $productName, 'province' => 'ขอนแก่น', 'max_price' => '5000', 'verified' => '1']));
    ok_mk($found['total'] === 1 && $found['rows'][0]['uuid'] === $uuid && $found['rows'][0]['verification'] === 'verified', 'found by name, province, price and verification');
    ok_mk($browse->search(['max_price' => 4999, 'q' => $productName])['total'] === 0 && $browse->search(['q' => '%'])['total'] === 0, 'price limit and literal wildcard');
    $page = mk_call(fn() => (new MarketController())->browse(), 'GET', [], ['q' => $productName]);
    ok_mk($page['code'] === 200 && str_contains($page['body'], $uuid) && str_contains($page['body'], 'ผู้ขายยืนยันแล้ว') && str_contains($page['body'], 'ไม่ใช่ราคาตลาด'), 'the browse page');
    $_SESSION = [];
    ok_mk(mk_call(fn() => (new MarketController())->toggleSaved($uuid), 'POST')['redirect'] === '/account/login', 'saving needs a member');
    $_SESSION = [MemberAuth::SESSION_MEMBER_ID => $bob];
    ok_mk(mk_call(fn() => (new MarketController())->toggleSaved($uuid), 'POST', ['_token' => 'forged'])['code'] === 419 && !$browse->isSaved($bob, (int)$l['id']), 'a forged token saves nothing');
    mk_call(fn() => (new MarketController())->toggleSaved($uuid), 'POST');
    ok_mk($browse->isSaved($bob, (int)$l['id']) && count($browse->saved($bob)) === 1, 'saved');
    mk_call(fn() => (new MarketController())->saveSearch(), 'POST', ['category' => 'cpu', 'max_price' => '9000']);
    mk_call(fn() => (new MarketController())->saveSearch(), 'POST', ['max_price' => '9000', 'category' => 'cpu', 'sort' => 'price_asc']);
    ok_mk(count($browse->searches($bob)) === 1 && $browse->saveSearch($bob, [])['error'] === 'EMPTY', 'the same search is stored once, an empty one never');
    ok_mk(!$browse->deleteSearch($alice, $browse->searches($bob)[0]['id']), 'only the owner deletes a search');
    $show = mk_call(fn() => (new SellerController())->show($uuid));
    ok_mk($show['code'] === 200 && str_contains($show['body'], 'รายงานประกาศนี้') && str_contains($show['body'], 'ร้านใหม่'), 'the listing page offers save and report');
    ok_mk($browse->report($alice, $uuid, 'suspicious')['error'] === 'OWN_LISTING' && $browse->report($bob, $uuid, 'because')['error'] === 'BAD_REASON', 'own listing and unknown reason refused');
    ok_mk($browse->report($bob, $uuid, 'suspicious', 'โทรมา 0812345678')['error'] === 'BAD_NOTE', 'contact details refused in a report');
    ok_mk($browse->report($bob, $uuid, 'suspicious', 'ราคาไม่ตรงกับรูป')['ok'] && $browse->report($bob, $uuid, 'wrong_info')['error'] === 'ALREADY_REPORTED', 'once per member');
    $carol = $member('Carol');
    $browse->report($carol, $uuid, 'already_sold');

    // ---- D4 moderation and the capability ----
    $adminRow = ['role' => 'admin', 'is_active' => 1];
    $queues = array_column((new ModerationQueue($db))->summary($adminRow, time() + 7200), null, 'key');
    ok_mk($queues['listing_reports']['count'] >= 2 && $queues['listing_reports']['age_hours'] >= 1 && $queues['notifications']['count'] >= 1, 'counts and ages');
    ok_mk((new ModerationQueue($db))->summary(['role' => 'operator', 'is_active' => 1]) !== [] && !isset(array_column((new ModerationQueue($db))->summary(['role' => 'operator', 'is_active' => 1]), null, 'key')['listing_reports']), 'an operator sees no moderation queue');
    $signIn = static function (int $id): void { $_SESSION = [AuthService::SESSION_USER_ID => $id, AuthService::SESSION_LOGIN_AT => time(), AuthService::SESSION_LAST_SEEN_AT => time()]; };
    $reportId = (int)$db->query("SELECT id FROM listing_reports WHERE listing_id = " . (int)$l['id'] . " ORDER BY id LIMIT 1")->fetchColumn();
    $signIn($operator);
    $r = mk_call(fn() => (new AdminModerationController())->listingReport(), 'POST', ['id' => (string)$reportId, 'decision' => 'upheld', 'note' => 'x']);
    ok_mk($listing()['status'] === 'approved' && $r['code'] !== 200, 'an operator cannot decide');
    $signIn($admin);
    $granted = mk_call(fn() => (new AdminController())->userCapability(), 'POST', ['user_id' => (string)$operator, 'held' => '1']);
    ok_mk($granted['redirect'] === '/admin/users' && (new UserCapabilities($db))->listFor($operator) === ['content_moderator'], 'granted by an admin');
    $signIn($operator);
    $current = (new AuthService())->currentUser();
    ok_mk((new AuthorizationService())->can($current, 'listings.moderate') && (new AuthorizationService())->can($current, 'reports.moderate') && !(new AuthorizationService())->can($current, 'users.manage') && !(new AuthorizationService())->can($current, 'review.decide'), 'the capability adds moderation only');
    mk_call(fn() => (new AdminController())->userCapability(), 'POST', ['user_id' => (string)$admin, 'held' => '1']);
    ok_mk((new UserCapabilities($db))->listFor($admin) === [], 'a moderator cannot grant the capability to anyone');
    $queuePage = mk_call(fn() => (new AdminModerationController())->index());
    ok_mk($queuePage['code'] === 200 && str_contains($queuePage['body'], 'id="listing-reports"') && str_contains($queuePage['body'], 'ราคาไม่ตรงกับรูป'), 'the moderator sees the queue');
    mk_call(fn() => (new AdminModerationController())->listingReport(), 'POST', ['id' => (string)$reportId, 'decision' => 'upheld']);
    ok_mk($listing()['status'] === 'approved', 'removing needs a reason');
    mk_call(fn() => (new AdminModerationController())->listingReport(), 'POST', ['id' => (string)$reportId, 'decision' => 'upheld', 'note' => 'ข้อมูลไม่ตรง']);
    ok_mk($listing()['status'] === 'rejected' && $browse->one($uuid) === null, 'an upheld report removes the listing');
    ok_mk((int)$db->query("SELECT COUNT(*) FROM listing_reports WHERE listing_id = " . (int)$l['id'] . " AND status = 'open'")->fetchColumn() === 0, 'the other reports close with it');
    ok_mk(in_array('removed_after_report', array_column((new ListingLifecycle($db))->history((int)$l['id']), 'event'), true), 'in the history');
    ok_mk($browse->saved($bob)[0]['asking_price'] === null, 'a saved listing that left the site shows no price');
    mk_call(fn() => (new AdminModerationController())->seller(), 'POST', ['member_id' => (string)$alice, 'decision' => 'revoke', 'note' => 'ทดสอบ']);
    ok_mk($profiles->get($alice)['verification'] === 'revoked', 'verification revoked from the page');
    (new UserCapabilities($db))->set($operator, 'content_moderator', false, $admin);
    ok_mk(!(new AuthorizationService())->can((new AuthService())->currentUser(), 'listings.moderate'), 'revoked at once');
    ok_mk((new UserCapabilities($db))->set($operator, 'superuser', true, $admin)['error'] === 'CAPABILITY', 'unknown capability refused');
    ok_mk($evidence() === $prices, 'nothing here created an observation or a price (HG-17)');

    // ---- D5 log-only notifications ----
    $log = sys_get_temp_dir() . '/mi-notify-' . bin2hex(random_bytes(4)) . '.log';
    $kinds = $db->query("SELECT kind FROM notification_queue WHERE member_id = $alice ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    ok_mk($kinds === ['seller_verified', 'seller_verified', 'listing_approved', 'listing_removed', 'seller_revoked'], 'queued: ' . json_encode($kinds));
    $sent = (new NotificationQueue($db))->deliverToLog($log);
    ok_mk($sent >= 5 && (new NotificationQueue($db))->deliverToLog($log) === 0, 'delivered once');
    $text = (string)file_get_contents($log);
    unlink($log);
    ok_mk(str_contains($text, "member=$alice") && !str_contains($text, 'Alice') && !str_contains($text, 'ร้าน') && !str_contains($text, 'ข้อมูลไม่ตรง'), 'the log holds identifiers only');

    // ---- D6 deleting the account removes the new rows ----
    (new MemberAuth($db))->deleteAccount($alice);
    (new MemberAuth($db))->deleteAccount($bob);
    foreach (["seller_profiles WHERE member_id = $alice", "seller_verification_events WHERE member_id = $alice", "notification_queue WHERE member_id = $alice", "saved_listings WHERE member_id = $bob", "saved_searches WHERE member_id = $bob", "listing_reports WHERE member_id = $bob", "listing_status_events WHERE listing_id = " . (int)$l['id']] as $where) {
        ok_mk((int)$db->query("SELECT COUNT(*) FROM $where")->fetchColumn() === 0, "removed with the account: $where");
    }
    echo "market_listing_test: PASS\n";
} finally {
    $config['seller']['listings_public'] = false;
    $_SESSION = [];
    $db->rollBack();
}
