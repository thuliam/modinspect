<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Collection\OfflineEvidenceImportService;

function ok_ss(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

// The documented minimal second-source file (docs/SECOND_SOURCE_INTAKE.md) must validate under dataset=real in a DRY RUN (nothing is written).
$header = trim((string)file_get_contents(__DIR__ . '/../examples/import/second_source_minimal.csv'));
ok_ss($header === 'source_listing_url,source_item_id,listing_title,asking_price,observed_at,condition_hint', 'The minimal template header is the documented one');
// the importer only reads files inside the project root on Linux (safePath), so the fixture lives under the git-ignored storage/import/test
$dir = ROOT_PATH . '/storage/import/test';
if (!is_dir($dir)) { mkdir($dir, 0777, true); }
$tmp = $dir . '/mi_second_source_' . bin2hex(random_bytes(4)) . '.csv';
$rows = [
    $header,
    'https://www.facebook.com/marketplace/item/9000000000001,SS-FB-1,RTX 3070 8GB used,8200,2026-11-14 12:00:00,used',
    'https://pantip.com/topic/90000001,SS-PT-1,การ์ดจอ RTX 3070 มือสอง,8000,2026-11-13 12:00:00,used',
    ',,RTX 3070 no reference,8100,2026-11-12 12:00:00,used',
    'https://example.test/listing/1,SS-EX-1,RTX 3070 mock domain,8100,2026-11-12 12:00:00,used',
];
file_put_contents($tmp, implode("\n", $rows) . "\n");
try {
    $r = (new OfflineEvidenceImportService())->import($tmp, 'real', true, 100);
    ok_ss($r['dry_run'] === true && $r['candidates_created'] === 0, 'A dry run writes nothing');
    $why = json_encode(['scanned' => $r['records_scanned'], 'valid' => $r['valid_rows'], 'invalid' => $r['invalid_rows'], 'duplicates' => $r['duplicate_rows'], 'errors' => $r['row_errors']], JSON_UNESCAPED_UNICODE);
    ok_ss($r['valid_rows'] === 2, 'Two listing-level rows from two different domains are valid: ' . $why);
    ok_ss($r['invalid_rows'] === 2, 'A row without a listing reference and a mock-domain row are rejected: ' . $why);
} finally { @unlink($tmp); }

echo "second_source_intake_test: PASS\n";
