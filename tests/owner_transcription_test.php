<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Collection\CaptureMethod;
use App\Services\Collection\OfflineEvidenceImportService;
use App\Services\Pricing\PriceSnapshotService;
use App\Services\Pricing\SnapshotConfidenceService;
use App\Services\Provenance\ObservationProvenanceService;

/**
 * LS-4 K-3 P1 (DL-079): owner-collected listing rows (capture_method=owner_transcription) and machine captures follow ONE pattern: the importer record
 * carries evidence_snapshot_json + evidence_hash. For an owner transcription the importer freezes the row itself; a machine capture (an agent) must bring
 * its own snapshot and hash. Provenance gates the domain; confidence caps evidence that is entirely human-recorded below "high" and says why.
 */
function ok_ot(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

// ---- CaptureMethod (pure)
$rec = ['capture_method' => 'owner_transcription', 'source_listing_url' => 'https://www.facebook.com/marketplace/item/9000000000001', 'source_item_id' => 'FB-9000000000001',
    'listing_title' => 'RTX 3070 8GB used', 'asking_price' => '8,200', 'observed_at' => '2026-11-14 12:00:00', 'condition_hint' => 'used'];
$a = CaptureMethod::freeze($rec); $b = CaptureMethod::freeze($rec);
ok_ot($a === $b && preg_match('/^[a-f0-9]{64}$/', $a['hash']) === 1 && hash('sha256', $a['snapshot']) === $a['hash'], 'Freezing is deterministic and the hash is the SHA-256 of the stored snapshot');
$snap = json_decode($a['snapshot'], true);
ok_ot(($snap['capture_method'] ?? '') === 'owner_transcription' && ($snap['asking_price'] ?? '') === '8200' && !isset($snap['seller']), 'The snapshot names the capture method, normalises the price and carries no seller field');
ok_ot(CaptureMethod::freeze(array_merge($rec, ['asking_price' => '8300']))['hash'] !== $a['hash'], 'A different price gives a different hash');
foreach (['https://www.facebook.com/marketplace/item/1', 'https://m.facebook.com/x', 'https://pantip.com/topic/1', 'https://www.kaidee.com/product-1'] as $u) { ok_ot(CaptureMethod::domainAllowed($u), "$u is an allowed domain"); }
foreach (['https://evilfacebook.com/x', 'https://facebook.com.evil.test/x', 'https://example.test/x', 'not a url', ''] as $u) { ok_ot(!CaptureMethod::domainAllowed($u), "$u is not an allowed domain"); }
ok_ot(CaptureMethod::fromExcerpt("A=1\nCAPTURE_METHOD=owner_transcription\nB=2") === 'owner_transcription' && CaptureMethod::fromExcerpt('A=1') === null, 'The capture method is read back from the stored excerpt');

// ---- importer validation (dry run, nothing written)
$dir = ROOT_PATH . '/storage/import/test';
if (!is_dir($dir)) { mkdir($dir, 0777, true); }
$write = static function (array $rows) use ($dir): string {
    $f = $dir . '/mi_ot_' . bin2hex(random_bytes(4)) . '.csv';
    $h = 'capture_method,source_listing_url,source_item_id,listing_title,asking_price,observed_at,condition_hint';
    file_put_contents($f, $h . "\n" . implode("\n", $rows) . "\n");
    return $f;
};
$dry = static function (string $f): array { return (new OfflineEvidenceImportService())->import($f, 'real', true, 50); };
$files = [];
try {
    $files[] = $f = $write([
        'owner_transcription,https://www.facebook.com/marketplace/item/9000000000001,FB-9000000000001,RTX 3070 8GB used,8200,2026-11-14 12:00:00,used',
        'owner_transcription,https://example.test/listing/1,EX-1,RTX 3070 other domain,8200,2026-11-14 12:00:00,used',
        'owner_transcription,https://www.facebook.com/marketplace/item/9000000000002,,RTX 3070 no item id,8200,2026-11-14 12:00:00,used',
        'owner_transcription,https://www.facebook.com/profile.php?id=100000000000001,FB-5,RTX 3070 personal profile link,8200,2026-11-14 12:00:00,used',
        'telepathy,https://www.facebook.com/marketplace/item/9000000000003,FB-3,RTX 3070 unknown method,8200,2026-11-14 12:00:00,used',
        'machine_capture,https://www.facebook.com/marketplace/item/9000000000004,FB-4,RTX 3070 machine without snapshot,8200,2026-11-14 12:00:00,used',
    ]);
    $r = $dry($f);
    $codes = []; foreach ($r['row_errors'] as $e) { foreach ($e['errors'] as $c) { $codes[$c] = ($codes[$c] ?? 0) + 1; } }
    ok_ot($r['valid_rows'] === 1 && $r['invalid_rows'] === 5, 'Exactly one of six rows is valid: ' . json_encode([$r['valid_rows'], $r['invalid_rows'], $codes]));
    foreach (['OWNER_TRANSCRIPTION_DOMAIN_NOT_ALLOWED', 'OWNER_TRANSCRIPTION_REQUIRES_ITEM_ID', 'OWNER_TRANSCRIPTION_PERSONAL_PROFILE_LINK', 'UNKNOWN_CAPTURE_METHOD', 'MACHINE_CAPTURE_REQUIRES_SNAPSHOT_AND_HASH'] as $code) { ok_ot(isset($codes[$code]), "$code is reported: " . json_encode($codes)); }
} finally { foreach ($files as $f) { @unlink($f); } }

// ---- end to end on the isolated test database (rolled back): import, approve, provenance, snapshot rule, confidence
global $config;
$db = Database::connection($config['db']);
$db->beginTransaction();
$file = null;
try {
    $file = $write(['owner_transcription,https://www.facebook.com/marketplace/item/9000000000001,FB-9000000000001,NVIDIA GeForce RTX 3070 8GB used,8200,2026-11-14 12:00:00,used']);
    $sum = (new OfflineEvidenceImportService())->import($file, 'real', false, 50);
    ok_ot($sum['candidates_created'] === 1, 'The owner-transcription row is imported: ' . json_encode([$sum['candidates_created'], $sum['duplicate_rows'], $sum['row_errors']]));
    $row = $db->query("SELECT o.id oid, o.product_id, o.evidence_level, r.id rid, e.excerpt, e.content_hash, e.evidence_type, s.source_key, s.name source_name, s.domain source_domain
        FROM raw_price_observations r JOIN market_evidence e ON e.raw_observation_id = r.id JOIN data_sources s ON s.id = r.source_id
        LEFT JOIN price_observations o ON o.raw_observation_id = r.id WHERE r.id = (SELECT MAX(id) FROM raw_price_observations)")->fetch();
    ok_ot($row && str_contains((string)$row['excerpt'], 'CAPTURE_METHOD=owner_transcription') && str_contains((string)$row['excerpt'], 'EVIDENCE_HASH='), 'The stored excerpt records the capture method and the frozen hash');
    ok_ot($row['evidence_type'] === 'snippet', 'The evidence is typed as a recorded snippet, not a page capture: ' . $row['evidence_type']);
    ok_ot((int)$row['evidence_level'] === 3, 'The evidence level is one step below a machine capture (3, not 4): ' . $row['evidence_level']);
    // the price-engine view of the row
    $cohort = ['source_key' => $row['source_key'], 'source_name' => $row['source_name'], 'source_domain' => 'facebook.com', 'raw_title' => 'RTX 3070', 'raw_price_text' => '8200',
        'source_url_encrypted' => 'https://www.facebook.com/marketplace/item/9000000000001', 'external_reference_hash' => str_repeat('a', 64), 'excerpt' => $row['excerpt'],
        'content_hash' => $row['content_hash'], 'observed_at' => '2026-11-14 12:00:00', 'verified_status' => 'approved', 'price_type' => 'asking', 'product_variant_id' => null,
        'listing_type' => 'single_item', 'quality_flags' => '[]', 'calculation_price' => 8200, 'is_deposit' => 0, 'is_defective' => 0, 'is_duplicate' => 0, 'evidence_level' => 3, 'source_evidence_quality' => 'unknown'];
    $quality = (new ObservationProvenanceService())->classify($cohort)['quality'];
    ok_ot($quality === ObservationProvenanceService::LISTING_LEVEL, 'The frozen owner-transcription row is listing level: ' . $quality);
    $svc = new PriceSnapshotService();
    $m = new ReflectionMethod($svc, 'exclusionReason'); $m->setAccessible(true);
    ok_ot($m->invoke($svc, $cohort, 'asking') === null, 'An approved owner-transcription row is included in the used cohort');
    // the same row from a domain outside the allow-list is not listing level, even with a snapshot
    $bad = $cohort; $bad['source_url_encrypted'] = 'https://example.test/listing/1';
    $bad['excerpt'] = str_replace('facebook.com', 'example.test', (string)$row['excerpt']);
    ok_ot((new ObservationProvenanceService())->classify($bad)['quality'] !== ObservationProvenanceService::LISTING_LEVEL, 'A domain outside the allow-list is never listing level for an owner transcription');

    // confidence: human-recorded evidence lifts the owner-text cap but is capped below high and says so
    $ownerRow = static fn(int $i): array => ['source_key' => 'offline_owner_text_x', 'source_name' => 'Owner text', 'source_domain' => '', 'source_id' => 1, 'observed_at' => '2026-11-10', 'evidence_level' => 3, 'excerpt' => 'PROVENANCE_GRADE=OWNER_TEXT_NO_URL', 'source_evidence_quality' => 'unknown'];
    $fbRow = static fn(int $i): array => ['source_key' => 'offline_real_facebook_com', 'source_name' => 'Offline REAL Import - facebook.com', 'source_domain' => 'facebook.com', 'source_id' => 2, 'observed_at' => '2026-11-10', 'evidence_level' => 3, 'excerpt' => "CAPTURE_METHOD=owner_transcription\nEVIDENCE_HASH=" . str_repeat('c', 64), 'source_evidence_quality' => 'unknown'];
    $machineRow = static fn(int $i): array => ['source_key' => 'offline_real_facebook_com', 'source_name' => 'Offline REAL Import - facebook.com', 'source_domain' => 'facebook.com', 'source_id' => 2, 'observed_at' => '2026-11-10', 'evidence_level' => 4, 'excerpt' => "CAPTURE_METHOD=machine_capture\nEVIDENCE_HASH=" . str_repeat('d', 64), 'source_evidence_quality' => 'high'];
    $conf = new SnapshotConfidenceService();
    $ownerOnly = $conf->calculate(array_map($ownerRow, range(1, 15)), 'asking', '2026-11-16');
    ok_ot(in_array('OWNER_TEXT_ONLY_CAP', $ownerOnly['policy_caps'], true) && $ownerOnly['overall_score'] <= 59.0, 'Owner-text only stays capped at 59');
    $mixed = $conf->calculate(array_merge(array_map($ownerRow, range(1, 8)), array_map($fbRow, range(1, 8))), 'asking', '2026-11-16');
    ok_ot(!in_array('OWNER_TEXT_ONLY_CAP', $mixed['policy_caps'], true), 'One listing-level human-recorded source lifts the owner-text-only cap');
    ok_ot(in_array('HUMAN_RECORDED_EVIDENCE_CAP', $mixed['policy_caps'], true) && $mixed['overall_score'] <= 79.0 && $mixed['label'] !== 'high', 'Human-recorded evidence is capped below high and the cap is stated: ' . json_encode([$mixed['overall_score'], $mixed['policy_caps']]));
    $withMachine = $conf->calculate(array_merge(array_map($ownerRow, range(1, 4)), array_map($fbRow, range(1, 4)), array_map($machineRow, range(1, 8))), 'asking', '2026-11-16');
    ok_ot(!in_array('HUMAN_RECORDED_EVIDENCE_CAP', $withMachine['policy_caps'], true), 'A machine capture (agent route) in the sample removes the human-recorded cap: same rule, same pattern');
    ok_ot($ownerOnly['method_version'] === 'confidence-v2', 'Method version is unchanged (additive cap, no effect on data without the marker)');
} finally {
    $db->rollBack();
    if ($file) { @unlink($file); }
}
echo "owner_transcription_test: PASS\n";
