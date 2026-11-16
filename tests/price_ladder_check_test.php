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

echo "price_ladder_check_test: PASS\n";
