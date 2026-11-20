<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Catalog\UpgradeFinder as U;

/** LS-25 A6: the upgrade finder is a spec-step list ordered by price. No "faster/better", only priced candidates, notes only state what to check. Pure. */
function ok_uf(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$row = static fn(int $id, string $cat, ?float $median, string $name = ''): array => ['id' => $id, 'category_slug' => $cat, 'full_name' => $name ?: "M$id", 'slug' => "m$id",
    'median' => $median, 'valid_sample_size' => $median ? 5 : 0, 'accepted_observation_count' => $median ? 5 : 0];
$all = [$row(1, 'cpu', 3000.0), $row(2, 'cpu', 4500.0), $row(3, 'cpu', 6000.0), $row(4, 'cpu', null), $row(5, 'cpu', 2000.0), $row(6, 'gpu', 9000.0), $row(7, 'gpu', 12000.0), $row(8, 'gpu', 7000.0)];
$specs = [
    1 => ['cores' => '6', 'threads' => '12', 'socket' => 'AM4', 'memory_type' => 'DDR4'],
    2 => ['cores' => '8', 'threads' => '16', 'socket' => 'AM4', 'memory_type' => 'DDR4'],
    3 => ['cores' => '8', 'threads' => '16', 'socket' => 'AM5', 'memory_type' => 'DDR5'],
    4 => ['cores' => '12', 'threads' => '24', 'socket' => 'AM4', 'memory_type' => 'DDR4'],
    5 => ['cores' => '4', 'threads' => '8', 'socket' => 'AM4', 'memory_type' => 'DDR4'],
    6 => ['vram_gb' => '8', 'board_power_watt' => '170'], 7 => ['vram_gb' => '12', 'board_power_watt' => '200'], 8 => ['vram_gb' => '6', 'board_power_watt' => '150'],
];
$r = U::find($all[0], $all, $specs);
ok_uf($r['supported'] && $r['base_priced'], 'A priced CPU is supported');
ok_uf(array_map(static fn($i) => $i['product']['id'], $r['items']) === [2, 3], 'Only larger, priced CPUs of the same category, cheapest first (4 is unpriced, 5 is smaller, GPUs are another category)');
ok_uf($r['unpriced_hidden'] === 1, 'The unpriced larger model is counted, not shown');
ok_uf($r['items'][0]['delta'] === 1500.0 && $r['items'][0]['median'] === 4500.0, 'The price difference to the current model is stated');
ok_uf($r['items'][0]['steps'][0] === ['key' => 'cores', 'from' => 6.0, 'to' => 8.0], 'The step names the fact and both values');
$joined = static fn(array $i): string => implode(' | ', $i['notes']);
ok_uf(str_contains($joined($r['items'][0]), 'ซ็อกเก็ตเดียวกัน') && str_contains($joined($r['items'][1]), 'ต้องเปลี่ยนเมนบอร์ด') && str_contains($joined($r['items'][1]), 'ต้องเปลี่ยน RAM'), 'Same socket asks for a BIOS check; another socket or memory type says what must be replaced');
$g = U::find($all[5], $all, $specs);
ok_uf(count($g['items']) === 1 && $g['items'][0]['product']['id'] === 7 && str_contains($joined($g['items'][0]), '200 วัตต์') && str_contains($joined($g['items'][0]), 'ความยาวการ์ด'), 'GPU: more VRAM listed with a power and length check');
$unpricedBase = U::find($row(1, 'cpu', null), $all, $specs);
ok_uf($unpricedBase['base_priced'] === false && $unpricedBase['items'] !== [] && $unpricedBase['items'][0]['delta'] === null, 'An unpriced current model gives no price difference');
ok_uf(U::find($row(9, 'psu', 1000.0), $all, [])['supported'] === false && U::specKeys('psu') === [], 'A category without step facts says so');
ok_uf(U::find($all[0], $all, [1 => ['cores' => '6']])['items'] === [], 'A missing fact on either side lists nothing (nothing is guessed)');
$ramAll = [$row(21, 'ram', 1500.0), $row(22, 'ram', 2500.0), $row(23, 'ram', 3500.0)];
$ramSpecs = [21 => ['capacity_gb' => '16', 'memory_type' => 'DDR4'], 22 => ['capacity_gb' => '32', 'memory_type' => 'DDR4'], 23 => ['capacity_gb' => '32', 'memory_type' => 'DDR5']];
ok_uf(array_map(static fn($i) => $i['product']['id'], U::find($ramAll[0], $ramAll, $ramSpecs)['items']) === [22], 'RAM of another generation is not offered as a step up');
$many = []; $manySpecs = [1 => ['vram_gb' => '4']]; $many[] = $row(1, 'gpu', 1000.0);
for ($i = 2; $i <= 12; $i++) { $many[] = $row($i, 'gpu', 1000.0 + $i); $manySpecs[$i] = ['vram_gb' => (string)(4 + $i)]; }
ok_uf(count(U::find($many[0], $many, $manySpecs)['items']) === U::LIMIT, 'At most LIMIT candidates are shown');
$src = (string)file_get_contents(ROOT_PATH . '/app/Services/Catalog/UpgradeFinder.php') . (string)file_get_contents(ROOT_PATH . '/app/Views/modules/upgrade.php');
ok_uf(!preg_match('/เร็วกว่า|แรงกว่า|คุ้มกว่า|ดีกว่า|แนะนำให้ซื้อ/u', preg_replace('/ระบบไม่บอกว่า.*?เท่านั้น/u', '', $src)), 'The wording never claims faster, better or worth it');
echo "upgrade_finder_test: PASS\n";
