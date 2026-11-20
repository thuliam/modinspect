<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Auth\AuthService;
use App\Services\Listing\SellerCheck as C;

/**
 * LS-14 Module 8 (OD-3, ADR-007). The number is never stored (only an HMAC and the last 4 digits), a report counts publicly only after a person
 * verified it, a dispute takes it out of the count, a moderated report cannot be moderated twice, and without a key the service refuses to run.
 * Rolled-back test DB.
 */
function ok_c(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

ok_c(C::normalize('phone', '081-234-5678') === '0812345678' && C::normalize('phone', '+66 81 234 5678') === '0812345678' && C::normalize('phone', '02 123 4567') === '021234567', 'Phone numbers are normalised to the 0xxxxxxxxx form');
ok_c(C::normalize('phone', '12345') === null && C::normalize('phone', '1812345678') === null && C::normalize('phone', 'abc') === null, 'Implausible phone numbers are refused');
ok_c(C::normalize('bank_account', '123-4-56789-0') === '1234567890' && C::normalize('bank_account', '1234567') === null && C::normalize('bank_account', str_repeat('9', 16)) === null, 'Bank accounts are 8 to 15 digits');
ok_c(C::normalize('email', 'x') === null, 'Unknown identifier types are refused');

global $config;
$db = Database::connection($config['db']);
ok_c($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$key = str_repeat('k', 40);
$svc = new C($db, $key);
try { (new C($db, 'short'))->hash('phone', '0812345678'); ok_c(false, 'a short key must refuse'); } catch (RuntimeException $e) { ok_c($e->getMessage() === 'SELLER_CHECK_KEY_MISSING', 'Without a proper key the service refuses to run'); }
ok_c($svc->hash('phone', '0812345678') === hash_hmac('sha256', 'phone|0812345678', $key) && $svc->hash('phone', '0812345678') !== $svc->hash('bank_account', '0812345678'), 'The hash is an HMAC and the type is part of it');
ok_c($svc->hash('phone', '0812345678') !== (new C($db, str_repeat('z', 40)))->hash('phone', '0812345678'), 'A different key gives a different hash');

$db->beginTransaction();
try {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $db->exec("INSERT INTO members (display_name, terms_accepted_at) VALUES ('R', NOW())"); $m = (int)$db->lastInsertId();
    $admin = (int)(new AuthService())->createUser('sc-' . bin2hex(random_bytes(4)) . '@example.test', 'A', 'CorrectHorseBattery99', 'admin')['user_id'];

    ok_c($svc->report($m, 'phone', '123', 'no_delivery', '')['error'] === 'BAD_IDENTIFIER', 'A bad number is refused');
    ok_c($svc->report($m, 'phone', '0812345678', 'scam', '')['error'] === 'BAD_PROBLEM', 'The problem must be from the list');
    foreach (['โทรหาผม 0899999999', 'ดู https://x.example', 'ไลน์ @seller_x', 'บัญชี 1234567890123'] as $bad) { ok_c($svc->report($m, 'phone', '0812345678', 'other', $bad)['error'] === 'NOTE_HAS_CONTACT', "A note with contact details or a number is refused: $bad"); }

    $r = $svc->report($m, 'phone', '081-234-5678', 'no_delivery', 'โอนแล้ว 3 วันไม่ส่งของ');
    ok_c($r['ok'], 'A report is accepted');
    // the number is nowhere in the database
    $row = $db->query("SELECT * FROM seller_reports WHERE uuid = '{$r['uuid']}'")->fetch(PDO::FETCH_ASSOC);
    ok_c($row['identifier_last4'] === '5678' && $row['identifier_hash'] === $svc->hash('phone', '0812345678') && !str_contains(json_encode($row), '0812345678') && !str_contains(json_encode($row), '081-234-5678'), 'Only the HMAC and the last 4 digits are stored, never the number');
    ok_c($row['status'] === 'pending', 'A new report is pending');

    // pending reports are invisible to the public
    $l = $svc->lookup('phone', '0812345678');
    ok_c($l['ok'] && $l['count'] === 0, 'A pending report is not shown');
    ok_c($svc->moderate($admin, (int)$row['id'], 'rejected')['error'] === 'NOTE_REQUIRED', 'A rejection needs a reason');
    ok_c($svc->moderate($admin, (int)$row['id'], 'verified')['ok'], 'A person verifies it');
    ok_c((int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE user_id = $admin AND action = 'seller_report_verified'")->fetchColumn() === 1, 'Verification is audited with the admin');
    ok_c($svc->moderate($admin, (int)$row['id'], 'rejected', 'x')['error'] === 'BAD_STATE', 'A decided report cannot be decided again');
    $svc->report($m, 'phone', '0812345678', 'no_delivery', ''); $second = (int)$db->lastInsertId();
    $svc->moderate($admin, (int)$db->query("SELECT MAX(id) FROM seller_reports")->fetchColumn(), 'verified');
    $svc->report($m, 'phone', '0812345678', 'fake_item', ''); $svc->moderate($admin, (int)$db->query("SELECT MAX(id) FROM seller_reports")->fetchColumn(), 'rejected', 'หลักฐานไม่พอ');
    $l = $svc->lookup('phone', '+66812345678');
    ok_c($l['count'] === 2 && $l['by_type'] === ['no_delivery' => 2], 'The public count is verified reports only, grouped by type, whatever way the number is written: ' . json_encode($l));
    ok_c($svc->lookup('bank_account', '0812345678')['count'] === 0 && $svc->lookup('phone', '0899999999')['count'] === 0, 'The same digits as an account, and another number, find nothing');
    ok_c(!isset($l['reporter']) && count($l) === 3, 'The lookup returns a count and types and nothing else');

    // dispute: the reports leave the public count
    ok_c($svc->dispute('phone', '0812345678', 'สั้น', 'a@example.com')['error'] === 'EXPLANATION_REQUIRED' && $svc->dispute('phone', '0812345678', 'ไม่ใช่เบอร์ของผู้ที่ถูกรายงานจริง ๆ', 'not-an-email')['error'] === 'BAD_EMAIL', 'A dispute needs an explanation and a real email');
    ok_c($svc->dispute('phone', '0812345678', 'ไม่ใช่เบอร์ของผู้ที่ถูกรายงานจริง ๆ', 'Owner@Example.com')['ok'], 'A dispute is accepted');
    ok_c($svc->lookup('phone', '0812345678')['count'] === 0, 'A disputed report is not counted while the dispute is open');
    $open = $svc->openDisputes();
    ok_c(count($open) >= 1 && $open[0]['contact_email'] === 'owner@example.com' && $open[0]['last4'] === '5678', 'The admin sees the dispute with the contact email');
    $disputed = (int)$db->query("SELECT id FROM seller_reports WHERE status = 'disputed' ORDER BY id LIMIT 1")->fetchColumn();
    ok_c($svc->moderate($admin, $disputed, 'verified', 'ตรวจหลักฐานซ้ำแล้ว')['ok'] && $svc->lookup('phone', '0812345678')['count'] === 1, 'After review a disputed report can be verified again and counts again');
    ok_c($svc->closeDispute($admin, (int)$open[0]['id']) && !$svc->closeDispute($admin, (int)$open[0]['id']), 'A dispute is closed once, with an audit entry');
    $q = $svc->queue();
    ok_c(isset($q[0]['same_identifier_reports']) && !array_key_exists('identifier_hash', $q[0]), 'The admin queue shows how many other reports share the number but never the hash');
} finally {
    $db->rollBack();
}
// LS-25: dispute age against the (draft) response window
$nowD = new DateTimeImmutable('2026-11-20 12:00:00');
ok_c(C::dueInfo('2026-11-10 12:00:00', $nowD) === ['age_days' => 10, 'overdue' => false] && C::dueInfo('2026-11-05 11:00:00', $nowD)['overdue'] === true && C::dueInfo('2026-11-06 12:00:00', $nowD)['overdue'] === false, 'Overdue only after more than the window (14 days)');
ok_c(C::dueInfo('garbage', $nowD) === ['age_days' => 0, 'overdue' => false] && C::dueInfo('2026-12-01 00:00:00', $nowD)['age_days'] === 0, 'An unreadable or future date is never overdue');
echo "seller_check_test: PASS\n";
