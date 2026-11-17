<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Pricing\PriceSnapshotService;
use App\Services\Provenance\ObservationProvenanceService;

/**
 * LS-4 K-3c: does a row imported from the DOCUMENTED minimal second-source file (docs/SECOND_SOURCE_INTAKE.md) reach a used price?
 * The excerpt below is exactly what OfflineEvidenceImportService::snippet() stores for such a row (the template has no evidence_hash or
 * evidence_snapshot_json column). Answer today: NO (generic source, INSUFFICIENT_PROVENANCE). This test pins that so a change of the rule is a visible, deliberate decision (DL-078).
 */
function ok_g(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$excerpt = implode("\n", ['IMPORT_PROVIDER=offline_file_import', 'IMPORT_RUN_ID=x', 'DATASET=REAL', 'ROW=1',
    'SOURCE_LISTING_URL=https://www.facebook.com/marketplace/item/9000000000001', 'SOURCE_ITEM_ID=SS-FB-1', 'EXTERNAL_REF=SS-FB-1',
    'LISTING_TITLE=RTX 3070 8GB used', 'ASKING_PRICE=8200', 'OBSERVED_AT=2026-11-14 12:00:00']);
$row = ['source_key' => 'offline_real_facebook_com', 'source_name' => 'Offline real import facebook.com', 'source_domain' => 'facebook.com',
    'raw_title' => 'RTX 3070 8GB used', 'raw_price_text' => '8200', 'source_url_encrypted' => 'https://www.facebook.com/marketplace/item/9000000000001',
    'external_reference_hash' => str_repeat('a', 64), 'excerpt' => $excerpt, 'content_hash' => str_repeat('b', 64), 'observed_at' => '2026-11-14 12:00:00',
    'verified_status' => 'approved', 'price_type' => 'asking', 'product_variant_id' => null, 'listing_type' => 'single_item', 'quality_flags' => '[]', 'calculation_price' => 8200,
    'is_deposit' => 0, 'is_defective' => 0, 'is_duplicate' => 0];

$q = (new ObservationProvenanceService())->classify($row)['quality'];
$svc = new PriceSnapshotService();
$m = new ReflectionMethod($svc, 'exclusionReason');
$m->setAccessible(true);
$reason = $m->invoke($svc, $row, 'asking');

// with an immutable snapshot the same row is listing level and included
$withSnap = $row;
$withSnap['excerpt'] .= "\nEVIDENCE_SNAPSHOT_JSON={\"t\":\"x\"}\nEVIDENCE_HASH=" . str_repeat('c', 64);
$q2 = (new ObservationProvenanceService())->classify($withSnap)['quality'];
ok_g($q2 === ObservationProvenanceService::LISTING_LEVEL && $m->invoke($svc, $withSnap, 'asking') === null, 'With an evidence snapshot and hash the row is listing level and included');
ok_g($q === ObservationProvenanceService::GENERIC_SOURCE && $reason === 'INSUFFICIENT_PROVENANCE', 'PINNED (DL-078): a row from the documented minimal template is generic-source and excluded from the used price; if this changes it must be a deliberate provenance decision. Got ' . $q . ' / ' . ($reason ?? 'INCLUDED'));
echo "second_source_snapshot_gate_test: PASS\n";
