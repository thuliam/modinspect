<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Catalog\WatchList;
use App\Services\Member\MemberAuth;
use App\Services\Member\MemberWatchList;
use App\Services\Push\SubscriptionStore;

/** LS-87: a signed-in member's watch list on the server: merge once from the cookie, toggle, the member limit, account deletion removes it. DB rolled back. */
function ok_mw(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();
try {
    $db->exec("INSERT INTO members (display_name, status, terms_accepted_at) VALUES ('t', 'active', NOW())");
    $m1 = (int)$db->lastInsertId();
    $db->exec("INSERT INTO members (display_name, status, terms_accepted_at) VALUES ('u', 'active', NOW())");
    $m2 = (int)$db->lastInsertId();
    $w = new MemberWatchList($db, 3);
    $cookie = [['slug' => 'rtx-3060', 'median' => 7800.0], ['slug' => 'ryzen-5-5600', 'median' => null], ['slug' => 'rx-6600', 'median' => 4800.0], ['slug' => 'gtx-1650', 'median' => 2100.0]];
    $l = $w->merge($m1, $cookie);
    ok_mw(array_column($l, 'slug') === ['rtx-3060', 'ryzen-5-5600', 'rx-6600'], 'The cookie list is merged in order, up to the member limit');
    ok_mw($l[0]['median'] === 7800.0 && $l[1]['median'] === null, 'The median seen when watching began is kept, a missing one stays null');
    ok_mw(count($w->merge($m1, $cookie)) === 3, 'Merging again adds nothing (idempotent)');
    ok_mw($w->load($m2) === [], 'Another member sees none of it');
    $r = $w->toggle($m1, 'gtx-1650', 2000.0);
    ok_mw($r['full'] && !$r['added'] && count($w->load($m1)) === 3, 'At the member limit a new model is refused');
    $r = $w->toggle($m1, 'rx-6600', 4700.0);
    ok_mw(!$r['added'] && !$r['full'] && array_column($w->load($m1), 'slug') === ['rtx-3060', 'ryzen-5-5600'], 'Toggling a watched model removes it');
    $r = $w->toggle($m1, 'gtx-1650', 2000.0);
    ok_mw($r['added'] && WatchList::has($w->load($m1), 'gtx-1650'), 'With room, a new model is added');

    $store = new SubscriptionStore($db);
    $p = ['endpoint' => 'https://push.example.test/abc', 'p256dh' => str_repeat('A', 87), 'auth' => str_repeat('b', 22), 'watch' => []];
    $store->save($p, $m1);
    $row = $db->query("SELECT member_id FROM push_subscriptions WHERE endpoint='https://push.example.test/abc'")->fetch();
    ok_mw((int)$row['member_id'] === $m1, 'A push subscription made while signed in belongs to the member');

    (new MemberAuth($db))->deleteAccount($m1);
    ok_mw((int)$db->query("SELECT COUNT(*) FROM watch_items WHERE member_id=$m1")->fetchColumn() === 0, 'Deleting the account removes the watch list');
    ok_mw((int)$db->query("SELECT COUNT(*) FROM push_subscriptions WHERE member_id=$m1")->fetchColumn() === 0, 'Deleting the account removes its push subscriptions');
    ok_mw(count((new MemberWatchList($db))->load($m2)) === 0, 'The other member is untouched');
    echo "member_watch_list_test: PASS\n";
} finally {
    if ($db->inTransaction()) { $db->rollBack(); }
}
