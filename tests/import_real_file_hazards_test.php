<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Collection\OfflineEvidenceImportService;

/**
 * LS-6 M-1: two ways a real daily file from the owner could fail quietly or confusingly (found by reading the importer, pinned here).
 *  1. Excel's "CSV UTF-8" starts the file with a byte-order mark. If it stayed in the header, the first column name (capture_method) would not match,
 *     the row would import WITHOUT the owner-transcription freeze, and it would then sit outside every price with no clear error.
 *  2. The mock/test guard looked for the text "test" anywhere in the listing URL, so a real page called "contestshop" or a post slug with "latest"
 *     was rejected as a mock source. The guard must still reject real mock/fixture/test markers.
 */
function ok_rh(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$dir = ROOT_PATH . '/storage/import/test';
if (!is_dir($dir)) { mkdir($dir, 0777, true); }
$header = 'capture_method,source_listing_url,source_item_id,listing_title,asking_price,observed_at,condition_hint';
$files = [];
$dry = static function (string $content) use ($dir, &$files): array {
    $f = $dir . '/mi_rh_' . bin2hex(random_bytes(4)) . '.csv';
    file_put_contents($f, $content);
    $files[] = $f;
    return (new OfflineEvidenceImportService())->import($f, 'real', true, 50);
};
$codes = static function (array $r): array {
    $c = [];
    foreach ($r['row_errors'] as $e) { foreach ($e['errors'] as $x) { $c[$x] = ($c[$x] ?? 0) + 1; } }
    return $c;
};
try {
    $good = 'owner_transcription,https://www.facebook.com/marketplace/item/9100000000001,FB-9100000000001,RTX 3070 8GB used,8200,2026-11-14 12:00:00,used';

    // 1. byte-order mark, with LF and with CRLF line endings (Excel on Windows writes CRLF)
    foreach (["\xEF\xBB\xBF" . $header . "\n" . $good . "\n", "\xEF\xBB\xBF" . $header . "\r\n" . $good . "\r\n", $header . "\r\n" . $good . "\r\n"] as $i => $content) {
        $r = $dry($content);
        ok_rh($r['valid_rows'] === 1, "Variant $i: the row is valid: " . json_encode([$r['valid_rows'], $codes($r)]));
    }
    // and the freeze really happened: a BOM-prefixed file must not be treated as a row with no capture method (it would pass without a snapshot)
    $r = $dry("\xEF\xBB\xBF" . $header . "\n" . 'owner_transcription,https://example.com/x,EX-1,RTX 3070 wrong domain,8200,2026-11-14 12:00:00,used' . "\n");
    ok_rh(isset($codes($r)['OWNER_TRANSCRIPTION_DOMAIN_NOT_ALLOWED']), 'With a BOM the capture_method column is still recognised (a wrong domain is still caught): ' . json_encode($codes($r)));

    // 2. the mock/test guard
    foreach (['https://www.facebook.com/contestshop/posts/9100000000002', 'https://www.facebook.com/latestgadgets/posts/9100000000003'] as $n => $url) {
        $r = $dry($header . "\n" . "owner_transcription,$url,FB-91000000000" . (10 + $n) . ",RTX 3070 8GB used,8200,2026-11-14 12:00:00,used\n");
        ok_rh($r['valid_rows'] === 1 && !isset($codes($r)['REAL_DATASET_CANNOT_USE_MOCK_TEST_SOURCE']), "A real page name containing the letters 'test' is not a mock source ($url): " . json_encode($codes($r)));
    }
    foreach (['https://www.facebook.com/test/posts/9100000000004', 'https://www.facebook.com/shop-mock/posts/9100000000005', 'https://www.facebook.com/fixture_shop/posts/9100000000006', 'https://test.facebook.com/marketplace/item/9100000000007', 'https://www.facebook.com/marketplace/item/9100000000008?mock=1'] as $url) {
        $r = $dry($header . "\n" . "owner_transcription,$url,FB-9100000000099,RTX 3070 8GB used,8200,2026-11-14 12:00:00,used\n");
        ok_rh(isset($codes($r)['REAL_DATASET_CANNOT_USE_MOCK_TEST_SOURCE']), "A real mock/test/fixture marker is still rejected ($url): " . json_encode($codes($r)));
    }
} finally { foreach ($files as $f) { @unlink($f); } }
echo "import_real_file_hazards_test: PASS\n";
