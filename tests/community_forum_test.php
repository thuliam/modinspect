<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Community\ContentGuard as G;
use App\Services\Community\Forum;

/**
 * LS-24 M9: the community forum. Names and contact details never get in, one member cannot vote or flag their own content, three flags hide an item and a person
 * decides, a review summary appears only from five reviews, and nothing here can move a price. Test database inside a transaction, rolled back.
 */
function ok_cf2(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

// ---- ContentGuard ----
$bad = ['PHONE' => 'โทร 081-234-5678 ได้เลย', 'EMAIL' => 'ส่งมาที่ a.b@example.com นะ', 'LINE_ID' => 'ไลน์: seller_99 ทักมา', 'LINK' => 'ดูที่ https://example.com/x หรือ www.shop.co.th', 'ACCOUNT_NUMBER' => 'โอนมาที่ 123-4-56789-0',
    'ACCUSATION' => 'คนขายคนนี้โกงเงินผม เป็นมิจฉาชีพ', 'SPAM' => 'ดีมากกกกกกกกกกกกกกกกกกกกก'];
foreach ($bad as $code => $text) { ok_cf2(in_array($code, G::check($text . ' ครับผม'), true), "$code is refused: " . json_encode(G::check($text . ' ครับผม'))); }
ok_cf2(in_array('ACCUSATION', G::check('he is a scammer, blacklist him please'), true) && in_array('ACCUSATION', G::check('ขึ้นบัญชีดำด้วย ไม่ส่งของ'), true), 'Branding words are refused in Thai and English');
ok_cf2(G::check('การ์ดใช้ได้ 2 วันแล้วจอดับ ผู้ขายไม่ตอบข้อความหลังจากนั้น ต้องซ่อมเอง') === [], 'Telling what happened to the writer is allowed');
ok_cf2(G::check('RTX 3070 ซื้อมา 8500 ใช้เล่นเกม 1440p ลื่นดี') === [] && G::check('ประกัน 6 เดือน ซื้อ 15 มกราคม') === [], 'Prices, dates and model numbers are not taken for an account number or a phone');
ok_cf2(G::check('') === ['EMPTY'] && in_array('TOO_SHORT', G::check('ดี'), true) && in_array('TOO_LONG', G::check(str_repeat('ก', 1600)), true), 'Empty, short and long texts are refused');
ok_cf2(count(G::messages(array_keys(G::MESSAGES))) === count(G::MESSAGES), 'Every code has a Thai message');

// ---- the forum on the test database ----
global $config;
$db = Database::connection($config['db']);
ok_cf2($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$prices = static fn(): string => md5(json_encode([$db->query('SELECT COUNT(*) FROM price_indices')->fetchColumn(), $db->query('SELECT COUNT(*) FROM price_observations')->fetchColumn(), $db->query("SELECT COUNT(*) FROM price_observations WHERE verified_status = 'approved'")->fetchColumn(), $db->query('SELECT SUM(median) FROM price_indices')->fetchColumn()]));
$db->beginTransaction();
try {
    $before = $prices();
    $mk = static function (string $name) use ($db): int { $db->prepare("INSERT INTO members (display_name, email, terms_accepted_at) VALUES (:n, :e, NOW())")->execute(['n' => $name, 'e' => bin2hex(random_bytes(4)) . '@example.test']); return (int)$db->lastInsertId(); };
    [$a, $b, $c, $d, $e] = [$mk('Somchai Jaidee'), $mk('B'), $mk('C'), $mk('D'), $mk('E')];
    $pid = (int)$db->query("SELECT id FROM products WHERE is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
    $f = new Forum($db); $now = new DateTimeImmutable('2026-11-20 10:00:00');

    ok_cf2(!str_contains(Forum::handle($a), 'Somchai') && preg_match('/^สมาชิก#[0-9a-f]{4}$/u', Forum::handle($a)) === 1 && Forum::handle($a) === Forum::handle($a) && Forum::handle($a) !== Forum::handle($b), 'The public name is a pseudonym, stable, and not the sign-in name');

    // threads
    $t = $f->createThread($a, $pid, 'ถามเรื่องการ์ดจอมือสองครับ', 'ใครเคยซื้อรุ่นนี้มือสองบ้าง ใช้งานเป็นยังไง ร้อนไหม', $now);
    ok_cf2($t['ok'] === true, 'A clean thread is created');
    $r = $f->createThread($a, $pid, 'ถามเรื่องการ์ดจอ', 'ติดต่อ 081-234-5678 ได้เลย ' . 'x', $now);
    ok_cf2($r['ok'] === false && in_array('PHONE', $r['errors'], true), 'A thread with a phone number is refused');
    ok_cf2($f->createThread($a, $pid, 'ถามเรื่องการ์ดจอมือสองครับ', 'ใครเคยซื้อรุ่นนี้มือสองบ้าง ใช้งานเป็นยังไง ร้อนไหม', $now->modify('+1 hour'))['errors'] === ['DUPLICATE'], 'The same thread again within a day is refused');
    ok_cf2($f->createThread($a, 999999999, 'หัวข้อทดสอบ ครับผม', 'เนื้อหาทดสอบครับผมยาวพอสมควร', $now)['errors'] === ['BAD_PRODUCT'], 'An unknown model is refused');
    for ($i = 2; $i <= 6; $i++) { ok_cf2($f->createThread($b, null, "หัวข้อทดสอบที่ $i ครับ", "เนื้อหาทดสอบที่ $i ของสมาชิกบี ยาวพอสมควร", $now)['ok'], "thread $i"); }
    ok_cf2($f->createThread($b, null, 'หัวข้อทดสอบที่ 7 ครับ', 'เนื้อหาทดสอบที่ 7 ของสมาชิกบี ยาวพอสมควร', $now)['errors'] === ['RATE_LIMIT'], 'The sixth thread of the day is refused');
    ok_cf2($f->createThread($b, null, 'หัวข้อวันถัดไป ครับ', 'เนื้อหาของวันถัดไป ยาวพอสมควรครับ', $now->modify('+25 hours'))['ok'], 'The limit resets after a day');

    // posts
    $p1 = $f->createPost($b, $t['id'], 'ผมใช้รุ่นนี้มาปีกว่า ไม่มีปัญหา อุณหภูมิไม่เกิน 70 องศา', null, $now);
    ok_cf2($p1['ok'], 'A reply is created');
    $p2 = $f->createPost($c, $t['id'], 'ขอบคุณครับ แล้วเสียงพัดลมดังไหม', $p1['id'], $now);
    ok_cf2($p2['ok'], 'A reply to a reply is created');
    ok_cf2($f->createPost($c, $t['id'], 'ตอบซ้อนสองชั้นไม่ได้', $p2['id'], $now)['errors'] === ['BAD_PARENT'], 'Replies nest one level only');
    ok_cf2($f->createPost($c, 999999999, 'ไม่มีกระทู้นี้ครับ', null, $now)['errors'] === ['NOT_FOUND'], 'An unknown thread is refused');
    ok_cf2($f->createPost($c, $t['id'], 'ดูที่ https://example.com ครับ', null, $now)['ok'] === false, 'A link in a reply is refused');
    $view = $f->thread($t['id']);
    ok_cf2($view['thread']['post_count'] === 2 && count($view['posts']) === 2 && $view['posts'][0]['handle'] !== '' && !str_contains(json_encode($view, JSON_UNESCAPED_UNICODE), 'Somchai'), 'The thread shows two replies and only pseudonyms');
    ok_cf2($f->createPost($e, $t['id'], 'เนื้อหาซ้ำซ้ำ ครับ ผม', null, $now)['ok'] && $f->createPost($e, $t['id'], 'เนื้อหาซ้ำซ้ำ ครับ ผม', null, $now)['errors'] === ['DUPLICATE'], 'The same reply twice is refused');

    // lock
    $adm = (int)$db->query("SELECT id FROM users LIMIT 1")->fetchColumn();
    if ($adm === 0) { $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES ('m', :e, 'x', 'admin')")->execute(['e' => 'fm-' . bin2hex(random_bytes(3)) . '@example.test']); $adm = (int)$db->lastInsertId(); }
    ok_cf2($f->moderate($adm, 'thread', $t['id'], 'lock')['ok'] && $f->createPost($d, $t['id'], 'ตอบหลังล็อกไม่ได้ครับ', null, $now)['errors'] === ['LOCKED'], 'A locked thread takes no new reply');
    ok_cf2($f->moderate($adm, 'thread', $t['id'], 'unlock')['ok'] && $f->createPost($d, $t['id'], 'ตอบหลังปลดล็อกได้ครับ', null, $now)['ok'], 'Unlocking reopens it');

    // votes
    $v1 = $f->vote($c, 'post', $p1['id']); $v2 = $f->vote($c, 'post', $p1['id']);
    ok_cf2($v1 === ['ok' => true, 'voted' => true] && $v2 === ['ok' => true, 'voted' => false], 'A vote toggles');
    ok_cf2($f->vote($b, 'post', $p1['id'])['error'] === 'OWN_CONTENT' && $f->vote($c, 'post', 999999999)['error'] === 'NOT_FOUND', 'No vote on own content or on nothing');
    $f->vote($c, 'post', $p1['id']); $f->vote($d, 'post', $p1['id']);
    ok_cf2((int)$db->query("SELECT helpful_count FROM community_posts WHERE id = {$p1['id']}")->fetchColumn() === 2, 'The helpful count follows the votes');

    // flags: three different members hide it, a person decides
    ok_cf2($f->flag($b, 'post', $p1['id'], 'spam')['error'] === 'OWN_CONTENT' && $f->flag($c, 'post', $p1['id'], 'nope')['error'] === 'BAD_REASON', 'No flag on own content, and the reason must be known');
    ok_cf2($f->flag($c, 'post', $p1['id'], 'pii')['hidden'] === false && $f->flag($c, 'post', $p1['id'], 'pii')['error'] === 'ALREADY_FLAGGED', 'One flag per member per item');
    ok_cf2($f->flag($d, 'post', $p1['id'], 'abuse')['hidden'] === false && $f->flag($e, 'post', $p1['id'], 'accusation')['hidden'] === true, 'The third member hides it');
    ok_cf2(count($f->thread($t['id'])['posts']) === 3 && !in_array($p1['id'], array_column($f->thread($t['id'])['posts'], 'id'), true), 'A hidden reply is gone from the thread');
    $q = $f->queue();
    ok_cf2(count($q) === 1 && $q[0]['target_type'] === 'post' && (int)$q[0]['flags'] === 3 && $q[0]['status'] === 'hidden' && $q[0]['preview'] !== '' && str_contains((string)$q[0]['reasons'], 'pii'), 'The queue shows the item, its flags, reasons and text');
    ok_cf2($f->moderate($adm, 'post', $p1['id'], 'dismiss')['ok'] && count($f->thread($t['id'])['posts']) === 4 && $f->queue() === [], 'Dismissing shows it again and closes the flags');
    $f->flag($c, 'post', $p1['id'], 'spam'); $f->flag($d, 'post', $p1['id'], 'spam'); $f->flag($e, 'post', $p1['id'], 'spam');
    ok_cf2($f->moderate($adm, 'post', $p1['id'], 'uphold')['ok'] && (string)$db->query("SELECT hidden_reason FROM community_posts WHERE id = {$p1['id']}")->fetchColumn() === 'moderator' && $f->queue() === [], 'Upholding keeps it hidden under the moderator reason');
    ok_cf2((int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE user_id = $adm AND action IN ('community_dismiss','community_uphold','community_lock','community_unlock')")->fetchColumn() === 4, 'Every moderator decision is audited');
    ok_cf2($f->moderate($adm, 'post', $p1['id'], 'lock')['error'] === 'BAD_ACTION' && $f->moderate($adm, 'post', $p1['id'], 'zap')['error'] === 'BAD_ACTION', 'Only threads lock, and unknown actions are refused');

    // reviews
    $rv = ['price_paid' => '8,500', 'source_kind' => 'marketplace', 'condition_received' => 'good', 'issue_30d' => 'none', 'would_buy_again' => '1', 'note' => 'ของตรงตามประกาศ'];
    $r1 = $f->createReview($a, $pid, $rv, $now);
    ok_cf2($r1['ok'], 'A review is created');
    ok_cf2($f->createReview($a, $pid, $rv, $now)['errors'] === ['ALREADY_REVIEWED'], 'One review per member per model');
    foreach ([['price_paid' => '0'], ['price_paid' => 'abc'], ['source_kind' => 'x'], ['condition_received' => 'x'], ['issue_30d' => 'x'], ['would_buy_again' => '2'], ['note' => 'โทร 0812345678 ได้']] as $k => $bad1) {
        ok_cf2($f->createReview($mk("R$k"), $pid, array_merge($rv, $bad1), $now)['ok'] === false, 'A bad review field is refused: ' . json_encode($bad1));
    }
    $s0 = $f->reviewSummary($pid);
    ok_cf2($s0['count'] === 1 && $s0['shown'] === false && $s0['no_issue_pct'] === null, 'One review: the summary is not shown');
    foreach ([[$b, 'none', '1'], [$c, 'none', '1'], [$d, 'minor', '0'], [$e, 'major', '0']] as [$m, $issue, $again]) { ok_cf2($f->createReview($m, $pid, array_merge($rv, ['issue_30d' => $issue, 'would_buy_again' => $again]), $now)['ok'], 'review'); }
    $s = $f->reviewSummary($pid);
    ok_cf2($s['count'] === 5 && $s['shown'] === true && $s['no_issue_pct'] === 60 && $s['again_pct'] === 60, 'Five reviews: the summary is shown with its count: ' . json_encode($s));
    $f->flag($b, 'review', $r1['id'], 'wrong'); $f->flag($c, 'review', $r1['id'], 'wrong'); $f->flag($d, 'review', $r1['id'], 'wrong');
    ok_cf2($f->reviewSummary($pid)['count'] === 4 && $f->reviewSummary($pid)['shown'] === false, 'A hidden review leaves the summary, which drops back below five');
    ok_cf2(!str_contains(json_encode($f->reviews($pid), JSON_UNESCAPED_UNICODE), 'Somchai') && array_sum(array_map(static fn(array $r): int => (int)$r['price_paid'], $f->reviews($pid))) > 0, 'Reviews show pseudonyms and the price the reviewer says they paid');

    // nothing here moved a price; deleting a member deletes the content
    ok_cf2($prices() === $before, 'SIGNAL ONLY: no snapshot, observation, approval or median changed');
    $mine = (int)$db->query("SELECT COUNT(*) FROM community_threads WHERE member_id = $a")->fetchColumn() + (int)$db->query("SELECT COUNT(*) FROM community_reviews WHERE member_id = $a")->fetchColumn();
    ok_cf2($mine >= 2, 'Member A has content');
    $db->exec("DELETE FROM members WHERE id = $a");
    ok_cf2((int)$db->query("SELECT COUNT(*) FROM community_threads WHERE member_id = $a")->fetchColumn() === 0 && (int)$db->query("SELECT COUNT(*) FROM community_reviews WHERE member_id = $a")->fetchColumn() === 0, 'Deleting the member deletes their threads and reviews');
    ok_cf2($f->counts()['open_flags'] >= 0, 'Counts work');
} finally {
    $db->rollBack();
}
$src = (string)file_get_contents(ROOT_PATH . '/app/Services/Community/Forum.php');
ok_cf2(!preg_match('/price_indices|price_observations|new_price_references|\bUPDATE products\b/', $src), 'The forum never touches a price table');
echo "community_forum_test: PASS\n";
