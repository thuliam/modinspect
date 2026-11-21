<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Controllers\DealCheckerController;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\RedirectException;
use App\Models\DealCheck;
use App\Services\Deal\DealOutcome as O;

/** LS-28 B1: post-purchase outcome. Validation is strict, one row per result, the POST needs a token and a real uuid, and no price table is touched. Test DB only. */
function ok_do(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

ok_do(O::validate(['outcome' => 'bought', 'final_price' => '4,500'])['final_price'] === 4500.0, 'Commas are accepted in the price');
ok_do(O::validate(['outcome' => 'negotiated', 'final_price' => '฿ 3200.50'])['final_price'] === 3200.5, 'A baht sign and spaces are accepted');
ok_do(O::validate(['outcome' => 'bought', 'final_price' => ''])['ok'] === true && O::validate(['outcome' => 'bought'])['final_price'] === null, 'The price is optional');
ok_do(O::validate(['outcome' => 'passed', 'final_price' => '999'])['final_price'] === null, 'Passing carries no price');
foreach (['0', '-5', 'abc', '10000001', '1e5', '12.345'] as $bad) { ok_do(O::validate(['outcome' => 'bought', 'final_price' => $bad])['error'] === 'price', "A bad price is refused: $bad"); }
ok_do(O::validate(['outcome' => 'hacked'])['error'] === 'outcome' && O::validate([])['error'] === 'outcome', 'An unknown or missing outcome is refused');

global $config;
$db = Database::connection($config['db']);
ok_do($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$p = $db->query('SELECT id FROM products WHERE is_active = 1 ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$uuid = (new DealCheck())->create(['product_id' => $p['id'], 'user_price' => 5000, 'condition_level' => 'good', 'warranty_months' => null, 'has_box' => 0, 'has_receipt' => 0, 'has_benchmark' => 0,
    'result_label' => 'insufficient', 'min' => null, 'max' => null, 'recommendation' => 'test', 'warning' => null, 'consent' => 0]);
$call = static function (callable $fn): array {
    http_response_code(200); ob_start();
    try { $fn(); } catch (RedirectException $e) { ob_end_clean(); return ['redirect' => $e->path, 'code' => 302, 'body' => '']; }
    return ['redirect' => null, 'code' => http_response_code(), 'body' => (string)ob_get_clean()];
};
$_SERVER['REQUEST_METHOD'] = 'POST'; $_SESSION = [];
$_POST = ['outcome' => 'bought', 'final_price' => '4500', '_token' => 'wrong'];
ok_do($call(static fn() => (new DealCheckerController())->outcome($uuid))['code'] === 419, 'A wrong token is refused');
$_POST['_token'] = Csrf::token();
ok_do($call(static fn() => (new DealCheckerController())->outcome('not-a-uuid'))['code'] === 404, 'A malformed uuid is a 404');
ok_do($call(static fn() => (new DealCheckerController())->outcome('00000000-0000-4000-8000-000000000000'))['code'] === 404, 'An unknown result is a 404');
$r = $call(static fn() => (new DealCheckerController())->outcome($uuid));
ok_do($r['redirect'] === "/deal-checker/result/$uuid#outcome", 'A valid post returns to the result');
$deal = (new DealCheck())->findByUuid($uuid);
$saved = (new DealCheck())->outcomeFor((int)$deal['id']);
ok_do($saved['outcome'] === 'bought' && (float)$saved['final_price'] === 4500.0, 'The outcome is stored');
$_POST = ['outcome' => 'passed', '_token' => Csrf::token()];
$call(static fn() => (new DealCheckerController())->outcome($uuid));
ok_do((int)$db->query('SELECT COUNT(*) FROM deal_outcomes WHERE deal_check_id = ' . (int)$deal['id'])->fetchColumn() === 1, 'Saving again replaces the row (one per result)');
ok_do((new DealCheck())->outcomeFor((int)$deal['id'])['outcome'] === 'passed', 'The latest answer wins');
$_POST = ['outcome' => 'bought', 'final_price' => 'abc', '_token' => Csrf::token()];
$call(static fn() => (new DealCheckerController())->outcome($uuid));
ok_do(($_SESSION['deal_outcome_flash'] ?? '') !== '' && (new DealCheck())->outcomeFor((int)$deal['id'])['outcome'] === 'passed', 'A bad price changes nothing and tells the person');
$_SERVER['REQUEST_METHOD'] = 'GET';
$page = $call(static fn() => (new DealCheckerController())->result($uuid));
ok_do($page['code'] === 200 && str_contains($page['body'], 'หลังดูผลนี้ เกิดอะไรขึ้น') && str_contains($page['body'], 'ไม่เปลี่ยนราคากลาง'), 'The result page shows the follow-up card');
$src = (string)file_get_contents(ROOT_PATH . '/app/Models/DealCheck.php');
ok_do(!preg_match('/INSERT INTO (price_observations|raw_price_observations|price_indices|price_snapshots)/', $src), 'No price table is written');
$db->exec('DELETE FROM deal_checks WHERE uuid = ' . $db->quote($uuid));
echo "deal_outcome_test: PASS\n";
