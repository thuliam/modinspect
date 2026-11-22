<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Seo\SeoService as S;

/**
 * LS-20 D2-D4: the largest picture of a page is fetched first, priced models lead the home page, and the site has a web app manifest.
 */
function ok_sp(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
$root = dirname(__DIR__);

// ---- D2 ----
$hw = (string)file_get_contents($root . '/app/Views/components/hw-art.php');
ok_sp(str_contains($hw, "bool \$eager = false") && str_contains($hw, "\$eager ? 'fetchpriority=\"high\"' : 'loading=\"lazy\"'"), 'A product photo is lazy by default and fetched first when asked');
ok_sp(str_contains((string)file_get_contents($root . '/app/Views/price/show.php'), "mi_product_photo(\$product, \$base, 'mi-photo', true)"), 'The photo at the top of a product page is the eager one');
ok_sp(str_contains((string)file_get_contents($root . '/app/Views/home/index.php'), '/original.webp" fetchpriority="high"'), 'The home hero picture is fetched first');
foreach (['product-card.php', 'product-list.php'] as $c) {
    preg_match_all('/mi_product_photo\(([^;]*?)\);/', (string)file_get_contents($root . '/app/Views/components/' . $c), $calls);
    ok_sp(count($calls[1]) >= 1 && !array_filter($calls[1], static fn(string $a): bool => str_contains($a, 'true')), "Pictures in lists ($c) stay lazy (no eager argument)");
}

// ---- D3 ----
$model = (string)file_get_contents($root . '/app/Models/Product.php');
ok_sp(preg_match('/function popular\(.*?ORDER BY \(pi\.median IS NOT NULL AND pi\.valid_sample_size > 0 AND accepted_observation_count > 0\) DESC, p\.is_popular DESC/s', $model) === 1, 'popular() puts models with a public price first (same rule as the product card), then the popular ones');
global $config;
$rows = (new App\Models\Product())->popular();   // runs on the isolated test database: the ORDER BY must be valid SQL on this server
ok_sp(is_array($rows) && count($rows) <= 6, 'popular() runs');

// ---- D4 ----
$m = json_decode(S::manifestJson('ModInspect', 'https://x.test/app/'), true);
ok_sp(is_array($m) && $m['name'] === 'ModInspect' && $m['start_url'] === 'https://x.test/app/' && $m['scope'] === 'https://x.test/app/' && $m['display'] === 'standalone' && $m['lang'] === 'th', 'The manifest has name, absolute start and scope, standalone display, Thai');
ok_sp(count($m['icons']) === 2 && $m['icons'][0]['sizes'] === '192x192' && $m['icons'][1]['sizes'] === '512x512' && str_starts_with($m['icons'][0]['src'], 'https://x.test/app/assets/brand/'), 'Two icons, absolute under the base URL');
foreach ($m['icons'] as $ic) { ok_sp(is_file($root . '/public/assets/brand/' . basename($ic['src'])), basename($ic['src']) . ' exists'); [$w] = getimagesize($root . '/public/assets/brand/' . basename($ic['src'])); ok_sp($w === (int)$ic['sizes'], basename($ic['src']) . ' really is ' . $ic['sizes']); }
ok_sp(mb_strlen($m['short_name']) <= 12, 'A short name that fits under a home-screen icon');
ok_sp(str_contains((string)file_get_contents($root . '/public/assets/ci-tokens.css'), $m['background_color']) && $m['background_color'] === $m['theme_color'], 'The colours are the CI canvas, the same as the page theme-color');
ok_sp(str_contains((string)file_get_contents($root . '/public/index.php'), "\$router->get('/manifest.webmanifest',[SeoController::class,'manifest']);"), 'The route exists');
$hd = (string)file_get_contents($root . '/app/Views/layouts/header.php');
ok_sp(str_contains($hd, '<link rel="manifest" href="<?= $base ?>/manifest.webmanifest">') && str_contains($hd, 'content="#0a0d14"'), 'Every page links the manifest');
ok_sp(!str_contains(json_encode($m), 'serviceWorker') && !preg_match('/serviceWorker/', (string)file_get_contents($root . '/public/assets/site.js')), 'No service worker: it is a shortcut, not an offline promise');
ok_sp(str_contains((string)file_get_contents($root . '/cli/smoke_check.php'), "'/manifest.webmanifest'"), 'The post-deploy smoke check covers it');
echo "ls20_speed_pwa_test: PASS\n";
