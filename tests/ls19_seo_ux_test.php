<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Seo\SeoService as S;

/**
 * LS-19 C1/C2 + B1-B4: structured data and share picture of a product page, the product jump bar, the tickable guide checklist,
 * the "/" search shortcut and the same-category button on a mixed Compare table.
 */
function ok_l19(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
$root = dirname(__DIR__);

// ---- C1 JSON-LD ----
$priced = ['full_name' => 'NVIDIA GeForce RTX 3070', 'category_name' => 'GPU', 'brand_name' => 'NVIDIA', 'median' => 7800, 'q1' => 7300, 'q3' => 8199.4, 'valid_sample_size' => 13, 'accepted_observation_count' => 13];
$j = json_decode(S::productJsonLd($priced, 'https://x.test/price/a', 'https://x.test/a.jpg'), true);
ok_l19($j['@type'] === 'Product' && $j['brand']['name'] === 'NVIDIA' && $j['url'] === 'https://x.test/price/a', 'A Product with name, brand and URL');
ok_l19($j['offers']['@type'] === 'AggregateOffer' && $j['offers']['lowPrice'] === '7300' && $j['offers']['highPrice'] === '8199' && $j['offers']['offerCount'] === 13 && $j['offers']['priceCurrency'] === 'THB', 'The offer is the shown common range and its sample count');
ok_l19($j['offers']['itemCondition'] === 'https://schema.org/UsedCondition' && str_contains($j['description'], 'ไม่ใช่ราคาปิดดีล'), 'Used condition, and it says asking price, not sold');
ok_l19(!isset($j['aggregateRating']) && !isset($j['review']), 'No rating or review is ever claimed');
foreach ([['valid_sample_size' => 2], ['accepted_observation_count' => 0], ['median' => null], ['q1' => null]] as $cut) {
    $u = json_decode(S::productJsonLd(array_merge($priced, $cut), 'u', 'i'), true);
    ok_l19(!isset($u['offers']), 'No price data without a public price: ' . json_encode($cut));
}
ok_l19(!isset(json_decode(S::productJsonLd(array_merge($priced, ['brand_name' => 'Generic']), 'u', 'i'), true)['brand']), 'A generic spec class has no brand');
$evil = S::productJsonLd(array_merge($priced, ['full_name' => '</script><script>alert(1)</script>']), 'u', 'i');
ok_l19(!str_contains($evil, '</') && !str_contains($evil, '<script'), 'The JSON cannot close its script tag (HEX_TAG)');

// ---- C2 share picture ----
ok_l19(S::productImage('https://x.test/', 'nvidia-geforce-rtx-3070', $root . '/public') === 'https://x.test/assets/products/nvidia-geforce-rtx-3070.jpg', 'A product with a photo shares its photo');
ok_l19(S::productImage('https://x.test', 'no-such-model', $root . '/public') === 'https://x.test/assets/brand/hero-hardware.png', 'Otherwise the brand picture');
ok_l19(S::productImage('https://x.test', '../../.env', $root . '/public') === 'https://x.test/assets/brand/hero-hardware.png', 'A strange slug never reaches the file system');
$header = (string)file_get_contents($root . '/app/Views/layouts/header.php');
ok_l19(str_contains($header, '<script type="application/ld+json"><?= $jsonLd ?></script>') && str_contains($header, "if (!empty(\$jsonLd) && is_string(\$jsonLd))"), 'The layout prints the structured data only when a page passes it');

// ---- B1-B4 ----
$show = (string)file_get_contents($root . '/app/Views/price/show.php');
ok_l19(str_contains($show, '<nav id="product-nav" class="nav nav-pills mi-section-nav"') && substr_count($show, 'data-nav="') >= 4, 'The product page has the jump bar and labelled sections');
$site = (string)file_get_contents($root . '/public/assets/site.js');
ok_l19(str_contains($site, "\$('body').scrollspy({ target: '#product-nav', offset: 200 });") && str_contains($site, "\$('main [data-nav][id]')"), 'The bar is built from the sections present and marked by Bootstrap scrollspy');
ok_l19(str_contains($site, "if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey || \$(e.target).is('input, textarea, select, [contenteditable], .select2-search__field'))"), '"/" never fires while typing or with a modifier');
$guide = (string)file_get_contents($root . '/app/Views/modules/condition-guide.php');
ok_l19(str_contains($guide, 'class="custom-control-input"') && str_contains($guide, '<button type="reset"') && !preg_match('/\bon[a-z]+="/i', $guide) && !str_contains($guide, 'method="post"'), 'The guide checklist is a plain form: checkboxes, a native reset, no inline handler, nothing posted');
$cmp = (string)file_get_contents($root . '/app/Views/modules/compare.php');
ok_l19(str_contains($cmp, 'เทียบเฉพาะหมวด') && str_contains($cmp, 'count($s) >= 2'), 'A mixed Compare table offers a same-category table for categories with two or more models');
echo "ls19_seo_ux_test: PASS\n";
