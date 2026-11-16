<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Research\OwnerApprovalPlan;
use App\Services\Research\OwnerIntakeDryRun;
use App\Services\Research\OwnerIntakePreparer;

/**
 * Intake dry run and approval plan (LS-3 J-3 first slice): the shared services must give the rules of the command line, report counts only,
 * leave the database untouched and delete their temporary file. A synthetic sanitised CSV lives in the git-ignored storage/import/test.
 */
function ok_id(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$dir = ROOT_PATH . '/storage/import/test';
if (!is_dir($dir)) { mkdir($dir, 0777, true); }
$csv = $dir . '/intake-dryrun-' . bin2hex(random_bytes(4)) . '.csv';
$cols = ['row_id', 'note_date', 'category', 'model_key', 'variant_key', 'title', 'asking_price_thb', 'listing_type', 'bundle_total_line', 'wanted_or_trade', 'defective_hint', 'price_anomaly', 'enrichment_status', 'missing_fields'];
$row = static fn(array $o): array => array_map(static fn(string $c): string => (string)($o[$c] ?? ''), $cols);
$good = ['category' => 'gpu', 'model_key' => 'rtx 3070', 'listing_type' => 'single_item', 'enrichment_status' => 'complete', 'note_date' => '2026-11-10', 'bundle_total_line' => 'no', 'wanted_or_trade' => 'no', 'defective_hint' => 'no'];
// later keys must win: array_merge, not the + operator (the left side of + wins, which silently ignored every override in my first draft)
$mk = static fn(array $o): array => $row(array_merge($good, $o));
$rows = [
    $mk(['row_id' => 'T-1', 'title' => 'RTX 3070 8GB Dual มือสอง unique-a1', 'asking_price_thb' => '8100']),
    $mk(['row_id' => 'T-2', 'title' => 'RTX 3070 8GB Ventus มือสอง unique-b2', 'asking_price_thb' => '8300']),
    $mk(['row_id' => 'T-3', 'title' => 'RTX 3070 8GB Dual มือสอง unique-a1', 'asking_price_thb' => '8100']),                 // repost of T-1
    $mk(['row_id' => 'T-4', 'title' => 'RTX 3070 รับซื้อ unique-c3', 'asking_price_thb' => '8000', 'wanted_or_trade' => 'yes']), // wanted
    $mk(['row_id' => 'T-5', 'title' => 'RTX 3070 เสีย unique-d4', 'asking_price_thb' => '3000', 'defective_hint' => 'yes']),    // defective
    $mk(['row_id' => 'T-6', 'title' => 'RTX 3070 ไม่มีวันที่ unique-e5', 'asking_price_thb' => '8000', 'note_date' => '']),    // no date
    $mk(['row_id' => 'T-7', 'title' => 'RTX 3070 8GB Dual มือสอง unique-f6', 'asking_price_thb' => '8200', 'enrichment_status' => 'missing', 'missing_fields' => 'vram']), // single-size model: allowed
    $mk(['row_id' => 'T-8', 'title' => 'RTX 3060 มือสอง unique-g7', 'asking_price_thb' => '7000', 'model_key' => 'rtx 3060', 'enrichment_status' => 'missing', 'missing_fields' => 'vram']), // two sizes: skipped
];
$fh = fopen($csv, 'wb'); fputcsv($fh, $cols, ',', '"', ''); foreach ($rows as $r) { fputcsv($fh, $r, ',', '"', ''); } fclose($fh);
$bad = $dir . '/intake-bad-' . bin2hex(random_bytes(4)) . '.csv';
file_put_contents($bad, "foo,bar\n1,2\n");
$uploads = ROOT_PATH . '/storage/import_uploads';
$leftoversBefore = count(glob($uploads . '/intake-dryrun-*') ?: []);
try {
    global $config;
    $db = Database::connection($config['db']);

    // the shared preparer: same rules as the command line
    $p = OwnerIntakePreparer::prepare($csv, 'gpu');
    ok_id(count($p['records']) === 3, 'Kept rows: T-1, T-2 and T-7 (got ' . count($p['records']) . ')');
    $ids = array_column($p['records'], 'external_ref');
    ok_id($ids === ['T-1', 'T-2', 'T-7'], 'The right rows are kept: ' . json_encode($ids));
    ok_id(($p['skipped']['repost_duplicate'] ?? 0) === 1 && ($p['skipped']['wanted_or_trade'] ?? 0) === 1 && ($p['skipped']['defective'] ?? 0) === 1 && ($p['skipped']['no_date'] ?? 0) === 1 && ($p['skipped']['enrichment_missing'] ?? 0) === 1, 'Every skip reason is counted: ' . json_encode($p['skipped']));
    $vi = array_values(array_filter($p['records'], static fn($r) => $r['external_ref'] === 'T-7'))[0]['ingestion_note'];
    ok_id(str_contains($vi, 'vram_inferred=single_size_model'), 'The single-size VRAM inference is marked on the record');

    // header validation
    ok_id(OwnerIntakeDryRun::headerProblems($csv) === [], 'A proper file has no header problems');
    ok_id(in_array('title', OwnerIntakeDryRun::headerProblems($bad), true), 'A file without the research columns is refused');
    ok_id(OwnerIntakeDryRun::headerProblems($dir . '/does-not-exist.csv') === ['unreadable'], 'A missing file is reported');

    // the dry run reports counts, writes nothing and removes its temp file
    $tables = ['raw_price_observations', 'price_observations', 'market_evidence', 'observation_review_decisions', 'collector_jobs', 'data_sources', 'audit_logs', 'products', 'product_aliases'];
    $count = static function () use ($db, $tables): array { $o = []; foreach ($tables as $t) { $o[$t] = (int)$db->query("SELECT COUNT(*) FROM $t")->fetchColumn(); } return $o; };
    $before = $count();
    $r = (new OwnerIntakeDryRun($db))->run($csv, 'gpu');
    ok_id($r['ok'] && $r['prepared'] === 3 && $r['already_present'] + $r['new'] === 3, 'The dry run reports prepared and new counts: ' . json_encode($r));
    ok_id($r['import']['valid_rows'] + $r['import']['invalid_rows'] + $r['import']['duplicate_rows'] >= 1 || $r['new'] === 0, 'The importer dry run saw the new rows');
    ok_id($count() === $before, 'The dry run wrote nothing: ' . json_encode(array_diff_assoc($count(), $before)));
    ok_id(count(glob($uploads . '/intake-dryrun-*') ?: []) === $leftoversBefore, 'The temporary import file was deleted');
    ok_id(!str_contains(json_encode($r, JSON_UNESCAPED_UNICODE), 'unique-a1') && !str_contains(json_encode($r, JSON_UNESCAPED_UNICODE), 'RTX'), 'The result carries counts only: no title text');
    $rb = (new OwnerIntakeDryRun($db))->run($bad);
    ok_id(!$rb['ok'] && str_starts_with((string)$rb['error'], 'MISSING_COLUMNS'), 'A bad file gives an error and no counts');

    // approval plan: read-only, the same structure the command line applies
    $plan = OwnerApprovalPlan::build($db);
    ok_id(isset($plan['plan'], $plan['left_pending']) && is_array($plan['plan']) && is_array($plan['left_pending']), 'The approval plan has a plan and a left-pending list');
    foreach ($plan['plan'] as $p2) { ok_id(count($p2['keep']) >= 1 && isset($p2['median'], $p2['already'], $p2['name']), 'Each planned product has rows to approve, a median and its existing count'); }
    foreach ($plan['left_pending'] as $lp) { ok_id(in_array($lp[2], ['too_few', 'bimodal'], true), 'A left-pending product says why'); }
    ok_id($count() === $before, 'Building the plan wrote nothing');
} finally {
    @unlink($csv); @unlink($bad);
}

echo "owner_intake_dryrun_test: PASS\n";
