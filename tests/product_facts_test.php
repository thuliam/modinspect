<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Catalog\ProductFacts as F;

/**
 * Product facts on the product page (requirement "release date, model age, specifications", docs/PRODUCT_REQUIREMENTS.md #6, owner scope 2026-11-18):
 * the year a model came out and how old the MODEL is (never the wear of a unit someone sells), and a specification list in which every figure that came from a
 * manufacturer page carries its source and check date. No benchmark and no rank appear here: no licensed source exists. A missing fact is left out, never invented,
 * and a failing source must not break the page. Pure (no database).
 */
function ok_pf(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

// model age: counted from the release YEAR, so it is "about" N years, and it says it is the model's age
$a = F::modelAge(2022, 2026);
ok_pf($a !== null && $a['year'] === 2022 && $a['age'] === 4 && str_contains($a['label'], 'เปิดตัวปี 2022') && str_contains($a['label'], 'อายุรุ่นประมาณ 4 ปี'), 'A 2022 model in 2026 is about 4 years old: ' . json_encode($a, JSON_UNESCAPED_UNICODE));
ok_pf(str_contains($a['note'], 'ไม่ใช่อายุการใช้งาน'), 'The note says this is the model age, not the age of a used unit: ' . $a['note']);
$n = F::modelAge(2026, 2026);
ok_pf($n !== null && $n['age'] === 0 && str_contains($n['label'], 'เปิดตัวปี 2026') && !str_contains($n['label'], '0 ปี'), 'A model of this year says so instead of "0 years": ' . json_encode($n, JSON_UNESCAPED_UNICODE));
ok_pf(F::modelAge(null, 2026) === null && F::modelAge(0, 2026) === null && F::modelAge(1960, 2026) === null && F::modelAge(2031, 2026) === null, 'Missing, zero, implausible and future years give nothing');
ok_pf(F::modelAge(2027, 2026) === null && F::modelAge(2026, 2026) !== null, 'A year after the current one is not shown');

// specification rows
$cpu = ['release_year' => 2022, 'generation' => 'Alder Lake', 'socket' => 'LGA1700', 'memory_type' => 'DDR4/DDR5', 'tdp_watt' => 65, 'form_factor' => null, 'capacity_gb' => null, 'spec_summary' => '6 คอร์ / 12 เธรด, LGA1700'];
$rows = F::rows($cpu, [], 2026);
$by = array_column($rows, null, 'label');
ok_pf(isset($by['เปิดตัว']) && str_contains($by['เปิดตัว']['value'], 'อายุรุ่นประมาณ 4 ปี'), 'The release row carries the model age: ' . json_encode($by['เปิดตัว'] ?? null, JSON_UNESCAPED_UNICODE));
ok_pf(($by['สถาปัตยกรรม / เจเนอเรชัน']['value'] ?? '') === 'Alder Lake' && ($by['Socket']['value'] ?? '') === 'LGA1700' && ($by['ชนิดหน่วยความจำ']['value'] ?? '') === 'DDR4/DDR5' && ($by['TDP']['value'] ?? '') === '65 W', 'Generation, socket, memory type and TDP come from the catalog');
ok_pf(!isset($by['ขนาด / ฟอร์มแฟกเตอร์']) && !isset($by['ความจุ']), 'Empty facts are left out, never shown as a dash or invented');
foreach ($rows as $r) { ok_pf(array_key_exists('source', $r) && $r['source'] === null, 'Catalog facts carry no manufacturer source: ' . $r['label']); }

// reference specs from a manufacturer page: shown with source and date; they replace the catalog value of the same key
$ref = [
    ['spec_key' => 'socket', 'spec_value' => 'AM4', 'unit' => null, 'source_url' => 'https://example.com/amd', 'date_checked' => '2026-11-17', 'review_status' => 'official_checked'],
    ['spec_key' => 'max_gpu_length_mm', 'spec_value' => '360', 'unit' => 'mm', 'source_url' => 'https://example.com/case', 'date_checked' => '2026-11-17', 'review_status' => 'needs_variant_review'],
    ['spec_key' => 'psu_capacity_watt', 'spec_value' => '750', 'unit' => 'W', 'source_url' => 'https://example.com/psu', 'date_checked' => '2026-11-17', 'review_status' => 'official_checked'],
    ['spec_key' => 'unknown_future_key', 'spec_value' => 'x', 'unit' => null, 'source_url' => 'https://example.com/x', 'date_checked' => '2026-11-17', 'review_status' => 'official_checked'],
];
$prod = ['release_year' => null, 'generation' => null, 'socket' => 'AM5', 'memory_type' => null, 'tdp_watt' => null, 'form_factor' => 'ATX', 'capacity_gb' => null, 'spec_summary' => ''];
$by2 = array_column(F::rows($prod, $ref, 2026), null, 'label');
ok_pf(($by2['Socket']['value'] ?? '') === 'AM4' && ($by2['Socket']['source'] ?? '') === 'https://example.com/amd' && count(array_filter(F::rows($prod, $ref, 2026), static fn(array $r): bool => $r['label'] === 'Socket')) === 1, 'A manufacturer value replaces the catalog value of the same fact and is shown once, with its source');
ok_pf(($by2['การ์ดจอยาวสุดที่ใส่ได้']['value'] ?? '') === '360 mm' && str_contains((string)($by2['การ์ดจอยาวสุดที่ใส่ได้']['note'] ?? ''), 'ตรวจรุ่นย่อย'), 'An exact-variant warning is shown next to the figure: ' . json_encode($by2['การ์ดจอยาวสุดที่ใส่ได้'] ?? null, JSON_UNESCAPED_UNICODE));
ok_pf(($by2['กำลังไฟ PSU']['value'] ?? '') === '750 W' && ($by2['กำลังไฟ PSU']['note'] ?? null) === null, 'A checked figure has no warning');
ok_pf(!isset($by2['unknown_future_key']) && count(F::rows($prod, $ref, 2026)) === 4, 'A spec key the page does not know is not shown: ' . count(F::rows($prod, $ref, 2026)));
ok_pf(($by2['ขนาด / ฟอร์มแฟกเตอร์']['value'] ?? '') === 'ATX', 'A catalog value with no manufacturer figure stays');

// the source date is kept
$withDate = array_column(F::rows($prod, $ref, 2026), null, 'label');
ok_pf(($withDate['Socket']['checked'] ?? '') === '2026-11-17', 'The check date travels with the figure');

// only http(s) source links are ever passed on (a stored link is data, never trusted as a script)
$bad = [['spec_key' => 'socket', 'spec_value' => 'AM4', 'unit' => null, 'source_url' => 'javascript:alert(1)', 'date_checked' => '2026-11-17', 'review_status' => 'official_checked']];
$r = array_column(F::rows($prod, $bad, 2026), null, 'label');
ok_pf(($r['Socket']['value'] ?? '') === 'AM4' && array_key_exists('source', $r['Socket']) && $r['Socket']['source'] === null, 'A source that is not an http(s) link is not offered as a link');

// F-2: CPU/GPU manufacturer figures (cores, clocks, cache, VRAM, bus) and the manufacturer launch date
$cpuRef = static fn(string $k, string $v): array => ['spec_key' => $k, 'spec_value' => $v, 'unit' => null, 'source_url' => 'https://www.amd.com/x', 'date_checked' => '2026-11-18', 'review_status' => 'official_checked'];
$cpuProd = ['release_year' => 2022, 'generation' => 'Zen3', 'socket' => 'AM4', 'memory_type' => null, 'tdp_watt' => null, 'form_factor' => null, 'capacity_gb' => null];
$full = F::rows($cpuProd, [
    $cpuRef('release_date', '2020-11-05'), $cpuRef('architecture', 'Zen 3'), $cpuRef('cores', '6'), $cpuRef('threads', '12'), $cpuRef('p_cores', '6'), $cpuRef('e_cores', '0'),
    $cpuRef('base_ghz', '3.7'), $cpuRef('boost_ghz', '4.6'), $cpuRef('l2_mb', '3'), $cpuRef('l3_mb', '32'), $cpuRef('memory_support', 'DDR4, up to 3200 MT/s'),
    $cpuRef('gpu_cores', '3072'), $cpuRef('boost_mhz', '2460'), $cpuRef('vram_gb', '8'), $cpuRef('bus_width_bit', '128'), $cpuRef('power_connector', '1x8-Pin'), $cpuRef('interface', 'PCIe Gen 4'),
], 2026);
$by3 = array_column($full, null, 'label');
ok_pf(($by3['จำนวนคอร์']['value'] ?? '') === '6' && ($by3['จำนวนเธรด']['value'] ?? '') === '12', 'Cores and threads are shown: ' . json_encode(array_keys($by3), JSON_UNESCAPED_UNICODE));
ok_pf(($by3['ความเร็วพื้นฐาน']['value'] ?? '') === '3.7 GHz' && ($by3['ความเร็วบูสต์สูงสุด']['value'] ?? '') === '4.6 GHz', 'Clocks carry their unit');
ok_pf(($by3['L2 cache']['value'] ?? '') === '3 MB' && ($by3['L3 cache']['value'] ?? '') === '32 MB', 'Cache carries its unit');
ok_pf(($by3['หน่วยความจำที่รองรับ']['value'] ?? '') === 'DDR4, up to 3200 MT/s', 'CPU memory support is shown as the manufacturer states it');
ok_pf(($by3['VRAM']['value'] ?? '') === '8 GB' && ($by3['บัสหน่วยความจำ']['value'] ?? '') === '128 bit' && ($by3['ความเร็วบูสต์ (การ์ดจอ)']['value'] ?? '') === '2460 MHz', 'GPU VRAM, bus and boost clock carry their unit');
ok_pf(($by3['ขั้วต่อไฟเสริม']['value'] ?? '') === '1x8-Pin' && ($by3['ช่องต่อ']['value'] ?? '') === 'PCIe Gen 4', 'Power connector and interface are shown');
ok_pf(count(array_filter($full, static fn(array $r): bool => $r['label'] === 'สถาปัตยกรรม / เจเนอเรชัน')) === 1 && ($by3['สถาปัตยกรรม / เจเนอเรชัน']['value'] ?? '') === 'Zen 3' && ($by3['สถาปัตยกรรม / เจเนอเรชัน']['source'] ?? '') === 'https://www.amd.com/x', 'The manufacturer architecture replaces the catalog generation: one row, with its source');
// the manufacturer launch date wins over the catalog year and the age follows it
ok_pf(isset($by3['เปิดตัว']) && str_contains($by3['เปิดตัว']['value'], '2020-11-05') && str_contains($by3['เปิดตัว']['value'], 'อายุรุ่นประมาณ 6 ปี') && ($by3['เปิดตัว']['source'] ?? '') === 'https://www.amd.com/x' && ($by3['เปิดตัว']['checked'] ?? '') === '2026-11-18', 'The manufacturer date replaces the catalog year (2020, not 2022), the age follows it, and the source travels: ' . json_encode($by3['เปิดตัว'] ?? null, JSON_UNESCAPED_UNICODE));
ok_pf(str_contains((string)($by3['เปิดตัว']['note'] ?? ''), 'ไม่ใช่อายุการใช้งาน'), 'The release row still says this is the model age');
// a launch quarter works too; a date wins over a quarter
$q = array_column(F::rows($cpuProd, [$cpuRef('release_quarter', '2023-Q1')], 2026), null, 'label');
ok_pf(str_contains($q['เปิดตัว']['value'] ?? '', '2023') && str_contains($q['เปิดตัว']['value'], 'Q1') && str_contains($q['เปิดตัว']['value'], 'อายุรุ่นประมาณ 3 ปี'), 'A launch quarter is shown with the model age: ' . json_encode($q['เปิดตัว'] ?? null, JSON_UNESCAPED_UNICODE));
$both = array_column(F::rows($cpuProd, [$cpuRef('release_quarter', '2023-Q1'), $cpuRef('release_date', '2021-01-02')], 2026), null, 'label');
ok_pf(str_contains($both['เปิดตัว']['value'] ?? '', '2021-01-02') && !str_contains($both['เปิดตัว']['value'], 'Q1'), 'An exact date wins over a quarter');
// no manufacturer date: the catalog year stays; a nonsense reference date is ignored
$none = array_column(F::rows($cpuProd, [$cpuRef('release_date', 'soon'), $cpuRef('release_quarter', '')], 2026), null, 'label');
ok_pf(str_contains($none['เปิดตัว']['value'] ?? '', 'เปิดตัวปี 2022') && ($none['เปิดตัว']['source'] ?? null) === null, 'A reference date that is not a date is ignored and the catalog year stays');
$badDate = array_column(F::rows($cpuProd, [$cpuRef('release_date', '2024-13-45')], 2026), null, 'label');
ok_pf(str_contains($badDate['เปิดตัว']['value'] ?? '', 'เปิดตัวปี 2022') && ($badDate['เปิดตัว']['source'] ?? null) === null, 'An impossible calendar date is ignored');
$future = array_column(F::rows($cpuProd, [$cpuRef('release_date', '2031-01-01')], 2026), null, 'label');
ok_pf(str_contains($future['เปิดตัว']['value'] ?? '', 'เปิดตัวปี 2022'), 'A manufacturer date after the current year is ignored');
$noCatalogYear = array_column(F::rows(array_merge($cpuProd, ['release_year' => null]), [$cpuRef('release_date', '2024-01-08')], 2026), null, 'label');
ok_pf(str_contains($noCatalogYear['เปิดตัว']['value'] ?? '', '2024-01-08') && str_contains($noCatalogYear['เปิดตัว']['value'], 'อายุรุ่นประมาณ 2 ปี'), 'The manufacturer date works when the catalog has no year');
// a variant warning on the launch date row
$varDate = array_column(F::rows($cpuProd, [['spec_key' => 'release_date', 'spec_value' => '2021-01-02', 'unit' => null, 'source_url' => 'https://www.nvidia.com/x', 'date_checked' => '2026-11-18', 'review_status' => 'needs_variant_review']], 2026), null, 'label');
ok_pf(str_contains((string)($varDate['เปิดตัว']['note'] ?? ''), 'ไม่ใช่อายุการใช้งาน') && str_contains((string)($varDate['เปิดตัว']['note'] ?? ''), 'ตรวจรุ่นย่อย'), 'A variant warning is added to the release note without losing the model-age note');

// page wiring
$root = dirname(__DIR__);
$ctl = (string)file_get_contents($root . '/app/Controllers/PriceController.php');
ok_pf(str_contains($ctl, 'ProductFacts::rows') && str_contains($ctl, "'facts'"), 'The controller builds the facts and gives them to the view');
$mdl = (string)file_get_contents($root . '/app/Models/Product.php');
ok_pf(str_contains($mdl, 'function referenceSpecs') && str_contains($mdl, 'product_reference_specs') && str_contains($mdl, 'catch'), 'The model reads the reference specs and fails soft when the table is missing');
$view = (string)file_get_contents($root . '/app/Views/price/show.php');
ok_pf(str_contains($view, 'components/product-facts.php') && substr_count($view, 'components/product-facts.php') === 1, 'The product page shows the facts once, for priced and unpriced products alike');
$comp = (string)file_get_contents($root . '/app/Views/components/product-facts.php');
ok_pf(str_contains($comp, 'ข้อมูลรุ่น') && str_contains($comp, 'ยังไม่มีคะแนนเบนช์มาร์ก'), 'The component says plainly that no benchmark score is shown');
ok_pf(str_contains($comp, 'rel="noopener') && str_contains($comp, 'htmlspecialchars'), 'Source links are escaped and open safely');
echo "product_facts_test: PASS\n";
