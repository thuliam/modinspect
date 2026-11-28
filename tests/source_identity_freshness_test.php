<?php
declare(strict_types=1);

/** LS-88 T2 and T3: one platform is one source; the freshness wording follows the day of viewing, not the day of calculation. Pure, no database writes. */
require __DIR__ . '/bootstrap.php';

use App\Services\Deal\ShareText;
use App\Services\Pricing\PublicFreshness;
use App\Services\Pricing\SnapshotConfidenceService;
use App\Services\Pricing\SourceIdentity;

function assert_sif(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

// T2: every spelling and every import batch of Facebook is one source
$facebook = [
    SourceIdentity::key('facebook.com', 'Offline REAL import facebook.com', 'offline_real_facebook_com', 1),
    SourceIdentity::key('www.facebook.com', 'Offline REAL import www.facebook.com', 'offline_real_www_facebook_com', 2),
    SourceIdentity::key('m.facebook.com', null, null, 3),
    SourceIdentity::key('FB.com', null, null, 4),
    SourceIdentity::key('', 'Owner collected text', 'offline_owner_text_owner_collected_facebook_text', 5),
    SourceIdentity::key(null, 'Owner collected text r2', 'offline_owner_text_owner_collected_facebook_text_r2', 6),
];
assert_sif(array_unique($facebook) === ['facebook.com'], 'all Facebook rows must share one source key: ' . implode(',', array_unique($facebook)));
assert_sif(SourceIdentity::key('', 'a', 'offline_owner_text_batch1', 7) === SourceIdentity::key('', 'b', 'offline_owner_text_batch2', 8), 'owner text batches without a platform are one source');
assert_sif(SourceIdentity::key('kaidee.com', null, null, 9) !== 'facebook.com' && SourceIdentity::key('pantip.com', null, null, 10) === 'pantip.com', 'other platforms stay separate');
assert_sif(SourceIdentity::key('', '', '', 11) === '11' && SourceIdentity::key(null, null, null) === 'unknown', 'a row with nothing falls back to its id');

$today = '2026-11-28';
$row = static fn(int $i, string $domain, string $key): array => ['observed_at' => '2026-11-27 12:00:00', 'source_domain' => $domain, 'source_name' => $key, 'source_key' => $key, 'source_id' => $i, 'evidence_level' => 4, 'source_evidence_quality' => 'medium', 'excerpt' => ''];
$rows = [];
for ($i = 0; $i < 12; $i++) {
    $rows[] = [$row(1, '', 'offline_owner_text_owner_collected_facebook_text'), $row(2, 'facebook.com', 'offline_real_facebook_com'), $row(3, 'www.facebook.com', 'offline_real_www_facebook_com')][$i % 3];
}
$service = new SnapshotConfidenceService();
$one = $service->calculate($rows, 'asking', $today);
assert_sif($one['method_version'] === 'confidence-v3', 'method version');
assert_sif($one['components']['source_diversity']['unique_source_count'] === 1 && in_array('SINGLE_SOURCE_CAP', $one['policy_caps'], true), 'one platform must be treated as a single source');
$two = $rows; $two[0] = $row(4, 'kaidee.com', 'offline_real_kaidee_com');
assert_sif($service->calculate($two, 'asking', $today)['components']['source_diversity']['unique_source_count'] === 2, 'a second platform counts');

// T3: fresh on the day of calculation, no longer "new" later
$dates = ['2026-11-20 10:00:00', '2026-11-22 10:00:00', '2026-11-23 10:00:00'];
$now = PublicFreshness::evaluate($dates, $today);
assert_sif($now['code'] === 'FRESH_OBSERVATIONS' && $now['newest_observed_at'] === '2026-11-23 10:00:00' && $now['fresh_ratio'] === 100.0, 'recent listings are fresh');
foreach (['2026-12-28', '2027-01-15', '2027-06-01'] as $later) {
    $then = PublicFreshness::evaluate($dates, $later);
    assert_sif($then['code'] !== 'FRESH_OBSERVATIONS' && $then['fresh_ratio'] === 0.0, "listings older than 30 days must not read as new on $later");
}
assert_sif(PublicFreshness::evaluate($dates, '2027-06-01')['code'] === 'STALE_DATA', 'very old data reads as stale');
$stored = ['LOW_SAMPLE_SIZE', 'FRESH_OBSERVATIONS', 'SINGLE_SOURCE'];
assert_sif(PublicFreshness::reasonsToday($stored, 'LIMITED_FRESHNESS') === ['LOW_SAMPLE_SIZE', 'LIMITED_FRESHNESS', 'SINGLE_SOURCE'], 'only the freshness reason is replaced');
assert_sif(PublicFreshness::reasonsToday($stored, null) === $stored && PublicFreshness::evaluate([], $today)['code'] === null, 'no dates, no change');

// the shared line names the newest listing, not the calculation day
$product = ['full_name' => 'Sample', 'median' => 100, 'q1' => 90, 'q3' => 110, 'valid_sample_size' => 3, 'accepted_observation_count' => 3, 'last_calculated_at' => '2026-11-28 09:00:00', 'newest_observed_at' => '2026-11-23 10:00:00'];
$text = (string)ShareText::forProduct($product, 'u');
assert_sif(str_contains($text, 'ประกาศล่าสุด 23 พ.ย. 2569') && !str_contains($text, '28 พ.ย.'), 'share text uses the newest listing date');
assert_sif(ShareText::thaiDate(null) === null && ShareText::thaiDate('') === null, 'empty dates are not invented');

echo "source_identity_freshness_test: PASS\n";
