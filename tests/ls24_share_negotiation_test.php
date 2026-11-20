<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Deal\Negotiation as N;
use App\Services\Deal\ShareCard;
use App\Services\Deal\ShareText;

/**
 * LS-24 C1/C2/A3: the share line and the product price card say nothing the page does not, and the bargaining help only places the price inside the market band.
 */
function ok_sn(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

// ---- share text ----
$p = ['full_name' => 'NVIDIA GeForce RTX 3070', 'q1' => 7300, 'q3' => 8199, 'median' => 7890, 'valid_sample_size' => 13, 'accepted_observation_count' => 13, 'last_calculated_at' => '2026-11-19 10:00:00'];
$t = ShareText::forProduct($p, 'https://example.test/price/x');
ok_sn($t !== null && str_contains($t, '฿7,300 – ฿8,199') && str_contains($t, 'ตัวอย่าง 13 ประกาศ') && str_contains($t, '19 พ.ย. 2569') && str_contains($t, 'ไม่ใช่ราคาปิดดีล') && str_ends_with($t, 'https://example.test/price/x'), 'The share line has the range, the sample size, the Thai date, the asking-price caveat and the link: ' . $t);
foreach ([['median' => null], ['valid_sample_size' => 0], ['accepted_observation_count' => 0], ['q1' => null]] as $drop) { ok_sn(ShareText::forProduct(array_merge($p, $drop), 'u') === null, 'Nothing is shared when the page shows no price: ' . json_encode($drop)); }

// ---- the product card uses the same figures; GD with FreeType may be missing on a machine, so only draw when it is there ----
$fonts = ROOT_PATH . '/public/assets/fonts';
if (ShareCard::available($fonts)) {
    $png = ShareCard::render(['name' => $p['full_name'], 'price' => 7890.0, 'caption' => 'ราคากลางมือสองที่พบ', 'label' => 'ตัวอย่าง 13 ประกาศ', 'tone' => 'neutral', 'range' => [7300.0, 8199.0], 'median' => null], $fonts, ROOT_PATH . '/public/assets/brand/modinpect-lockup-dark-480.png');
    ok_sn(str_starts_with($png, "\x89PNG") && ($im = imagecreatefromstring($png)) && imagesx($im) === 1200 && imagesy($im) === 630, 'The card is a 1200 x 630 PNG');
}
$ctl = (string)file_get_contents(ROOT_PATH . '/app/Controllers/PriceController.php');
ok_sn(str_contains($ctl, "public function card(string \$slug)") && str_contains($ctl, 'ShareText::forProduct($product,\'x\')===null') && str_contains($ctl, 'ShareCard::available'), 'The card route answers 404 for a model with no price or without GD');
ok_sn(str_contains((string)file_get_contents(ROOT_PATH . '/public/index.php'), "'/price/{slug}/card'"), 'The card route is registered');
ok_sn(str_contains((string)file_get_contents(ROOT_PATH . '/app/Views/price/show.php'), 'data-copy-text'), 'The product page offers the copy button');
ok_sn(str_contains((string)file_get_contents(ROOT_PATH . '/public/assets/site.js'), "'[data-copy-text]'"), 'The copy glue exists');

// ---- negotiation ----
$q1 = 7300.0; $q3 = 8200.0; $m = 7890.0;
$above = N::plan('RTX 3070', 9500.0, $q1, $q3, $m, 13);
ok_sn($above['mode'] === 'above_band' && $above['offer'] === 7900 && $above['ceiling'] === 8200, 'Above the band: open at the median, never past the top of the band');
$upper = N::plan('RTX 3070', 8100.0, $q1, $q3, $m, 13);
ok_sn($upper['mode'] === 'upper_band' && $upper['offer'] >= 7300 && $upper['offer'] < 8100 && $upper['ceiling'] <= 8200, 'Upper half of the band: open between the bottom and the median');
$lower = N::plan('RTX 3070', 7500.0, $q1, $q3, $m, 13);
ok_sn($lower['mode'] === 'lower_band' && $lower['offer'] >= 7300 && $lower['offer'] <= 7500 && $lower['ceiling'] === 7500, 'Lower half: a small step down, never below the bottom of the band');
foreach ([$above, $upper, $lower] as $pl) { ok_sn($pl['offer'] % 50 === 0 && $pl['ceiling'] >= $pl['offer'] && count($pl['messages']) === 3 && str_contains($pl['messages'][0]['text'], number_format($pl['offer'])), 'Offers are rounded to 50, the ceiling is not under the offer, and the three messages carry the offer'); }
$below = N::plan('RTX 3070', 5000.0, $q1, $q3, $m, 13);
ok_sn($below['mode'] === 'below_band' && $below['offer'] === null && $below['messages'] === [] && $below['tips'] !== [], 'Below the band: no offer to push lower, only a reason to check the item');
ok_sn(N::plan('x', 8000.0, null, null, null) === null && N::plan('x', 0.0, $q1, $q3, $m) === null && N::plan('x', 8000.0, 9000.0, 8000.0, 8500.0) === null, 'No band, no price or a broken band: nothing is said');
$all = json_encode([$above, $upper, $lower, $below], JSON_UNESCAPED_UNICODE);
ok_sn(!preg_match('/ประกัน.*บาท|กล่อง.*บาท|หักเงิน|บวกเพิ่ม/u', $all), 'No baht is added or taken off for warranty or box');
ok_sn(str_contains((string)file_get_contents(ROOT_PATH . '/app/Views/deal/result.php'), 'Negotiation::plan') && str_contains((string)file_get_contents(ROOT_PATH . '/app/Views/listing/reader.php'), 'Negotiation::plan'), 'The helper is on the deal result and the reader result');
echo "ls24_share_negotiation_test: PASS\n";
