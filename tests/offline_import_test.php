<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Models\Dashboard;
use App\Services\Collection\OfflineEvidenceImportService;
use App\Services\Pricing\PriceSnapshotService;
use App\Services\Review\ReviewCalibrationService;

function assert_import(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function write_import_file(string $name, string $contents): string
{
    $dir = ROOT_PATH . '/storage/import/test';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $path = $dir . '/' . $name;
    file_put_contents($path, $contents);
    return $path;
}

function count_import(PDO $db, string $sql): int
{
    return (int)$db->query($sql)->fetchColumn();
}

global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();

try {
    $productId = (int)$db->query("SELECT id FROM products WHERE slug='amd-ryzen-7-5700x3d' LIMIT 1")->fetchColumn();
    assert_import($productId > 0, 'Missing 5700X3D product');
    $suffix = bin2hex(random_bytes(4));
    $service = new OfflineEvidenceImportService();

    $jsonPath = write_import_file('offline-import-' . $suffix . '.json', json_encode([
        'records' => [
            [
                'source_type' => 'marketplace',
                'source_url' => 'https://market.local/listing/' . $suffix . '-5700x3d-a',
                'source_domain' => 'market.local',
                'title' => 'AMD Ryzen 7 5700X3D มือสอง',
                'listing_text' => 'ขาย 5700X3D ใช้งานปกติ ราคา 7500 บาท ประกัน 6 เดือน',
                'observed_at' => '2026-09-09 12:00:00',
                'displayed_price' => '7500',
                'currency' => 'THB',
                'product_hint' => '5700X3D',
                'external_listing_id' => 'json-' . $suffix . '-a',
            ],
            [
                'source_type' => 'marketplace',
                'source_url' => 'https://market.local/listing/' . $suffix . '-5700x3d-b',
                'source_domain' => 'market.local',
                'title' => 'R7 5700X3D มือสอง สวย',
                'listing_text' => 'ปล่อย Ryzen 5700X3D ราคา 7800 ใช้งานได้ดี',
                'observed_at' => '2026-09-09 12:00:00',
                'displayed_price' => '7800',
                'currency' => 'THB',
                'external_listing_id' => 'json-' . $suffix . '-b',
            ],
            [
                'source_type' => 'marketplace',
                'source_url' => 'https://market.local/listing/' . $suffix . '-5700x3d-c',
                'source_domain' => 'market.local',
                'title' => 'Ryzen5700x3d ราคา 9500 ลดได้ 9000',
                'listing_text' => 'ขาย 5700X3D ตัวเดียว มีราคาคุยกันได้ 9500 หรือ 9000',
                'observed_at' => '2026-09-09 12:00:00',
                'currency' => 'THB',
                'external_listing_id' => 'json-' . $suffix . '-c',
            ],
            [
                'source_type' => 'marketplace',
                'source_domain' => 'market.local',
                'title' => '',
                'listing_text' => '',
                'observed_at' => 'not-a-date',
                'displayed_price' => '-1',
                'currency' => 'USD',
            ],
        ],
    ], JSON_UNESCAPED_UNICODE));

    $beforeRaw = count_import($db, 'SELECT COUNT(*) FROM raw_price_observations');
    $dry = $service->import($jsonPath, 'test', true, 10);
    assert_import($dry['dry_run'] === true, 'Dry-run flag missing');
    assert_import($dry['dataset'] === 'TEST', 'Default/test dataset classification missing');
    assert_import($dry['records_scanned'] === 4, 'Dry-run scanned count mismatch');
    assert_import($dry['valid_rows'] === 3, 'Dry-run valid rows mismatch');
    assert_import($dry['invalid_rows'] === 1, 'Dry-run invalid row missing');
    assert_import($dry['estimated_candidate_count'] === 3, 'Dry-run estimated candidate count mismatch');
    assert_import(count_import($db, 'SELECT COUNT(*) FROM raw_price_observations') === $beforeRaw, 'Dry-run mutated raw observations');

    $real = $service->import($jsonPath, 'real', false, 10);
    assert_import($real['dataset'] === 'REAL', 'Explicit REAL classification missing');
    assert_import($real['candidates_created'] === 3, 'Expected three created candidates from JSON import');
    assert_import($real['evidence_created'] === 3 && $real['extractions_created'] === 3 && $real['reviews_created'] === 3, 'Pipeline rows missing');
    assert_import(($real['green'] + $real['amber'] + $real['red']) === 3 && $real['amber'] >= 1, 'Expected reviewable pipeline lanes');

    $sourceId = (int)$db->query("SELECT id FROM data_sources WHERE source_key='offline_real_market_local' LIMIT 1")->fetchColumn();
    assert_import($sourceId > 0, 'Offline real source missing');
    $pending = count_import($db, "SELECT COUNT(*) FROM price_observations o JOIN raw_price_observations r ON r.id=o.raw_observation_id WHERE r.source_id={$sourceId} AND o.verified_status='pending'");
    assert_import($pending === 3, 'REAL import must remain pending for human review');
    $provenance = (string)$db->query("SELECT e.excerpt FROM market_evidence e JOIN raw_price_observations r ON r.id=e.raw_observation_id WHERE r.source_id={$sourceId} ORDER BY e.id LIMIT 1")->fetchColumn();
    assert_import(str_contains($provenance, 'IMPORT_PROVIDER=offline_file_import') && str_contains($provenance, 'DATASET=REAL'), 'Import provenance missing from evidence');

    $repeat = $service->import($jsonPath, 'real', false, 10);
    assert_import($repeat['candidates_created'] === 0, 'Duplicate re-import should not create candidates');
    assert_import($repeat['duplicate_rows'] >= 3, 'Duplicate re-import should report duplicates');

    $csvPath = write_import_file('offline-import-' . $suffix . '.csv',
        "source_type,source_url,source_reference,title,listing_text,observed_at,displayed_price,currency,source_domain,external_listing_id,notes\n" .
        "marketplace,https://market.local/listing/{$suffix}-csv,,RTX 3070 มือสอง,ขาย RTX3070 ราคา 8500 ใช้งานปกติ,2026-09-09 12:00:00,8500,THB,market.local,csv-{$suffix},CSV import fixture\n"
    );
    $csv = $service->import($csvPath, 'real', false, 10);
    assert_import($csv['format'] === 'csv' && $csv['candidates_created'] === 1, 'CSV import failed');

    $pausedKey = 'offline_real_paused_local';
    $db->prepare("INSERT INTO data_sources (source_key,name,domain,source_type,access_method,allowed_collection_method,risk_level,reliability_score,evidence_quality,freshness_expectation_days,is_active,is_paused) VALUES (:source_key,'Offline Paused Source','paused.local','marketplace','manual','manual','medium',40,'low',14,1,1)")
        ->execute(['source_key' => $pausedKey]);
    $pausedPath = write_import_file('offline-import-paused-' . $suffix . '.json', json_encode([
        ['source_type' => 'marketplace', 'source_url' => 'https://paused.local/listing/' . $suffix, 'source_domain' => 'paused.local', 'title' => 'AMD Ryzen 7 5700X3D', 'listing_text' => 'ขาย 5700X3D ราคา 7600', 'observed_at' => '2026-09-09 12:00:00', 'displayed_price' => '7600', 'currency' => 'THB']
    ], JSON_UNESCAPED_UNICODE));
    $blocked = $service->import($pausedPath, 'real', false, 10);
    assert_import($blocked['source_policy_blocked'] === 1 && $blocked['candidates_created'] === 0, 'Paused source policy should block import');

    $mockRealPath = write_import_file('offline-import-mock-real-' . $suffix . '.json', json_encode([
        ['source_type' => 'mock', 'source_url' => 'https://mock.local/listing/' . $suffix, 'source_domain' => 'mock.local', 'title' => 'AMD Ryzen 7 5700X3D', 'listing_text' => 'ขาย 5700X3D ราคา 7600', 'observed_at' => '2026-09-09 12:00:00', 'displayed_price' => '7600', 'currency' => 'THB']
    ], JSON_UNESCAPED_UNICODE));
    $mockReal = $service->import($mockRealPath, 'real', true, 10);
    assert_import($mockReal['invalid_rows'] === 1 && in_array('REAL_DATASET_CANNOT_USE_MOCK_TEST_SOURCE', $mockReal['row_errors'][0]['errors'], true), 'Mock/test source cannot be REAL accidentally');

    $dashboard = new Dashboard();
    $obsIds = $db->query("SELECT o.id FROM price_observations o JOIN raw_price_observations r ON r.id=o.raw_observation_id WHERE r.source_id={$sourceId} ORDER BY o.id LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
    assert_import(count($obsIds) === 3, 'Imported observations missing before review');
    assert_import($dashboard->correctObservation((int)$obsIds[0], 'price', '7550', 'Offline import calibration correction')['ok'] === true, 'Correction should work for imported evidence');
    foreach ($obsIds as $id) {
        assert_import($dashboard->decideObservation((int)$id, 'approved', 'Offline import calibration approval') === true, 'Imported observation approval failed');
    }
    $snapshot = (new PriceSnapshotService())->recalculateProduct($productId, 'asking');
    assert_import($snapshot !== null, 'Approved REAL observations should integrate with snapshot');
    assert_import((new PriceSnapshotService())->reproduceSnapshot((int)$snapshot['snapshot_id'])['result'] === 'MATCH', 'Imported REAL snapshot provenance should reproduce');

    $analytics = (new ReviewCalibrationService())->report(['source_id' => $sourceId]);
    assert_import($analytics['dataset_label'] === 'REAL', 'Calibration analytics should recognize imported REAL evidence');
    assert_import($analytics['valid_observation_yield']['real_reviewed_candidates'] >= 3, 'REAL reviewed denominator missing');
    assert_import($analytics['calibration_status'] === 'LIVE_SAMPLE_INSUFFICIENT', 'Small REAL sample cannot be READY_FOR_SCALE');

    echo "Offline import test passed\n";
    echo "Dry-run valid/invalid: {$dry['valid_rows']}/{$dry['invalid_rows']}\n";
    echo "JSON import candidates: {$real['candidates_created']}\n";
    echo "CSV import candidates: {$csv['candidates_created']}\n";
    echo "Duplicate re-import skipped: {$repeat['duplicate_rows']}\n";
    echo "REAL calibration status: {$analytics['calibration_status']}\n";
    echo "Snapshot reproduction: MATCH\n";
} finally {
    $db->rollBack();
}
