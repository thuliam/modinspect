<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Seo\SeoService as S;

function ok_seo(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

ok_seo(S::canonical('https://example.org/app/', '/price/rtx-3070/?utm=x#a') === 'https://example.org/app/price/rtx-3070', 'Canonical drops query, fragment and the trailing slash');
ok_seo(S::canonical('https://example.org', '/') === 'https://example.org/', 'The home page canonical keeps a single slash');

$robots = S::robotsTxt('https://example.org/app/');
foreach (['/admin', '/login', '/deal-checker/result/'] as $d) { ok_seo(str_contains($robots, "Disallow: $d\n"), "robots.txt disallows $d"); }
ok_seo(str_contains($robots, "Sitemap: https://example.org/app/sitemap.xml\n") && str_starts_with($robots, "User-agent: *\n"), 'robots.txt points at the sitemap');

$xml = S::sitemapXml('https://example.org', ['/', '/price', '/price/a&b', '/admin/products', '/login', '/price', '/deal-checker/result/abc']);
$doc = simplexml_load_string($xml);
ok_seo($doc !== false, 'The sitemap is well-formed XML');
$locs = array_map('strval', iterator_to_array($doc->url->loc ?? [], false));
$all = []; foreach ($doc->url as $u) { $all[] = (string)$u->loc; }
ok_seo($all === ['https://example.org/', 'https://example.org/price', 'https://example.org/price/a&b'], 'Private paths and duplicates are left out, ampersands survive escaping: ' . json_encode($all));

ok_seo(S::description('default', null) === 'default' && S::description('default', '  ') === 'default', 'Blank custom text falls back to the default');
ok_seo(S::description('d', "a   b\n<c>x</c>") === 'a b x', 'Whitespace is collapsed and tags are stripped');
$long = S::description('d', str_repeat('ก', 400));
ok_seo(mb_strlen($long, 'UTF-8') === 160 && str_ends_with($long, '…'), 'Long Thai text is cut to 160 characters on a character boundary');

echo "seo_service_test: PASS\n";
