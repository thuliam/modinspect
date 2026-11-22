<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Services\Benchmark\BenchmarkMatcher;

/** LS-32 A: strict name matching for benchmark rows; a near name, a duplicate row or a variant never links. Pure, no database. */
function ok_bm(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

ok_bm(BenchmarkMatcher::normalize('NVIDIA GeForce RTX 3080') === BenchmarkMatcher::normalize('GeForce RTX 3080'), 'Vendor words must not change the key');
ok_bm(BenchmarkMatcher::normalize('AMD Ryzen 7 5700X') !== BenchmarkMatcher::normalize('AMD Ryzen 7 5700X3D'), 'X and X3D must stay different');
ok_bm(BenchmarkMatcher::normalize('Intel Core i5-12400F') !== BenchmarkMatcher::normalize('Intel Core i5-12400'), 'F and non-F must stay different');

$csv = [
    ['name' => 'NVIDIA GeForce RTX 3080', 'url' => 'u1', 'inno_score' => '70'],
    ['name' => 'NVIDIA GeForce RTX 3080 Ti', 'url' => 'u2', 'inno_score' => '75'],
    ['name' => 'AMD Radeon RX 6600', 'url' => 'u3', 'inno_score' => '50'],
    ['name' => 'Radeon RX 6600', 'url' => 'u4', 'inno_score' => '51'],
];
$products = [
    ['id' => 1, 'full_name' => 'NVIDIA GeForce RTX 3080'],
    ['id' => 2, 'full_name' => 'NVIDIA GeForce RTX 3080 Ti'],
    ['id' => 3, 'full_name' => 'AMD Radeon RX 6600'],
    ['id' => 4, 'full_name' => 'NVIDIA GeForce RTX 3090'],
];
$plan = BenchmarkMatcher::plan($csv, $products);
ok_bm(array_column($plan['matched'], 'product_id') === [1, 2], 'Exactly the unambiguous names match: ' . json_encode(array_column($plan['matched'], 'product_id')));
ok_bm($plan['matched'][0]['row']['url'] === 'u1' && $plan['matched'][1]['row']['url'] === 'u2', 'The 3080 must not take the 3080 Ti row');
ok_bm($plan['ambiguous'] === ['AMD Radeon RX 6600'], 'Two rows with one normalised name are ambiguous, not guessed');
ok_bm($plan['unmatched_products'] === ['NVIDIA GeForce RTX 3090'], 'A model without a row is reported');
echo "benchmark_scores_test: PASS\n";
