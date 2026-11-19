<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Collection\OfflineEvidenceImportService;

/**
 * LS-14 Module 9.3 (OD-5): an imported market row may carry the same optional listing facts as a seller listing (condition %, warranty, province,
 * shipping, defects ...). A bad fact rejects that row by field name, good facts are stored on the observation, and a file without any of them
 * imports exactly as before. The price engine does not read these columns. Test database; the rows are removed afterwards.
 */
function ok_il(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

global $config;
$db = Database::connection($config['db']);
ok_il($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$dir = ROOT_PATH . '/storage/import/test';
if (!is_dir($dir)) { mkdir($dir, 0777, true); }
$tag = bin2hex(random_bytes(3));
$header = 'source_listing_url,source_item_id,listing_title,asking_price,observed_at,condition_hint,condition_percent,under_warranty,warranty_months,province,shipping_option,shipping_cost,has_test_result,test_result_note,defect_codes,defect_note';
$rows = [
    $header,
    "https://www.facebook.com/marketplace/item/91{$tag}01,IL-$tag-1,NVIDIA GeForce RTX 3070 8GB used,8200,2026-11-14 12:00:00,used,90,yes,6,นนทบุรี,paid,60,1,ผ่านเกม 2 ชม.,scratch;fan_noise,รอยที่ฝาข้าง",
    "https://www.facebook.com/marketplace/item/91{$tag}02,IL-$tag-2,NVIDIA GeForce RTX 3070 8GB used,8100,2026-11-14 12:05:00,used,150,,,,,,,,,",
    "https://www.facebook.com/marketplace/item/91{$tag}03,IL-$tag-3,NVIDIA GeForce RTX 3070 8GB used,8000,2026-11-14 12:10:00,used,,,,,,,,,,โทร 081-234-5678",
    "https://www.facebook.com/marketplace/item/91{$tag}04,IL-$tag-4,NVIDIA GeForce RTX 3070 8GB used,7900,2026-11-14 12:15:00,used,,,,Atlantis,,,,,,",
];
$file = $dir . "/mi_listing_attrs_$tag.csv";
file_put_contents($file, implode("\n", $rows) . "\n");
$db->beginTransaction();
try {
    $dry = (new OfflineEvidenceImportService())->import($file, 'real', true, 100);
    $why = json_encode(['valid' => $dry['valid_rows'], 'invalid' => $dry['invalid_rows'], 'errors' => $dry['row_errors']], JSON_UNESCAPED_UNICODE);
    ok_il($dry['valid_rows'] === 1 && $dry['invalid_rows'] === 3, 'One good row, three bad ones (condition over 100, phone number in a note, unknown province): ' . $why);
    ok_il(str_contains($why, 'INVALID_LISTING_CONDITION_PERCENT') && str_contains($why, 'INVALID_LISTING_DEFECT_NOTE') && str_contains($why, 'INVALID_LISTING_PROVINCE'), 'Each bad fact is named by its field: ' . $why);

    $before = [(int)$db->query('SELECT COUNT(*) FROM price_indices')->fetchColumn()];
    $real = (new OfflineEvidenceImportService())->import($file, 'real', false, 100);
    ok_il($real['candidates_created'] >= 1, 'The good row is imported: ' . json_encode($real, JSON_UNESCAPED_UNICODE));
    $o = $db->query("SELECT o.* FROM price_observations o JOIN raw_price_observations r ON r.id = o.raw_observation_id WHERE r.raw_title LIKE '%RTX 3070%' ORDER BY o.id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    ok_il($o && (int)$o['condition_percent'] === 90 && (int)$o['under_warranty'] === 1 && (int)$o['warranty_months'] === 6 && $o['province'] === 'นนทบุรี'
        && $o['shipping_option'] === 'paid' && (float)$o['shipping_cost'] === 60.0 && (int)$o['has_benchmark'] === 1 && $o['defect_codes'] === 'scratch,fan_noise', 'The listing facts are stored on the observation (has_test_result is has_benchmark): ' . json_encode($o, JSON_UNESCAPED_UNICODE));
    ok_il($o['verified_status'] === 'pending', 'The observation still waits for a human review');
    ok_il($before === [(int)$db->query('SELECT COUNT(*) FROM price_indices')->fetchColumn()], 'No snapshot was created by the import');

    // a file without any listing fact imports as before
    $plain = $dir . "/mi_listing_plain_$tag.csv";
    file_put_contents($plain, "source_listing_url,source_item_id,listing_title,asking_price,observed_at,condition_hint\nhttps://www.facebook.com/marketplace/item/92{$tag}01,IL-P-$tag,NVIDIA GeForce RTX 3070 8GB used,8300,2026-11-14 13:00:00,used\n");
    $p = (new OfflineEvidenceImportService())->import($plain, 'real', true, 100);
    ok_il($p['valid_rows'] === 1 && $p['invalid_rows'] === 0, 'A file without the new columns is still valid');
    @unlink($plain);
} finally {
    $db->rollBack();
    @unlink($file);
}
echo "import_listing_attributes_test: PASS\n";
