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
ok_sf(count(F::apply($products, $specs, 'case', ['socket' => 'AM4'])) === 12, 'A category without special filters ignores them');

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

// LS-22: interface, PSU watts, GPU chip maker, cooler type; each read from the name only when the name says it outright
$more = [
    $p(40, 'storage', 'NVMe M.2 SSD 1TB'), $p(41, 'storage', 'SATA SSD 512GB'), $p(42, 'storage', 'Mystery drive 1TB'),
    $p(50, 'psu', 'Corsair RM750e 750W'), $p(51, 'psu', 'Seasonic 550W'), $p(52, 'psu', 'No watt PSU'),
    $p(60, 'gpu', 'NVIDIA GeForce RTX 3070'), $p(61, 'gpu', 'AMD Radeon RX 6800'), $p(62, 'gpu', 'Intel Arc A750'), $p(63, 'gpu', 'Mystery card'),
    $p(70, 'cooling', 'Noctua NH-D15 Air Cooler'), $p(71, 'cooling', 'Arctic Liquid Freezer 240 AIO'), $p(72, 'cooling', 'Unknown cooler'),
];
ok_sf($ids(F::apply($more, [], 'storage', ['iface' => 'NVMe'])) === [40], 'Storage interface NVMe from the name (M.2 counts)');
ok_sf($ids(F::apply($more, [], 'storage', ['iface' => 'SATA'])) === [41], 'Storage interface SATA');
ok_sf($ids(F::apply($more, [], 'psu', ['watt' => '750'])) === [50], 'PSU watt filter');
ok_sf(!in_array(52, $ids(F::apply($more, [], 'psu', ['watt' => '550'])), true), 'A PSU with no watt in the name never matches');
ok_sf($ids(F::apply($more, [], 'gpu', ['chip' => 'AMD'])) === [61] && $ids(F::apply($more, [], 'gpu', ['chip' => 'Intel'])) === [62] && $ids(F::apply($more, [], 'gpu', ['chip' => 'NVIDIA'])) === [60], 'GPU chip maker');
ok_sf($ids(F::apply($more, [], 'cooling', ['ctype' => 'AIO'])) === [71] && $ids(F::apply($more, [], 'cooling', ['ctype' => 'Air'])) === [70], 'Cooler type');
ok_sf(F::options($more, [], 'psu') === ['watt' => ['550', '750']], 'PSU watt options sort numerically');
ok_sf(isset(F::options($more, [], 'gpu')['chip']) && !in_array('', F::options($more, [], 'gpu')['chip'], true), 'GPU chip options exclude unknown');

// LS-23 B5: a collected spec beats the guess from the name
$a1 = F::attrs($p(80, 'ram', 'Mystery 16GB DDR4-2666'), ['speed_mhz' => '3200']);
ok_sf($a1['speed'] === '3200', 'speed_mhz spec beats the speed in the name');
ok_sf(F::attrs($p(81, 'storage', 'Drive 1TB'), ['interface' => 'NVMe PCIe 4.0'])['iface'] === 'NVMe' && F::attrs($p(82, 'psu', 'PSU'), ['psu_capacity_watt' => '850'])['watt'] === '850', 'interface and PSU watt specs are used');
ok_sf(F::attrs($p(83, 'cooling', 'Noctua Air Tower AIO-compatible'), ['cooler_type' => 'aio'])['ctype'] === 'AIO', 'cooler_type spec beats the name');

// LS-72: RAM by brand, form factor and kit; the product columns come before the name and the reference specs
$r = static fn(int $id, string $name, string $brand, string $type, string $ff, int $cap, int $speed, ?int $kit): array => ['id' => $id, 'category_slug' => 'ram', 'full_name' => $name, 'brand_name' => $brand, 'memory_type' => $type, 'form_factor' => $ff, 'capacity_gb' => $cap, 'memory_speed_mhz' => $speed, 'kit_modules' => $kit];
$ram = [
    $r(90, 'Kingston FURY Beast DDR5-6000 32GB (2x16GB)', 'Kingston', 'DDR5', 'DIMM', 32, 6000, 2),
    $r(91, 'RAM DDR4-3200 16GB', 'Generic', 'DDR4', 'DIMM', 16, 3200, null),
    $r(92, 'Corsair Vengeance DDR4-3200 16GB SO-DIMM (2x8GB)', 'Corsair', 'DDR4', 'SO-DIMM', 16, 3200, 2),
    $r(93, 'Kingston FURY Impact DDR5-5600 16GB SO-DIMM', 'Kingston', 'DDR5', 'SO-DIMM', 16, 5600, 1),
];
$ids2 = static fn(array $rows): array => array_map(static fn($x) => $x['id'], $rows);
ok_sf($ids2(F::apply($ram, [], 'ram', ['brand' => 'Kingston'])) === [90, 93], 'RAM by brand');
ok_sf($ids2(F::apply($ram, [], 'ram', ['form' => 'SO-DIMM'])) === [92, 93], 'RAM by form factor');
ok_sf($ids2(F::apply($ram, [], 'ram', ['form' => 'SO-DIMM', 'brand' => 'Kingston', 'mem' => 'DDR5'])) === [93], 'brand, form factor and type combine');
ok_sf($ids2(F::apply($ram, [], 'ram', ['kit' => '2'])) === [90, 92], 'RAM by modules in the kit');
ok_sf($ids2(F::apply($ram, [], 'ram', ['speed_min' => '5600', 'form' => 'DIMM'])) === [90], 'speed range and form factor combine');
$ro = F::options($ram, [], 'ram');
ok_sf(($ro['brand'] ?? []) === ['Corsair', 'Generic', 'Kingston'] && ($ro['form'] ?? []) === ['DIMM', 'SO-DIMM'] && ($ro['kit'] ?? []) === ['1', '2'] && ($ro['cap'] ?? []) === ['16', '32'], 'RAM options list only the values that exist: ' . json_encode($ro));
ok_sf(F::attrs($r(94, 'Old name DDR3-1333 4GB', 'X', 'DDR4', 'DIMM', 8, 3200, null), ['memory_type' => 'DDR3', 'capacity_gb' => '4', 'speed_mhz' => '1333'])['mem'] === 'DDR4', 'the product column beats the reference spec and the name');
ok_sf(F::label('form', 'ram') === 'ฟอร์มแฟกเตอร์' && F::label('form', 'motherboard') === 'ขนาดบอร์ด', 'form factor is labelled per category');
ok_sf(!isset(F::attrs(['id' => 95, 'category_slug' => 'motherboard', 'full_name' => 'Board', 'brand_name' => 'ASUS', 'memory_type' => 'DDR5'], [])['brand']), 'brand is a RAM filter only');

echo "spec_filter_test: PASS\n";
