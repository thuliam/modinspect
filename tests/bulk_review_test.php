<?php
declare(strict_types=1);

/** LS-90 T8: several rows decided at once go through the same rule as one, are shown again before saving, and each leaves an audit line. */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/support/PriceFixture.php';

use App\Controllers\AdminReviewController;
use App\Core\Csrf;
use App\Core\RedirectException;
use App\Services\Auth\AuthService;
use App\Services\Review\BulkReview;

function assert_bulk(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

assert_bulk(BulkReview::ids('3, 5,5,abc,-1,0,7') === [3, 5, 7] && BulkReview::ids(['9', ['x'], '2']) === [9, 2] && BulkReview::ids(null) === [], 'id parsing');
assert_bulk(count(BulkReview::ids(range(1, 80))) === BulkReview::MAX_ROWS, 'a batch is limited');

$fx = new PriceFixture('bulk');
try {
    $db = $fx->db;
    $retitle = static function (int $observation, string $title) use ($db): void {
        $db->prepare('UPDATE raw_price_observations r JOIN price_observations o ON o.raw_observation_id=r.id SET r.raw_title=? WHERE o.id=?')->execute([$title, $observation]);
    };
    $ids = [];
    foreach ([1000, 1100, 1200, 1300] as $price) { $ids[] = $fx->observation($price, 'pending'); }
    [$a, $b, $c, $flagged] = $ids;
    $retitle($flagged, 'ของ ทดสอบ sold');
    $done = $fx->observation(900, 'rejected');

    $service = new BulkReview($db);
    $preview = array_column($service->preview([$a, $flagged, $done]), null, 'id');
    assert_bulk(isset($preview[$a], $preview[$flagged]) && !isset($preview[$done]), 'only pending rows are offered');
    assert_bulk($preview[$a]['warnings'] === [] && $preview[$flagged]['warnings'] === ['SOLD_OR_RESERVED'], 'warnings are listed');

    // the pages, as a signed-in admin
    $_SESSION = [];
    $admin = (int)(new AuthService())->createUser('bulk-' . bin2hex(random_bytes(4)) . '@example.test', 'Bulk Admin', bin2hex(random_bytes(12)), 'admin')['user_id'];
    $signIn = static function (int $id): void {
        $_SESSION = [AuthService::SESSION_USER_ID => $id, AuthService::SESSION_LOGIN_AT => time(), AuthService::SESSION_LAST_SEEN_AT => time()];
    };
    $signIn($admin);
    $call = static function (callable $action, array $post = [], string $method = 'POST'): array {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = '/admin/review-queue';
        $_GET = [];
        $_POST = $method === 'POST' ? $post + ['_token' => Csrf::token()] : [];
        http_response_code(200);
        $path = null;
        ob_start();
        try { $action(); } catch (RedirectException $e) { $path = $e->path; }
        return ['code' => http_response_code(), 'path' => $path, 'page' => (string)ob_get_clean()];
    };
    $status = static fn(int $id): string => (string)$db->query("SELECT verified_status FROM price_observations WHERE id=$id")->fetchColumn();
    $controller = static fn(): AdminReviewController => new AdminReviewController();

    $page = $call(fn() => $controller()->review(), [], 'GET');
    assert_bulk(str_contains($page['page'], 'js-bulk-review') && str_contains($page['page'], 'data-bulk-form="1"') && str_contains($page['page'], 'dataTables.select.min.js'), 'the queue offers selection');

    $confirm = $call(fn() => $controller()->reviewBulk(), ['decision' => 'approved', 'ids' => "$a,$b,$c,$flagged"]);
    assert_bulk($confirm['code'] === 200 && substr_count($confirm['page'], 'name="ids[]"') === 4, 'four rows to confirm');
    assert_bulk(preg_match('~value="' . $a . '"[^>]*checked~', $confirm['page']) === 1 && preg_match('~value="' . $flagged . '"[^>]*checked~', $confirm['page']) !== 1, 'a flagged row starts unticked');
    assert_bulk($status($a) === 'pending', 'the confirmation page writes nothing');
    assert_bulk($call(fn() => $controller()->reviewBulk(), ['decision' => 'publish', 'ids' => (string)$a])['path'] === '/admin/review-queue', 'an unknown decision is refused');

    assert_bulk($call(fn() => $controller()->reviewBulkApply(), ['_token' => 'bad', 'decision' => 'approved', 'ids' => [(string)$a], 'confirm' => '1'])['code'] === 419, 'CSRF');
    $call(fn() => $controller()->reviewBulkApply(), ['decision' => 'approved', 'ids' => [(string)$a]]);
    assert_bulk($status($a) === 'pending', 'without the confirmation box nothing is saved');

    $r = $call(fn() => $controller()->reviewBulkApply(), ['decision' => 'approved', 'ids' => [(string)$a, (string)$b, (string)$c, (string)$done], 'confirm' => '1', 'note' => 'checked']);
    assert_bulk($r['path'] === '/admin/review-queue' && $status($a) === 'approved' && $status($c) === 'approved' && $status($flagged) === 'pending' && $status($done) === 'rejected', 'the ticked pending rows are approved, the rest untouched');
    assert_bulk($fx->count("SELECT COUNT(*) FROM audit_logs WHERE user_id=? AND action='review_approved' AND entity_id IN ($a,$b,$c)", [$admin]) === 3, 'one audit line per row with the actor');
    assert_bulk($fx->count("SELECT COUNT(*) FROM price_indices WHERE product_id=?", [$fx->productId]) === 1, 'the price was calculated once, after all rows');

    $call(fn() => $controller()->reviewBulkApply(), ['decision' => 'rejected', 'ids' => [(string)$flagged], 'confirm' => '1']);
    assert_bulk($status($flagged) === 'rejected', 'bulk reject');

    // a role without review.decide
    $viewer = (int)(new AuthService())->createUser('bulk-op-' . bin2hex(random_bytes(4)) . '@example.test', 'Bulk Operator', bin2hex(random_bytes(12)), 'operator')['user_id'];
    $another = $fx->observation(1400, 'pending');
    $signIn($viewer);
    $r = $call(fn() => $controller()->reviewBulkApply(), ['decision' => 'approved', 'ids' => [(string)$another], 'confirm' => '1']);
    assert_bulk($status($another) === 'pending' && $r['code'] !== 200, 'no permission, nothing saved: ' . $r['code']);

    $db->exec("DELETE FROM audit_logs WHERE user_id IN ($admin,$viewer)");
    $db->exec("UPDATE observation_review_decisions SET reviewer_id=NULL WHERE reviewer_id IN ($admin,$viewer)");
    $db->exec("DELETE FROM users WHERE id IN ($admin,$viewer)");
    echo "bulk_review_test: PASS\n";
} finally {
    $_SESSION = [];
    $fx->cleanup();
}
