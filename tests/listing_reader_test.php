<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Listing\ListingReader as R;
use App\Services\Listing\RedFlags;

/**
 * LS-24 A1/A2: reading a pasted listing. Contact details never survive, a model number is never taken for a price, the price engine's verdict is used as it is,
 * a price the text does not give clearly is left to the visitor, and the phrase watch-list describes the text, not the seller.
 */
function ok_lr(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

// ---- masking ----
$c = R::clean("ขาย RTX 3070 โทร 081-234-5678 หรือ +66 81 234 5678 อีเมล seller@example.com ไลน์: seller_99 line id abcd1234\x07");
ok_lr(!preg_match('/\d{3}[-\s]?\d{3}[-\s]?\d{4}|seller@|seller_99|abcd1234|\x07/', $c) && str_contains($c, '[เบอร์ถูกซ่อน]') && str_contains($c, '[อีเมลถูกซ่อน]') && str_contains($c, '[ไลน์ถูกซ่อน]'), 'Phone numbers, e-mail and LINE ids are masked: ' . $c);
ok_lr(mb_strlen(R::clean(str_repeat('ก', 9000)), 'UTF-8') <= R::MAX_CHARS, 'The text is capped');

// ---- price: a model number or a spec is never the price ----
$f = static fn(string $t, array $models = []): array => R::findPrice($t, $models);
ok_lr($f('ขาย RTX 3070 ราคา 8,500 บาท', ['3070'])['price'] === 8500.0 && $f('ขาย RTX 3070 ราคา 8,500 บาท', ['3070'])['confidence'] === 'high', 'A marked price beats the model number');
ok_lr($f('RTX 3070 8GB 8500 บาท', ['3070'])['price'] === 8500.0, 'A number followed by baht is the price, 8GB is not');
ok_lr($f('DDR4 3200MHz 16GB เหลือ 1,200', [])['price'] === 1200.0, 'Speed and size are skipped');
ok_lr($f('฿7,900 ต่อรองได้', [])['price'] === 7900.0, 'A baht sign marks the price');
ok_lr($f('RTX 3070 มือสอง สภาพดี', ['3070'])['price'] === null && $f('RTX 3070 มือสอง สภาพดี', ['3070'])['confidence'] === 'none', 'No price in the text means no price');
$amb = $f('ราคา 8,500 บาท หรือ 9,000 บาท', []);
ok_lr($amb['price'] === null && $amb['confidence'] === 'ambiguous' && count($amb['candidates']) === 2, 'Two different prices are not guessed between');
ok_lr($f('RTX 3070 มือสอง 6500 ต่อรองได้', ['3070'])['confidence'] === 'low' && $f('RTX 3070 มือสอง 6500 ต่อรองได้', ['3070'])['price'] === 6500.0, 'A single bare number is a low-confidence guess');
ok_lr($f('ขาย 6500', [])['confidence'] === 'high', 'A number right after the word for sell is a marked price');

// ---- red flags: every rule is complete and fires on its own phrase; none talks about a person ----
$samples = ['show_unit' => 'ตัวโชว์', 'no_warranty' => 'ไม่มีประกัน', 'repaired' => 'เคยซ่อมมา', 'mining' => 'ตัวขุด', 'prepay' => 'โอนก่อน', 'no_test' => 'ขายตามสภาพ ไม่รับคืน', 'urgent' => 'รีบขาย', 'engineering' => 'engineering sample',
    'bios_flash' => 'flash bios', 'overclock' => 'เคยโอเวอร์คล็อก', 'no_receipt' => 'ไม่มีใบเสร็จ'];
ok_lr(array_keys($samples) === RedFlags::codes(), 'Every rule has a sample in this test');
foreach ($samples as $code => $text) {
    $hit = array_column(RedFlags::scan("ขาย การ์ดจอ $text ครับ"), null, 'code');
    ok_lr(isset($hit[$code]) && $hit[$code]['label'] !== '' && $hit[$code]['why'] !== '' && $hit[$code]['ask'] !== '' && in_array($hit[$code]['level'], ['info', 'caution'], true), "Rule $code fires and is complete");
    ok_lr(!preg_match('/คนขาย|ผู้ขายเป็น|โกง|มิจฉาชีพ|หลอก/u', $hit[$code]['label'] . $hit[$code]['why']), "Rule $code describes the text, not the seller");
}
ok_lr(RedFlags::scan('ขาย RTX 3070 สภาพสวย ประกันเหลือ 6 เดือน มีกล่อง ใบเสร็จครบ') === [], 'A clean listing raises nothing');
ok_lr(mb_strlen(RedFlags::scan(str_repeat('ตัวโชว์ ', 1))[0]['phrase'], 'UTF-8') <= 24, 'The echoed phrase is short');

// ---- the whole read, with a stub catalog and engine ----
$catalog = [['id' => 1, 'full_name' => 'NVIDIA GeForce RTX 3070', 'aliases' => ['rtx 3070', 'rtx3070', '3070']], ['id' => 2, 'full_name' => 'AMD Ryzen 5 5600', 'aliases' => ['5600', 'ryzen 5 5600']]];
$row = static fn(int $id): ?array => $id === 1 ? ['id' => 1, 'full_name' => 'NVIDIA GeForce RTX 3070', 'slug' => 'nvidia-geforce-rtx-3070', 'q1' => 7300, 'q3' => 8200, 'median' => 7890, 'accepted_observation_count' => 13] : null;
$eval = static fn(array $p, float $price): array => ['label' => $price > (float)$p['q3'] ? 'above_range' : 'in_range', 'tier' => null, 'recommendation' => 'x', 'warning' => 'y'];
$r = R::read("ขาย NVIDIA GeForce RTX 3070 ราคา 8,500 บาท ประกันเหลือ 6 เดือน มีกล่อง", $catalog, $row, $eval);
ok_lr($r['product']['id'] === 1 && $r['price'] === 8500.0 && $r['price_confidence'] === 'high' && $r['verdict']['label'] === 'above_range' && $r['facts']['warranty_months'] === 6 && $r['facts']['has_box'] === true, 'Model, price, warranty and box are read and the engine verdict is used: ' . json_encode($r['product']));
$r = R::read('ขาย RTX 3070 มือสอง สภาพดี', $catalog, $row, $eval);
ok_lr($r['product'] !== null && $r['price'] === null && $r['verdict'] === null, 'No price: the model is read, no verdict is invented');
$r = R::read('ขาย RTX 3070 มือสอง สภาพดี', $catalog, $row, $eval, 7500.0);
ok_lr($r['price'] === 7500.0 && $r['price_confidence'] === 'typed' && $r['verdict']['label'] === 'in_range', 'A typed price is used and said to be typed');
$r = R::read('รับซื้อ RTX 3070 ราคา 8,000', $catalog, $row, $eval);
ok_lr($r['verdict'] === null && $r['notes'] !== [], 'A wanted post is not compared with the market');
$r = R::read('ขาย RTX 3070 พร้อม CPU Ryzen 5 5600 + เมนบอร์ด ราคา 15,000 บาท', $catalog, $row, $eval);
ok_lr($r['verdict'] === null && $r['notes'] !== [], 'A bundle is not compared with a single-part range');
$r = R::read('ขายโน้ตบุ๊กมือสอง Dell', $catalog, $row, $eval);
ok_lr($r['product'] === null && $r['questions'] !== [], 'An unknown model is said so and the questions still come');
ok_lr(R::read("   \n ", $catalog, $row, $eval)['empty'] === true, 'Empty text is empty');
$r = R::read("ขาย RTX 3070 8,500 บาท โทร 0812345678", $catalog, $row, $eval);
ok_lr(!str_contains(json_encode($r, JSON_UNESCAPED_UNICODE), '0812345678'), 'The result never carries the phone number');

ok_lr(R::warrantyMonths('ประกันเหลือ 6 เดือน') === 6 && R::warrantyMonths('ประกันศูนย์อีก 8 เดือน') === 8 && R::warrantyMonths('ประกัน 1 ปี') === 12 && R::warrantyMonths('warranty 2 years') === null && R::warrantyMonths('ไม่มีประกัน') === null, 'Warranty phrases with a word between, and years, are read');

// ---- wiring ----
$ctl = (string)file_get_contents(ROOT_PATH . '/app/Controllers/ListingReaderController.php');
ok_lr(str_contains($ctl, 'Csrf::verify') && str_contains($ctl, 'RateLimiter') && !str_contains($ctl, 'INSERT') && !str_contains($ctl, 'file_put_contents'), 'The controller checks CSRF, rate limits, and stores nothing');
$idx = (string)file_get_contents(ROOT_PATH . '/public/index.php');
ok_lr(str_contains($idx, "get('/read-listing'") && str_contains($idx, "post('/read-listing'"), 'The routes exist');
echo "listing_reader_test: PASS\n";
