<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Build\BuildCompatibility as C;
use App\Services\Build\BuildPricing as P;
use App\Services\Build\BuildStats as S;

/**
 * LS-14 Module 6: the Build page's price basis (used price first, then an admin's new-price reference, else none), the compatibility checks
 * (ok / fail / unknown, unknown never reported as ok) and the six spec-based stats (unknown is never 0, no benchmark claim). Pure.
 */
function ok_b(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

// ---- pricing ----
$used = ['median' => 5000, 'q1' => 4500, 'q3' => 5500, 'valid_sample_size' => 5, 'accepted_observation_count' => 5];
$bare = ['median' => null, 'valid_sample_size' => 0, 'accepted_observation_count' => 0];
$ref = ['price' => 3290, 'source_url' => 'https://shop.example/x', 'checked_on' => '2026-11-18'];
ok_b(P::for($used, $ref)['basis'] === 'used' && P::for($used, $ref)['expected'] === 5000.0, 'A used-market price always wins over a new-price reference');
$r = P::for($bare, $ref);
ok_b($r['basis'] === 'new_ref' && $r['expected'] === 3290.0 && $r['label'] === 'ราคาของใหม่อ้างอิง' && $r['source_url'] === 'https://shop.example/x', 'Without a used price the new-price reference is used and labelled');
ok_b(P::for($bare, null)['basis'] === 'none' && P::for($bare, null)['expected'] === 0.0, 'No price at all is "none" and adds nothing');
ok_b(P::for($bare, ['price' => 0, 'source_url' => 'x', 'checked_on' => 'x'])['basis'] === 'none', 'A zero reference is not a price');
ok_b(P::for(['median' => 5000, 'q1' => 1, 'q3' => 1, 'valid_sample_size' => 5, 'accepted_observation_count' => 0], null)['basis'] === 'none', 'A median without accepted rows is not a used price (same rule as the price pages)');
$t = P::totals([P::for($used, null), P::for($bare, $ref), P::for($bare, null)]);
ok_b($t['used'] === 1 && $t['new_ref'] === 1 && $t['none'] === 1 && $t['expected'] === 8290.0, 'Totals count each basis and add only real prices');
$note = P::totalsNote($t);
ok_b(str_contains($note, '1 ชิ้นใช้ราคามือสอง') && str_contains($note, 'ราคาของใหม่อ้างอิง') && str_contains($note, 'ไม่ได้รวมในยอด'), 'The total says how it was made');

// ---- compatibility ----
$part = static fn(array $product, array $specs = []): array => ['product' => $product + ['full_name' => 'x'], 'specs' => $specs];
$by = static fn(array $checks): array => array_column($checks, null, 'key');
$c = $by(C::check(['cpu' => $part(['socket' => 'AM4']), 'motherboard' => $part(['socket' => 'AM5', 'memory_type' => 'DDR5'])]));
ok_b($c['socket']['status'] === 'fail' && $c['socket']['blocking'] === true, 'Different sockets fail and block');
ok_b(C::blocked(array_values($c)), 'A blocking failure blocks the build');
$c = $by(C::check(['cpu' => $part(['socket' => 'AM4']), 'motherboard' => $part(['socket' => ''])]));
ok_b($c['socket']['status'] === 'unknown' && !C::blocked(array_values($c)), 'An empty socket is unknown, never ok and never a failure');
$c = $by(C::check(['ram' => $part(['memory_type' => 'DDR4']), 'motherboard' => $part(['memory_type' => 'DDR5'])]));
ok_b($c['memory']['status'] === 'fail' && $c['memory']['blocking'], 'DDR4 RAM on a DDR5 board fails and blocks');
$c = $by(C::check(['ram' => $part(['memory_type' => 'DDR5']), 'cpu' => $part(['socket' => 'AM4'], ['memory_support' => 'DDR4'])]));
ok_b($c['cpu_memory']['status'] === 'fail', 'RAM the CPU does not support fails');
ok_b(!isset($by(C::check(['ram' => $part(['memory_type' => 'DDR5']), 'cpu' => $part([])]))['cpu_memory']), 'No CPU memory figure, no CPU memory check (nothing invented)');

// PSU: GPU system recommendation wins; else (CPU + GPU + 100) * 1.25 rounded up to 50
ok_b(C::psuNeed(['gpu' => $part([], ['system_psu_recommendation_watt' => '650'])]) === 650, 'GPU system PSU recommendation is used as is');
ok_b(C::psuNeed(['cpu' => $part(['tdp_watt' => 65]), 'gpu' => $part([], ['board_power_watt' => '200'])]) === 500, '(65+200+100)*1.25=456 rounds up to 500');
ok_b(C::psuNeed(['cpu' => $part(['tdp_watt' => 65]), 'gpu' => $part([])]) === null, 'A GPU without a power figure makes the need unknown');
$c = $by(C::check(['psu' => $part(['full_name' => 'Corsair RM550x 550W']), 'cpu' => $part(['tdp_watt' => 65]), 'gpu' => $part([], ['system_psu_recommendation_watt' => '650'])]));
ok_b($c['psu']['status'] === 'fail' && $c['psu']['blocking'] === false, 'A 550 W PSU under a 650 W need is a warning, not a block (wattage read from the model name)');
$c = $by(C::check(['psu' => $part([], ['psu_capacity_watt' => '750']), 'cpu' => $part(['tdp_watt' => 65]), 'gpu' => $part([], ['system_psu_recommendation_watt' => '650'])]));
ok_b($c['psu']['status'] === 'ok', '750 W covers 650 W');
$c = $by(C::check(['psu' => $part([]), 'cpu' => $part(['tdp_watt' => 65])]));
ok_b($c['psu']['status'] === 'unknown', 'A PSU without a wattage is unknown');

// form factor, cooler height and socket, GPU length
$c = $by(C::check(['case' => $part([], ['form_factor' => 'Micro-ATX tower']), 'motherboard' => $part(['form_factor' => 'ATX'])]));
ok_b($c['form_factor']['status'] === 'fail', 'An ATX board does not fit a Micro-ATX case');
$c = $by(C::check(['case' => $part([], ['form_factor' => 'ATX mid tower']), 'motherboard' => $part(['form_factor' => 'Mini-ITX'])]));
ok_b($c['form_factor']['status'] === 'ok', 'A Mini-ITX board fits an ATX case');
$c = $by(C::check(['case' => $part([], ['max_cpu_cooler_height_mm' => '160']), 'cooling' => $part([], ['cooler_height_mm' => '165', 'socket' => 'AM4;AM5'])]));
ok_b($c['cooler_height']['status'] === 'fail', 'A 165 mm cooler does not fit under 160 mm');
$c = $by(C::check(['cpu' => $part(['socket' => 'LGA1700']), 'cooling' => $part([], ['socket' => 'AM4; AM5'])]));
ok_b($c['cooler_socket']['status'] === 'fail' && !$c['cooler_socket']['blocking'], 'A cooler that does not list the CPU socket is a warning');
$c = $by(C::check(['cpu' => $part(['socket' => 'AM5']), 'cooling' => $part([], ['socket' => 'AM4;AM5;LGA1700'])]));
ok_b($c['cooler_socket']['status'] === 'ok', 'A listed socket is ok');
$c = $by(C::check(['case' => $part([], ['max_gpu_length_mm' => '360']), 'gpu' => $part([])]));
ok_b($c['gpu_length']['status'] === 'unknown' && str_contains($c['gpu_length']['text'], '360'), 'GPU length is unknown (it depends on the brand) and the case limit is shown');
ok_b(C::check(['cpu' => $part(['socket' => 'AM4'])]) === [], 'A check appears only when both of its parts are chosen');

// ---- stats ----
$cpuA = ['role' => 'cpu', 'product' => [], 'specs' => ['cores' => '8', 'boost_ghz' => '4.5'], 'price' => 4500.0];     // mt 36
$cpuB = ['role' => 'cpu', 'product' => [], 'specs' => ['cores' => '16', 'boost_ghz' => '5.0'], 'price' => 15000.0];   // mt 80, best
$cpuI = ['role' => 'cpu', 'product' => [], 'specs' => ['p_cores' => '6', 'e_cores' => '4', 'cores' => '10', 'boost_ghz' => '4.0'], 'price' => 5000.0];
$gpuA = ['role' => 'gpu', 'product' => [], 'specs' => ['gpu_cores' => '2560', 'boost_mhz' => '1777 / 1800', 'vram_gb' => '8'], 'price' => 8000.0];
$gpuB = ['role' => 'gpu', 'product' => [], 'specs' => ['gpu_cores' => '10240', 'boost_mhz' => '2000', 'vram_gb' => '24'], 'price' => 60000.0];
$ref = S::reference([$cpuA, $cpuB, $cpuI, $gpuA, $gpuB]);
ok_b(abs($ref['cpu_mt'] - 80.0) < 0.001 && abs($ref['gpu'] - 20480.0) < 0.001 && $ref['vram'] === 24.0, 'Reference figures are the catalog best: ' . json_encode($ref));
ok_b(abs(S::metrics('cpu', [], $cpuI['specs'])['cpu_mt'] - 32.0) < 0.001, 'An efficiency core counts as half: (6 + 0.5*4) * 4.0 = 32');
ok_b(abs(S::metrics('gpu', [], $gpuA['specs'])['gpu'] - 2560 * 1.777) < 0.01, 'The lowest stated GPU clock is used');
$ax = static fn(array $parts): array => array_column(S::axes($parts, $ref), null, 'key');
$ram16 = ['product' => ['full_name' => 'Kit DDR4 2x8GB'], 'specs' => [], 'price' => 1500.0];
$nvme = ['product' => ['full_name' => 'X 1TB NVMe'], 'specs' => [], 'price' => 1800.0];
$low = $ax(['cpu' => $cpuA, 'gpu' => $gpuA, 'ram' => $ram16, 'storage' => $nvme]);
$high = $ax(['cpu' => $cpuB, 'gpu' => $gpuB, 'ram' => $ram16, 'storage' => $nvme]);
ok_b(count($low) === 6 && array_keys($low) === ['overall', 'gaming', 'ai', 'workstation', 'creator', 'value'], 'Six axes in a fixed order');
foreach (['overall', 'gaming', 'ai', 'workstation', 'creator'] as $k) {
    ok_b($low[$k]['value'] !== null && $high[$k]['value'] !== null && $high[$k]['value'] > $low[$k]['value'], "A stronger build scores higher on $k");
    ok_b($high[$k]['value'] <= 100 && $low[$k]['value'] >= 0, "$k stays within 0..100");
}
ok_b($low['value']['value'] !== null && $low['value']['value'] > $high['value']['value'], 'The cheaper build is better value per baht: ' . $low['value']['value'] . ' vs ' . $high['value']['value']);
$noGpu = $ax(['cpu' => $cpuA, 'ram' => $ram16, 'storage' => $nvme]);
ok_b($noGpu['gaming']['value'] === null && $noGpu['ai']['value'] === null, 'Without a graphics card, Gaming and AI are "no data", not 0');
ok_b($noGpu['workstation']['value'] !== null, 'Workstation can still be scored without a graphics card (its known parts carry 0.85 of the weight)');
ok_b($ax(['gpu' => $gpuA])['value']['value'] === null, 'Value needs the CPU price too when a graphics card is chosen');
$unknownCpu = $ax(['cpu' => ['product' => [], 'specs' => [], 'price' => 1.0], 'gpu' => $gpuA]);
ok_b($unknownCpu['workstation']['value'] === null, 'A CPU with no spec makes CPU-heavy axes "no data" instead of 0');
ok_b(S::metrics('ram', ['full_name' => 'Kingston 2x32GB DDR5'], [])['ram'] === 64.0 && S::metrics('ram', ['full_name' => '1x128GB'], [])['ram'] === 64.0, 'RAM is read from the kit name and capped');
ok_b(S::metrics('storage', ['full_name' => 'WD Blue 1TB'], [])['storage'] === null, 'A storage part with no stated type is unknown');
ok_b(S::metrics('storage', ['full_name' => 'X 1TB NVMe SSD'], [])['storage'] === 100.0 && S::metrics('storage', ['full_name' => 'Samsung 870 EVO SSD'], [])['storage'] === 50.0 && S::metrics('storage', ['full_name' => 'Seagate 2TB HDD 7200rpm'], [])['storage'] === 20.0, 'Storage class: NVMe 100, SATA SSD 50, hard disk 20');
ok_b(str_contains(S::NOTE, 'ไม่ใช่ผลทดสอบจริง') && !preg_match('/fps|เฟรม/i', S::NOTE), 'The note says it is not a benchmark and makes no frame-rate claim');

// ---- wiring: the controller passes the references to the plan, blocks on a blocking check, and shows stats and checks on a saved build ----
$root = dirname(__DIR__);
$ctl = (string)file_get_contents($root . '/app/Controllers/ModuleController.php');
ok_b(str_contains($ctl, 'generate($products,(float)$budget,$use,$resolution,$mix,$selected,$newRefs)') && str_contains($ctl, 'BuildCompatibility::blocked($checks)'), 'storeBuild uses the new-price references and blocks on a blocking check');
$prev = (string)file_get_contents($root . '/app/Services/Build/BuildPreview.php');
ok_b(str_contains($ctl, '(new \App\Services\Build\BuildPreview())->run(') && str_contains($prev, 'BuildStats::axes(') && str_contains($prev, 'BuildCompatibility::check('), 'The saved build page gets the stats and the checks from BuildPreview');
$view = (string)file_get_contents($root . '/app/Views/modules/build.php');
ok_b(preg_match('/js-add"[^>]*disabled/', $view) === 0 && str_contains($view, 'BuildPricing::for($p,'), 'No part is disabled in the markup (the server decides which parts do not fit); each shows its price basis');
ok_b(str_contains((string)file_get_contents($root . '/app/Views/components/build-summary.php'), 'BuildStats::NOTE'), 'The stats chart carries the not-a-benchmark note');

echo "build_services_test: PASS\n";
