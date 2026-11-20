<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Controllers\WatchController;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\RedirectException;
use App\Services\Catalog\WatchList as W;

/** LS-25 C5: the watch list is a visitor cookie; parsing is strict, toggling is capped, the page shows direction only, the POST needs a token. Test DB, nothing written. */
function ok_wl(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$l = W::parse('a-b~3000,c-d,a-b~1,BAD SLUG~9,<script>,e~x,f~12345678901,,g~0');
ok_wl(array_column($l, 'slug') === ['a-b', 'c-d', 'g'], 'Malformed entries and duplicates are dropped, the first value of a slug wins: ' . json_encode($l));
ok_wl($l[0]['median'] === 3000.0 && $l[1]['median'] === null, 'A price is read when present, null when not');
ok_wl(W::parse(null) === [] && W::parse('') === [], 'No cookie is an empty list');
$t = W::toggle([], 'x1', 1234.6);
ok_wl($t['added'] && W::serialize($t['list']) === 'x1~1235', 'Adding stores the rounded median');
ok_wl(W::toggle($t['list'], 'x1', null)['list'] === [] && W::toggle($t['list'], 'x1', null)['added'] === false, 'Toggling again removes');
ok_wl(W::serialize(W::toggle([], 'y', null)['list']) === 'y', 'A model without a price is stored without one');
$full = []; for ($i = 0; $i < W::MAX; $i++) { $full[] = ['slug' => "m$i", 'median' => null]; }
$f = W::toggle($full, 'extra', 100.0);
ok_wl($f['full'] === true && $f['added'] === false && count($f['list']) === W::MAX, 'The list never grows past MAX');
ok_wl(count(W::parse(implode(',', array_map(static fn($i) => "m$i", range(1, 50))))) === W::MAX, 'A long cookie is cut to MAX');
ok_wl(W::change(1000.0, 1100.0) === ['direction' => 'up', 'diff' => 100.0, 'percent' => 10] && W::change(1000.0, 900.0)['direction'] === 'down' && W::change(1000.0, 1000.0)['direction'] === 'same', 'Change is a direction with the amount');
ok_wl(W::change(null, 900.0) === null && W::change(1000.0, null) === null && W::change(0.0, 5.0) === null, 'Without both prices nothing is said');

global $config;
$db = Database::connection($config['db']);
ok_wl($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$prod = $db->query('SELECT slug FROM products WHERE is_active = 1 ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$call = static function (callable $fn): array {
    http_response_code(200); ob_start();
    try { $fn(); } catch (RedirectException $e) { ob_end_clean(); return ['redirect' => $e->path, 'code' => 302, 'body' => '']; }
    return ['redirect' => null, 'code' => http_response_code(), 'body' => (string)ob_get_clean()];
};
$_SERVER['REQUEST_URI'] = '/watch'; $_SERVER['REQUEST_METHOD'] = 'POST'; $_COOKIE = [];
$_SESSION = []; $_POST = ['slug' => $prod['slug'], '_token' => 'wrong'];
ok_wl($call(static fn() => (new WatchController())->toggle())['code'] === 419, 'A wrong token is refused');
$_SESSION = []; $_POST = ['slug' => 'no-such-model-xyz', '_token' => Csrf::token()];
ok_wl($call(static fn() => (new WatchController())->toggle())['code'] === 404, 'An unknown model is a 404, nothing is stored');
$_POST = ['slug' => $prod['slug'], '_token' => Csrf::token()];
$r = $call(static fn() => (new WatchController())->toggle());
ok_wl($r['redirect'] === '/price/' . rawurlencode($prod['slug']) && str_starts_with((string)$_COOKIE[W::COOKIE], $prod['slug']), 'A valid toggle adds the model and returns to its page');
// LS-26: removing from the list page returns to the list; only that fixed destination is accepted
$_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = ['slug' => $prod['slug'], '_token' => Csrf::token(), 'back' => 'watching'];
$rb = $call(static fn() => (new WatchController())->toggle());
ok_wl($rb['redirect'] === '/watching' && !str_contains((string)($_COOKIE[W::COOKIE] ?? ''), $prod['slug']), 'Removing from the list page goes back to the list and the model is gone');
$_POST = ['slug' => $prod['slug'], '_token' => Csrf::token(), 'back' => 'https://evil.example/'];
$re = $call(static fn() => (new WatchController())->toggle());
ok_wl($re['redirect'] === '/price/' . rawurlencode($prod['slug']), 'Any other back value is ignored (no open redirect)');
$_SERVER['REQUEST_METHOD'] = 'GET';
$page = $call(static fn() => (new WatchController())->index());
ok_wl($page['code'] === 200 && str_contains($page['body'], htmlspecialchars($prod['slug'])) && (str_contains($page['body'], 'ไม่ได้บอกว่าควรซื้อหรือรอ')), 'The list page shows the model');
$_COOKIE = [W::COOKIE => 'ghost-model~1000'];
ok_wl(str_contains($call(static fn() => (new WatchController())->index())['body'], 'ยังไม่ได้ติดตามรุ่นไหน'), 'A slug that no longer exists is skipped');
$src = (string)file_get_contents(ROOT_PATH . '/app/Controllers/WatchController.php');
ok_wl(!str_contains($src, 'INSERT') && !str_contains($src, 'UPDATE') && !str_contains($src, 'prepare('), 'The watch list writes nothing to the database');
echo "watch_list_test: PASS\n";
