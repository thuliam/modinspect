<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Models\Dashboard;
use App\Services\Collection\OfflineEvidenceImportService;
use App\Services\Review\ReviewCalibrationService;

/**
 * LS-6 M-3: the calibration report (used by L-2 once the owner has reviewed real rows) splits reviewed evidence by HOW it was captured, so owner
 * transcriptions are never blended with machine captures, owner text or fixtures. Fixture rows are a bucket of their own and never count as live.
 * Deltas are measured against the same report before the import, so other rows in the test database do not matter.
 */
function ok_cc(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

global $config;
$db = Database::connection($config['db']);
$dir = ROOT_PATH . '/storage/import/test';
if (!is_dir($dir)) { mkdir($dir, 0777, true); }
$tag = sprintf('%06d', random_int(0, 999999));
$file = $dir . "/cc_$tag.csv";
$db->beginTransaction();
try {
    $svc = new ReviewCalibrationService();
    $before = $svc->report([]);
    ok_cc(isset($before['capture_methods']) && is_array($before['capture_methods']), 'The report has a capture_methods breakdown');
    $get = static fn(array $rep, string $k, string $f): int => (int)($rep['capture_methods'][$k][$f] ?? 0);

    $day = date('Y-m-d 00:00:00', strtotime('-3 days'));
    $rows = [];
    foreach ([['01', 'NVIDIA GeForce RTX 3070 8GB used', 7800], ['02', 'NVIDIA GeForce RTX 3070 8GB used มือสอง', 8000], ['03', 'NVIDIA GeForce RTX 3070 8GB used ประกัน', 8200]] as [$n, $t, $p]) {
        $rows[] = "owner_transcription,https://www.facebook.com/marketplace/item/93{$tag}{$n},FB-93{$tag}{$n},\"$t\",$p,$day,";
    }
    file_put_contents($file, "capture_method,source_listing_url,source_item_id,listing_title,asking_price,observed_at,condition_hint\n" . implode("\n", $rows) . "\n");
    $sum = (new OfflineEvidenceImportService())->import($file, 'real', false, 10);
    ok_cc($sum['candidates_created'] === 3, 'Three owner-transcription rows imported: ' . json_encode([$sum['candidates_created'], $sum['row_errors']]));

    // a machine capture (the agent route) brings its own snapshot and hash and lands in its own bucket
    $jsonFile = $dir . "/cc_$tag.json";
    $snapJson = '{"captured":"fixture page"}';
    file_put_contents($jsonFile, json_encode(['records' => [['capture_method' => 'machine_capture', 'source_listing_url' => "https://www.facebook.com/marketplace/item/93{$tag}09", 'source_item_id' => "FB-93{$tag}09", 'listing_title' => 'NVIDIA GeForce RTX 3070 8GB used machine', 'asking_price' => '8100', 'observed_at' => $day, 'evidence_snapshot_json' => $snapJson, 'evidence_hash' => hash('sha256', $snapJson)]]]));
    $ms = (new OfflineEvidenceImportService())->import($jsonFile, 'real', false, 10);
    @unlink($jsonFile);
    ok_cc($ms['candidates_created'] === 1, 'One machine-capture row imported: ' . json_encode([$ms['candidates_created'], $ms['row_errors']]));
    $mid = $svc->report([]);
    ok_cc($get($mid, 'machine_capture', 'candidates') - $get($before, 'machine_capture', 'candidates') === 1, 'The machine capture is counted in its own bucket');
    ok_cc($get($mid, 'owner_transcription', 'candidates') - $get($before, 'owner_transcription', 'candidates') === 3, 'Before review: three owner-transcription candidates, none reviewed yet');
    ok_cc($get($mid, 'owner_transcription', 'reviewed') - $get($before, 'owner_transcription', 'reviewed') === 0, 'Pending rows are not counted as reviewed');

    sleep(1);   // see tests/daily_flow_rehearsal_test.php: an approval inside the import's own second changes no column
    $ids = array_map('intval', $db->query("SELECT o.id FROM price_observations o JOIN raw_price_observations r ON r.id = o.raw_observation_id JOIN market_evidence e ON e.raw_observation_id = r.id
        WHERE e.excerpt LIKE '%CAPTURE_METHOD=owner_transcription%' AND e.excerpt LIKE '%/marketplace/item/93{$tag}%' ORDER BY o.id")->fetchAll(PDO::FETCH_COLUMN));
    ok_cc(count($ids) === 3, 'Three observations found');
    $dash = new Dashboard();
    ok_cc($dash->decideObservation($ids[0], 'approved', 'cc') && $dash->decideObservation($ids[1], 'approved', 'cc') && $dash->decideObservation($ids[2], 'rejected', 'cc'), 'Two approvals and one rejection are recorded');

    $after = $svc->report([]);
    foreach (['candidates' => 3, 'reviewed' => 3, 'approved' => 2, 'rejected' => 1, 'corrected' => 0] as $f => $want) {
        $d = $get($after, 'owner_transcription', $f) - $get($before, 'owner_transcription', $f);
        ok_cc($d === $want, "owner_transcription $f changed by $want, got $d");
    }
    foreach (['owner_text', 'fixture'] as $other) {
        ok_cc($get($after, $other, 'candidates') === $get($before, $other, 'candidates'), "The $other bucket is untouched by owner-transcription rows");
    }
    ok_cc($after['real_records'] - $before['real_records'] === 4 && $after['mock_test_records'] === $before['mock_test_records'], 'The four rows (three transcriptions, one machine capture) count as real, not fixture');
    ok_cc($after['valid_observation_yield']['mock_test_excluded_from_live_kpi'] === true, 'Fixture rows stay out of the live yield');
    ok_cc(array_sum(array_column($after['capture_methods'], 'candidates')) === $after['sample_size'], 'Every row is in exactly one bucket: buckets add up to the sample size');
    ok_cc($after['capture_methods']['fixture']['candidates'] === $after['mock_test_records'], 'The fixture bucket is exactly the mock/test records');
    $liveBuckets = $after['capture_methods']['owner_transcription']['candidates'] + $after['capture_methods']['machine_capture']['candidates'] + $after['capture_methods']['owner_text']['candidates'] + $after['capture_methods']['other_real']['candidates'];
    ok_cc($liveBuckets === $after['real_records'], 'The four live buckets add up to the real records: fixtures never leak into them');
} finally {
    $db->rollBack();
    @unlink($file);
}
echo "calibration_capture_method_test: PASS\n";
