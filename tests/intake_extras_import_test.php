<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Collection\OfflineEvidenceImportService;
use App\Services\Intake\IntakeExtras;

/**
 * LS-23 B3: the optional facts file joins the listing file and the importer stores the facts on the observation (warranty, age, province, box, receipt);
 * wanted / trade / bundle listings are left out, defective ones keep is_defective. Test database inside a transaction, rolled back.
 */
function ok_ie(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

// ---- pure join ----
$L = static fn(string $id): array => ['capture_method' => 'owner_transcription', 'source_listing_url' => "https://www.facebook.com/marketplace/item/$id/", 'source_item_id' => $id, 'listing_title' => 'RTX 3070 used', 'asking_price' => '8200', 'observed_at' => '2026-11-14 12:00:00', 'condition_hint' => 'used'];
$X = static fn(string $id, array $more): array => array_merge(['source_item_id' => $id, 'product_name_guess' => '', 'warranty_remaining_months' => '', 'has_box' => '', 'has_receipt' => '', 'usage_hint_months' => '', 'province' => '', 'posted_at' => '', 'flag' => ''], $more);
$m = IntakeExtras::merge([$L('1'), $L('2'), $L('3'), $L('4'), $L('5')], [
    $X('1', ['warranty_remaining_months' => '6', 'has_box' => '1', 'has_receipt' => '0', 'usage_hint_months' => '14', 'province' => 'นนทบุรี']),
    $X('2', ['flag' => 'bundle']), $X('3', ['flag' => 'defective']), $X('4', ['flag' => 'wanted']), $X('99', [])]);
ok_ie(count($m['records']) === 3 && $m['left_out'] === ['bundle' => 1, 'wanted' => 1], 'Bundle and wanted listings are left out, the rest stay');
ok_ie($m['extras_without_listing'] === 1 && $m['extras_used'] === 2, 'An extra row with no listing is counted, not imported');
$r1 = $m['records'][0];
ok_ie($r1['warranty_months'] === '6' && $r1['under_warranty'] === '1' && $r1['product_age_months'] === '14' && $r1['province'] === 'นนทบุรี' && $r1['has_box'] === '1' && $r1['has_receipt'] === '0', 'Facts are renamed to the importer columns');
ok_ie($m['records'][1]['is_defective'] === '1', 'A defective listing keeps is_defective');
ok_ie($m['records'][2]['warranty_months'] === '' && $m['records'][2]['has_box'] === '', 'A listing without extras gets empty facts');
$zero = IntakeExtras::merge([$L('7')], [$X('7', ['warranty_remaining_months' => '0'])])['records'][0];
ok_ie($zero['under_warranty'] === '0', 'Zero months of warranty left means not under warranty');

// ---- end to end: files -> prepare CLI -> importer -> observation ----
global $config;
$db = Database::connection($config['db']);
ok_ie($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$tag = bin2hex(random_bytes(3));
$dir = sys_get_temp_dir() . '/mi_ie_' . bin2hex(random_bytes(4)); mkdir($dir, 0700, true);
$csv = static function (string $file, array $header, array $rows) use ($dir): void { $h = fopen($dir . '/' . $file, 'w'); fputcsv($h, $header, ',', '"', ''); foreach ($rows as $r) { fputcsv($h, $r, ',', '"', ''); } fclose($h); };
$csv('A_used_listings.csv', ['capture_method', 'source_listing_url', 'source_item_id', 'listing_title', 'asking_price', 'observed_at', 'condition_hint'], [
    ['owner_transcription', "https://www.facebook.com/marketplace/item/93{$tag}01/", "IE-$tag-1", 'NVIDIA GeForce RTX 3070 8GB used', '8200', '2026-11-14 12:00:00', 'used'],
    ['owner_transcription', "https://www.facebook.com/marketplace/item/93{$tag}02/", "IE-$tag-2", 'NVIDIA GeForce RTX 3070 8GB used x2 bundle', '15000', '2026-11-14 12:05:00', 'used']]);
$csv('A_used_listings_extra.csv', ['source_item_id', 'product_name_guess', 'warranty_remaining_months', 'has_box', 'has_receipt', 'usage_hint_months', 'province', 'posted_at', 'flag'], [
    ["IE-$tag-1", '', '6', '1', '0', '14', 'นนทบุรี', '', ''], ["IE-$tag-2", '', '', '', '', '', '', '', 'bundle']]);
$importDir = ROOT_PATH . '/storage/import/test'; if (!is_dir($importDir)) { mkdir($importDir, 0777, true); }   // the importer reads only files inside the project root
$out = $importDir . '/ie_' . $tag . '.csv';
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(ROOT_PATH . '/cli/intake_prepare_import.php') . ' ' . escapeshellarg($dir) . ' --out=' . escapeshellarg($out) . ' 2>&1', $o, $code);
$sum = json_decode(implode("\n", $o), true);
ok_ie($code === 0 && $sum['written'] === 1 && $sum['left_out'] === ['bundle' => 1] && is_file($out), 'The prepare CLI writes one row and reports the bundle left out: ' . implode(' ', $o));
ok_ie(!str_contains(implode("\n", $o), 'RTX') && !str_contains(implode("\n", $o), 'facebook.com'), 'The prepare CLI prints no title or link');

$db->beginTransaction();
try {
    $real = (new OfflineEvidenceImportService())->import($out, 'real', false, 100);
    ok_ie($real['candidates_created'] === 1, 'The prepared file imports: ' . json_encode($real, JSON_UNESCAPED_UNICODE));
    $o2 = $db->query("SELECT o.* FROM price_observations o JOIN raw_price_observations r ON r.id = o.raw_observation_id WHERE r.raw_title LIKE '%RTX 3070%' ORDER BY o.id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    ok_ie($o2 && (int)$o2['warranty_months'] === 6 && (int)$o2['under_warranty'] === 1 && (int)$o2['product_age_months'] === 14 && $o2['province'] === 'นนทบุรี'
        && (int)$o2['has_box'] === 1 && (int)$o2['has_receipt'] === 0, 'Warranty, age, province, box and receipt are on the observation: ' . json_encode($o2, JSON_UNESCAPED_UNICODE));
    ok_ie($o2['verified_status'] === 'pending', 'The observation waits for a human review');
} finally {
    $db->rollBack();
    array_map('unlink', glob($dir . '/*') ?: []); @rmdir($dir); @unlink($out);
}
echo "intake_extras_import_test: PASS\n";
