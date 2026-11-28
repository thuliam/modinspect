<?php
declare(strict_types=1);

/**
 * LS-89 BE-2: the whole loop works from the admin pages with no command line: approve → the price and the trend update,
 * withdraw with a reason → the price is recalculated or disappears, the price status page shows it and can recalculate it.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/support/PriceFixture.php';

use App\Controllers\AdminController;
use App\Controllers\AdminPriceController;
use App\Core\Csrf;
use App\Core\RedirectException;
use App\Models\Product;
use App\Services\Admin\PriceStatus;
use App\Services\Auth\AuthService;
use App\Services\Pricing\PriceSnapshotService;
use App\Services\Review\ReviewDecisionService;

function assert_rpl(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SESSION = [];
$fx = new PriceFixture('loop');
$extraUsers = [];
try {
    $pid = $fx->productId;
    $slug = (string)$fx->db->query("SELECT slug FROM products WHERE id=$pid")->fetchColumn();
    $public = static fn(): array => (array)(new Product())->findBySlug($slug);
    $login = static function (int $userId): void {
        $_SESSION = [AuthService::SESSION_USER_ID => $userId, AuthService::SESSION_LOGIN_AT => time(), AuthService::SESSION_LAST_SEEN_AT => time()];
    };
    /** runs a controller action and returns [redirect path or null, flash, page] */
    $call = static function (callable $action, array $post = [], string $method = 'POST'): array {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = '/admin';
        $_POST = $post === [] ? [] : ['_token' => Csrf::token()] + $post;
        unset($_SESSION['flash']);
        http_response_code(200);
        $path = null;
        ob_start();
        try { $action(); } catch (RedirectException $e) { $path = $e->path; }
        $page = (string)ob_get_clean();
        $flash = $_SESSION['flash'] ?? null;
        return ['path' => $path, 'flash' => $flash, 'page' => $page, 'code' => http_response_code()];
    };
    $login($fx->userId);

    // 1) two approved rows: no public price yet. Approving the third publishes it, without any command
    $first = $fx->observation(1000);
    $fx->observation(1100);
    $third = $fx->observation(1200, 'pending');
    assert_rpl($public()['median'] === null, 'no price below three rows');
    $r = $call(fn() => (new AdminController())->reviewDecision(), ['observation_id' => $third, 'decision' => 'approved']);
    assert_rpl($r['path'] === '/admin/review/' . $third, 'redirect back to the listing: ' . json_encode($r['path']));
    assert_rpl(($r['flash']['type'] ?? '') === 'success' && str_contains($r['flash']['message'], '1,100'), 'the message names the new median: ' . json_encode($r['flash'], JSON_UNESCAPED_UNICODE));
    assert_rpl((float)$public()['median'] === 1100.0, 'the approval published the price');
    assert_rpl($fx->count('SELECT COUNT(*) FROM price_trend_points WHERE product_id=? AND sample_size=3', [$pid]) === 1, 'the trend month was built too');

    // 2) deciding the same row again says so
    $r = $call(fn() => (new AdminController())->reviewDecision(), ['observation_id' => $third, 'decision' => 'approved']);
    assert_rpl(($r['flash']['type'] ?? '') === 'danger', 'a second decision is reported, not silent');

    // 3) rejecting a pending row leaves the price alone
    $fourth = $fx->observation(9000, 'pending');
    $snapshots = $fx->count('SELECT COUNT(*) FROM price_indices WHERE product_id=?', [$pid]);
    $r = $call(fn() => (new AdminController())->reviewDecision(), ['observation_id' => $fourth, 'decision' => 'rejected']);
    assert_rpl(($r['flash']['type'] ?? '') === 'success' && $fx->count('SELECT COUNT(*) FROM price_indices WHERE product_id=?', [$pid]) === $snapshots, 'no new snapshot for a rejection');

    // 4) withdrawing needs a reason; with one the price falls below the minimum and disappears
    $r = $call(fn() => (new AdminController())->reviewWithdraw(), ['observation_id' => $first, 'to' => 'excluded', 'reason' => '   ']);
    assert_rpl(($r['flash']['type'] ?? '') === 'danger' && (float)$public()['median'] === 1100.0, 'no withdrawal without a reason');
    $r = $call(fn() => (new AdminController())->reviewWithdraw(), ['observation_id' => $first, 'to' => 'pending', 'reason' => 'wrong price typed']);
    assert_rpl(($r['flash']['type'] ?? '') === 'warning', 'the admin is told the model no longer has enough listings: ' . json_encode($r['flash'], JSON_UNESCAPED_UNICODE));
    assert_rpl($public()['median'] === null, 'the price built on the withdrawn row is not shown');
    assert_rpl($fx->count('SELECT COUNT(*) FROM price_trend_points WHERE product_id=?', [$pid]) === 0, 'the trend month fell below the minimum');
    assert_rpl($fx->count("SELECT COUNT(*) FROM audit_logs WHERE action='review_withdrawn' AND entity_id=? AND user_id=?", [$first, $fx->userId]) === 1, 'withdrawal audit with actor');
    assert_rpl($fx->count("SELECT COUNT(*) FROM observation_review_decisions WHERE price_observation_id=? AND decision='review_required' AND notes='wrong price typed' AND reviewer_id=?", [$first, $fx->userId]) === 1, 'withdrawal record with reason');
    $status = (new PriceStatus())->detail($pid);
    assert_rpl($status['state'] === 'hidden' && $status['can_recalculate'] === false, 'state hidden, nothing to calculate yet: ' . $status['state']);

    // 5) back in the queue it can be corrected and approved again; the price returns with the corrected value
    $r = $call(fn() => (new AdminController())->reviewCorrection(), ['observation_id' => $first, 'field_name' => 'price', 'corrected_value' => '1050', 'reason' => 'read the post again']);
    assert_rpl(($r['flash']['type'] ?? '') === 'success', 'correction saved message');
    $call(fn() => (new AdminController())->reviewDecision(), ['observation_id' => $first, 'decision' => 'approved']);
    assert_rpl((float)$public()['median'] === 1100.0 && (float)$public()['price_low'] === 1050.0, 'the corrected row is in the new price');

    // 6) a change made outside the review pages shows up as hidden, and the button repairs it
    $fx->observation(1300);
    $fx->db->exec("UPDATE price_observations SET verified_status='excluded' WHERE id=$first");
    assert_rpl($public()['median'] === null, 'hidden after an outside change');
    $row = null;
    foreach ((new PriceStatus())->overview()['rows'] as $candidate) { if ((int)$candidate['product_id'] === $pid) { $row = $candidate; } }
    assert_rpl($row !== null && $row['state'] === 'hidden' && $row['can_recalculate'], 'the overview lists the hidden model: ' . json_encode($row, JSON_UNESCAPED_UNICODE));
    $page = $call(fn() => (new AdminPriceController())->status(), [], 'GET');
    assert_rpl(str_contains($page['page'], '/admin/price-status/' . $pid) && str_contains($page['page'], 'name="all"'), 'the page links the model and offers to recalculate');
    $page = $call(fn() => (new AdminPriceController())->detail((string)$pid), [], 'GET');
    assert_rpl(str_contains($page['page'], '/admin/review/' . $third) && str_contains($page['page'], 'name="product_id"'), 'the detail page lists the listings and the button');
    $r = $call(fn() => (new AdminPriceController())->recalculate(), ['product_id' => $pid]);
    assert_rpl($r['path'] === '/admin/price-status/' . $pid && ($r['flash']['type'] ?? '') === 'success', 'recalculated: ' . json_encode($r['flash'], JSON_UNESCAPED_UNICODE));
    assert_rpl((float)$public()['median'] === 1200.0, 'the price is public again');
    assert_rpl((new PriceStatus())->detail($pid)['state'] === 'ok', 'state ok');
    assert_rpl($fx->count("SELECT COUNT(*) FROM audit_logs WHERE action='price_recalculated' AND entity_id=? AND user_id=?", [$pid, $fx->userId]) === 1, 'recalculation audit');

    // 7) the same numbers from different listings still need a new snapshot once the stored one is not publishable
    $members = $fx->db->query("SELECT id FROM price_observations WHERE product_id=$pid AND verified_status='approved' AND price_value=1100")->fetchColumn();
    $fx->observation(1100);
    $fx->db->exec("UPDATE price_observations SET verified_status='excluded' WHERE id=" . (int)$members);
    assert_rpl($public()['median'] === null, 'swapped member hides the snapshot');
    $call(fn() => (new AdminPriceController())->recalculate(), ['product_id' => $pid]);
    assert_rpl((float)$public()['median'] === 1200.0, 'identical numbers are stored again');

    // 8) an operator can look but not recalculate; a bad token changes nothing
    $fx->db->prepare("INSERT INTO users (name,email,password_hash,role,is_active) VALUES ('LS89 operator',?,?,'operator',1)")
        ->execute(['op-' . bin2hex(random_bytes(4)) . '@example.test', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
    $operator = (int)$fx->db->lastInsertId();
    $extraUsers[] = $operator;
    $login($operator);
    $r = $call(fn() => (new AdminPriceController())->recalculate(), ['product_id' => $pid]);
    assert_rpl($r['code'] === 403, 'operator refused');
    $login($fx->userId);
    $snapshots = $fx->count('SELECT COUNT(*) FROM price_indices WHERE product_id=?', [$pid]);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_token' => 'bad', 'product_id' => $pid];
    try { (new AdminPriceController())->recalculate(); assert_rpl(false, 'redirect expected'); } catch (RedirectException $e) { assert_rpl($e->path === '/admin/price-status', 'back to the list'); }
    assert_rpl($fx->count('SELECT COUNT(*) FROM price_indices WHERE product_id=?', [$pid]) === $snapshots, 'bad token stores nothing');
    assert_rpl((new ReviewDecisionService())->withdraw($third, 'approved', 'x', $fx->userId)['error'] === 'UNSUPPORTED_DECISION', 'unknown target refused');

    echo "review_price_loop_test: PASS\n";
} finally {
    $_SESSION = [];
    foreach ($extraUsers as $id) {
        $fx->db->exec('DELETE FROM audit_logs WHERE user_id=' . (int)$id);
        $fx->db->exec('DELETE FROM users WHERE id=' . (int)$id);
    }
    $fx->cleanup();
}
