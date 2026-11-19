<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Build\BuildPreview;

/**
 * LS-15: BuildPreview is the one place that turns a selection into what the Build page shows (price list, totals, stats, checks) and into the
 * status of every candidate part. Synthetic catalog, no database. The property at the end is the one users feel: a part the picker offers as fitting
 * must not make the summary complain, and a part it marks "does not fit" must make the summary block the save.
 */
function ok_bp(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$mk = static function (int $id, string $cat, string $name, array $x = []): array {
    return array_merge(['id' => $id, 'category_name' => $cat, 'category_slug' => strtolower($cat), 'full_name' => $name, 'slug' => 'p' . $id, 'brand_name' => 'B', 'spec_summary' => '',
        'q1' => 900, 'median' => 1000, 'q3' => 1100, 'valid_sample_size' => 5, 'accepted_observation_count' => 5, 'confidence_score' => 70, 'socket' => '', 'memory_type' => '', 'form_factor' => '', 'tdp_watt' => null, 'recommended_psu_watt' => null], $x);
};
$products = [
    $mk(1, 'CPU', 'CPU AM4', ['socket' => 'AM4']), $mk(2, 'CPU', 'CPU AM5', ['socket' => 'AM5']),
    $mk(10, 'Motherboard', 'MB AM4 ATX', ['socket' => 'AM4', 'memory_type' => 'DDR4', 'form_factor' => 'ATX']), $mk(11, 'Motherboard', 'MB AM5', ['socket' => 'AM5', 'memory_type' => 'DDR5', 'form_factor' => 'ATX']),
    $mk(12, 'Motherboard', 'MB no socket', ['memory_type' => 'DDR4', 'form_factor' => 'ATX']),
    $mk(20, 'RAM', 'RAM DDR4', ['memory_type' => 'DDR4']), $mk(21, 'RAM', 'RAM DDR5', ['memory_type' => 'DDR5']),
    $mk(30, 'GPU', 'GPU 650', []), $mk(31, 'GPU', 'GPU 850', []),
    $mk(40, 'PSU', 'PSU 550W'), $mk(41, 'PSU', 'PSU 750W'), $mk(42, 'PSU', 'PSU mystery'),
    $mk(50, 'Case', 'Case ATX', ['form_factor' => 'ATX mid tower']), $mk(51, 'Case', 'Case mATX', ['form_factor' => 'Micro-ATX tower']),
    $mk(60, 'Cooling', 'Cooler AM4+AM5'), $mk(61, 'Cooling', 'Cooler Intel only'),
    $mk(70, 'Storage', 'SSD NVMe'),
    $mk(71, 'Storage', 'SSD unpriced', ['median' => null, 'valid_sample_size' => 0, 'accepted_observation_count' => 0, 'confidence_score' => 0]),
];
$specs = [
    1 => ['memory_support' => 'DDR4', 'boost_ghz' => '4.5', 'cores' => '8'], 2 => ['memory_support' => 'DDR5', 'boost_ghz' => '5.0', 'cores' => '8'],
    30 => ['system_psu_recommendation_watt' => '650', 'gpu_cores' => '2560', 'boost_mhz' => '1700', 'vram_gb' => '8'], 31 => ['system_psu_recommendation_watt' => '850', 'gpu_cores' => '4000', 'boost_mhz' => '2000', 'vram_gb' => '12'],
    60 => ['socket' => 'AM4;AM5'], 61 => ['socket' => 'LGA1700'],
];
$svc = new BuildPreview();
$run = static fn(array $sel, array $refs = [], float $budget = 30000.0) => $svc->run($products, $sel, $refs, $specs, $budget);

// ---- empty and invalid selections ----
$e = $run([]);
ok_bp(count($e['items']) === 8 && $e['chosen_count'] === 0 && $e['stats'] === [] && $e['candidates'] === [] && $e['blocked'] === false && $e['totals']['expected'] === 0.0, 'Nothing chosen: eight empty rows, no stats, no candidate marks, nothing blocked');
ok_bp(array_column($e['items'], 'role') === ['cpu', 'motherboard', 'gpu', 'ram', 'storage', 'psu', 'case', 'cooling'], 'The eight categories come in a fixed order');
$bad = $run(['cpu' => 10, 'gpu' => 9999, 'nonsense' => 1, 'ram' => 0]);
ok_bp($bad['chosen_count'] === 0, 'A part of the wrong category, an unknown id and an unknown role are ignored');

// ---- totals, basis and remaining budget ----
$t = $run(['cpu' => 1, 'storage' => 71], [71 => ['price' => 1990, 'source_url' => 'https://s.example/x', 'checked_on' => '2026-11-18']], 5000.0);
ok_bp($t['totals']['expected'] === 2990.0 && $t['totals']['used'] === 1 && $t['totals']['new_ref'] === 1 && $t['remaining'] === 2010.0, 'A new-price reference counts in the total and the remaining budget: ' . json_encode($t['totals']));
$item = array_values(array_filter($t['items'], static fn($i) => $i['role'] === 'storage'))[0];
ok_bp($item['basis'] === 'new_ref' && $item['basis_label'] === 'ราคาของใหม่อ้างอิง' && $item['confidence'] === 0.0, 'The new-price part says so and has no confidence');
ok_bp($run(['storage' => 71])['totals']['none'] === 1 && $run(['storage' => 71])['remaining'] === 30000.0, 'A part with no price adds nothing');
ok_bp($run(['cpu' => 1], [], 0.0)['remaining'] === null, 'Without a budget there is no remaining figure');

// ---- candidates ----
$c = $run(['cpu' => 1])['candidates'];
ok_bp(($c['motherboard'][11]['status'] ?? '') === 'fail' && str_contains($c['motherboard'][11]['reason'], 'AM5') && ($c['motherboard'][12]['status'] ?? '') === 'unknown' && !isset($c['motherboard'][10]), 'With an AM4 CPU: the AM5 board does not fit (and says why), the board without a socket is "unknown", the AM4 board is simply fine');
ok_bp(($c['ram'][21]['status'] ?? '') === 'fail' && !isset($c['ram'][20]), 'DDR5 RAM does not fit a CPU that supports DDR4');
ok_bp(($c['cooling'][61]['status'] ?? '') === 'warn' && !isset($c['cooling'][60]), 'A cooler that does not list the socket is a warning (it can still be bracketed), not a block');
ok_bp(!isset($c['cpu']), 'The category already chosen from is not marked against itself');
$c2 = $run(['cpu' => 1, 'motherboard' => 10, 'gpu' => 30])['candidates'];
ok_bp(($c2['psu'][40]['status'] ?? '') === 'warn' && !isset($c2['psu'][41]) && ($c2['psu'][42]['status'] ?? '') === 'unknown', 'With a 650 W graphics card: a 550 W PSU is a warning, 750 W is fine, an unknown wattage is "unknown"');
ok_bp(($c2['case'][51]['status'] ?? '') === 'warn' && !isset($c2['case'][50]), 'A Micro-ATX case for an ATX board is a warning');
ok_bp(!isset($c2['gpu']) || !array_filter($c2['gpu'], static fn($x) => $x['status'] === 'unknown'), 'No graphics card is marked "unknown" only because its length depends on the brand (that check stays in the summary)');
ok_bp(($c2['gpu'][31] ?? null) === null, 'A bigger graphics card is not marked when no PSU is chosen yet');
$c3 = $run(['cpu' => 1, 'motherboard' => 10, 'gpu' => 30, 'psu' => 41])['candidates'];
ok_bp(($c3['gpu'][31]['status'] ?? '') === 'warn' && str_contains($c3['gpu'][31]['reason'], '750'), 'With a 750 W PSU chosen, an 850 W-class graphics card is warned about');

// ---- the summary and the candidates agree (a property over every candidate of a few selections) ----
$problems = [];
foreach ([['cpu' => 1], ['cpu' => 1, 'motherboard' => 10], ['cpu' => 1, 'motherboard' => 10, 'gpu' => 30, 'psu' => 41], ['cpu' => 2, 'motherboard' => 11, 'case' => 50, 'cooling' => 60]] as $sel) {
    $base = $run($sel);
    foreach ($products as $p) {
        $role = strtolower($p['category_name']);
        if (($sel[$role] ?? 0) === $p['id']) { continue; }
        $st = $base['candidates'][$role][$p['id']]['status'] ?? 'ok';
        $after = $run([$role => $p['id']] + $sel);
        $roleChecks = array_values(array_filter($after['checks'], static fn($ch) => in_array($role, $ch['roles'], true) && !($ch['status'] === 'unknown' && $ch['key'] === 'gpu_length')));
        $worst = 'ok'; $rank = ['ok' => 0, 'unknown' => 1, 'warn' => 2, 'fail' => 3];
        foreach ($roleChecks as $ch) { $lv = $ch['status'] === 'ok' ? 'ok' : ($ch['status'] === 'unknown' ? 'unknown' : ($ch['blocking'] ? 'fail' : 'warn')); if ($rank[$lv] > $rank[$worst]) { $worst = $lv; } }
        if ($worst !== $st) { $problems[] = json_encode($sel) . " + part {$p['id']}: picker says $st, summary says $worst"; }
        if ($st === 'fail' && !$after['blocked']) { $problems[] = json_encode($sel) . " + part {$p['id']}: marked fail but the build is not blocked"; }
        if ($st !== 'fail' && $after['blocked'] && $base['blocked'] === false) { $problems[] = json_encode($sel) . " + part {$p['id']}: not marked fail but the build becomes blocked"; }
    }
}
ok_bp($problems === [], "The picker and the summary disagree:\n  " . implode("\n  ", array_slice($problems, 0, 6)));

// ---- stats come with a selection, and the page shows the same numbers as the summary ----
$s = $run(['cpu' => 1, 'gpu' => 30]);
ok_bp(count($s['stats']) === 6 && $s['psu_need'] === 650, 'Six stats and the PSU need come with a selection');
echo "build_preview_test: PASS\n";
