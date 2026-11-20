<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Intake\IntakeCoverage;
use App\Services\Intake\IntakeValidator as V;

/**
 * LS-23 B1/B2: the data contract and the intake check. Rows are judged by code only; the report never repeats a cell; completeness is measured against
 * the criteria in database/contracts/intake-v1.json (the same numbers the master prompt gives collectors).
 */
function ok_iv(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$dir = sys_get_temp_dir() . '/mi_iv_' . bin2hex(random_bytes(4));
mkdir($dir, 0700, true);
$write = static function (string $name, array $header, array $rows) use ($dir): string {
    $h = fopen($dir . '/' . $name, 'w'); fwrite($h, "\xEF\xBB\xBF"); fputcsv($h, $header, ',', '"', ''); foreach ($rows as $r) { fputcsv($h, $r, ',', '"', ''); } fclose($h);
    return $dir . '/' . $name;
};
$now = new DateTimeImmutable('2026-11-19 12:00:00');
$ctx = ['now' => $now];

// ---- the contract itself: every dataset has a file name, columns, and every required column has a type ----
$c = V::contract();
ok_iv($c['version'] === 'intake-v1' && count($c['datasets']) === 8, 'The contract has the eight datasets of the master prompt');
foreach ($c['datasets'] as $k => $d) {
    ok_iv(str_ends_with($d['file'], '.csv') && $d['columns'] !== [], "$k has a file and columns");
    foreach ($d['columns'] as $col) { ok_iv(isset($col['name'], $col['required'], $col['type']), "$k column is fully described"); }
}
ok_iv(array_column($c['datasets']['A']['columns'], 'name') === ['capture_method', 'source_listing_url', 'source_item_id', 'listing_title', 'asking_price', 'observed_at', 'condition_hint'], 'A keeps the template column order');
ok_iv(in_array('board_power_watt', V::specKeys(), true) && in_array('speed_mhz', V::specKeys(), true), 'Supported and proposed spec keys are both known');

// ---- A: good rows pass ----
$hA = array_column($c['datasets']['A']['columns'], 'name');
$good = static fn(string $id, string $url = 'https://www.facebook.com/marketplace/item/', string $title = 'RTX 3070 used', string $price = '9500', string $at = '2026-11-18 20:15:00'): array
    => ['owner_transcription', $url . (str_contains($url, 'item/') ? $id . '/' : ''), $id, $title, $price, $at, 'used'];
$p = $write('A.csv', $hA, [$good('111'), $good('112', 'https://www.kaidee.com/product-')]);
$r = V::validateFile('A', $p, $ctx);
ok_iv($r['rows'] === 2 && $r['valid_rows'] === 2 && $r['row_errors'] === [] && $r['header_errors'] === [], 'Two good listings pass: ' . json_encode($r['codes']));

// ---- A: each fault gets its own code ----
$bad = [
    'ROW_PROFILE_LINK' => $good('201', 'https://www.facebook.com/profile.php?id=99'),
    'ROW_DOMAIN_NOT_ALLOWED' => $good('202', 'https://www.lazada.co.th/item/1'),
    'ROW_PHONE_IN_TEXT' => $good('203', 'https://www.facebook.com/marketplace/item/', 'RTX 3070 โทร 081-234-5678'),
    'ROW_BAD_PRICE_FORMAT' => $good('204', 'https://www.facebook.com/marketplace/item/', 'x', '7,500'),
    'ROW_BUDDHIST_YEAR' => $good('205', 'https://www.facebook.com/marketplace/item/', 'x', '7500', '2569-11-18 10:00:00'),
    'ROW_DATE_IN_FUTURE' => $good('206', 'https://www.facebook.com/marketplace/item/', 'x', '7500', '2027-01-01 10:00:00'),
    'ROW_TRACKING_PARAMS' => $good('207', 'https://www.facebook.com/marketplace/item/207/?fbclid=abc'),
    'ROW_FORMULA_CELL' => $good('208', 'https://www.facebook.com/marketplace/item/', '=HYPERLINK("x")'),
    'ROW_BAD_ENUM' => ['scraped'] + $good('209'),
    'ROW_PRICE_RANGE' => $good('210', 'https://www.facebook.com/marketplace/item/', 'x', '99999999'),
    'ROW_EMAIL_IN_TEXT' => $good('211', 'https://www.facebook.com/marketplace/item/', 'ติดต่อ seller@example.com'),
    'ROW_LINE_ID_IN_TEXT' => $good('212', 'https://www.facebook.com/marketplace/item/', 'ไลน์: seller_99'),
];
$rows = array_values($bad); $rows[] = $good('201');   // same item id as the first bad row
$p = $write('A_bad.csv', $hA, $rows);
$r = V::validateFile('A', $p, $ctx);
foreach (array_keys($bad) as $code) { ok_iv(isset($r['codes'][$code]), "Fault $code is reported: " . json_encode($r['codes'])); }
ok_iv(isset($r['codes']['ROW_DUPLICATE_KEY']), 'A repeated source_item_id is reported');
ok_iv($r['valid_rows'] === 0, 'No faulty row is counted valid');

// ---- header faults ----
$p = $write('A_head.csv', ['capture_method', 'source_listing_url', 'listing_title', 'asking_price', 'observed_at', 'seller_name'], [['owner_transcription', 'https://pantip.com/topic/1', 't', '1', '2026-11-18 10:00:00', 'x']]);
$r = V::validateFile('A', $p, $ctx);
ok_iv(in_array('HEADER_MISSING_COLUMN:source_item_id', $r['header_errors'], true) && in_array('HEADER_UNKNOWN_COLUMN:seller_name', $r['header_errors'], true), 'Missing and unknown columns are reported by name');
ok_iv(V::validateFile('A', $dir . '/nope.csv', $ctx)['header_errors'] === ['FILE_NOT_FOUND'], 'A missing file says so');
file_put_contents($dir . '/latin.csv', "a,b\n\xE9,x\n");
ok_iv(V::validateFile('A', $dir . '/latin.csv', $ctx)['header_errors'] === ['FILE_NOT_UTF8'], 'A non UTF-8 file says so');

// ---- B: known products, dates, price ----
$ctxP = $ctx + ['products' => [V::norm('ASUS Prime B550M-K') => 'motherboard'], 'names' => [V::norm('ASUS Prime B550M-K') => 'ASUS Prime B550M-K'], 'b_targets' => ['ASUS Prime B550M-K', 'Corsair RM750e']];
$hB = array_column($c['datasets']['B']['columns'], 'name');
$p = $write('B.csv', $hB, [['ASUS Prime B550M-K', '3290', 'https://shop.example.co.th/p/1', '2026-11-15', 'yes', ''], ['Unknown Board 9000', '3290', 'https://shop.example.co.th/p/2', '2026-11-15', '', ''], ['ASUS Prime B550M-K', '3290', 'https://shop.example.co.th/p/3', '2026-09-01', '', '']]);
$r = V::validateFile('B', $p, $ctxP);
ok_iv($r['valid_rows'] === 1 && isset($r['codes']['ROW_UNKNOWN_PRODUCT']) && isset($r['codes']['ROW_DATE_TOO_OLD']), 'B: unknown model and a stale price date are rejected: ' . json_encode($r['codes']));
$cov = IntakeCoverage::evaluate(['B' => $r], $ctxP)['B'];
ok_iv($cov['targets'] === 2 && $cov['covered'] === 1 && $cov['missing'] === ['Corsair RM750e'], 'B coverage names the model that still has no price');

// ---- C: spec keys and required keys per category ----
$hC = array_column($c['datasets']['C']['columns'], 'name');
$spec = static fn(string $k, string $v): array => ['ASUS Prime B550M-K', $k, $v, '', 'https://www.asus.com/x', '2026-11-10', 'official_checked', ''];
$p = $write('C.csv', $hC, [$spec('socket', 'AM4'), $spec('memory_type', 'DDR4'), $spec('socket', 'AM5'), $spec('colour', 'black')]);
$r = V::validateFile('C', $p, $ctxP);
ok_iv(isset($r['codes']['ROW_UNKNOWN_SPEC_KEY']) && isset($r['codes']['ROW_DUPLICATE_KEY']) && $r['valid_rows'] === 2, 'C: an unknown spec key and a second value for the same key are rejected');
$cov = IntakeCoverage::evaluate(['C' => $r], $ctxP)['C'];
ok_iv($cov['models_incomplete'][0]['missing_required'] === ['form_factor'], 'C coverage lists the required key still missing (motherboard needs form_factor)');

// ---- D: aliases ----
$hD = array_column($c['datasets']['D_aliases']['columns'], 'name');
$ctxA = $ctx + ['aliases' => ['5700x3d' => true]];
$p = $write('D.csv', $hD, [['AMD Ryzen 7 5700X3D', '5700x3d', 'shorthand', ''], ['AMD Ryzen 7 5700X3D', '5700 X3D', 'spacing', ''], ['AMD Ryzen 7 5700X3D', 'r7 5700x3d', 'shorthand', '']]);
$r = V::validateFile('D_aliases', $p, $ctxA);
ok_iv(isset($r['codes']['ROW_ALIAS_ALREADY_EXISTS']) && isset($r['codes']['ROW_DUPLICATE_KEY']), 'D: an alias that exists, or repeats inside the file, is rejected: ' . json_encode($r['codes']));
$cov = IntakeCoverage::evaluate(['D_aliases' => $r])['D_aliases'];
ok_iv($cov['models_meeting_criteria'] === [] && $cov['models_below_criteria'][0]['aliases'] === 1, 'D coverage needs four aliases per model');

// ---- E: categories need six checks with three must_check ----
$hE = array_column($c['datasets']['E']['criteria'] ? $c['datasets']['E']['columns'] : [], 'name');
$e = [];
for ($i = 1; $i <= 6; $i++) { $e[] = ['case', "ข้อ $i", 'ตรวจด้วยตา', 'บุบหรือขึ้นสนิม', $i <= 3 ? 'must_check' : 'should_check', 'none', '']; }
$p = $write('E.csv', $hE, $e);
$r = V::validateFile('E', $p, $ctx);
ok_iv($r['valid_rows'] === 6, 'E: six well formed checks pass');
ok_iv(IntakeCoverage::evaluate(['E' => $r])['E']['categories_meeting_criteria'] === ['case'], 'E coverage: case meets six checks with three must_check');

// ---- A coverage: five listings from two sources in the window meet the criteria, three do not ----
$mk = static fn(int $i, string $host): array => ['capture_method' => 'owner_transcription', 'source_listing_url' => "https://$host/x/$i", 'source_item_id' => "i$i", 'listing_title' => 'rtx 3070', 'asking_price' => '9500', 'observed_at' => '2026-11-10 10:00:00', 'condition_hint' => ''];
$resolve = static fn(string $t): ?string => 'NVIDIA GeForce RTX 3070';
$five = [$mk(1, 'www.facebook.com'), $mk(2, 'www.facebook.com'), $mk(3, 'www.facebook.com'), $mk(4, 'pantip.com'), $mk(5, 'pantip.com')];
$cov = IntakeCoverage::evaluate(['A' => ['valid' => $five]], $ctx + ['resolve' => $resolve])['A'];
ok_iv(count($cov['products_meeting_criteria']) === 1 && $cov['products_meeting_criteria'][0]['sources'] === 2, 'A coverage: five rows from two sources meet the criteria');
$cov = IntakeCoverage::evaluate(['A' => ['valid' => array_slice($five, 0, 3)]], $ctx + ['resolve' => $resolve])['A'];
ok_iv(count($cov['products_below_criteria']) === 1 && $cov['products_meeting_criteria'] === [], 'A coverage: three rows from one source do not');
$old = $mk(9, 'pantip.com'); $old['observed_at'] = '2026-08-01 10:00:00';
$cov = IntakeCoverage::evaluate(['A' => ['valid' => [$old]]], $ctx + ['resolve' => $resolve])['A'];
ok_iv($cov['rows_outside_window'] === 1, 'A coverage: a row older than 45 days is outside the window');

// ---- the CLI: reads a folder, never prints a cell, writes nothing ----
$secret = 'ZZSECRET' . bin2hex(random_bytes(3));
$write('A_used_listings.csv', $hA, [$good('301', 'https://www.facebook.com/marketplace/item/', "$secret โทร 0812345678"), $good('302', 'https://www.facebook.com/marketplace/item/', $secret)]);
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(ROOT_PATH . '/cli/intake_validate.php') . ' ' . escapeshellarg($dir) . ' --json --no-db 2>&1', $out, $code);
$text = implode("\n", $out);
ok_iv($code === 1 && str_contains($text, 'ROW_PHONE_IN_TEXT'), 'The CLI exits 1 and names the problem code');
ok_iv(!str_contains($text, $secret) && !str_contains($text, '0812345678') && !str_contains($text, 'facebook.com/marketplace/item/301'), 'The CLI report never repeats a title, number or link');
ok_iv(json_decode($text, true)['files']['A']['valid_rows'] === 1, 'The CLI counts the one valid row');
ok_iv(!preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP)\b/', (string)file_get_contents(ROOT_PATH . '/cli/intake_validate.php') . (string)file_get_contents(ROOT_PATH . '/app/Services/Intake/IntakeValidator.php')), 'The intake check is read-only');

// ---- zip: entries are read by name; a hostile path writes nothing outside ----
if (class_exists('ZipArchive')) {
    $zp = $dir . '/batch.zip'; $z = new ZipArchive(); $z->open($zp, ZipArchive::CREATE);
    $z->addFile($dir . '/A_used_listings.csv', 'sub/dir/A_used_listings.csv'); $z->addFromString('../../evil_marker.csv', 'x'); $z->addFromString('_batch_notes.txt', 'ok'); $z->close();
    $out = []; exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(ROOT_PATH . '/cli/intake_validate.php') . ' ' . escapeshellarg($zp) . ' --json --no-db 2>&1', $out, $code);
    $j = json_decode(implode("\n", $out), true);
    ok_iv(is_array($j) && $j['batch_notes_present'] === true && $j['files']['A']['rows'] === 2, 'A zip is read by file name, whatever folder it sits in');
    ok_iv(!is_file(dirname($dir, 1) . '/evil_marker.csv') && !is_file(dirname($dir, 2) . '/evil_marker.csv'), 'A path inside the zip is never written');
}

array_map('unlink', glob($dir . '/*') ?: []); @rmdir($dir);
echo "intake_validator_test: PASS\n";
