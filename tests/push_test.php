<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Push\PriceAlertPlanner as P;
use App\Services\Push\PushPayload;

/** LS-86: push subscription validation and the price-change plan, on fixtures (no database, no network, nothing is sent). */
function ok_pu(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
$k = str_repeat('A', 87); $a = str_repeat('b', 22);
$good = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => $k, 'auth' => $a], 'watch' => [['slug' => 'rtx-3060', 'median' => 7800], ['slug' => 'Bad Slug!', 'median' => 1], ['slug' => 'ryzen-5-5600', 'median' => 'x']]];
$v = PushPayload::validate($good);
ok_pu($v['ok'] && count($v['watch']) === 2 && $v['watch'][0]['median'] === 7800.0 && $v['watch'][1]['median'] === null, 'A valid subscription keeps well-formed slugs only, a non-numeric median becomes null');
ok_pu(!PushPayload::validate(['endpoint' => 'http://x.test/p'] + $good)['ok'], 'A plain http endpoint is refused');
ok_pu(PushPayload::validate(['keys' => ['p256dh' => 'short', 'auth' => $a]] + $good)['error'] === 'p256dh', 'A malformed p256dh is refused');
ok_pu(PushPayload::validate(['keys' => ['p256dh' => $k, 'auth' => '!!']] + $good)['error'] === 'auth', 'A malformed auth secret is refused');
ok_pu(!PushPayload::validate([])['ok'], 'An empty body is refused');
$many = []; for ($i = 0; $i < 30; $i++) { $many[] = ['slug' => "m-$i", 'median' => 100]; }
ok_pu(count(PushPayload::validate(['watch' => $many] + $good)['watch']) === PushPayload::MAX_WATCH, 'The watch list is capped');

$w = [['slug' => 'a', 'median' => 1000.0], ['slug' => 'b', 'median' => 1000.0], ['slug' => 'c', 'median' => null], ['slug' => 'd', 'median' => 1000.0]];
$c = P::changes($w, ['a' => 940.0, 'b' => 1030.0, 'c' => 5.0]);
ok_pu(count($c) === 1 && $c[0]['slug'] === 'a' && $c[0]['change_percent'] === -6.0, 'Only a change of 5% or more is alerted; no baseline, no price today, or a small move is not');
ok_pu(P::due(null, 1000) && P::due(date('Y-m-d H:i:s', 0), 90000) && !P::due(date('Y-m-d H:i:s', 1000), 1000 + 3600), 'At most one notification per 24 hours');

$root = dirname(__DIR__);
$ctl = (string)file_get_contents($root . '/app/Controllers/PushController.php');
ok_pu(str_contains($ctl, "empty(\$config['push']['enabled'])") && str_contains($ctl, 'Csrf::verify'), 'The routes are off by default and CSRF-checked');
$cfg = (string)file_get_contents($root . '/config/app.php');
ok_pu(str_contains($cfg, "'push' => ['enabled' => filter_var(\$_ENV['PUSH_ENABLED'] ?? 'false'"), 'PUSH_ENABLED defaults to false');
ok_pu(is_file($root . '/database/migrations/036_push_subscriptions.sql') && !preg_match('/^s+(ip_|user_agent|email|phone|name)/mi', (string)file_get_contents($root . '/database/migrations/036_push_subscriptions.sql')), 'The table holds no personal fields');
echo "push_test: PASS\n";
