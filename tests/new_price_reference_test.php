<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Pricing\NewPriceReferenceSources;
use App\Services\Pricing\PriceSnapshotService;

/**
 * Owner decision 2026-11-17 (DL-077): Priceza rows are a separate new-price reference. 46 of the 88 pending Priceza rows on DEV are LISTING LEVEL, so without
 * this guard approving them would put retail prices into the used asking median. The guard excludes them from the used cohort whatever their provenance or price_type.
 */
function ok_np(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

ok_np(NewPriceReferenceSources::isReference('offline_real_priceza_com') && NewPriceReferenceSources::isReference(' OFFLINE_REAL_PRICEZA_COM '), 'Priceza is a reference source (case and spaces ignored)');
ok_np(!NewPriceReferenceSources::isReference('offline_owner_text_owner_collected_facebook_text') && !NewPriceReferenceSources::isReference('') && !NewPriceReferenceSources::isReference('offline_real_other'), 'Owner text and other sources are not references');

$svc = new PriceSnapshotService();
$m = new ReflectionMethod($svc, 'exclusionReason');
$m->setAccessible(true);
$base = ['verified_status' => 'approved', 'price_type' => 'asking', 'product_variant_id' => null, 'listing_type' => 'single_item', 'quality_flags' => '[]', 'calculation_price' => 9000,
    'is_deposit' => 0, 'is_defective' => 0, 'is_duplicate' => 0, 'source_name' => 'x', 'source_domain' => 'example.test', 'source_key' => 'offline_owner_text_x', 'excerpt' => ''];
ok_np($m->invoke($svc, $base, 'asking') === null, 'Control: an approved owner-text asking row is included');
$priceza = array_merge($base, ['source_key' => 'offline_real_priceza_com', 'source_name' => 'Priceza', 'source_domain' => 'priceza.com']);
ok_np($m->invoke($svc, $priceza, 'asking') === 'NEW_PRICE_REFERENCE_SOURCE', 'An approved Priceza asking row is excluded from the used asking cohort: ' . json_encode($m->invoke($svc, $priceza, 'asking')));
ok_np($m->invoke($svc, array_merge($priceza, ['verified_status' => 'pending']), 'asking') === 'NOT_ACCEPTED', 'Earlier rules keep their reasons (pending stays NOT_ACCEPTED)');
echo "new_price_reference_test: PASS\n";
