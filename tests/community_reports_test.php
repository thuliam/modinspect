<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Auth\AuthService;
use App\Services\Community\CommunityReports as C;

/**
 * LS-21 (R-11, ADR-006 option A). The invariants: a report is a signal (it changes no price, snapshot, confidence or approval); a person decides each one and it
 * is audited; an accepted sold-price report only becomes a PENDING candidate with price_type 'sold'; the reporter code rotates daily and is not the address;
 * abuse limits hold; nothing free-text is accepted. Rolled-back test DB.
 */
function ok_cr(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$now = new DateTimeImmutable('2026-11-19 12:00:00');
$exists = static fn(int $id): bool => in_array($id, [5, 7], true);
$v = static fn(string $t, array $in, int $cur = 1) => C::validate($t, $in, $cur, $exists, $now);

// ---- validation, per type ----
ok_cr($v('stale', [])['ok'] && $v('stale', [])['payload'] === [], 'stale needs nothing');
ok_cr($v('nonsense', [])['error'] === 'BAD_TYPE', 'An unknown type is refused');
ok_cr($v('wrong_product', ['should_be' => 'not_in_catalog'])['payload'] === ['should_be' => 'not_in_catalog'], 'wrong_product: "not in the catalog" is allowed');
ok_cr($v('wrong_product', ['should_be' => '5'])['payload'] === ['should_be_product_id' => 5] && $v('wrong_product', ['should_be' => '9'])['error'] === 'BAD_SHOULD_BE' && $v('wrong_product', ['should_be' => '5'], 5)['error'] === 'BAD_SHOULD_BE' && $v('wrong_product', ['should_be' => 'x'])['error'] === 'BAD_SHOULD_BE' && $v('wrong_product', [])['error'] === 'BAD_SHOULD_BE', 'wrong_product: only an existing, different catalog model or "not in the catalog"');
ok_cr($v('price_looks_off', ['direction' => 'higher'])['payload'] === ['direction' => 'higher'] && $v('price_looks_off', ['direction' => 'lower', 'price' => '7,900'])['payload'] === ['direction' => 'lower', 'seen_price' => 7900], 'price_looks_off: a direction, and an optional price (commas allowed)');
ok_cr($v('price_looks_off', ['direction' => 'sideways'])['error'] === 'BAD_DIRECTION' && $v('price_looks_off', ['direction' => 'higher', 'price' => 'abc'])['error'] === 'BAD_PRICE' && $v('price_looks_off', ['direction' => 'higher', 'price' => '0'])['error'] === 'BAD_PRICE' && $v('price_looks_off', ['direction' => 'higher', 'price' => '10000001'])['error'] === 'BAD_PRICE', 'price_looks_off: bad direction, text, zero and above the cap are refused');
$sold = ['price' => '7500', 'month' => '2026-10', 'condition' => 'good', 'warranty_months' => '6', 'has_box' => 'on'];
ok_cr($v('sold_price', $sold)['payload'] === ['price' => 7500, 'month' => '2026-10', 'condition' => 'good', 'warranty_months' => 6, 'has_box' => true, 'has_receipt' => false], 'sold_price: price, month, condition, warranty, flags');
ok_cr($v('sold_price', array_merge($sold, ['warranty_months' => '']))['payload']['warranty_months'] === null, 'A blank warranty is unknown, not zero');
ok_cr($v('sold_price', array_merge($sold, ['month' => '2026-11']))['ok'] && $v('sold_price', array_merge($sold, ['month' => '2025-11']))['ok'], 'This month and 12 months back are allowed');
ok_cr($v('sold_price', array_merge($sold, ['month' => '2026-12']))['error'] === 'BAD_MONTH' && $v('sold_price', array_merge($sold, ['month' => '2025-10']))['error'] === 'BAD_MONTH' && $v('sold_price', array_merge($sold, ['month' => '2026-13']))['error'] === 'BAD_MONTH' && $v('sold_price', array_merge($sold, ['month' => 'last week']))['error'] === 'BAD_MONTH', 'A sold month in the future, over a year old or malformed is refused');
ok_cr($v('sold_price', array_merge($sold, ['price' => '']))['error'] === 'BAD_PRICE' && $v('sold_price', array_merge($sold, ['condition' => 'new']))['error'] === 'BAD_CONDITION' && $v('sold_price', array_merge($sold, ['warranty_months' => '121']))['error'] === 'BAD_WARRANTY' && $v('sold_price', array_merge($sold, ['warranty_months' => 'x']))['error'] === 'BAD_WARRANTY', 'sold_price: the price is required, the condition is one of five, the warranty is 0 to 120');
ok_cr($v('spec_wrong', ['spec' => 'socket'])['payload'] === ['spec' => 'socket'] && $v('spec_wrong', ['spec' => 'colour'])['error'] === 'BAD_SPEC', 'spec_wrong: a spec from the list');
$hostile = $v('spec_wrong', ['spec' => 'socket', 'note' => 'call me 0812345678', 'email' => 'a@b.c', 'name' => 'X']);
ok_cr($hostile['payload'] === ['spec' => 'socket'], 'Any extra field (a note, a phone, an email, a name) is dropped: nothing personal can be stored');

// ---- the key and the anonymous code ----
try { new C(Database::connection($GLOBALS['config']['db']), 'short'); ok_cr(false, 'a short key must refuse'); } catch (RuntimeException $e) { ok_cr($e->getMessage() === 'COMMUNITY_KEY_MISSING', 'Without a proper key the service refuses to run'); }
ok_cr(C::enabled(['community_reports' => ['enabled' => true, 'hmac_key' => str_repeat('k', 32)]]) && !C::enabled(['community_reports' => ['enabled' => true, 'hmac_key' => 'short']]) && !C::enabled(['community_reports' => ['enabled' => false, 'hmac_key' => str_repeat('k', 40)]]) && !C::enabled([]), 'The feature is on only when enabled AND keyed');
global $config;
$db = Database::connection($config['db']);
ok_cr($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$key = str_repeat('k', 40);
$svc = new C($db, $key);
$h1 = $svc->reporterHash('203.0.113.9', $now);
ok_cr(preg_match('/^[0-9a-f]{32}$/', $h1) === 1 && $h1 === $svc->reporterHash('203.0.113.9', $now->modify('+3 hours')), 'One client on one day always gets the same 32-character code');
ok_cr($h1 !== $svc->reporterHash('203.0.113.9', $now->modify('+1 day')), 'The code changes the next day: a person cannot be followed across days');
ok_cr($h1 !== $svc->reporterHash('203.0.113.10', $now) && $h1 !== (new C($db, str_repeat('z', 40)))->reporterHash('203.0.113.9', $now) && !str_contains($h1, '203') && $h1 !== substr(md5('203.0.113.9'), 0, 32) && $h1 !== substr(hash('sha256', '203.0.113.9'), 0, 32), 'It depends on the client and the key, and is not a plain hash of the address');

$db->beginTransaction();
try {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $pid = (int)$db->query("SELECT id FROM products WHERE is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
    $pid2 = (int)$db->query("SELECT id FROM products WHERE is_active = 1 AND id <> $pid ORDER BY id LIMIT 1")->fetchColumn();
    ok_cr($pid > 0 && $pid2 > 0, 'The test catalog has two products');
    $admin = (int)(new AuthService())->createUser('cr-' . bin2hex(random_bytes(4)) . '@example.test', 'A', 'CorrectHorseBattery99', 'admin')['user_id'];

    // what the public price looks like before anything happens
    $priceState = static function () use ($db, $pid): string { return json_encode([$db->query("SELECT COUNT(*), COALESCE(MAX(id), 0), COALESCE(SUM(median), 0) FROM price_indices WHERE product_id = $pid")->fetch(PDO::FETCH_NUM), $db->query("SELECT COUNT(*), COALESCE(SUM(verified_status = 'approved'), 0) FROM price_observations WHERE product_id = $pid AND price_type = 'asking'")->fetch(PDO::FETCH_NUM)]); };
    $before = $priceState();

    // ---- submit ----
    ok_cr($svc->submit($pid, 'nonsense', [], '1.1.1.1', $now)['error'] === 'BAD_TYPE' && $svc->submit(999999999, 'stale', [], '1.1.1.1', $now)['error'] === 'BAD_PRODUCT', 'Unknown type and unknown product are refused');
    ok_cr($svc->submit($pid, 'sold_price', ['price' => 'x'], '1.1.1.1', $now)['error'] === 'BAD_PRICE', 'A bad field is refused and nothing is stored');
    ok_cr((int)$db->query('SELECT COUNT(*) FROM community_reports')->fetchColumn() === 0, 'Nothing was stored for refused reports');
    $a = $svc->submit($pid, 'stale', [], '1.1.1.1', $now);
    ok_cr($a['ok'] && $a['duplicate'] === false && $a['id'] > 0, 'A valid report is stored');
    $row = $db->query('SELECT * FROM community_reports WHERE id = ' . (int)$a['id'])->fetch(PDO::FETCH_ASSOC);
    ok_cr($row['status'] === 'new' && $row['report_type'] === 'stale' && (int)$row['product_id'] === $pid && $row['reporter_hash'] === $svc->reporterHash('1.1.1.1', $now) && $row['moderator_id'] === null && $row['candidate_observation_id'] === null && !str_contains(json_encode($row), '1.1.1.1'), 'It is stored as new, with the anonymous code and no address');
    $dup = $svc->submit($pid, 'stale', [], '1.1.1.1', $now);
    ok_cr($dup['ok'] && $dup['duplicate'] === true && (int)$db->query('SELECT COUNT(*) FROM community_reports')->fetchColumn() === 1, 'The same report again is accepted silently and stored once');
    ok_cr($svc->submit($pid, 'spec_wrong', ['spec' => 'cores'], '1.1.1.1', $now)['ok'] && $svc->submit($pid, 'spec_wrong', ['spec' => 'tdp'], '1.1.1.1', $now)['ok'], 'Two more different reports for the product are stored (three in all)');
    ok_cr($svc->submit($pid, 'spec_wrong', ['spec' => 'socket'], '1.1.1.1', $now)['error'] === 'PRODUCT_DAILY_LIMIT', 'A fourth report for the same product from the same client the same day is refused');
    ok_cr($svc->submit($pid2, 'stale', [], '1.1.1.1', $now)['ok'], 'The limit is per product');
    ok_cr($svc->submit($pid, 'stale', [], '2.2.2.2', $now)['ok'], 'Another client is not affected');
    ok_cr($svc->submit($pid, 'spec_wrong', ['spec' => 'socket'], '1.1.1.1', $now->modify('+1 day'))['ok'], 'The next day the same client may report again (a new code)');
    $r = $svc->submit($pid, 'wrong_product', ['should_be' => (string)$pid2], '3.3.3.3', $now);
    ok_cr($r['ok'] && json_decode((string)$db->query('SELECT payload FROM community_reports WHERE id = ' . (int)$r['id'])->fetchColumn(), true) === ['should_be_product_id' => $pid2], 'wrong_product points at a catalog model');
    $named = array_values(array_filter($svc->queue('all'), static fn(array $q): bool => (int)$q['id'] === (int)$r['id']))[0] ?? [];
    ok_cr(($named['should_be_name'] ?? '') === (string)$db->query('SELECT full_name FROM products WHERE id = ' . (int)$pid2)->fetchColumn(), 'LS-22: the queue shows the name of the model the reporter pointed at');
    $soldRow = $svc->submit($pid, 'sold_price', $sold, '4.4.4.4', $now);
    ok_cr($soldRow['ok'], 'A sold-price report is stored');
    ok_cr($priceState() === $before, 'SIGNAL ONLY: after all these reports no snapshot, median or approved asking observation changed');
    $snap = $db->query('SELECT snapshot_id FROM community_reports WHERE id = ' . (int)$soldRow['id'])->fetchColumn();
    $expectSnap = $db->query("SELECT id FROM price_indices WHERE product_id = $pid AND product_variant_id IS NULL AND COALESCE(provenance_status, 'legacy_unavailable') <> 'legacy_unavailable' ORDER BY last_calculated_at DESC, id DESC LIMIT 1")->fetchColumn();
    ok_cr(($snap === null && $expectSnap === false) || (int)$snap === (int)$expectSnap, 'The report remembers the public snapshot the visitor saw (or none)');

    // ---- the queue ----
    $q = $svc->queue('new');
    ok_cr(count($q) >= 7 && $q[0]['status'] === 'new' && isset($q[0]['full_name'], $q[0]['payload']) && is_array($q[0]['payload']) && (int)$q[0]['id'] < (int)end($q)['id'], 'The queue lists new reports, oldest first, with the product and decoded fields');
    ok_cr($svc->counts()['new'] >= 7 && $svc->productsNeedingAttention(3)[0]['new_reports'] >= 1, 'Counts and the products that need a look are available');

    // ---- moderation ----
    ok_cr($svc->moderate($admin, (int)$a['id'], 'maybe')['error'] === 'BAD_ACTION' && $svc->moderate($admin, (int)$a['id'], 'rejected', '')['error'] === 'NOTE_REQUIRED' && $svc->moderate($admin, (int)$a['id'], 'spam', '  ')['error'] === 'NOTE_REQUIRED', 'A rejection or spam mark needs a reason');
    ok_cr($svc->moderate($admin, (int)$a['id'], 'accepted')['ok'] && $db->query('SELECT status FROM community_reports WHERE id = ' . (int)$a['id'])->fetchColumn() === 'accepted', 'A person can accept');
    ok_cr($svc->moderate($admin, (int)$a['id'], 'rejected', 'changed my mind')['error'] === 'BAD_STATE', 'A decided report cannot be decided again');
    ok_cr((int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE user_id = $admin AND action = 'community_report_accepted' AND entity_id = " . (int)$a['id'])->fetchColumn() === 1, 'The decision is audited with the person');
    ok_cr($svc->moderate($admin, (int)$r['id'], 'spam', 'same text posted on many products')['ok'] && $svc->moderate($admin, (int)$dup['id'] ?: 0, 'accepted')['error'] === 'BAD_STATE', 'Spam is marked with a reason; a missing report cannot be decided');
    ok_cr($svc->queue('accepted')[0]['moderator_id'] !== null && count($svc->queue('spam')) >= 1 && $svc->queue('nonsense') !== [], 'Filters by status work (an unknown filter shows everything)');

    // ---- the candidate ----
    ok_cr($svc->createCandidate($admin, (int)$soldRow['id'])['error'] === 'NOT_ACCEPTED', 'A report that is not accepted gives no candidate');
    ok_cr($svc->createCandidate($admin, (int)$a['id'])['error'] === 'NOT_A_SOLD_PRICE' && $svc->createCandidate($admin, 987654321)['error'] === 'NOT_FOUND', 'Only an accepted sold-price report can');
    ok_cr($svc->moderate($admin, (int)$soldRow['id'], 'accepted')['ok'], 'Accept the sold-price report');
    $c1 = $svc->createCandidate($admin, (int)$soldRow['id'], $now);
    ok_cr($c1['ok'] && $c1['observation_id'] > 0, 'An accepted sold-price report becomes a candidate');
    $o = $db->query('SELECT * FROM price_observations WHERE id = ' . (int)$c1['observation_id'])->fetch(PDO::FETCH_ASSOC);
    ok_cr($o['verified_status'] === 'pending' && $o['price_type'] === 'sold' && $o['asking_price'] === null && (float)$o['price_value'] === 7500.0 && (int)$o['evidence_level'] === 1 && $o['condition_level'] === 'good' && (int)$o['warranty_months'] === 6 && (int)$o['has_box'] === 1 && (int)$o['has_receipt'] === 0 && $o['listing_type'] === 'single_item' && str_starts_with((string)$o['observed_at'], '2026-10-01') && (int)$o['product_id'] === $pid, 'The candidate is PENDING, a SOLD price, lowest evidence level, from the month given: ' . json_encode($o));
    ok_cr(json_decode((string)$o['quality_flags'], true) === ['COMMUNITY_REPORT'], 'It is flagged as a community report');
    $raw = $db->query('SELECT r.processing_status, s.source_key, s.evidence_quality, s.reliability_score FROM raw_price_observations r JOIN data_sources s ON s.id = r.source_id WHERE r.id = ' . (int)$o['raw_observation_id'])->fetch(PDO::FETCH_ASSOC);
    ok_cr($raw['processing_status'] === 'review' && $raw['source_key'] === 'community_report' && $raw['evidence_quality'] === 'low' && (float)$raw['reliability_score'] <= 20.0, 'Its source is the low-quality community source, in review');
    ok_cr($db->query('SELECT evidence_level FROM market_evidence WHERE raw_observation_id = ' . (int)$o['raw_observation_id'])->fetchColumn() === 'E' && (int)$db->query("SELECT COUNT(*) FROM observation_review_decisions WHERE price_observation_id = " . (int)$o['id'] . " AND decision = 'review_required'")->fetchColumn() === 1, 'Evidence level E and a review decision "review required" exist, so it shows in the review queue');
    ok_cr($svc->createCandidate($admin, (int)$soldRow['id'], $now)['error'] === 'ALREADY_CANDIDATE', 'One candidate per report');
    ok_cr($priceState() === $before, 'STILL SIGNAL ONLY: the candidate is pending and sold, so no public price, snapshot or approved asking observation changed');
    // the engine itself ignores it: a snapshot of the asking cohort excludes a sold row even if a reviewer approved it
    $cohortReason = (new ReflectionMethod(App\Services\Pricing\PriceSnapshotService::class, 'exclusionReason'));
    $cohortReason->setAccessible(true);
    $svcSnap = (new ReflectionClass(App\Services\Pricing\PriceSnapshotService::class))->newInstanceWithoutConstructor();
    ok_cr($cohortReason->invoke($svcSnap, $o, 'asking') === 'NOT_ACCEPTED' && $cohortReason->invoke($svcSnap, array_merge($o, ['verified_status' => 'approved']), 'asking') === 'WRONG_PRICE_TYPE', 'The price engine excludes it while pending, and still excludes it from the asking cohort if a reviewer approved it');
    ok_cr((int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE user_id = $admin AND action = 'community_report_candidate'")->fetchColumn() === 1, 'Creating the candidate is audited');
    // weekly cap: a second accepted sold report from the same client for the same product within a week
    $s2 = $svc->submit($pid, 'sold_price', array_merge($sold, ['price' => '7600']), '4.4.4.4', $now);
    ok_cr($s2['ok'] && $svc->moderate($admin, (int)$s2['id'], 'accepted', '', $now)['ok'], 'A second sold report from the same client is stored and accepted');
    ok_cr($svc->createCandidate($admin, (int)$s2['id'], $now->modify('+2 days'))['error'] === 'CANDIDATE_CAP', 'But only one candidate per client per product per week');
    $s3 = $svc->submit($pid2, 'sold_price', $sold, '4.4.4.4', $now);
    ok_cr($s3['ok'] && $svc->moderate($admin, (int)$s3['id'], 'accepted')['ok'] && $svc->createCandidate($admin, (int)$s3['id'], $now)['ok'], 'The cap is per product');
    ok_cr($svc->createCandidate($admin, (int)$s2['id'], $now->modify('+8 days'))['ok'], 'After a week the cap no longer applies');
    // ---- retention (ADR-006 S4): only REJECTED and SPAM reports, counted from the decision day; new and accepted are never purged ----
    $oldDay = new DateTimeImmutable('-100 days'); $recent = new DateTimeImmutable('-10 days');
    $mk = static function (string $addr, string $spec, DateTimeImmutable $at, string $decision, string $note) use ($svc, $pid, $admin): int {
        $r = $svc->submit($pid, 'spec_wrong', ['spec' => $spec], $addr, $at); $id = (int)$r['id'];
        if ($decision !== 'new') { $svc->moderate($admin, $id, $decision, $note, $at); }
        return $id;
    };
    $idRejOld = $mk('7.7.7.1', 'cores', $oldDay, 'rejected', 'old'); $idSpamOld = $mk('7.7.7.2', 'cores', $oldDay, 'spam', 'old'); $idAccOld = $mk('7.7.7.3', 'cores', $oldDay, 'accepted', '');
    $idNewOld = $mk('7.7.7.4', 'cores', $oldDay, 'new', ''); $idRejRecent = $mk('7.7.7.5', 'cores', $recent, 'rejected', 'recent');
    $cache = sys_get_temp_dir();
    $dry = App\Services\Ops\RetentionPurge::run($db, ['community_days' => 90], $cache, false);
    ok_cr($dry['results']['community_reports']['matched'] === 2 && $dry['results']['community_reports']['deleted'] === 0 && (int)$db->query("SELECT COUNT(*) FROM community_reports WHERE id IN ($idRejOld, $idSpamOld)")->fetchColumn() === 2, 'A dry run counts the old rejected and spam reports and deletes nothing');
    $applied = App\Services\Ops\RetentionPurge::run($db, ['community_days' => 90], $cache, true);
    $left = array_map('intval', $db->query("SELECT id FROM community_reports WHERE id IN ($idRejOld, $idSpamOld, $idAccOld, $idNewOld, $idRejRecent) ORDER BY id")->fetchAll(PDO::FETCH_COLUMN));
    ok_cr($applied['results']['community_reports']['deleted'] === 2 && $left === [$idAccOld, $idNewOld, $idRejRecent], 'Applied: the old rejected and spam reports are gone; accepted, new and recently rejected stay');
    try { App\Services\Ops\RetentionPurge::run($db, ['community_days' => 3], $cache, false); ok_cr(false, 'a period under the floor must be refused'); } catch (InvalidArgumentException $e) { ok_cr(str_contains($e->getMessage(), 'community_days'), 'A period under 7 days is refused'); }
} finally {
    $db->rollBack();
}
// ---- guards on the files: nothing free-text, the owner's gate, the wording, the docs ----
$root = dirname(__DIR__);
$unix = static fn(string $p): string => str_replace("\r\n", "\n", (string)file_get_contents($p));
$form = $unix($root . '/app/Views/community/report.php');
ok_cr(!preg_match('/<textarea|type="(email|tel|url|file|password)"/i', $form) && substr_count($form, 'type="text"') === 1 && str_contains($form, 'name="website"') && !preg_match('/name="(name|phone|email|contact|note|comment|message|address)"/i', $form), 'The form has no text box, contact field or upload; the one text input is the hidden honeypot');
ok_cr(str_contains($form, 'ไม่เก็บชื่อ ช่องทางติดต่อ หรือที่อยู่') && str_contains($form, 'มีคนตรวจทุกรายงาน') && str_contains($form, 'ไม่เปลี่ยนราคาได้เอง'), 'The visitor sees the privacy wording from ADR-006 (owner approval before launch)');
$ctl = $unix($root . '/app/Controllers/CommunityReportController.php');
ok_cr(substr_count($ctl, 'if (!$this->on()) { $this->notFound(); return; }') === 2 && str_contains($ctl, 'CommunityReports::enabled($config)'), 'Both public routes check the owner gate first');
$service = $unix($root . '/app/Services/Community/CommunityReports.php');
ok_cr(!preg_match('/UPDATE\s+price_|DELETE\s+FROM\s+price_|PriceSnapshotService|INSERT\s+INTO\s+price_indices|\x27approved\x27/i', $service . $ctl), 'The feature never updates or deletes a price table, never runs the snapshot service and never writes the value approved: a candidate is only ever inserted as pending');
ok_cr(str_contains(str_replace("\r\n", "\n", $unix($root . '/.env.example')), "COMMUNITY_REPORTS_ENABLED=false\nCOMMUNITY_REPORTS_HMAC_KEY="), 'The example env ships it OFF with an empty key');
ok_cr(str_contains($unix($root . '/config/app.php'), "'community_reports' => ['enabled' => filter_var(\$_ENV['COMMUNITY_REPORTS_ENABLED'] ?? 'false'"), 'Off unless the env says true');
ok_cr(str_contains($unix($root . '/app/Views/layouts/admin_header.php'), "'href' => '/admin/community-reports', 'permission' => 'reports.moderate'"), 'The Admin menu entry needs the reports.moderate permission (reviewer and admin)');
$idx = $unix($root . '/public/index.php');
ok_cr(str_contains($idx, "\$router->get('/admin/community-reports',[AdminController::class,'communityReports']);") && str_contains($idx, "\$router->post('/admin/community-reports/action',[AdminController::class,'communityReportAction']);"), 'The queue routes exist');
$adm = $unix($root . '/app/Controllers/AdminController.php');
ok_cr(str_contains($adm, "public function communityReports(): void {\n        if(!\$this->requirePermission('reports.moderate')) return;") && str_contains($adm, "public function communityReportAction(): void {\n        if(!\$this->requirePermission('reports.moderate')) return;\n        if(!Csrf::verify(\$_POST['_token']??null))"), 'Viewing the queue and deciding a report need the permission, and a decision needs a CSRF token');
ok_cr(str_contains($unix($root . '/app/Views/admin/data-health.php'), 'Community reports') && str_contains($unix($root . '/cli/retention_purge.php'), '--community-days=N'), 'Data health shows the counts and the retention CLI has the option (S4)');
ok_cr(str_contains($unix($root . '/app/Services/Seo/SeoService.php'), "'/account', '/report']"), 'robots.txt keeps the form out of search');
echo "community_reports_test: PASS\n";
