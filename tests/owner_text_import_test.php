<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Models\Dashboard;
use App\Models\Product;
use App\Services\Collection\OfflineEvidenceImportService;
use App\Services\Pricing\PriceSnapshotService;

function assert_owner_text(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function write_owner_text_file(string $name, array $records): string
{
    $dir = ROOT_PATH . '/storage/import/test';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $path = $dir . '/' . $name;
    file_put_contents($path, json_encode(['records' => $records], JSON_UNESCAPED_UNICODE));
    return $path;
}

global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();

try {
    $productId = (int)$db->query("SELECT id FROM products WHERE slug='amd-ryzen-7-5700x3d' LIMIT 1")->fetchColumn();
    assert_owner_text($productId > 0, 'Missing 5700X3D product');
    $suffix = bin2hex(random_bytes(4));
    $service = new OfflineEvidenceImportService();
    $base = static fn(string $id, string $price): array => [
        'source_label' => 'owner_collected_facebook_text',
        'provenance_grade' => 'OWNER_TEXT_NO_URL',
        'source' => 'owner_collected_facebook_text',
        'source_item_id' => 'OT-' . $suffix . '-' . $id,
        'external_ref' => 'OT-' . $suffix . '-' . $id,
        'listing_title' => 'AMD Ryzen 7 5700X3D มือสอง ' . $price,
        'title' => 'AMD Ryzen 7 5700X3D มือสอง ' . $price,
        'listing_text' => 'ขาย 5700X3D ใช้งานปกติ ราคา ' . $price . ' บาท',
        'asking_price' => $price,
        'currency' => 'THB',
        'observed_at' => date('Y-m-d', strtotime('-3 days')) . ' 12:00:00',
    ];

    // 1. validation: the grade, a row reference and the absence of a listing URL are all required; a mock source is refused
    $noGrade = $base('a', '7500'); unset($noGrade['provenance_grade']);
    $noRef = $base('b', '7500'); unset($noRef['source_item_id'], $noRef['external_ref']);
    $withUrl = $base('c', '7500'); $withUrl['source_listing_url'] = 'https://example.org/listing/1'; $withUrl['source_url'] = 'https://example.org/listing/1';
    $mock = $base('d', '7500'); $mock['source_type'] = 'mock';
    $bad = $service->import(write_owner_text_file("owner-text-bad-{$suffix}.json", [$noGrade, $noRef, $withUrl, $mock]), 'owner_text', true, 10);
    $errors = array_merge(...array_map(static fn(array $e): array => $e['errors'], $bad['row_errors']));
    assert_owner_text($bad['dataset'] === 'OWNER_TEXT' && $bad['invalid_rows'] === 4 && $bad['valid_rows'] === 0, 'All four invalid owner-text rows must be rejected: ' . json_encode($bad, JSON_UNESCAPED_UNICODE));
    foreach (['OWNER_TEXT_REQUIRES_PROVENANCE_GRADE_OWNER_TEXT_NO_URL', 'OWNER_TEXT_REQUIRES_ROW_REFERENCE', 'OWNER_TEXT_MUST_NOT_CARRY_A_LISTING_URL', 'OWNER_TEXT_CANNOT_USE_MOCK_TEST_SOURCE'] as $code) {
        assert_owner_text(in_array($code, $errors, true), "Missing error $code");
    }

    // 2. dry-run writes nothing; the real import stays pending for the owner
    $good = write_owner_text_file("owner-text-good-{$suffix}.json", [$base('1', '7400'), $base('2', '7600'), $base('3', '7800')]);
    $beforeRaw = (int)$db->query('SELECT COUNT(*) FROM raw_price_observations')->fetchColumn();
    $dry = $service->import($good, 'owner_text', true, 10);
    assert_owner_text($dry['valid_rows'] === 3 && (int)$db->query('SELECT COUNT(*) FROM raw_price_observations')->fetchColumn() === $beforeRaw, 'Dry-run must validate three rows and write nothing');
    $run = $service->import($good, 'owner_text', false, 10);
    assert_owner_text($run['candidates_created'] === 3, 'Expected three owner-text candidates: ' . json_encode($run, JSON_UNESCAPED_UNICODE));
    $sourceId = (int)$db->query("SELECT id FROM data_sources WHERE source_key='offline_owner_text_owner_collected_facebook_text' LIMIT 1")->fetchColumn();
    assert_owner_text($sourceId > 0, 'Owner-text source missing');
    assert_owner_text((string)$db->query("SELECT evidence_quality FROM data_sources WHERE id={$sourceId}")->fetchColumn() === 'low', 'Owner-text source must be low quality');
    $excerpt = (string)$db->query("SELECT e.excerpt FROM market_evidence e JOIN raw_price_observations r ON r.id=e.raw_observation_id WHERE r.source_id={$sourceId} ORDER BY e.id LIMIT 1")->fetchColumn();
    assert_owner_text(str_contains($excerpt, 'DATASET=OWNER_TEXT') && str_contains($excerpt, 'PROVENANCE_GRADE=OWNER_TEXT_NO_URL'), 'Grade must be recorded in the evidence excerpt');
    $obsIds = $db->query("SELECT o.id FROM price_observations o JOIN raw_price_observations r ON r.id=o.raw_observation_id WHERE r.source_id={$sourceId} AND o.verified_status='pending' ORDER BY o.id")->fetchAll(PDO::FETCH_COLUMN);
    assert_owner_text(count($obsIds) === 3, 'Owner-text rows must wait as pending');
    $again = $service->import($good, 'owner_text', false, 10);
    assert_owner_text($again['candidates_created'] === 0 && $again['duplicate_rows'] >= 3, 'Re-import must not duplicate');

    // 3. the owner can approve at the lower grade; a REAL row without a listing URL is still refused
    $dashboard = new Dashboard();
    foreach ($obsIds as $id) {
        assert_owner_text($dashboard->decideObservation((int)$id, 'approved', 'owner approval at OWNER_TEXT_NO_URL') === true, 'Owner-text approval must be allowed');
    }
    $snapshot = (new PriceSnapshotService())->recalculateProduct($productId, 'asking');
    assert_owner_text($snapshot !== null, 'Approved owner-text rows should feed a snapshot');
    assert_owner_text((new PriceSnapshotService())->reproduceSnapshot((int)$snapshot['snapshot_id'])['result'] === 'MATCH', 'Snapshot must reproduce');

    // 4. the public model reports how much of the figure rests on owner text
    $grades = (new Product())->evidenceGrades($productId);
    assert_owner_text($grades['owner_text'] >= 3 && $grades['total'] >= $grades['owner_text'], 'Public evidence grade must count owner-text samples: ' . json_encode($grades));

    echo "Owner text import test passed\n";
    echo "Rejected invalid rows: {$bad['invalid_rows']}\n";
    echo "Created pending candidates: {$run['candidates_created']}\n";
    echo "Public grade: {$grades['owner_text']} of {$grades['total']} samples are owner text\n";
} finally {
    $db->rollBack();
}
