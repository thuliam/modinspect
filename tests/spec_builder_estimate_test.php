<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\SpecBuilderService;

/**
 * LS-10 Q-2: the numbers and sentences a visitor gets from /build. Pure (no database): totals, the budget reserve, which parts count, the default
 * pick when nothing is chosen, and the wording of the notes. The wording cases were found by reading SpecBuilderService (LS-10 Q-1): the "missing
 * categories" note printed raw English keys inside Thai text, the 4K note named RTX 3070 whatever GPU was chosen, and a build over budget still said
 * only "reserve ฿0".
 */
function ok_se(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$part = static fn(int $id, string $slug, string $cat, float $q1, float $med, float $q3, array $extra = []): array => array_merge(
    ['id' => $id, 'slug' => $slug, 'category_name' => $cat, 'full_name' => $slug, 'q1' => $q1, 'median' => $med, 'q3' => $q3, 'valid_sample_size' => 5, 'accepted_observation_count' => 5, 'confidence_score' => 70.0, 'socket' => '', 'memory_type' => ''],
    $extra
);
$cpu = $part(1, 'intel-core-i5-12400f', 'CPU', 3800, 4200, 4600);
$cpuHigh = $part(2, 'amd-ryzen-7-5700x3d', 'CPU', 7000, 7800, 8500);
$gpu3070 = $part(3, 'nvidia-geforce-rtx-3070', 'GPU', 7800, 8400, 9000);
$gpu4090 = $part(4, 'nvidia-geforce-rtx-4090', 'GPU', 55000, 60000, 65000);
$ram = $part(5, 'ram-ddr4-16', 'RAM', 600, 700, 800);
$unpriced = $part(6, 'ssd-unpriced', 'Storage', 0, 0, 0, ['median' => null, 'valid_sample_size' => 0, 'accepted_observation_count' => 0]);
$all = [$cpu, $cpuHigh, $gpu3070, $gpu4090, $ram, $unpriced];
$gen = static fn(array $ids, float $budget = 30000.0, string $use = 'gaming', string $res = '1080p', string $mix = 'used', ?array $catalog = null, array $refs = []): array => (new SpecBuilderService())->generate($catalog ?? $all, $budget, $use, $res, $mix, $ids, $refs);

// totals are the sums of the chosen parts' low, median and high
$r = $gen(['cpu' => 1, 'gpu' => 3, 'ram' => 5]);
ok_se(count($r['items']) === 3 && $r['low'] === 12200.0 && $r['expected'] === 13300.0 && $r['high'] === 14400.0, 'Totals are the sums of the chosen parts: ' . json_encode([$r['low'], $r['expected'], $r['high']]));
ok_se(str_contains($r['notes'], '฿16,700'), 'The reserve is budget minus the expected total (30,000 - 13,300): ' . $r['notes']);

// LS-14 OD-2 (owner, 2026-11-18): a chosen part without a market price is KEPT in the build so the visitor can finish a whole PC, but it adds
// nothing to the used-price totals and says "ยังไม่มีราคา" (before LS-14 it was dropped silently)
$r = $gen(['cpu' => 1, 'storage' => 6]);
ok_se(array_column($r['items'], 'component_role') === ['cpu', 'storage'] && $r['expected'] === 4200.0, 'A chosen unpriced part stays in the build: ' . json_encode(array_column($r['items'], 'component_role')));
ok_se((float)$r['items'][1]['price_expected'] === 0.0 && str_contains((string)$r['items'][1]['notes'], 'ยังไม่มีราคา') && str_contains($r['notes'], 'ไม่ได้รวมในยอด'), 'It adds 0 and is labelled; the total says a part was left out: ' . $r['notes']);
// with an admin's new-price reference it uses that price, labelled, and never as a used price
$r = $gen(['cpu' => 1, 'storage' => 6], 30000.0, 'gaming', '1080p', 'used', null, [6 => ['price' => 1990, 'source_url' => 'https://shop.example/ssd', 'checked_on' => '2026-11-18']]);
ok_se((float)$r['items'][1]['price_expected'] === 1990.0 && str_contains((string)$r['items'][1]['notes'], 'ราคาของใหม่อ้างอิง') && (float)$r['items'][1]['confidence_score'] === 0.0, 'A new-price reference fills the price, labelled, with no confidence');
ok_se(str_contains($r['notes'], 'ราคาของใหม่อ้างอิง'), 'The notes say the total includes a new price');
// each of the three conditions on its own is enough to keep a part from counting as a used price (a median alone, a sample size alone, an accepted-row count alone)
$noMedian = $part(7, 'ssd-no-median', 'Storage', 500, 0, 700, ['median' => null]);
$noSample = $part(8, 'psu-no-sample', 'PSU', 800, 900, 1000, ['valid_sample_size' => 0]);
$noAccepted = $part(9, 'case-no-accepted', 'Case', 600, 700, 800, ['accepted_observation_count' => 0]);
$r = $gen(['cpu' => 1, 'storage' => 7, 'psu' => 8, 'case' => 9], 30000.0, 'gaming', '1080p', 'used', [$cpu, $noMedian, $noSample, $noAccepted]);
ok_se(array_column($r['items'], 'price_expected') == [$cpu['median'], 0, 0, 0], 'A missing median, a zero sample size or zero accepted rows each keep a part from adding a price: ' . json_encode(array_column($r['items'], 'price_expected')));

// nothing chosen: the default pick is the recommender's balanced plan (LS-13 S-6), no longer three hard-coded models
$r = $gen([], 20000.0, 'gaming');
ok_se(array_column($r['items'], 'product_id') === [1, 3, 5], 'Gaming at 20,000: the balanced plan takes the cpu, the gpu and the ram of the catalog: ' . json_encode(array_column($r['items'], 'product_id')));
ok_se($r['expected'] === 13300.0, 'The default plan fits what is left after the unpriced categories (13,400): ' . $r['expected']);
$r = $gen([], 20000.0, 'gaming', '1080p', 'used', [$cpu, $gpu3070, $gpu4090]);
ok_se(!in_array(4, array_column($r['items'], 'product_id'), true), 'The default plan never takes a gpu far above its share (RTX 4090 at 60,000)');
// the default is the BALANCED preset exactly: with cpus at 4,200, 9,000 and 9,500 and 40,000 to spend, balanced stops at 90% of its share (9,000) and full would take 9,500
$cpuMid = $part(21, 'cpu-9000', 'CPU', 8500, 9000, 9500); $cpuTop = $part(22, 'cpu-9500', 'CPU', 9000, 9500, 10000);
$r = $gen([], 40000.0, 'gaming', '1080p', 'used', [$cpu, $cpuMid, $cpuTop, $gpu3070]);
ok_se(array_column($r['items'], 'product_id') === [21, 3], 'Nothing chosen gives the balanced plan, not the saving or the full one: ' . json_encode(array_column($r['items'], 'product_id')));
$r = $gen([], 30000.0, 'office', '1080p', 'used', [$cpu, $cpuHigh, $gpu3070]);
ok_se(array_column($r['items'], 'product_id') === [2], 'From 22,000 and no gaming use: the stronger CPU and no GPU');
ok_se($gen([], 30000.0, 'gaming', '1080p', 'used', [$unpriced])['items'] === [], 'No part is priced: an empty plan, not an invented one');
ok_se(array_column($gen([], 30000.0, 'gaming', '1080p', 'used', [$ram, $unpriced])['items'], 'component_role') === ['ram'], 'Only the categories that have a priced part are filled');

// reserve never goes negative, and an over-budget build says so
$r = $gen(['cpu' => 2, 'gpu' => 4], 30000.0);
ok_se(str_contains($r['notes'], 'งบคงเหลือโดยประมาณ ฿0'), 'The reserve is floored at 0 when the parts cost more than the budget');
ok_se(str_contains($r['notes'], 'เกินงบประมาณ') && str_contains($r['notes'], '฿37,800'), 'An over-budget build is stated with the amount (67,800 - 30,000): ' . $r['notes']);
ok_se(!str_contains($gen(['cpu' => 1], 30000.0)['notes'], 'เกินงบประมาณ'), 'A build within budget carries no over-budget sentence');

// the missing categories are named in Thai, in the page's own words
$r = $gen(['cpu' => 1]);
foreach (['เมนบอร์ด', 'RAM', 'ที่เก็บข้อมูล', 'พาวเวอร์ซัพพลาย', 'เคส', 'ชุดระบายความร้อน'] as $label) { ok_se(str_contains($r['notes'], $label), "The reserve note names $label: " . $r['notes']); }
foreach (['motherboard', 'storage', 'psu', 'cooling'] as $raw) { ok_se(!str_contains($r['notes'], $raw), "No raw key '$raw' inside Thai text: " . $r['notes']); }

// the 4K sentence names the GPU the visitor actually chose
$r = $gen(['gpu' => 3], 30000.0, 'gaming', '4k');
ok_se(str_contains($r['notes'], 'RTX 3070') && str_contains($r['notes'], '1440p'), 'RTX 3070 at 4K keeps its 1440p note');
$r = $gen(['gpu' => 4], 80000.0, 'gaming', '4k');
ok_se(!str_contains($r['notes'], 'RTX 3070'), 'Another GPU at 4K is never described as an RTX 3070: ' . $r['notes']);
$r = $gen(['gpu' => 3], 30000.0, 'gaming', '1080p');
ok_se(!str_contains($r['notes'], '1440p'), 'No 4K sentence at 1080p');

// the "new" mix reminds that the index is a used-price index
ok_se(str_contains($gen(['cpu' => 1], 30000.0, 'gaming', '1080p', 'new')['notes'], 'ราคามือสอง'), 'The new-parts mix says the index is a used-price index');
// the Build page labels a category "has price data" only when at least one of its parts is priced (found on DEV: motherboard, PSU, case and cooling were labelled
// "มีข้อมูลราคา" with no selectable part). One rule decides it, SpecBuilderService::isPriced, and the side menu must use it for class, label and icon.
ok_se(SpecBuilderService::isPriced($cpu) && !SpecBuilderService::isPriced($unpriced) && !SpecBuilderService::isPriced($noMedian) && !SpecBuilderService::isPriced($noSample) && !SpecBuilderService::isPriced($noAccepted), 'isPriced follows the three conditions');
// LS-15: the page decides every part's price label in ONE place, BuildPricing::for (which itself uses isPriced), for the picker cards and the summary alike
$view = (string)file_get_contents(dirname(__DIR__) . '/app/Views/modules/build.php');
ok_se(str_contains($view, 'BuildPricing::for($p, $newRefs[(int) $p[\'id\']] ?? null)'), 'Each picker card takes its price and label from BuildPricing::for');
ok_se(!str_contains($view, 'มีข้อมูลราคา') && !str_contains($view, 'isPriced'), 'The view has no second, hand-written rule for what counts as priced');
ok_se(str_contains((string)file_get_contents(dirname(__DIR__) . '/app/Services/Build/BuildPricing.php'), 'SpecBuilderService::isPriced($product)'), 'BuildPricing uses the one isPriced rule');
echo "spec_builder_estimate_test: PASS\n";
