<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Router;
use App\Services\Catalog\CompareTable;
use App\Services\Catalog\PriceList;

/**
 * LS-16 review fixes (docs/LS16_GAP_REVIEW_AND_LONG_SPRINT.md):
 *  B-1 a HEAD request reaches the GET handler (it was a 404 on every page);
 *  U-1 the price index sorts and filters the same rows, unpriced models always last on a price order;
 *  U-2 the empty Compare page offers same-category priced pairs;
 *  U-3/U-4 the condition guide uses the site's category cards, every page has an og:image.
 */
function ok_ls16(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

// ---- B-1 router ----
final class Ls16Probe { public static array $hits = []; public function page(string $id = ''): void { self::$hits[] = 'get:' . $id; } public function save(): void { self::$hits[] = 'post'; } }
$r = new Router();
$r->get('/x/{id}', [Ls16Probe::class, 'page']);
$r->post('/x', [Ls16Probe::class, 'save']);
$r->dispatch('HEAD', '/x/7');
ok_ls16(Ls16Probe::$hits === ['get:7'], 'HEAD reaches the GET handler with its parameter: ' . json_encode(Ls16Probe::$hits));
$r->dispatch('GET', '/x/8');
ok_ls16(end(Ls16Probe::$hits) === 'get:8', 'GET still works');
$r->dispatch('POST', '/x');
ok_ls16(end(Ls16Probe::$hits) === 'post' && count(Ls16Probe::$hits) === 3, 'POST still reaches only the POST handler');

// ---- U-1 price list ----
$p = static fn(int $id, string $name, ?float $median, int $n = 5, string $cat = 'CPU') => ['id' => $id, 'slug' => 's' . $id, 'full_name' => $name, 'category_name' => $cat,
    'median' => $median, 'valid_sample_size' => $median === null ? 0 : $n, 'accepted_observation_count' => $median === null ? 0 : $n];
$rows = [$p(1, 'Ryzen 7', 9000.0, 3), $p(2, 'Core i5', null), $p(3, 'Ryzen 5', 4000.0, 9), $p(4, 'Arc', 6000.0, 9), $p(5, 'Athlon', null)];
$ids = static fn(array $list): array => array_map(static fn($x) => $x['id'], $list);
ok_ls16($ids(PriceList::apply($rows, '', false)) === [1, 2, 3, 4, 5], 'Default keeps the catalog order (popular first)');
ok_ls16($ids(PriceList::apply($rows, 'nonsense', false)) === [1, 2, 3, 4, 5], 'An unknown sort key keeps the catalog order');
ok_ls16($ids(PriceList::apply($rows, 'price_asc', false)) === [3, 4, 1, 2, 5], 'Price low to high, unpriced last in their original order');
ok_ls16($ids(PriceList::apply($rows, 'price_desc', false)) === [1, 4, 3, 2, 5], 'Price high to low, unpriced still last (never "most expensive")');
ok_ls16($ids(PriceList::apply($rows, 'samples', false)) === [3, 4, 1, 2, 5], 'Most samples first, ties keep the catalog order');
ok_ls16($ids(PriceList::apply($rows, 'name', false)) === [4, 5, 2, 3, 1], 'Name A to Z');
ok_ls16($ids(PriceList::apply($rows, 'price_asc', true)) === [3, 4, 1], 'Priced only drops models without a price range');
$half = $p(6, 'Half', 5000.0); $half['accepted_observation_count'] = 0;
ok_ls16(!PriceList::isPriced($half) && PriceList::apply([$half], '', true) === [], 'A median without an accepted observation is not a price (same rule as the product card)');

// ---- U-2 compare pairs ----
$cat = [$p(1, 'CPU A', 1.0), $p(2, 'CPU B', null), $p(3, 'CPU C', 2.0), $p(4, 'CPU D', 3.0), $p(10, 'GPU A', 1.0, 5, 'GPU'), $p(20, 'RAM A', 1.0, 5, 'RAM'), $p(21, 'RAM B', 2.0, 5, 'RAM')];
$pairs = CompareTable::suggestPairs($cat);
ok_ls16(count($pairs) === 2 && $pairs[0]['category'] === 'CPU' && $pairs[0]['a']['slug'] === 's1' && $pairs[0]['b']['slug'] === 's3', 'One pair per category, the first two PRICED models: ' . json_encode($pairs));
ok_ls16($pairs[1]['category'] === 'RAM', 'A category with a single priced model gives no pair (GPU skipped)');
ok_ls16(count(CompareTable::suggestPairs($cat, 1)) === 1 && CompareTable::suggestPairs([]) === [], 'The limit holds and an empty catalog gives nothing');

// ---- U-3 / U-4 views ----
$root = dirname(__DIR__);
$guide = (string)file_get_contents($root . '/app/Views/modules/condition-guide.php');
ok_ls16(str_contains($guide, 'class="mi-cat reveal"') && str_contains($guide, 'mi_art_img('), 'The condition guide index uses the home page category cards');
$header = (string)file_get_contents($root . '/app/Views/layouts/header.php');
ok_ls16(str_contains($header, '<meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">') && str_contains($header, '$ogDefault = rtrim((string) $config[\'base_url\'], \'/\') . \'/assets/brand/hero-hardware.png\''), 'og:image is an absolute, escaped URL (brand picture unless a page passes its own, LS-19 C2)');
ok_ls16(is_file($root . '/public/assets/brand/hero-hardware.png'), 'The og:image file exists');
$index = (string)file_get_contents($root . '/app/Views/price/index.php');
ok_ls16(substr_count($index, 'data-autosubmit') === 2 && !str_contains($index, 'CPU/GPU แล้ว'), 'The price index has the two auto-applied controls and no longer says it covers only CPU/GPU');
ok_ls16(str_contains((string)file_get_contents($root . '/public/assets/site.js'), "\$('[data-autosubmit]').on('change'"), 'site.js wires data-autosubmit');
echo "ls16_review_fixes_test: PASS\n";
