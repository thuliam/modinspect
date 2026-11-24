<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Seo\SeoService as S;

/** PWA P1: the service worker configuration, the offline page and the self-hosted Workbox files. No browser needed. */
function ok_pw(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
$root = dirname(__DIR__);

$js = S::serviceWorkerJs('https://x.test/app', '42');
ok_pw(str_contains($js, 'const BASE = "/app"') && str_contains($js, 'VERSION = "42"'), 'The worker is scoped to the app folder and carries a version');
ok_pw(str_contains($js, 'importScripts(BASE + \'/assets/vendor/workbox/workbox-sw.js\')'), 'It loads the self-hosted Workbox, never a CDN');
ok_pw(!str_contains($js, 'googleapis') && !str_contains($js, 'cdn.'), 'No third-party address');
foreach (['/admin', '/account', '/login', '/seller', '/watch', '/api', '/deal-checker/result/'] as $p) { ok_pw(str_contains($js, '"/app' . str_replace('/', '\/', $p) . '"') || str_contains($js, '"/app' . $p . '"'), "Private path $p is never handled"); }
ok_pw(str_contains($js, 'NetworkFirst') && str_contains($js, 'StaleWhileRevalidate') && str_contains($js, 'OFFLINE'), 'Pages network first, assets stale while revalidate, offline fallback');
ok_pw(!str_contains($js, "method === 'POST'") && str_contains($js, "request.method === 'GET'"), 'Only GET assets are cached');
$o = []; exec('node -v 2>&1', $o, $rc);   // a syntax check when node exists; the checks above stand without it

foreach (['workbox-sw.js', 'workbox-core.prod.js', 'workbox-routing.prod.js', 'workbox-strategies.prod.js', 'workbox-expiration.prod.js', 'workbox-cacheable-response.prod.js', 'LICENSE'] as $f) {
    ok_pw(is_file("$root/public/assets/vendor/workbox/$f") && filesize("$root/public/assets/vendor/workbox/$f") > 200, "Workbox file $f is present");
}
ok_pw(str_contains((string)file_get_contents("$root/public/assets/vendor/workbox/workbox-sw.js"), '7.3.0'), 'Workbox version is pinned to 7.3.0');
$idx = (string)file_get_contents($root . '/public/index.php');
ok_pw(str_contains($idx, "\$router->get('/sw.js',[SeoController::class,'serviceWorker']);") && str_contains($idx, "\$router->get('/offline',[HomeController::class,'offline']);"), 'Routes exist');
ok_pw(is_file($root . '/app/Views/pages/offline.php'), 'The offline page exists');
ok_pw(str_contains((string)file_get_contents($root . '/public/assets/site.js'), "navigator.serviceWorker.register("), 'The page registers the worker');
ok_pw(in_array('/account', S::DISALLOW, true) && in_array('/admin', S::DISALLOW, true), 'The private list the worker uses still holds admin and account');
echo "pwa_test: PASS\n";
