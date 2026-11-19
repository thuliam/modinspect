<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Deal\ShareCard;
use App\Services\Image\ThaiText;

/**
 * LS-21 (owner: G-7 Font Awesome, the Thai font for the share card):
 *  - Font Awesome is the official 5.15.4 package: no font file is referenced that does not exist (the old CSS pointed woff2 at *.html, a 404 on every page);
 *  - the two Prompt TrueType files are the official ones (git blob hashes) and recorded with their licence;
 *  - ThaiText draws every tone mark (GD cannot shape Thai) and ShareCard draws the 1200 x 630 result picture from the same wording as the page,
 *    with nothing about the visitor, and the pages offer it only when GD with FreeType is there.
 */
function ok_l21(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
$root = dirname(__DIR__);
$read = static fn(string $p): string => (string)file_get_contents($root . '/' . $p);

// ---- Font Awesome ----
$fa = $read('public/assets/admin/vendor/fontawesome/css/fontawesome-all.css');
ok_l21(str_contains($fa, 'Font Awesome Free 5.15.4') && !str_contains($fa, '.html'), 'The CSS is the official 5.15.4 and no font URL ends in .html');
preg_match_all('/url\("?\.\.\/webfonts\/([a-z0-9-]+\.(?:woff2|woff))/', $fa, $m);
ok_l21(count(array_unique($m[1])) === 6, 'The CSS names the six woff2 / woff files: ' . implode(', ', array_unique($m[1])));
foreach (array_unique($m[1]) as $f) { ok_l21(is_file($root . '/public/assets/admin/vendor/fontawesome/webfonts/' . $f), "$f exists"); }
ok_l21(is_file($root . '/public/assets/admin/vendor/fontawesome/LICENSE.txt'), 'The Font Awesome licence file is kept');
$man = json_decode($read('public/assets/admin/vendor/MANIFEST.json'), true)['components']['fontawesome'] ?? [];
ok_l21(($man['version'] ?? '') === '5.15.4' && ($man['provenance'] ?? '') === 'official-npm-verified', 'The vendor manifest pins it as an official, verified package');
foreach (['header', 'admin_header', 'auth_header'] as $l) { ok_l21(str_contains($read("app/Views/layouts/$l.php"), 'fontawesome-all.css?v=<?= (int) @filemtime('), "$l.php versions the Font Awesome stylesheet (a replaced vendor CSS is not served from an old cache)"); }
ok_l21(str_contains($read('docs/THIRD_PARTY_LICENSES.md'), '| Font Awesome Free | 5.15.4 |'), 'The licence list says 5.15.4');

// ---- the Prompt TrueType files are the official ones ----
foreach (['Prompt-Regular.ttf' => '2a736dbba7d0bcb384bc1866516574f96e3d4893', 'Prompt-SemiBold.ttf' => '011a0507ab4e2ae83384acb0185d6593f3c35605'] as $f => $sha) {
    $b = $read('public/assets/fonts/' . $f);
    ok_l21(sha1('blob ' . strlen($b) . "\0" . $b) === $sha, "$f is byte-identical to the official file (git blob hash)");
}
ok_l21(str_contains($read('docs/THIRD_PARTY_LICENSES.md'), 'Prompt-Regular.ttf') && str_contains($read('docs/THIRD_PARTY_LICENSES.md'), 'SIL Open Font License 1.1'), 'The fonts are recorded with their licence');

// ---- ThaiText.split: which marks it takes over ----
$s = ThaiText::split('ช่วงที่พบ');
ok_l21($s['plain'] === 'ชวงทีพบ' && count($s['marks']) === 2 && $s['marks'][0]['lift'] === null && $s['marks'][1]['lift'] === 'ี', 'A mark on a consonant stays at normal height, a mark over an upper vowel is lifted: ' . json_encode($s, JSON_UNESCAPED_UNICODE));
ok_l21(ThaiText::split('อยู่')['marks'][0]['lift'] === null && ThaiText::split('อยู่')['marks'][0]['base'] === 1, 'A mark after a lower vowel belongs to the consonant before it, not lifted');
ok_l21(ThaiText::split('ต่ำ')['marks'][0]['beforeAm'] === true && ThaiText::split('ต่ำ')['plain'] === 'ตำ', 'A mark before sara am is moved left of its ring');
ok_l21(ThaiText::split('NVMe M.2')['marks'] === [] && ThaiText::split('NVMe M.2')['plain'] === 'NVMe M.2', 'Latin text passes through untouched');
ok_l21(ThaiText::split("\u{0E48}")['marks'] === [], 'A stray mark with nothing under it is dropped, not drawn in the air');

if (!ShareCard::available($root . '/public/assets/fonts')) {
    echo "ls21_assets_card_test: PASS (drawing checks skipped: this PHP has no GD with FreeType; static checks ran)\n";
    $skipDraw = true;
} else { $skipDraw = false; }

if (!$skipDraw) {
    // ---- the lifted mark really shows: "ที่" must put ink above the vowel that "ที" does not ----
    $font = $root . '/public/assets/fonts/Prompt-SemiBold.ttf';
    $ink = static function (string $t) use ($font): int {
        $im = imagecreatetruecolor(300, 200); imagefill($im, 0, 0, imagecolorallocate($im, 0, 0, 0)); $w = imagecolorallocate($im, 255, 255, 255);
        ThaiText::draw($im, 60, 20, 120, $w, $font, $t); $n = 0;
        for ($y = 0; $y < 200; $y++) { for ($x = 0; $x < 300; $x++) { if ((imagecolorat($im, $x, $y) & 0xFF) > 128) { $n++; } } }
        imagedestroy($im); return $n;
    };
    ok_l21($ink('ที่') - $ink('ที') >= 60, 'The tone mark over a vowel is drawn (ink of ที่ minus ที: ' . ($ink('ที่') - $ink('ที')) . ' px)');
    ok_l21($ink('อยู่') - $ink('อยู') >= 60 && $ink('ต่ำ') - $ink('ตำ') >= 60 && $ink('ป่า') - $ink('ปา') >= 60, 'The marks of อยู่, ต่ำ and ป่า are drawn too');
    ok_l21(ThaiText::width($font, 40, 'ที่') === ThaiText::width($font, 40, 'ที'), 'A mark adds no width');

    // ---- the card ----
    $fd = $root . '/public/assets/fonts'; $logo = $root . '/public/assets/brand/modinpect-lockup-dark-480.png';
    $base = ['name' => 'NVMe M.2 SSD 512GB', 'price' => 9200.0, 'label' => 'สูงกว่าช่วงที่พบมาก', 'tone' => 'bad', 'range' => [1970.0, 2240.0], 'median' => 2190.0];
    $png = ShareCard::render($base, $fd, $logo); $info = getimagesizefromstring($png);
    ok_l21($info !== false && $info[0] === 1200 && $info[1] === 630 && $info['mime'] === 'image/png', 'The card is a 1200 x 630 PNG');
    ok_l21($png === ShareCard::render($base, $fd, $logo), 'The same result draws the same picture (it can be cached)');
    ok_l21($png !== ShareCard::render(array_merge($base, ['tone' => 'ok']), $fd, $logo) && $png !== ShareCard::render(array_merge($base, ['range' => null, 'median' => null]), $fd, $logo) && $png !== ShareCard::render(array_merge($base, ['price' => 9300.0]), $fd, $logo), 'Tone, range and price each change the picture');
    $long = ShareCard::render(array_merge($base, ['name' => str_repeat('Corsair Vengeance LPX 32GB DDR4 ', 8)]), $fd, $logo);
    ok_l21(getimagesizefromstring($long) !== false, 'A very long model name still draws (wrapped, shrunk, cut)');
    try { ShareCard::render($base, $root . '/nowhere', $logo); ok_l21(false, 'a missing font must stop the render'); } catch (RuntimeException $e) { ok_l21($e->getMessage() === 'SHARE_CARD_FONT_MISSING', 'A missing font stops the render with a clear code'); }
    ok_l21(ShareCard::available($fd) && !ShareCard::available($root . '/nowhere'), 'available() needs GD, FreeType and both font files');
    echo "ls21_assets_card_test: drawing checks passed\n";
}

// ---- wiring, privacy, honesty ----
$ctl = $read('app/Controllers/DealCheckerController.php');
$card = substr($ctl, (int)strpos($ctl, 'public function card('), (int)strpos($ctl, 'public function result(') - (int)strpos($ctl, 'public function card('));
ok_l21($card !== '' && !preg_match('/condition_level|warranty|has_receipt|has_box|has_benchmark|user_agent|REMOTE_ADDR|email|phone/i', $card), 'The card is drawn from the model, price, verdict, range and median only: nothing about the visitor or what they entered about the item');
ok_l21(str_contains($card, "'deal-card-'.\$uuid,86400") && str_contains($card, "{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}") && str_contains($card, 'ShareCard::available(') && str_contains($card, "http_response_code(404)"), 'Drawn once a day per result, the address must be a UUID, 404 when unavailable or unknown');
ok_l21(str_contains($read('public/index.php'), "\$router->get('/deal-checker/result/{uuid}/card',[DealCheckerController::class,'card']);"), 'The route exists');
ok_l21(str_contains($ctl, '$cardOk=\App\Services\Deal\ShareCard::available(') && str_contains($ctl, "'ogWidth'=>\$cardOk?"), 'The result page offers the picture only when it can be drawn');
$view = $read('app/Views/deal/result.php');
ok_l21(str_contains($view, 'if (!empty($cardUrl))') && str_contains($view, 'บันทึกเป็นภาพ') && str_contains($view, 'download="modinpect-deal.png"'), 'The result page has a save button, only when the picture is available');
ok_l21(str_contains($view, "\$deal['result_label'] !== 'insufficient'") && str_contains($ctl, "\$deal['result_label']!=='insufficient'&&\$q1>0&&\$q3>0"), 'An insufficient-data result shows no range, on the page and on the card (it would contradict the verdict)');
ok_l21(str_contains($read('.github/workflows/ci-tests.yml'), 'extensions: pdo_mysql, mbstring, curl, gd') && str_contains($read('docs/GO_LIVE_CHECKLIST_TH.md'), 'FreeType'), 'CI has GD and the go-live checklist names the host requirement');
echo "ls21_assets_card_test: PASS\n";
