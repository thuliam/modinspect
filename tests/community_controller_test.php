<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Controllers\CommunityController;
use App\Controllers\PriceController;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\RedirectException;
use App\Services\Community\Forum;
use App\Services\Member\MemberAuth;

/**
 * LS-24 M9 through the controllers (in process, isolated test DB, rolled back): every route is 404 and no page links to the forum while the switch is off; with it on,
 * a visitor can read but not write, a write needs a token and a signed-in member, a bad text bounces back with the reason, the back address can only be a local path,
 * and the product page shows the review block. Moderation needs the reviewer permission.
 */
function ok_cc(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
/** @return array{redirect:?string,code:int,body:string} */
function cc_call(callable $fn): array
{
    http_response_code(200); ob_start();
    try { $fn(); } catch (RedirectException $e) { ob_end_clean(); return ['redirect' => $e->path, 'code' => 302, 'body' => '']; }
    return ['redirect' => null, 'code' => http_response_code(), 'body' => (string)ob_get_clean()];
}

global $config;
$_SERVER['REMOTE_ADDR'] = '10.251.' . random_int(0, 255) . '.' . random_int(1, 254);
$_SERVER['REQUEST_URI'] = '/community';
$db = Database::connection($config['db']);
ok_cc($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$db->beginTransaction();
try {
    $db->exec("INSERT INTO members (display_name, terms_accepted_at) VALUES ('Alice Realname', NOW())"); $alice = (int)$db->lastInsertId();
    $db->exec("INSERT INTO members (display_name, terms_accepted_at) VALUES ('Bob Realname', NOW())"); $bob = (int)$db->lastInsertId();
    $prod = $db->query('SELECT id, slug FROM products WHERE is_active = 1 ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $post = static function (array $data, ?int $member) use ($alice): void { $_SESSION = $member ? [MemberAuth::SESSION_MEMBER_ID => $member] : []; $_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = $data; };
    $tok = static function (?int $member): string { $_SESSION = $member ? [MemberAuth::SESSION_MEMBER_ID => $member] : []; return Csrf::token(); };

    // ---- switch off ----
    $config['community_forum']['enabled'] = false;
    $_SESSION = []; $_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = [];
    ok_cc(cc_call(fn() => (new CommunityController())->index())['code'] === 404 && cc_call(fn() => (new CommunityController())->thread('1'))['code'] === 404, 'Off: the list and a thread answer 404');
    foreach (['newThread' => [], 'reply' => ['1'], 'review' => [], 'vote' => [], 'flagItem' => []] as $m => $args) {
        $post(['_token' => $tok($alice), 'title' => 'x'], $alice);
        ok_cc(cc_call(fn() => (new CommunityController())->$m(...$args))['code'] === 404, "Off: $m answers 404 and writes nothing");
    }
    ok_cc((int)$db->query('SELECT COUNT(*) FROM community_threads')->fetchColumn() === 0, 'Off: nothing was written');
    $_SERVER['REQUEST_METHOD'] = 'GET'; $_POST = []; $_SESSION = [];
    $_SERVER['REQUEST_URI'] = '/price/' . $prod['slug'];
    $page = cc_call(fn() => (new PriceController())->show((string)$prod['slug']));
    ok_cc(!str_contains($page['body'], 'id="reviews"') && !str_contains($page['body'], '/community'), 'Off: the product page has no review block and no link to the forum');

    // ---- switch on ----
    $config['community_forum']['enabled'] = true;
    $_SESSION = []; $_SERVER['REQUEST_METHOD'] = 'GET';
    $r = cc_call(fn() => (new CommunityController())->index());
    ok_cc($r['code'] === 200 && str_contains($r['body'], 'เข้าสู่ระบบ'), 'On: a visitor sees the list and is told to sign in to write');

    $post(['_token' => $tok(null), 'title' => 'หัวข้อทดสอบของผู้เยี่ยมชม', 'body' => 'เนื้อหาทดสอบของผู้เยี่ยมชม ยาวพอสมควร'], null);
    $_POST['_token'] = $tok(null);
    ok_cc(cc_call(fn() => (new CommunityController())->newThread())['redirect'] === '/account/login', 'A visitor who tries to post goes to the login page');
    $post(['_token' => 'forged', 'title' => 'หัวข้อทดสอบ ครับ', 'body' => 'เนื้อหาทดสอบ ยาวพอสมควรครับ'], $alice);
    ok_cc(cc_call(fn() => (new CommunityController())->newThread())['code'] === 419, 'A forged token is refused (419)');

    $post(['title' => 'หัวข้อทดสอบของอลิซ ครับ', 'body' => 'เนื้อหาทดสอบของอลิซ ใครเคยซื้อรุ่นนี้บ้างครับ', 'product_id' => (string)$prod['id']], $alice); $_POST['_token'] = Csrf::token();
    $r = cc_call(fn() => (new CommunityController())->newThread());
    ok_cc(preg_match('#^/community/thread/(\d+)$#', (string)$r['redirect'], $m) === 1, 'A member posts and lands on the thread: ' . json_encode($r['redirect']));
    $tid = (int)$m[1];

    $post(['title' => 'โทร 081-234-5678 ครับ', 'body' => 'เนื้อหาที่มีไลน์: seller_99 และเบอร์ ครับผม'], $alice); $_POST['_token'] = Csrf::token();
    $r = cc_call(fn() => (new CommunityController())->newThread());
    ok_cc($r['redirect'] === '/community' && str_contains((string)($_SESSION['community_flash'] ?? ''), 'ห้ามใส่เบอร์โทร'), 'A text with contact details bounces back with the reason in Thai');

    $_SESSION = [MemberAuth::SESSION_MEMBER_ID => $bob]; $_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = [];
    $r = cc_call(fn() => (new CommunityController())->thread((string)$tid));
    ok_cc($r['code'] === 200 && str_contains($r['body'], 'หัวข้อทดสอบของอลิซ') && !str_contains($r['body'], 'Realname') && str_contains($r['body'], Forum::handle($alice)), 'The thread page shows the pseudonym and never the sign-in name');

    $post(['body' => 'ผมเคยใช้รุ่นนี้ ไม่มีปัญหาครับ'], $bob); $_POST['_token'] = Csrf::token();
    $r = cc_call(fn() => (new CommunityController())->reply((string)$tid));
    ok_cc(str_starts_with((string)$r['redirect'], "/community/thread/$tid#post-"), 'A reply lands on the post');

    $post(['type' => 'thread', 'id' => (string)$tid, 'back' => 'https://evil.example/steal'], $alice); $_POST['_token'] = Csrf::token();
    $r = cc_call(fn() => (new CommunityController())->vote());
    ok_cc($r['redirect'] === '/community' && str_contains((string)$_SESSION['community_flash'], 'ตัวเอง'), 'Voting on own post is refused, and a foreign back address is replaced by a local one');
    $post(['type' => 'thread', 'id' => (string)$tid, 'back' => "/community/thread/$tid"], $bob); $_POST['_token'] = Csrf::token();
    ok_cc(cc_call(fn() => (new CommunityController())->vote())['redirect'] === "/community/thread/$tid", 'A vote goes back to the thread');
    $post(['type' => 'thread', 'id' => (string)$tid, 'reason' => 'spam', 'back' => "/community/thread/$tid"], $bob); $_POST['_token'] = Csrf::token();
    $r = cc_call(fn() => (new CommunityController())->flagItem());
    ok_cc($r['redirect'] === "/community/thread/$tid" && (int)$db->query('SELECT COUNT(*) FROM community_flags')->fetchColumn() === 1, 'A flag is recorded');

    $post(['product_id' => (string)$prod['id'], 'price_paid' => '8500', 'source_kind' => 'shop', 'condition_received' => 'good', 'issue_30d' => 'none', 'would_buy_again' => '1', 'note' => 'ของตรงตามประกาศ'], $bob); $_POST['_token'] = Csrf::token();
    $r = cc_call(fn() => (new CommunityController())->review());
    ok_cc($r['redirect'] === '/price/' . $prod['slug'] . '#reviews', 'A review lands on the product page');
    $_SESSION = [MemberAuth::SESSION_MEMBER_ID => $bob]; $_SERVER['REQUEST_METHOD'] = 'GET'; $_POST = []; $_GET = [];
    $_SERVER['REQUEST_URI'] = '/price/' . $prod['slug'];
    $page = cc_call(fn() => (new PriceController())->show((string)$prod['slug']));
    ok_cc(str_contains($page['body'], 'id="reviews"') && str_contains($page['body'], 'ผู้รีวิวบอกว่าจ่าย ฿8,500') && str_contains($page['body'], 'ยังน้อยกว่า 5 คน') && !str_contains($page['body'], 'Realname'), 'On: the product page shows the review, says the summary waits for five, and no sign-in name');
} finally {
    $config['community_forum']['enabled'] = false;
    $db->rollBack();
}
$admin = (string)file_get_contents(ROOT_PATH . '/app/Controllers/AdminController.php');
ok_cc(str_contains($admin, "public function communityModeration(): void {\n        if(!\$this->requirePermission('reports.moderate')) return;") && str_contains($admin, "public function communityModerationAction(): void {\n        if(!\$this->requirePermission('reports.moderate')) return;\n        if(!Csrf::verify"), 'Moderation needs the reviewer permission and a CSRF token');
$idx = (string)file_get_contents(ROOT_PATH . '/public/index.php');
foreach (["'/community'", "'/community/thread/{id}'", "'/community/review'", "'/community/vote'", "'/community/flag'", "'/admin/community-moderation'", "'/admin/community-moderation/action'"] as $r) { ok_cc(str_contains($idx, $r), "Route $r is registered"); }
echo "community_controller_test: PASS\n";
