<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Models\Dashboard;
use App\Models\Product;
use App\Services\Collection\OfflineEvidenceImportService;
use App\Services\Collection\OwnerRowPreparer;
use App\Services\Pricing\PriceSnapshotService;
use App\Services\Review\ReviewCalibrationService;

/**
 * LS-6 M-2: a dress rehearsal of the owner's daily route on the isolated test database (rolled back), so the first real file holds no surprises.
 * Excel-style raw file -> cli helper logic (OwnerRowPreparer) -> real-dataset import -> human approval -> price snapshot -> confidence cap -> public
 * evidence note -> calibration report. Fixture data: it proves the route works, not that live listings will behave.
 */
function ok_dr(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

global $config;
$db = Database::connection($config['db']);
$dir = ROOT_PATH . '/storage/import/test';
if (!is_dir($dir)) { mkdir($dir, 0777, true); }
$tag = sprintf('%06d', random_int(0, 999999));   // marketplace item ids are digits
$raw = $dir . "/dr_raw_$tag.csv";
$prepared = $dir . "/dr_prepared_$tag.csv";
$day = date('Y-m-d', strtotime('-3 days'));
$ids = [];
$db->beginTransaction();
try {
    // the owner's loose file: Excel BOM + CRLF, own headers, share-link tracking, thousands separators, a seller column that must not travel
    $lines = [
        'Link,Title,Price,Date,Seller',
        "https://m.facebook.com/marketplace/item/92{$tag}01/?ref=search&fbclid=AAA,NVIDIA GeForce RTX 3070 8GB used,\"7,800\",$day,Somchai",
        "https://m.facebook.com/marketplace/item/92{$tag}02/?mibextid=1,NVIDIA GeForce RTX 3070 8GB used มือสอง,8000,$day,Somsri",
        "https://web.facebook.com/marketplace/item/92{$tag}03,NVIDIA GeForce RTX 3070 8GB used ประกันเหลือ,8200 บาท,$day,",
        "https://www.facebook.com/marketplace/item/92{$tag}04/,NVIDIA GeForce RTX 3070 8GB used สภาพสวย,8400,$day,",
        "https://www.facebook.com/marketplace/item/92{$tag}04?fbclid=BBB,NVIDIA GeForce RTX 3070 8GB used สภาพสวย repost,8400,$day,",
        "https://www.facebook.com/marketplace/item/92{$tag}05,NVIDIA GeForce RTX 3070 8GB used โทร 081-234-5678,8100,$day,",
    ];
    file_put_contents($raw, "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n");

    $prep = OwnerRowPreparer::prepare(OwnerRowPreparer::readCsv($raw), '');
    ok_dr($prep['summary']['read'] === 6 && $prep['summary']['ready'] === 4 && $prep['summary']['rejected'] === 2, 'Helper: 6 read, 4 ready, 2 rejected (repost of the same item, phone number): ' . json_encode($prep['summary']));
    ok_dr($prep['summary']['ignored_columns'] === ['seller'], 'Helper: the seller column is reported by name and dropped');
    $csv = OwnerRowPreparer::toCsv($prep['rows']);
    ok_dr(!str_contains($csv, 'Somchai') && !str_contains($csv, 'fbclid') && !str_contains($csv, '081'), 'Helper output carries no seller name, tracking or phone');
    file_put_contents($prepared, $csv);

    $imp = new OfflineEvidenceImportService();
    $dry = $imp->import($prepared, 'real', true, 50);
    ok_dr($dry['valid_rows'] === 4 && $dry['invalid_rows'] === 0 && $dry['duplicate_rows'] === 0, 'Importer dry run accepts all four prepared rows: ' . json_encode([$dry['valid_rows'], $dry['invalid_rows'], $dry['row_errors']]));
    $sum = $imp->import($prepared, 'real', false, 50);
    ok_dr($sum['candidates_created'] === 4, 'Import created four candidates: ' . json_encode([$sum['candidates_created'], $sum['duplicate_rows'], $sum['row_errors']]));
    $again = $imp->import($prepared, 'real', true, 50);
    ok_dr($again['valid_rows'] === 0 && $again['duplicate_rows'] === 4, 'Importing the same prepared file twice creates nothing new: ' . json_encode([$again['valid_rows'], $again['duplicate_rows']]));

    sleep(1);   // approval reports success only when a row column changed; an approval inside the very second of the import changes none (updated_at included) and returns false. A person never approves that fast (LS-6 finding)
    $obs = $db->query("SELECT o.id, o.product_id, o.verified_status FROM price_observations o JOIN raw_price_observations r ON r.id = o.raw_observation_id
        JOIN market_evidence e ON e.raw_observation_id = r.id WHERE e.excerpt LIKE '%CAPTURE_METHOD=owner_transcription%' AND e.excerpt LIKE '%/marketplace/item/92{$tag}%' ORDER BY o.id")->fetchAll();
    ok_dr(count($obs) === 4, 'Four owner-transcription observations are stored: ' . count($obs));
    $productIds = array_unique(array_column($obs, 'product_id'));
    ok_dr(count($productIds) === 1 && (int)$productIds[0] > 0, 'All four resolve to one product: ' . json_encode($productIds));
    ok_dr(array_unique(array_column($obs, 'verified_status')) === ['pending'], 'Nothing is approved automatically: the owner reviews every row');
    $productId = (int)$productIds[0];
    $dashboard = new Dashboard();
    foreach ($obs as $o) {
        ok_dr($dashboard->decideObservation((int)$o['id'], 'approved', 'rehearsal') === true, 'Owner approval is allowed for a listing-level owner transcription');
        $ids[] = (int)$o['id'];
    }

    $snap = (new PriceSnapshotService())->recalculateProduct($productId, 'asking');
    ok_dr($snap !== null, 'A snapshot is produced from the four approved rows');
    $snapshotId = (int)$snap['snapshot_id'];
    $members = $db->prepare('SELECT observation_id, inclusion_status, exclusion_reason FROM price_snapshot_observations WHERE snapshot_id = :s');
    $members->execute(['s' => $snapshotId]);
    $status = []; foreach ($members->fetchAll() as $m) { $status[(int)$m['observation_id']] = $m['inclusion_status'] . ':' . ($m['exclusion_reason'] ?? ''); }
    foreach ($ids as $id) { ok_dr(($status[$id] ?? '') === 'included:', "Observation $id is included in the used price: " . ($status[$id] ?? 'missing')); }
    $caps = $snap['confidence']['policy_caps'] ?? [];
    ok_dr(in_array('HUMAN_RECORDED_EVIDENCE_CAP', $caps, true) && !in_array('OWNER_TEXT_ONLY_CAP', $caps, true), 'The human-recorded cap applies, the owner-text-only cap does not: ' . json_encode($caps));
    ok_dr((float)$snap['confidence']['overall_score'] <= 79.0 && $snap['confidence']['label'] !== 'high', 'Confidence stays below high: ' . json_encode([$snap['confidence']['overall_score'], $snap['confidence']['label']]));
    ok_dr((new PriceSnapshotService())->reproduceSnapshot($snapshotId)['result'] === 'MATCH', 'The snapshot reproduces exactly from its stored inputs');

    $grades = (new Product())->evidenceGrades($productId);
    ok_dr($grades['transcribed'] === 4 && $grades['owner_text'] === 0, 'The public evidence note counts four transcribed rows: ' . json_encode($grades));

    $rep = (new ReviewCalibrationService())->report([]);
    ok_dr($rep['real_records'] >= 4, 'The calibration report counts the four reviewed rows as real (not fixture): ' . json_encode([$rep['real_records'], $rep['mock_test_records']]));
} finally {
    $db->rollBack();
    foreach ([$raw, $prepared] as $f) { @unlink($f); }
}
echo "daily_flow_rehearsal_test: PASS\n";
