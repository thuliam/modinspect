<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Auth\AuthService;
use App\Services\Listing\ListingAttributes as A;
use App\Services\Listing\ListingPhotoStore;
use App\Services\Listing\SellerListings;

/**
 * LS-14 Module 9 (OD-5): the shared listing facts, seller listings and their photos. Field rules (no phone numbers or links in free text, the
 * condition is the seller's own 0-100 estimate), ownership on every write, an edit goes back to draft, moderation is audited and creates no
 * price, at most 10 photos, real image types only, EXIF (GPS) removed. DB rolled back, photos written to a temp folder that is removed.
 */
function ok_s(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

// ---- attributes ----
$r = A::parse(['condition_percent' => '85', 'under_warranty' => 'ใช่', 'warranty_months' => '7', 'product_age_months' => '14', 'province' => 'นนทบุรี',
    'seller_warranty_terms' => 'รับประกัน 7 วันหลังรับของ', 'is_defective' => '0', 'has_repair_history' => 'no', 'shipping_option' => 'paid', 'shipping_cost' => '60',
    'has_test_result' => '1', 'test_result_note' => 'รัน 3DMark ผ่าน อุณหภูมิ 72 องศา', 'defect_codes' => ['scratch', 'fan_noise'], 'defect_note' => 'รอยที่ฝาข้าง']);
ok_s($r['errors'] === [] && $r['values']['condition_percent'] === 85 && $r['values']['under_warranty'] === 1 && $r['values']['shipping_cost'] === 60.0 && $r['values']['defect_codes'] === 'scratch,fan_noise', 'A full valid listing parses: ' . json_encode($r, JSON_UNESCAPED_UNICODE));
ok_s(A::parse([])['errors'] === [] && array_filter(A::parse([])['values'], static fn($v) => $v !== null) === [], 'Every field is optional; empty stays null');
$bad = A::parse(['condition_percent' => '101', 'province' => 'Atlantis', 'shipping_option' => 'drone', 'defect_codes' => 'scratch,evil', 'under_warranty' => 'maybe']);
ok_s(isset($bad['errors']['condition_percent'], $bad['errors']['province'], $bad['errors']['shipping_option'], $bad['errors']['defect_codes'], $bad['errors']['under_warranty']), 'Out-of-range, unknown and malformed values are rejected');
foreach (['โทร 081-234-5678', 'line.me/ti/p/abc', 'ดูที่ https://example.com', 'แอด @seller_shop'] as $contact) {
    ok_s(isset(A::parse(['defect_note' => $contact])['errors']['defect_note']), "Contact details are refused in free text: $contact");
}
ok_s(isset(A::parse(['shipping_option' => 'paid'])['errors']['shipping_cost']), 'Paid shipping needs an amount');
ok_s(A::parse(['shipping_option' => 'free', 'shipping_cost' => '99'])['values']['shipping_cost'] === 0.0, 'Free shipping costs 0 whatever was typed');
ok_s(A::parse(['under_warranty' => '0', 'warranty_months' => '5'])['values']['warranty_months'] === null, 'No warranty means no months left');
ok_s(count(A::PROVINCES) === 77, 'All 77 provinces are listed');
$lines = array_column(A::describe($r['values']), 'value', 'label');
ok_s(($lines['สภาพ (ผู้ขายประเมินเอง)'] ?? '') === '85%' && str_contains($lines['การส่ง'] ?? '', '฿60') && str_contains($lines['ตำหนิ'] ?? '', 'รอยขีดข่วน'), 'describe() gives Thai lines and says the condition is the seller\'s own estimate');

// ---- listings and photos ----
global $config;
$db = Database::connection($config['db']);
ok_s($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$tmpDir = sys_get_temp_dir() . '/mi-listing-photos-' . bin2hex(random_bytes(4));
$store = new ListingPhotoStore($tmpDir);
$db->beginTransaction();
try {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $mk = static function () use ($db): int { $db->exec("INSERT INTO members (display_name, terms_accepted_at) VALUES ('T', NOW())"); return (int)$db->lastInsertId(); };
    $alice = $mk(); $bob = $mk();
    $pid = (int)$db->query('SELECT id FROM products WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
    $svc = new SellerListings($db, $store);
    $evidence = static fn(): array => [(int)$db->query('SELECT COUNT(*) FROM price_observations')->fetchColumn(), (int)$db->query('SELECT COUNT(*) FROM price_indices')->fetchColumn()];
    $before = $evidence();

    ok_s(isset($svc->save($alice, null, $pid, '0', [])['errors']['asking_price']), 'A zero price is refused');
    ok_s(isset($svc->save($alice, null, 999999999, '5000', [])['errors']['product_id']), 'An unknown product is refused');
    $s1 = $svc->save($alice, null, $pid, '7,500', ['condition_percent' => '90', 'province' => 'ขอนแก่น']);
    ok_s($s1['ok'] && preg_match('/^[a-f0-9]{32}$/', $s1['uuid']) === 1, 'A listing is created as a draft with a random id');
    $l = $svc->find($s1['uuid']);
    ok_s($l['status'] === 'draft' && (float)$l['asking_price'] === 7500.0 && (int)$l['condition_percent'] === 90 && $l['province'] === 'ขอนแก่น', 'Stored values match');

    // ownership
    ok_s(!$svc->save($bob, $s1['uuid'], $pid, '1', [])['ok'] && $svc->act($bob, $s1['uuid'], 'delete')['error'] === 'NOT_FOUND', 'Another member cannot edit or delete it');
    ok_s($svc->owned($s1['uuid'], $bob) === null && count($svc->forMember($bob)) === 0 && count($svc->forMember($alice)) === 1, 'Lists are per member');

    // submit, moderate, edit goes back to draft
    ok_s($svc->act($alice, $s1['uuid'], 'submit')['ok'] && $svc->find($s1['uuid'])['status'] === 'submitted', 'The member submits it');
    ok_s($svc->act($alice, $s1['uuid'], 'submit')['error'] === 'BAD_STATE', 'It cannot be submitted twice');
    $admin = (new AuthService())->createUser('sl-' . bin2hex(random_bytes(4)) . '@example.test', 'SL', 'CorrectHorseBattery99', 'admin');
    $aid = (int)$admin['user_id'];
    ok_s($svc->moderate($aid, (int)$l['id'], 'rejected')['error'] === 'NOTE_REQUIRED', 'A rejection needs a reason');
    ok_s($svc->moderate($aid, (int)$l['id'], 'approved')['ok'] && $svc->find($s1['uuid'])['status'] === 'approved', 'An admin approves it');
    ok_s((int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE user_id = $aid AND action = 'seller_listing_approved'")->fetchColumn() === 1, 'Moderation is audited with the admin');
    ok_s($svc->moderate($aid, (int)$l['id'], 'approved')['error'] === 'BAD_STATE', 'Only a submitted listing can be moderated');
    ok_s($evidence() === $before, 'Approval creates no observation and changes no price (HG-17)');
    ok_s($svc->publicListing($s1['uuid'], false) === null && $svc->publicListing($s1['uuid'], true) !== null, 'An approved listing is public only when the owner turns public pages on');
    $svc->save($alice, $s1['uuid'], $pid, '7,200', ['condition_percent' => '90']);
    ok_s($svc->find($s1['uuid'])['status'] === 'draft' && $svc->publicListing($s1['uuid'], true) === null, 'Editing an approved listing sends it back to draft (and off the public page)');

    // photos: a JPEG with an EXIF GPS block, a fake image, the 11th photo
    $img = imagecreatetruecolor(2400, 1200); imagefill($img, 0, 0, imagecolorallocate($img, 30, 120, 200));
    ob_start(); imagejpeg($img, null, 90); $jpeg = (string)ob_get_clean(); imagedestroy($img);
    $exif = "Exif\0\0" . "MM\0*\0\0\0\x08" . str_repeat("GPSLatitude13.7563N", 4);
    $jpegWithExif = substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2);
    $tmp = static function (string $bytes): array { $f = tempnam(sys_get_temp_dir(), 'mip'); file_put_contents($f, $bytes); return ['tmp_name' => $f, 'error' => UPLOAD_ERR_OK, 'size' => strlen($bytes)]; };
    ok_s(str_contains($jpegWithExif, 'GPSLatitude') && getimagesize($tmp($jpegWithExif)['tmp_name']) !== false, 'The fixture is a valid JPEG that carries a GPS text block');
    ok_s($svc->addPhoto($alice, $s1['uuid'], $tmp($jpegWithExif), false)['ok'], 'A JPEG photo is accepted');
    $ph = $svc->photos((int)$l['id']);
    $path = $store->path($s1['uuid'], $ph[0]['file_name']);
    $saved = (string)file_get_contents((string)$path);
    ok_s($path !== null && !str_contains($saved, 'GPSLatitude') && !str_contains($saved, 'Exif') && (int)$ph[0]['width'] === 1600 && (int)$ph[0]['height'] === 800, 'The stored photo has no EXIF/GPS and is scaled to 1600 px');
    ok_s($svc->addPhoto($alice, $s1['uuid'], $tmp("<?php echo 'x'; ?>" . str_repeat('A', 2000)), false)['error'] === 'NOT_AN_IMAGE', 'A PHP file is not an image whatever its name');
    ok_s($svc->addPhoto($alice, $s1['uuid'], $tmp(str_repeat('A', ListingPhotoStore::MAX_BYTES + 1)), false)['error'] === 'TOO_LARGE', 'More than 5 MB is refused');
    ok_s($svc->addPhoto($bob, $s1['uuid'], $tmp($jpeg), false)['error'] === 'NOT_FOUND', 'Another member cannot add photos');
    for ($i = 1; $i < 10; $i++) { $svc->addPhoto($alice, $s1['uuid'], $tmp($jpeg), false); }
    ok_s(count($svc->photos((int)$l['id'])) === 10 && $svc->addPhoto($alice, $s1['uuid'], $tmp($jpeg), false)['error'] === 'TOO_MANY', 'The 11th photo is refused');
    ok_s($store->path($s1['uuid'], '../../.env') === null && $store->path('../x', $ph[0]['file_name']) === null, 'Photo paths cannot leave the listing folder');
    ok_s($svc->removePhoto($alice, $s1['uuid'], (int)$ph[0]['id']) && $store->path($s1['uuid'], $ph[0]['file_name']) === null, 'Removing a photo removes the file');
    ok_s($svc->act($alice, $s1['uuid'], 'delete')['ok'] && $svc->find($s1['uuid']) === null && !is_dir($tmpDir . '/' . $s1['uuid']), 'Deleting the listing removes it and its photo folder');
} finally {
    $db->rollBack();
    foreach (glob($tmpDir . '/*/*') ?: [] as $f) { @unlink($f); }
    foreach (glob($tmpDir . '/*') ?: [] as $d) { @rmdir($d); }
    @rmdir($tmpDir);
}
echo "seller_listing_test: PASS\n";
