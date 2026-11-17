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
// the decision is read-only by construction: it takes no database
ok_sr((new ReflectionMethod(S::class, 'decide'))->getNumberOfParameters() === 4, 'decide() has no database parameter');
echo "stale_reresolve_test: PASS\n";
