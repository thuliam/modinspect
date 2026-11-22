<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Search\SearchQuery;
use App\Services\Catalog\CompareTable;

/**
 * LS-17 (docs/LS17_LONG_SPRINT_PLAN.md, docs/SECURITY_REVIEW_2026-11-19.md):
 *  S-1 the Compare page looks at a bounded number of m[] entries (400 unknown slugs took 5.6 s, one lookup each);
 *  S-2 a search query is clipped to SearchQuery::MAX_LENGTH before it builds SQL conditions;
 *  S-3 array-shaped query values ("?q[]=x") are treated as absent on the public price, deal and advisor pages.
 */
function ok_l17(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
$root = dirname(__DIR__);

// ---- S-2 ----
ok_l17(SearchQuery::clip('  rtx 3070  ') === 'rtx 3070', 'clip trims');
ok_l17(mb_strlen(SearchQuery::clip(str_repeat('ab ', 500))) <= SearchQuery::MAX_LENGTH, 'clip caps the length');
ok_l17(SearchQuery::clip(str_repeat('ก', 200)) === str_repeat('ก', SearchQuery::MAX_LENGTH), 'clip counts Thai characters, never cuts one in half');
ok_l17(SearchQuery::MAX_LENGTH >= 60, 'the cap is above the longest catalog name');
$product = (string)file_get_contents($root . '/app/Models/Product.php');
ok_l17(preg_match('/function search\(.*?SearchQuery::clip\(\$query\).*?if \(\$query !== \'\'\)/s', $product) === 1, 'Product::search clips before it builds conditions');

// ---- S-1 ----
$module = (string)file_get_contents($root . '/app/Controllers/ModuleController.php');
ok_l17(preg_match('/\$wanted=array_slice\(.*CompareTable::MAX_MODELS\*2\);.*?foreach\(\$wanted as/s', $module) === 1, 'Compare caps the entries before the lookup loop');

// ---- S-3 ----
final class L17Probe extends \App\Core\Controller { public static function q(string $k): string { return self::queryString($k); } }
$_GET = ['a' => 'x', 'b' => ['x'], 'c' => 5];
ok_l17(L17Probe::q('a') === 'x' && L17Probe::q('b') === '' && L17Probe::q('missing') === '' && L17Probe::q('c') === '', 'queryString keeps strings only');
$_GET = [];
$price = (string)file_get_contents($root . '/app/Controllers/PriceController.php');
ok_l17(!preg_match('/\(string\)\s*\(\$_GET\[/', $price) && substr_count($price, 'self::queryString(') === 6, 'The price index reads q, category and sort (and, LS-21, the spec filters in one loop; LS-51, the budget min and max) through queryString');
foreach (['app/Views/deal/form.php', 'app/Views/modules/seller-advisor.php'] as $v) {
    ok_l17(!preg_match('/\(string\)\s*\(\$_GET\[/', (string)file_get_contents($root . '/' . $v)), "$v never casts a raw query value to string");
}
// ---- B1 compare boxes accept a slug, a name or an unambiguous part of a name ----
$cat17 = [
    ['slug' => 'nvidia-geforce-rtx-3060', 'full_name' => 'NVIDIA GeForce RTX 3060', 'model_name' => 'RTX 3060'],
    ['slug' => 'nvidia-geforce-rtx-3060-ti', 'full_name' => 'NVIDIA GeForce RTX 3060 Ti', 'model_name' => 'RTX 3060 Ti'],
    ['slug' => 'amd-ryzen-5-5600', 'full_name' => 'AMD Ryzen 5 5600', 'model_name' => 'Ryzen 5 5600'],
    ['slug' => 'amd-ryzen-5-5600x', 'full_name' => 'AMD Ryzen 5 5600X', 'model_name' => 'Ryzen 5 5600X'],
];
ok_l17(CompareTable::resolve('amd-ryzen-5-5600', $cat17) === 'amd-ryzen-5-5600', 'A slug resolves to itself');
ok_l17(CompareTable::resolve('nvidia geforce rtx 3060', $cat17) === 'nvidia-geforce-rtx-3060', 'A full name in any case resolves, even when a longer name contains it');
ok_l17(CompareTable::resolve('RTX 3060', $cat17) === 'nvidia-geforce-rtx-3060', 'An exact model name resolves');
ok_l17(CompareTable::resolve('3060 ti', $cat17) === 'nvidia-geforce-rtx-3060-ti', 'Typed words that only one model has resolve');
ok_l17(CompareTable::resolve('3060', $cat17) === 'nvidia-geforce-rtx-3060', '"3060" means the base RTX 3060 (the Ti carries a variant word)');
$sized = [['slug' => 'g8', 'full_name' => 'NVIDIA GeForce RTX 3060 8GB', 'model_name' => ''], ['slug' => 'g12', 'full_name' => 'NVIDIA GeForce RTX 3060 12GB', 'model_name' => '']];
ok_l17(CompareTable::resolve('3060', $sized) === null, 'Two sizes of the same chip and no base model: ambiguous, never a guess');
ok_l17(CompareTable::resolve('5600x', $cat17) === 'amd-ryzen-5-5600x' && CompareTable::resolve('ryzen 5600', $cat17) === 'amd-ryzen-5-5600' && CompareTable::resolve('ryzen 5', $cat17) === null, 'Whole words only: "5600" never matches 5600X, and "ryzen 5" fits two models');
ok_l17(CompareTable::resolve('  ', $cat17) === null && CompareTable::resolve('rtx 9999', $cat17) === null, 'Blank or unknown gives null');
echo "ls17_hardening_test: PASS\n";

// ---- B2-B5 ----
$theme = (string)file_get_contents($root . '/public/assets/mi-theme.css');
ok_l17(str_contains($theme, 'body.mi .text-muted { color: var(--ci-text-2) !important; }'), 'Bootstrap text-muted is mapped to the readable secondary token on the dark theme (it measured 3.85:1)');
ok_l17(str_contains($theme, 'body.mi table tbody th {') && !preg_match('/part-option|component-nav/', $theme), 'Row headers are styled for the dark theme and the dead builder selectors are gone');
ok_l17(!str_contains((string)file_get_contents($root . '/app/Views/components/range-answer.php'), '>ช่วงราคาที่พบ (Q1–Q3)<'), 'The visible price label says "ช่วงราคาที่พบบ่อย", Q1–Q3 only in the tooltip');
$nf = (string)file_get_contents($root . '/app/Views/errors/404.php');
ok_l17(str_contains($nf, 'action="<?= $base ?>/price"') && str_contains($nf, '/deal-checker'), 'The 404 page offers a search and a way on');
ok_l17(count(\App\Services\Catalog\ConditionGuide::categories()) === 8 && str_contains((string)file_get_contents($root . '/app/Views/modules/condition-guide.php'), "'cooling' => ['icon' => 'fa-snowflake']"), 'The guide covers the eight /build categories, each with a picture or icon');
echo "ls17_hardening_test (B): PASS\n";
