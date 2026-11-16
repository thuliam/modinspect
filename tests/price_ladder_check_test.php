<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Admin\PriceLadderCheck as L;

function ok_pl2(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
$it = static fn(string $n, float $m): array => ['name' => $n, 'median' => $m];

ok_pl2(L::parse('NVIDIA GeForce RTX 4070 Ti SUPER') === ['family' => 'rtx4', 'number' => 4070, 'rank' => 4], 'Ti SUPER parses as the top 4070 tier');
ok_pl2(L::parse('AMD Radeon RX 6600 XT')['family'] === 'rx6' && L::parse('NVIDIA GeForce GTX 1650')['family'] === 'gtx16', 'AMD generation and GTX 16 are separate families');
ok_pl2(L::parse('AMD Ryzen 5 5600') === null && L::parse('RAM DDR5-5600 32GB') === null, 'CPUs and RAM are not ladder members');

// a sane ladder has no flags
ok_pl2(L::violations([$it('NVIDIA GeForce RTX 3060', 8000), $it('NVIDIA GeForce RTX 3060 Ti', 9000), $it('NVIDIA GeForce RTX 3070', 9500), $it('NVIDIA GeForce RTX 3080', 14000)]) === [], 'A rising ladder is not flagged');
// 4070 priced clearly below 4060 is flagged, with the ratio
$v = L::violations([$it('NVIDIA GeForce RTX 4060', 12000), $it('NVIDIA GeForce RTX 4070', 9000)]);
ok_pl2(count($v) === 1 && $v[0]['higher'] === 'NVIDIA GeForce RTX 4070' && $v[0]['ratio'] === 0.75, 'A higher tier far below a lower tier is flagged');
// within tolerance (95%) is not flagged; just under 90% is
ok_pl2(L::violations([$it('NVIDIA GeForce RTX 4060', 10000), $it('NVIDIA GeForce RTX 4070', 9500)]) === [], 'Within tolerance is not flagged');
ok_pl2(count(L::violations([$it('NVIDIA GeForce RTX 4060', 10000), $it('NVIDIA GeForce RTX 4070', 8900)])) === 1, 'Just under the tolerance is flagged');
// suffix order inside one number: Ti above plain
ok_pl2(count(L::violations([$it('NVIDIA GeForce RTX 3060', 9000), $it('NVIDIA GeForce RTX 3060 Ti', 7000)])) === 1, 'A Ti priced below the plain model is flagged');
// different generations never compare (a 4060 is not judged against a 3090)
ok_pl2(L::violations([$it('NVIDIA GeForce RTX 3090', 40000), $it('NVIDIA GeForce RTX 4060', 9000)]) === [], 'Generations are independent');
// zero or missing medians are ignored, nothing is changed (pure function)
ok_pl2(L::violations([$it('NVIDIA GeForce RTX 3060', 0), $it('NVIDIA GeForce RTX 3070', 9000)]) === [], 'A zero median is ignored');

// ---- LS-3 J-8: CPU and RAM ladders
ok_pl2(L::parseCpu('AMD Ryzen 5 5600X') === ['family' => 'ryzen5-x', 'number' => 5600, 'rank' => 0], 'A Ryzen 5xxx X part: generation 5, suffix class x, model number 5600');
ok_pl2(L::parseCpu('AMD Ryzen 5 5600')['family'] !== L::parseCpu('AMD Ryzen 5 5600X')['family'], 'Suffix classes never share a family (X is not compared with non-X)');
ok_pl2(L::parseCpu('Intel Core i5-12400F') === ['family' => 'core12-f', 'number' => 12400, 'rank' => 0] && L::parseCpu('Intel Core i5-13400F')['family'] === 'core13-f', 'Core generations are separate families');
ok_pl2(L::parseCpu('Intel Core i7-14700KF')['family'] === 'core14-kf', 'KF is its own class');
ok_pl2(L::parseCpu('AMD Ryzen 7 7800X3D')['family'] === 'ryzen7-x3d' && L::parseCpu('AMD Ryzen 7 5800X3D')['family'] === 'ryzen5-x3d', 'X3D parts are their own class per generation');
ok_pl2(L::parseCpu('NVIDIA GeForce RTX 3070') === null && L::parseCpu('RAM DDR4-3200 16GB') === null, 'GPUs and RAM are not CPUs');
ok_pl2(L::parseRam('RAM DDR5-5600 32GB') === ['family' => 'ddr5-5600', 'number' => 32, 'rank' => 0] && L::parseRam('Corsair Vengeance LPX 32GB DDR4-3200') === null && L::parseRam('NVMe M.2 SSD 1TB') === null, 'RAM parse needs "DDR<gen>-<speed> <n>GB"; other naming and SSDs are not on the ladder');
ok_pl2(L::parseAny('NVIDIA GeForce RTX 3070')['family'] === 'rtx3' && L::parseAny('RAM DDR4-3200 16GB')['family'] === 'ddr4-3200' && L::parseAny('NVMe M.2 SSD 1TB') === null, 'parseAny picks the right ladder or none');

// RAM: a bigger kit of the same generation and speed must not cost clearly less
ok_pl2(L::violations([$it('RAM DDR5-5600 16GB', 5350), $it('RAM DDR5-5600 32GB', 11350), $it('RAM DDR5-5600 64GB', 22990)]) === [], 'A rising RAM ladder is not flagged');
$v = L::violations([$it('RAM DDR4-3200 16GB', 3000), $it('RAM DDR4-3200 32GB', 2500)]);
ok_pl2(count($v) === 1 && $v[0]['higher'] === 'RAM DDR4-3200 32GB' && $v[0]['lower'] === 'RAM DDR4-3200 16GB', 'A 32GB kit priced below the 16GB kit is flagged');
ok_pl2(L::violations([$it('RAM DDR4-3200 16GB', 3000), $it('RAM DDR4-3600 32GB', 2500)]) === [] && L::violations([$it('RAM DDR4-3200 16GB', 3000), $it('RAM DDR5-3200 32GB', 2500)]) === [], 'Different speeds or generations are never compared');
// CPU: inside one generation and suffix class a higher model number must not be priced clearly lower
$v = L::violations([$it('AMD Ryzen 5 5600X', 5000), $it('AMD Ryzen 7 5700X', 3500)]);
ok_pl2(count($v) === 1 && $v[0]['higher'] === 'AMD Ryzen 7 5700X', 'A 5700X priced well below a 5600X is flagged');
ok_pl2(L::violations([$it('AMD Ryzen 5 5600', 5000), $it('AMD Ryzen 5 5600X', 3500)]) === [], 'A non-X part is not compared with an X part');
ok_pl2(L::violations([$it('Intel Core i5-12400F', 4000), $it('Intel Core i5-13400F', 3000)]) === [], 'Different Core generations are not compared');
ok_pl2(L::violations([$it('Intel Core i3-12100F', 2200), $it('Intel Core i5-12400F', 3800), $it('Intel Core i7-12700F', 7000)]) === [], 'A rising Core ladder is not flagged');
// the three ladders never mix
ok_pl2(L::violations([$it('NVIDIA GeForce RTX 4060', 12000), $it('RAM DDR4-3200 32GB', 100), $it('AMD Ryzen 5 5600X', 50)]) === [], 'A cheap RAM kit or CPU never flags a GPU');

echo "price_ladder_check_test: PASS\n";
