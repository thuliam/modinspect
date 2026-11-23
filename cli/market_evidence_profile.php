<?php
declare(strict_types=1);

/**
 * LS-75: quality profile of a market-evidence file (the merged_for_import.json of cli/intake_merge_owner_batches.php, or any {"records":[...]} / [...] file).
 * Reads one local file and prints JSON; touches no database and no network. Categories are a rough keyword guess to size the data, never a decision.
 * It reports what a reviewer must know before trusting the rows: personal data, bundles, new items, sold or reserved posts, wanted posts, price outliers per category,
 * the same item posted twice (same URL, or same title and price on different URLs), how many posts the rows come from, and the age of the capture dates.
 * Usage: php cli/market_evidence_profile.php --file=storage/import/facebook_20261122/merged_for_import.json
 */
$o = getopt('', ['file:']); $file = (string) ($o['file'] ?? '');
if ($file === '' || !is_file($file)) { fwrite(STDERR, "Usage: php cli/market_evidence_profile.php --file=<json>\n"); exit(2); }
$d = json_decode((string) file_get_contents($file), true);
$rows = is_array($d) ? (array_is_list($d) ? $d : ($d['records'] ?? $d['observations'] ?? [])) : [];

$cat = static function (string $t): string {
    $s = mb_strtolower($t);
    if (preg_match('/(ชุดคอม|คอมครบชุด|ทั้งชุด|full set|bundle|คอมพิวเตอร์ตั้งโต๊ะ|\bpc\b ?set|เซ็ต|set\b|\+ ?(rtx|gtx|ryzen|i[3579]))/u', $s) && preg_match('/(\+|และ|พร้อม|set|ชุด)/u', $s)) { return 'bundle'; }
    if (preg_match('/\b(rtx|gtx|geforce|radeon|rx ?\d{3,4}|arc a\d|การ์ดจอ|vga)\b|การ์ดจอ/u', $s)) { return 'gpu'; }
    if (preg_match('/\b(ryzen|core i[3579]|i[3579]-\d{4,5}|xeon|athlon|pentium|celeron|cpu)\b|ซีพียู|ซีพียู/u', $s)) { return 'cpu'; }
    if (preg_match('/\b(ddr[345]|dimm|so-?dimm)\b|แรม|\bram\b/u', $s)) { return 'ram'; }
    if (preg_match('/\b(ssd|hdd|nvme|m\.2|sata|hard ?disk)\b|ฮาร์ดดิสก์|ฮาร์ดดิส/u', $s)) { return 'storage'; }
    if (preg_match('/(mainboard|motherboard|เมนบอร์ด|\b[abhxz]\d{3}m?\b)/u', $s)) { return 'motherboard'; }
    if (preg_match('/\b(psu|power supply)\b|พาวเวอร์|\b\d{3,4} ?w\b/u', $s)) { return 'psu'; }
    if (preg_match('/(เคส|\bcase\b|chassis)/u', $s)) { return 'case'; }
    if (preg_match('/(ฮีทซิงค์|พัดลม|cooler|aio|heatsink|ชุดน้ำ)/u', $s)) { return 'cooling'; }
    if (preg_match('/(monitor|จอ ?\d{2}|จอคอม)/u', $s)) { return 'monitor'; }
    return 'other';
};
$norm = static fn (string $v): string => preg_replace('/[^a-z0-9ก-๙]+/u', ' ', mb_strtolower(trim($v))) ?? '';

$n = 0; $by = []; $flags = ['pii' => 0, 'sold_or_reserved' => 0, 'new_item' => 0, 'wanted' => 0, 'deposit' => 0, 'no_price' => 0, 'bad_price' => 0, 'no_url' => 0, 'multi_item_hint' => 0];
$urls = []; $exact = []; $titlePrice = []; $dates = []; $badCurrency = 0; $nocap = 0;
foreach ($rows as $r) {
    if (!is_array($r)) { continue; }
    $n++;
    $title = (string) ($r['title'] ?? $r['product_hint'] ?? ''); $text = (string) ($r['listing_text'] ?? '');
    $all = $title . ' ' . $text; $c = $cat($title !== '' ? $title : $text);
    $price = (float) preg_replace('/[^0-9.]/', '', (string) ($r['displayed_price'] ?? '')); $url = strtolower(rtrim(preg_replace('/\?.*$/', '', (string) ($r['source_listing_url'] ?? '')), '/'));
    $by[$c]['n'] = ($by[$c]['n'] ?? 0) + 1; if ($price > 0) { $by[$c]['p'][] = $price; }
    if (preg_match('/(?<!\d)0[689]\d[ -]?\d{3}[ -]?\d{4}(?!\d)|\+66|line ?id|ไลน์ ?:|@[a-z0-9_.]{4,}|[a-z0-9._-]+@[a-z0-9.-]+\.[a-z]{2,}/iu', $all)) { $flags['pii']++; }
    if (preg_match('/(ขายแล้ว|\bsold\b|จองแล้ว|reserved|ติดจอง)/iu', $all)) { $flags['sold_or_reserved']++; }
    if (preg_match('/(มือ ?1\b|มือหนึ่ง|brand new|sealed|ใหม่แกะกล่อง|ประกันศูนย์ใหม่|ของใหม่)/iu', $all)) { $flags['new_item']++; }
    if (preg_match('/(รับซื้อ|ตามหา|\bwtb\b|ต้องการซื้อ)/iu', $all)) { $flags['wanted']++; }
    if (preg_match('/(มัดจำ|deposit)/iu', $all)) { $flags['deposit']++; }
    if (preg_match('/(several separately priced|multiple items|หลายรายการ)/iu', (string) ($r['notes'] ?? ''))) { $flags['multi_item_hint']++; }
    if ($price <= 0) { $flags['no_price']++; } elseif ($price < 100 || $price > 500000) { $flags['bad_price']++; }
    if ($url === '') { $flags['no_url']++; } else { $urls[$url] = ($urls[$url] ?? 0) + 1; }
    if (($r['currency'] ?? 'THB') !== 'THB') { $badCurrency++; }
    if (empty($r['capture_method'])) { $nocap++; }
    $exact[$url . '|' . $norm($title) . '|' . $price] = ($exact[$url . '|' . $norm($title) . '|' . $price] ?? 0) + 1;
    $tp = $norm($title) . '|' . $price; $titlePrice[$tp][$url] = true;
    $t = strtotime((string) ($r['observed_at'] ?? '')); if ($t) { $dates[date('Y-m-d', $t)] = ($dates[date('Y-m-d', $t)] ?? 0) + 1; }
}
$med = static function (array $a): ?float { if (!$a) { return null; } sort($a); $k = count($a); return $k % 2 ? $a[intdiv($k, 2)] : ($a[$k / 2 - 1] + $a[$k / 2]) / 2; };
$cats = [];
foreach ($by as $c => $v) { $p = $v['p'] ?? []; $cats[$c] = ['rows' => $v['n'], 'median' => $med($p), 'min' => $p ? min($p) : null, 'max' => $p ? max($p) : null]; }
arsort($cats); uasort($cats, static fn ($a, $b) => $b['rows'] <=> $a['rows']);
$crossPost = count(array_filter($titlePrice, static fn ($u) => count($u) > 1));
ksort($dates);
echo json_encode([
    'file' => basename($file), 'rows' => $n, 'distinct_post_urls' => count($urls), 'rows_per_post_max' => $urls ? max($urls) : 0, 'posts_with_several_rows' => count(array_filter($urls, static fn ($c) => $c > 1)),
    'exact_duplicate_rows' => count(array_filter($exact, static fn ($c) => $c > 1)), 'same_title_and_price_on_different_posts' => $crossPost, 'non_thb' => $badCurrency, 'without_capture_method' => $nocap,
    'flags' => $flags, 'categories_by_keyword' => $cats, 'capture_dates' => $dates,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
