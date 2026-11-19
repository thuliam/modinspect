<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Catalog\SpecFilter as F;

/**
 * LS-21: category-specific filters of the price index (socket, RAM type/size/speed range, VRAM). Pure.
 * A model that lacks the fact never matches a filter on it (nothing is guessed); RAM facts may come from the model name when no reference spec exists;
 * options list only values that exist in the chosen category.
 */
function ok_sf(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$p = static fn(int $id, string $cat, string $name): array => ['id' => $id, 'category_slug' => $cat, 'full_name' => $name];
$products = [
    $p(1, 'cpu', 'AMD Ryzen 5 5600'), $p(2, 'cpu', 'Intel Core i5-12400F'), $p(3, 'cpu', 'AMD Ryzen 7 7800X3D'), $p(4, 'cpu', 'No Spec CPU'),
    $p(10, 'ram', 'Corsair LPX 32GB DDR4-3200'), $p(11, 'ram', 'Kingston 16GB DDR5-5600'), $p(12, 'ram', 'Old 8GB DDR3-1600'), $p(13, 'ram', 'Mystery stick'),
    $p(20, 'storage', 'NVMe M.2 SSD 1TB'), $p(21, 'storage', 'SATA SSD 512GB'),
    $p(30, 'gpu', 'RTX 3070'), $p(31, 'gpu', 'RTX 4090'),
];
$specs = [1 => ['socket' => 'AM4'], 2 => ['socket' => 'LGA1700'], 3 => ['socket' => 'AM5'], 30 => ['vram_gb' => '8'], 31 => ['vram_gb' => '24'], 11 => ['capacity_gb' => '16', 'memory_type' => 'DDR5']];
$ids = static fn(array $rows): array => array_map(static fn(array $r): int => (int)$r['id'], $rows);

ok_sf($ids(F::apply($products, $specs, 'cpu', ['socket' => 'AM4'])) === [1], 'Socket filter returns only AM4');
ok_sf(!in_array(4, $ids(F::apply($products, $specs, 'cpu', ['socket' => 'AM4'])), true), 'A model without a socket fact never matches a socket filter');
ok_sf(count(F::apply($products, $specs, 'cpu', [])) === 12, 'No filter changes nothing');
ok_sf(count(F::apply($products, $specs, 'cpu', ['socket' => ''])) === 12, 'An empty value is ignored');
ok_sf(count(F::apply($products, $specs, 'psu', ['socket' => 'AM4'])) === 12, 'A category without special filters ignores them');

ok_sf($ids(F::apply($products, $specs, 'ram', ['mem' => 'DDR4'])) === [10], 'RAM type read from the name');
ok_sf($ids(F::apply($products, $specs, 'ram', ['mem' => 'DDR5', 'cap' => '16'])) === [11], 'RAM type + size combine');
ok_sf($ids(F::apply($products, $specs, 'ram', ['speed_min' => '3000'])) === [10, 11], 'Speed minimum');
ok_sf($ids(F::apply($products, $specs, 'ram', ['speed_min' => '2000', 'speed_max' => '3200'])) === [10], 'Speed range is inclusive');
ok_sf(!in_array(13, $ids(F::apply($products, $specs, 'ram', ['speed_min' => '1'])), true), 'A model with no speed never matches a speed range');
ok_sf($ids(F::apply($products, $specs, 'ram', ['speed_min' => 'abc'])) === [10, 11, 12, 13] || $ids(F::apply($products, $specs, 'ram', ['speed_min' => 'abc'])) === [10, 11, 12], 'A non-numeric bound is ignored, not an error');
ok_sf($ids(F::apply($products, $specs, 'storage', ['cap' => '1000'])) === [20], '1TB is 1000 GB');
ok_sf($ids(F::apply($products, $specs, 'gpu', ['vram' => '24'])) === [31], 'VRAM filter');

$o = F::options($products, $specs, 'ram');
ok_sf($o['mem'] === ['DDR3', 'DDR4', 'DDR5'], 'RAM type options are the types that exist: ' . json_encode($o));
ok_sf($o['cap'] === ['8', '16', '32'], 'Size options sort numerically: ' . json_encode($o['cap'] ?? null));
ok_sf($o['speed'] === ['1600', '3200', '5600'], 'Speed options sort numerically');
ok_sf(isset(F::options($products, $specs, 'cpu')['socket']) && !isset(F::options($products, $specs, 'cpu')['mem']), 'CPU offers a socket filter only');
ok_sf(F::options([$p(1, 'cpu', 'One')], [1 => ['socket' => 'AM4']], 'cpu') === [], 'A filter with a single possible value is not offered');

echo "spec_filter_test: PASS\n";
