<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\ProductResolver\ProductResolver;
use App\Services\Research\OwnerIntakeDeduper;
use App\Services\Research\StaleReResolver as S;
use App\Services\Validation\ObservationValidator;

/** LS-4 R-1: the read-only decision of the re-resolve design, on fixtures (no database). One fixture per outcome. */
function ok_sr(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$resolver = new ProductResolver([
    ['id' => 1, 'full_name' => 'NVIDIA GeForce RTX 5060', 'aliases' => []],
    ['id' => 2, 'full_name' => 'AMD Ryzen 5 5500', 'aliases' => []],
]);
$validator = new ObservationValidator(false);
$x = static fn(array $o = []): array => array_merge(['askingPrice' => 9000, 'currency' => 'THB', 'listingType' => 'single', 'conditionLevel' => 'used', 'warrantyMonths' => null,
    'hasBox' => null, 'hasReceipt' => null, 'isWantedPost' => false, 'isDeposit' => false, 'isDefective' => false, 'isWholePc' => false, 'confidence' => 0.95, 'raw' => []], $o);
$row = static fn(array $o = []): array => array_merge(['source_key' => 'offline_owner_text_line', 'title' => 'ขาย NVIDIA GeForce RTX 5060 มือสอง', 'old_reasons' => ['UNRESOLVED_PRODUCT'],
    'has_observation' => false, 'extraction' => $x()], $o);
$d = static fn(array $r, array $known = []): array => S::decide($r, $resolver, $validator, $known);

$ok = $d($row());
ok_sr($ok['outcome'] === 'would_create' && $ok['product_id'] === 1 && in_array($ok['lane'], ['green', 'amber'], true), 'An owner-text row the catalog matches would be created: ' . json_encode($ok));
ok_sr($d($row(['source_key' => 'priceza_real']))['outcome'] === 'NOT_OWNER_TEXT_SOURCE', 'A non owner-text source is never touched');
ok_sr($d($row(['source_key' => 'mock_fixture']))['outcome'] === 'NOT_OWNER_TEXT_SOURCE', 'A mock source is never touched');
ok_sr($d($row(['has_observation' => true]))['outcome'] === 'ALREADY_HAS_OBSERVATION', 'A row that already has an observation is skipped (idempotent)');
ok_sr($d($row(['extraction' => null]))['outcome'] === 'NO_STORED_EXTRACTION', 'No stored extraction is a skip, not a guess');
foreach (['BUNDLE', 'WANTED', 'DEPOSIT'] as $code) { ok_sr($d($row(['old_reasons' => ['UNRESOLVED_PRODUCT', $code]]))['outcome'] === 'BUNDLE_WANTED_OR_DEPOSIT', "$code is skipped"); }
ok_sr($d($row(['title' => 'ขาย การ์ดจอ รุ่นลึกลับ']))['outcome'] === 'STILL_UNRESOLVED', 'A title the catalog still cannot match stays unresolved');
ok_sr($d($row(['extraction' => $x(['askingPrice' => null])]))['outcome'] === 'NO_ASKING_PRICE', 'No price is a skip');
ok_sr($d($row(['extraction' => $x(['isDefective' => true])]))['outcome'] === 'RED_AFTER_REVALIDATION', 'A defective listing is red after revalidation');
$key = OwnerIntakeDeduper::keyFor('ขาย NVIDIA GeForce RTX 5060 มือสอง', 9000);
ok_sr($d($row(), [$key => true])['outcome'] === 'REPOST_OF_EXISTING_LISTING', 'A listing that already has an observation is a repost');
ok_sr($d($row(['extraction' => $x(['askingPrice' => 9100])]), [$key => true])['outcome'] === 'would_create', 'The same text at another price is a different listing key');
// LS-76: owner-transcribed Facebook captures (REAL dataset) are re-resolved; other REAL sources never are
ok_sr($d($row(['source_key' => 'offline_real_facebook_com']))['outcome'] === 'would_create', 'An owner-transcribed Facebook row is re-resolved');
ok_sr($d($row(['source_key' => 'offline_real_priceza_com']))['outcome'] === 'NOT_OWNER_TEXT_SOURCE', 'A REAL Priceza row is still never touched');
// LS-76: a title that states a whole spec class resolves to the generic class product; a missing fact means no class
$spec = static fn(array $r, array $ids): array => S::decide($r, $resolver, $validator, [], $ids);
$ramRow = $row(['source_key' => 'offline_real_facebook_com', 'title' => 'Kingston Fury Beast DDR4 16GB 2x8 3200MHz']);
$hit = $spec($ramRow, ['ram-ddr4-3200-16gb' => 77]);
ok_sr($hit['outcome'] === 'would_create' && $hit['product_id'] === 77 && $hit['method'] === 'spec_class_from_title', 'A RAM post naming type, speed and size resolves to the generic class: ' . json_encode($hit));
ok_sr($spec($ramRow, [])['outcome'] === 'STILL_UNRESOLVED', 'No class product in the catalog means unresolved');
ok_sr($spec($row(['source_key' => 'offline_real_facebook_com', 'title' => 'Kingston Fury Beast DDR4 16GB']), ['ram-ddr4-3200-16gb' => 77])['outcome'] === 'STILL_UNRESOLVED', 'A RAM post without its speed is never guessed into a class');
// LS-76: the approval plan for Facebook rows never touches a row a reviewer would not trust
$t = static fn(array $o = []): array => array_merge(['is_deposit' => 0, 'is_defective' => 0, 'is_duplicate' => 0, 'listing_type' => 'single_item', 'raw_title' => 'RTX 3060 12GB มือสอง'], $o);
ok_sr(!\App\Services\Research\OwnerApprovalPlan::untrusted($t()), 'A used single item is trusted');
foreach ([['listing_type' => 'bundle'], ['is_deposit' => 1], ['is_defective' => 1], ['is_duplicate' => 1], ['raw_title' => 'RTX 3060 ขายแล้ว'], ['raw_title' => 'RTX 3060 มือ 1 ใหม่แกะกล่อง'], ['raw_title' => 'RTX 3060 จองแล้ว']] as $bad) {
    ok_sr(\App\Services\Research\OwnerApprovalPlan::untrusted($t($bad)), 'Untrusted: ' . json_encode($bad, JSON_UNESCAPED_UNICODE));
}
// LS-77: a RAM title that contradicts the product it sits under is a misfiled row; a fact the title does not state is never a conflict
$p32 = ['memory_type' => 'DDR4', 'memory_speed_mhz' => 2666, 'capacity_gb' => 32];
ok_sr(\App\Services\Catalog\SpecConsistency::ramConflicts('Kingston Fury DDR4 16GB 2x8GB 2666 RAM kit', $p32) === ['capacity 16 != 32'], 'A 16GB kit under a 32GB class conflicts');
ok_sr(\App\Services\Catalog\SpecConsistency::ramConflicts('Ram T-force 32GB DDR4 16x2', $p32) === [], 'A matching title does not conflict');
ok_sr(\App\Services\Catalog\SpecConsistency::ramConflicts('แรม มือสอง ราคา 2000', $p32) === [], 'A title that states no facts never conflicts');
ok_sr(\App\Services\Research\OwnerApprovalPlan::untrusted($t(['raw_title' => 'DDR4 16GB 2x8GB 2666'] + $p32)), 'A misfiled RAM row is untrusted');
// the decision is read-only by construction: it takes no database (the spec-class map is plain data)
ok_sr((new ReflectionMethod(S::class, 'decide'))->getNumberOfParameters() === 5, 'decide() has no database parameter');
echo "stale_reresolve_test: PASS\n";
