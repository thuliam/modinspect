<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/app/Services/Collection/CaptureMethod.php';
require ROOT_PATH . '/app/Services/Research/OwnerIntakeDeduper.php';
require ROOT_PATH . '/app/Services/Collection/OwnerRowPreparer.php';

use App\Services\Collection\OwnerRowPreparer as P;

/**
 * LS-6 M-1: the daily file helper. Pure (no database): takes the owner's loose rows (link, title, price, date) and writes the strict import template
 * (capture_method, canonical listing URL, derived item id, normalised price, full timestamp). It never invents a price, title or seller detail, and every
 * row it cannot make valid is reported by code, not silently repaired.
 */
function ok_pr(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

// ---- URL canonicalisation: same listing, same URL, whatever the share link carried
$fb = 'https://www.facebook.com/marketplace/item/9100000000001';
foreach (['https://m.facebook.com/marketplace/item/9100000000001/?ref=search&fbclid=ABC#x', 'https://web.facebook.com/marketplace/item/9100000000001?mibextid=1', 'facebook.com/marketplace/item/9100000000001/', '  HTTPS://WWW.FACEBOOK.COM/marketplace/item/9100000000001  '] as $u) {
    ok_pr(P::canonicalUrl($u) === $fb, "canonical marketplace url for: $u => " . var_export(P::canonicalUrl($u), true));
}
ok_pr(P::canonicalUrl('https://web.facebook.com/photo.php?fbid=123&set=a.1&utm_source=x') === 'https://www.facebook.com/photo.php?fbid=123', 'photo.php keeps only fbid');
ok_pr(P::canonicalUrl('https://www.facebook.com/permalink.php?story_fbid=pfbid02abc&id=100&__cft__[0]=x') === 'https://www.facebook.com/permalink.php?story_fbid=pfbid02abc&id=100', 'permalink.php keeps story_fbid and id');
ok_pr(P::canonicalUrl('https://www.facebook.com/SomeShop/posts/pfbid0ABC?comment_id=1') === 'https://www.facebook.com/SomeShop/posts/pfbid0ABC', 'a fan page post loses its query');
ok_pr(P::canonicalUrl('https://pantip.com/topic/41234567?utm_source=x') === 'https://pantip.com/topic/41234567', 'pantip topic');
ok_pr(P::canonicalUrl('https://www.kaidee.com/product-12345678?ref=x') === 'https://www.kaidee.com/product-12345678', 'kaidee product');
foreach (['not a url', 'ftp://www.facebook.com/x', '', 'https://'] as $u) { ok_pr(P::canonicalUrl($u) === null, "not a usable url: [$u]"); }

// ---- item id derived from the canonical URL (and only when the URL really names one listing)
ok_pr(P::itemId($fb) === 'FB-9100000000001', 'marketplace item id');
ok_pr(P::itemId('https://www.facebook.com/SomeShop/posts/pfbid0ABC') === 'FB-pfbid0ABC', 'post id');
ok_pr(P::itemId('https://www.facebook.com/permalink.php?story_fbid=pfbid02abc&id=100') === 'FB-pfbid02abc', 'permalink story id');
ok_pr(P::itemId('https://www.facebook.com/photo.php?fbid=123') === 'FB-123', 'photo id');
ok_pr(P::itemId('https://pantip.com/topic/41234567') === 'PT-41234567', 'pantip id');
ok_pr(P::itemId('https://www.kaidee.com/product-12345678') === 'KD-12345678', 'kaidee id');
ok_pr(P::itemId('https://www.facebook.com/SomeShop') === null, 'a fan page home is not one listing');
ok_pr(P::itemId('https://www.facebook.com/marketplace/') === null, 'the marketplace home is not one listing');

// ---- prepare(): one record per case
$rec = static fn(array $r, int $n): array => $r + ['_row' => $n];
$records = [
    $rec(['url' => 'https://m.facebook.com/marketplace/item/9100000000001/?ref=x', 'title' => '  NVIDIA   RTX 3070 8GB ', 'price' => '8,200 บาท', 'date' => '2026-11-14', 'condition' => 'Used'], 2),
    $rec(['url' => 'https://www.facebook.com/marketplace/item/9100000000001?fbclid=zz', 'title' => 'RTX 3070 again', 'price' => '8200', 'date' => '2026-11-14'], 3),
    $rec(['url' => 'https://www.facebook.com/marketplace/item/9100000000002', 'title' => 'RTX 3070 โทร 081-234-5678', 'price' => '8200', 'date' => '2026-11-14'], 4),
    $rec(['url' => 'https://www.facebook.com/profile.php?id=100000000000001', 'title' => 'RTX 3070 profile', 'price' => '8200', 'date' => '2026-11-14', 'item_id' => 'FB-5'], 5),
    $rec(['url' => 'https://www.facebook.com/marketplace/item/9100000000003', 'title' => 'RTX 3070 negotiable', 'price' => 'ราคาคุย', 'date' => '2026-11-14'], 6),
    $rec(['url' => 'https://www.facebook.com/marketplace/item/9100000000004', 'title' => 'RTX 3070 no date', 'price' => '8200'], 7),
    $rec(['url' => 'https://www.facebook.com/marketplace/item/9100000000005', 'title' => 'RTX 3070 future', 'price' => '8200', 'date' => '2099-01-01'], 8),
    $rec(['url' => 'https://example.com/x/1', 'title' => 'RTX 3070 other site', 'price' => '8200', 'date' => '2026-11-14', 'item_id' => 'EX-1'], 9),
    $rec(['url' => 'https://www.facebook.com/SomeShop', 'title' => 'RTX 3070 page home', 'price' => '8200', 'date' => '2026-11-14'], 10),
    $rec(['url' => 'https://www.facebook.com/marketplace/item/9100000000006', 'title' => 'RTX 3070 odd condition', 'price' => '8200', 'date' => '2026-11-14 09:30:00', 'condition' => 'mint'], 11),
    $rec(['url' => 'https://www.facebook.com/marketplace/item/9100000000007', 'title' => 'NVIDIA RTX 3070 8GB', 'price' => '8200', 'date' => '2026-11-14'], 12),
    $rec(['url' => 'https://www.facebook.com/marketplace/item/9100000000008', 'title' => '', 'price' => '8200', 'date' => '2026-11-14'], 13),
    $rec(['url' => 'https://www.facebook.com/marketplace/item/9100000000009', 'title' => 'RTX 3070 free', 'price' => '0', 'date' => '2026-11-14'], 14),
];
$r = P::prepare($records, '');
$byRow = [];
foreach ($r['issues'] as $i) { $byRow[$i['row']] = $i; }
$codes = static fn(int $row): array => $byRow[$row]['codes'] ?? [];
$levels = static fn(int $row): string => $byRow[$row]['level'] ?? 'ok';

$good = $r['rows'][0] ?? null;
ok_pr($good !== null && $good === [
    'capture_method' => 'owner_transcription', 'source_listing_url' => $fb, 'source_item_id' => 'FB-9100000000001', 'listing_title' => 'NVIDIA RTX 3070 8GB',
    'asking_price' => '8200', 'observed_at' => '2026-11-14 00:00:00', 'condition_hint' => 'used',
], 'The good row is normalised exactly: ' . json_encode($good, JSON_UNESCAPED_UNICODE));
ok_pr($levels(3) === 'error' && in_array('DUPLICATE_IN_FILE', $codes(3), true), 'Same item twice in one file: the second is rejected');
ok_pr($levels(4) === 'error' && in_array('OWNER_TRANSCRIPTION_TITLE_HAS_PHONE_NUMBER', $codes(4), true), 'A phone number in the title is reported with the importer code');
ok_pr(in_array('OWNER_TRANSCRIPTION_PERSONAL_PROFILE_LINK', $codes(5), true), 'A profile link is reported with the importer code');
ok_pr(in_array('PRICE_NOT_NUMERIC', $codes(6), true), 'A non-numeric price is reported, never turned into a number');
ok_pr(in_array('OBSERVED_AT_MISSING', $codes(7), true), 'No date and no default: reported');
ok_pr(in_array('OBSERVED_AT_IN_FUTURE', $codes(8), true), 'A future date is reported');
ok_pr(in_array('OWNER_TRANSCRIPTION_DOMAIN_NOT_ALLOWED', $codes(9), true), 'A site outside the allow-list is reported');
ok_pr(in_array('OWNER_TRANSCRIPTION_REQUIRES_ITEM_ID', $codes(10), true), 'A page home has no derivable item id: reported');
ok_pr($levels(11) === 'warning' && in_array('CONDITION_HINT_DROPPED', $codes(11), true), 'An unknown condition is dropped with a warning, the row stays');
ok_pr($levels(12) === 'warning' && in_array('REPOST_IN_FILE', $codes(12), true), 'Same words and price under another item id: a warning only, the owner decides');
ok_pr(in_array('TITLE_MISSING', $codes(13), true), 'An empty title is reported');
ok_pr(in_array('PRICE_NOT_NUMERIC', $codes(14), true) || in_array('PRICE_OUT_OF_RANGE', $codes(14), true), 'A zero price is reported');
$ready = array_column($r['rows'], 'source_item_id');
ok_pr($ready === ['FB-9100000000001', 'FB-9100000000006', 'FB-9100000000007'], 'Only the usable rows are written: ' . json_encode($ready));
ok_pr($r['rows'][1]['observed_at'] === '2026-11-14 09:30:00' && $r['rows'][1]['condition_hint'] === '', 'A full timestamp is kept as given; the dropped condition is blank');
ok_pr($r['summary']['read'] === 13 && $r['summary']['ready'] === 3 && $r['summary']['rejected'] === 10 && $r['summary']['warnings'] === 2, 'Summary counts: ' . json_encode($r['summary']));

// a default date fills only rows with no date, and says how many
$d = P::prepare([$rec(['url' => $fb, 'title' => 'RTX 3070', 'price' => '8200'], 2), $rec(['url' => 'https://www.facebook.com/marketplace/item/9100000000010', 'title' => 'RTX 3070 b', 'price' => '8300', 'date' => '2026-11-13'], 3)], '2026-11-15');
ok_pr($d['rows'][0]['observed_at'] === '2026-11-15 00:00:00' && $d['rows'][1]['observed_at'] === '2026-11-13 00:00:00' && $d['summary']['observed_defaulted'] === 1, 'The default date fills one row and the count says so: ' . json_encode($d['summary']));

// seller identity never reaches the output, whatever extra columns the owner left in
$x = P::prepare([$rec(['url' => $fb, 'title' => 'RTX 3070', 'price' => '8200', 'date' => '2026-11-14', 'seller' => 'Somchai', 'phone' => '0812345678', 'location' => 'Bangkok'], 2)], '');
ok_pr(array_keys($x['rows'][0]) === P::OUT_COLUMNS && !str_contains(json_encode($x['rows'][0], JSON_UNESCAPED_UNICODE), 'Somchai'), 'Extra columns (seller, phone, location) are dropped, only the template columns come out');
ok_pr(($x["summary"]["ignored_columns"] ?? []) === ["location", "phone", "seller"], "The ignored column names (not values) are reported: " . json_encode($x["summary"]));

// ---- CSV round trip: Excel-style file (BOM, CRLF, Thai text, quoted comma), header aliases
$tmp = sys_get_temp_dir() . '/mi_pr_' . bin2hex(random_bytes(4)) . '.csv';
try {
    file_put_contents($tmp, "\xEF\xBB\xBF" . "Link,Title,Price,Date\r\n" . 'https://m.facebook.com/marketplace/item/9100000000011/?ref=x,"RTX 3070, 8GB สภาพดี",8200,2026-11-14' . "\r\n");
    $rows = P::readCsv($tmp);
    ok_pr(count($rows) === 1 && $rows[0]['_row'] === 2 && ($rows[0]['title'] ?? null) === 'RTX 3070, 8GB สภาพดี' && isset($rows[0]['link']), 'Excel-style file: BOM, CRLF, Thai, quoted comma and header aliases are read: ' . json_encode($rows, JSON_UNESCAPED_UNICODE));
    $out = P::toCsv(P::prepare($rows, '')['rows']);
    ok_pr(str_starts_with($out, implode(',', P::OUT_COLUMNS) . "\n") && !str_contains($out, "\r") && !str_starts_with($out, "\xEF\xBB\xBF"), 'The output has the template header, LF line ends and no BOM');
    ok_pr(str_contains($out, '"RTX 3070, 8GB สภาพดี"') && str_contains($out, 'FB-9100000000011'), 'The output keeps the quoted title and the derived id');
} finally { @unlink($tmp); }
ok_pr(P::OUT_COLUMNS === explode(',', trim((string)file_get_contents(ROOT_PATH . '/examples/import/owner_listing_urls.csv'))), 'The output columns are exactly the checked-in template header');
echo "owner_row_preparer_test: PASS\n";
