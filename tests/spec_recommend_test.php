<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\SpecBuilderService;

/**
 * LS-11 A + B: the "recommend" button on /build and the fixes to the four inert form fields. The recommendation is deterministic PHP over the priced parts:
 * the budget is split by use (and a little by resolution), a preset says how much of each share to spend, the part chosen is the priced one whose median is
 * closest to its share without going over, and the whole thing is scaled down until it fits what is left after the categories that have no price yet.
 * It is chosen by price, not by measured speed, and the notes say so. Pure (no database).
 */
function ok_sr(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$part = static fn(int $id, string $slug, string $cat, float $med, array $extra = []): array => array_merge(
    ['id' => $id, 'slug' => $slug, 'category_name' => $cat, 'full_name' => $slug, 'q1' => $med * .9, 'median' => $med, 'q3' => $med * 1.1, 'valid_sample_size' => 5, 'accepted_observation_count' => 5, 'confidence_score' => 70.0, 'socket' => '', 'memory_type' => ''],
    $extra
);
$cat = [
    $part(1, 'cpu-a', 'CPU', 3000), $part(2, 'cpu-b', 'CPU', 5000), $part(3, 'cpu-c', 'CPU', 8000), $part(4, 'cpu-d', 'CPU', 12000),
    $part(11, 'gpu-a', 'GPU', 4000), $part(12, 'gpu-b', 'GPU', 8000), $part(13, 'gpu-c', 'GPU', 12500), $part(14, 'gpu-d', 'GPU', 30000),
    $part(16, 'gpu-e', 'GPU', 16000),
    $part(15, 'gpu-unpriced', 'GPU', 0, ['median' => null, 'valid_sample_size' => 0, 'accepted_observation_count' => 0]),
    $part(21, 'ram-a', 'RAM', 600), $part(22, 'ram-b', 'RAM', 1200),
    $part(31, 'ssd-a', 'Storage', 700),
];
$svc = new SpecBuilderService();
$rec = static fn(array $c, float $budget, string $use, string $res, string $preset): array => (new SpecBuilderService())->recommend($c, $budget, $use, $res, $preset);
$ids = static fn(array $r): array => array_values($r['selected']);

// the balanced gaming plan at 1440p: each category takes the dearest priced part not above 90% of its share (cpu 20%, gpu 40%, ram 7%, storage 8% of 30,000)
$r = $rec($cat, 30000.0, 'gaming', '1440p', 'balanced');
ok_sr($r['selected'] === ['cpu' => 2, 'gpu' => 12, 'ram' => 22, 'storage' => 31], 'Balanced gaming 1440p picks cpu-b, gpu-b, ram-b, ssd-a: ' . json_encode($r['selected']));
ok_sr($r['expected'] === 14900.0, 'Expected total is the sum of the medians: ' . $r['expected']);

// the three presets spend more and more of the same budget
$sv = $rec($cat, 30000.0, 'gaming', '1440p', 'saving');
$fu = $rec($cat, 30000.0, 'gaming', '1440p', 'full');
ok_sr($sv['selected'] === ['cpu' => 1, 'gpu' => 12, 'ram' => 22, 'storage' => 31] && $sv['expected'] === 12900.0, 'Saving picks the cheaper step on the cpu: ' . json_encode($sv['selected']));
ok_sr($fu['selected'] === ['cpu' => 2, 'gpu' => 13, 'ram' => 22, 'storage' => 31] && $fu['expected'] === 19400.0, 'Full climbs until the next step would not fit: ' . json_encode($fu['selected']) . ' ' . $fu['expected']);
ok_sr($sv['expected'] <= $r['expected'] && $r['expected'] <= $fu['expected'], 'saving <= balanced <= full');
// 30,000 minus the categories with no price yet (motherboard 10%, psu 7%, case 5%, cooling 3%) is what the priced parts may use
ok_sr($fu['expected'] <= 30000.0 * 0.75, 'The plan never spends the share reserved for categories without a price: ' . $fu['expected']);
ok_sr(abs($fu['reserve'] - 7500.0) < 0.01, 'The reserve kept for unpriced categories is 25% here: ' . $fu['reserve']);

// the preset factor matters: with cpus at 4,000, 4,500, 5,000 and 5,700 and only the cpu priced (so its share is the whole spendable amount, 6,000 here),
// balanced stops at 90% (5,400, so 5,000), full may use 100% (5,700), saving stops at 70% (4,200, so 4,000)
$two = [$part(73, 'cpu-4000', 'CPU', 4000), $part(74, 'cpu-4500', 'CPU', 4500), $part(71, 'cpu-5000', 'CPU', 5000), $part(72, 'cpu-5700', 'CPU', 5700)];
ok_sr($rec($two, 30000.0, 'gaming', '1440p', 'balanced')['selected'] === ['cpu' => 71], 'Balanced stays at 90% of the share');
ok_sr($rec($two, 30000.0, 'gaming', '1440p', 'full')['selected'] === ['cpu' => 72], 'Full uses the whole share');
ok_sr($rec($two, 30000.0, 'gaming', '1440p', 'saving')['selected'] === ['cpu' => 73], 'Saving stays at 70% of the share (4,200, so 4,000)');

// low-confidence data is called out; confident data is not
$low = array_map(static fn(array $p): array => array_merge($p, ['confidence_score' => 40.0]), $cat);
ok_sr(str_contains($rec($low, 30000.0, 'gaming', '1440p', 'balanced')['notes'], 'ความมั่นใจของข้อมูลราคายังต่ำ'), 'Low confidence is named in the notes');
ok_sr(!str_contains($r['notes'], 'ความมั่นใจของข้อมูลราคายังต่ำ'), 'No low-confidence note when the data is confident');

// the use changes the split: an office build has no graphics card, and 4K moves money from the processor to the graphics card
$o = $rec($cat, 30000.0, 'office', '1080p', 'balanced');
ok_sr(!isset($o['selected']['gpu']) && $o['selected']['cpu'] === 3, 'Office picks no gpu and a stronger cpu: ' . json_encode($o['selected']));
$p1080 = $rec($cat, 30000.0, 'gaming', '1080p', 'balanced');
$p4k = $rec($cat, 30000.0, 'gaming', '4k', 'balanced');
ok_sr($p1080['selected']['gpu'] === 12 && $p4k['selected']['gpu'] === 13, '4K buys the bigger gpu: ' . $p1080['selected']['gpu'] . ' -> ' . $p4k['selected']['gpu']);
ok_sr($p1080['selected']['cpu'] === 2 && $p4k['selected']['cpu'] === 1, '4K takes the money from the cpu: ' . $p1080['selected']['cpu'] . ' -> ' . $p4k['selected']['cpu']);

// a part without a market price is never chosen
foreach (['saving', 'balanced', 'full'] as $preset) { ok_sr(!in_array(15, $ids($rec($cat, 80000.0, 'gaming', '4k', $preset)), true), "$preset never picks an unpriced part"); }

// too small a budget: everything is the cheapest priced part and the notes say the plan does not fit
$s = $rec($cat, 6000.0, 'gaming', '1080p', 'balanced');
ok_sr($s['selected'] === ['cpu' => 1, 'gpu' => 11, 'ram' => 21, 'storage' => 31] && $s['expected'] === 8300.0, 'Cheapest of each: ' . json_encode($s['selected']));
ok_sr(str_contains($s['notes'], 'เกินงบประมาณ ฿2,300'), 'Over budget is stated with the amount (8,300 - 6,000): ' . $s['notes']);
ok_sr(str_contains($s['notes'], 'หมวดที่ยังไม่มีข้อมูลราคา'), 'The notes name the categories without a price: ' . $s['notes']);

// honest wording and a fixed answer
ok_sr(str_contains($r['notes'], 'ไม่ได้วัดความแรง'), 'The notes say the choice follows price, not measured speed: ' . $r['notes']);
ok_sr($rec($cat, 30000.0, 'gaming', '1440p', 'balanced') === $r, 'The same input gives the same plan');
ok_sr($rec([], 30000.0, 'gaming', '1440p', 'balanced')['selected'] === [], 'No priced part: an empty plan, nothing invented');
ok_sr($rec([$cat[12]], 30000.0, 'gaming', '1440p', 'balanced')['selected'] === ['storage' => 31], 'Only the categories that have a priced part are filled');
try { $rec($cat, 30000.0, 'gaming', '1440p', 'cheapest'); ok_sr(false, 'An unknown preset must be refused'); } catch (InvalidArgumentException) { }

// compatibility: a chosen motherboard has the cpu's socket, and a chosen RAM the motherboard's memory type, at every budget and preset
$cc = [
    $part(41, 'cpu-am4', 'CPU', 4000, ['socket' => 'AM4']), $part(42, 'cpu-am5', 'CPU', 4200, ['socket' => 'AM5']),
    $part(51, 'mb-am4', 'Motherboard', 3000, ['socket' => 'AM4', 'memory_type' => 'DDR4']), $part(52, 'mb-am5', 'Motherboard', 3500, ['socket' => 'AM5', 'memory_type' => 'DDR5']),
    $part(61, 'ram-d4', 'RAM', 1000, ['memory_type' => 'DDR4']), $part(62, 'ram-d5', 'RAM', 1500, ['memory_type' => 'DDR5']),
];
$byId = []; foreach ($cc as $p) { $byId[$p['id']] = $p; }
foreach ([15000.0, 25000.0, 40000.0, 60000.0, 120000.0] as $b) {
    foreach (['saving', 'balanced', 'full'] as $preset) {
        foreach (['gaming', 'work', 'office'] as $use) {
            $x = $rec($cc, $b, $use, '1080p', $preset)['selected'];
            if (isset($x['cpu'], $x['motherboard'])) { ok_sr($byId[$x['cpu']]['socket'] === $byId[$x['motherboard']]['socket'], "Socket matches at $b $preset $use: " . json_encode($x)); }
            if (isset($x['ram'], $x['motherboard'])) { ok_sr($byId[$x['ram']]['memory_type'] === $byId[$x['motherboard']]['memory_type'], "Memory type matches at $b $preset $use: " . json_encode($x)); }
        }
    }
}

// the page and the controller
$root = dirname(__DIR__);
$view = (string)file_get_contents($root . '/app/Views/modules/build.php');
$ctl = (string)file_get_contents($root . '/app/Controllers/ModuleController.php');
ok_sr(str_contains($view, 'name="preset"') && str_contains($view, "\$old['preset']"), 'The form offers the presets in a select like the other controls, and keeps the choice after a round trip');
foreach (['saving', 'balanced', 'full'] as $p) { ok_sr(str_contains($view, "'$p' =>"), "The form offers the $p preset"); }
ok_sr(str_contains($view, 'name="action" value="recommend"'), 'The form has a recommend button');
ok_sr(str_contains($view, "\$old['parts']"), 'Recommended parts come back ticked through the same radios the visitor uses');
ok_sr(str_contains($ctl, "'recommend'") && str_contains($ctl, '->recommend('), 'The controller calls SpecBuilderService::recommend on the recommend action');
// B: the fields that did nothing are fixed. The mix field offered options that changed nothing (no new-price data exists); it is now fixed to used prices and says so.
ok_sr(!str_contains($view, 'value="mixed"') && !str_contains($view, 'value="new"') && !str_contains($view, 'ใหม่ + มือสอง'), 'No choice between new and used: the index has used prices only');
ok_sr(str_contains($view, 'name="mix_preference" value="used"'), 'The used-price value is still sent so the saved build and its validation keep working');
ok_sr(str_contains($view, "\$old['intended_use']") && str_contains($view, "\$old['resolution']"), 'The use and resolution the visitor chose are kept after a round trip');
echo "spec_recommend_test: PASS\n";
