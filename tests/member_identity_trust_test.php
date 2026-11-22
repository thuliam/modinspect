<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Controllers\AccountController;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\RedirectException;
use App\Services\Member\LegalDocuments;
use App\Services\Member\MemberAudit;
use App\Services\Member\MemberAuth;
use App\Services\Member\MemberCapabilities;
use App\Services\Member\MemberPreferences;
use App\Services\Member\PrivacyRequests;

/**
 * Marketplace P1-A (migration 032) on the ISOLATED test database, rolled back: preferences (marketing is opt-in), versioned legal documents (a draft or
 * retired version cannot be accepted, publishing retires the old version, an empty body cannot be published), the privacy request queue (no duplicates, legal
 * state machine, a request outlives the member), capabilities (allowlist, revoke keeps history, a disabled member has none), the audit trail, and the three
 * /account POST actions (need a member and a CSRF token; one member cannot touch another's data). Synthetic data only.
 */
function ok_it(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
function it_throws(callable $fn, string $code, string $label): void
{
    try { $fn(); } catch (Throwable $e) { ok_it(str_contains($e->getMessage(), $code), "$label (got {$e->getMessage()})"); return; }
    throw new RuntimeException("$label (nothing was thrown)");
}
/** @return array{redirect:?string,code:int} */
function it_call(callable $fn): array
{
    http_response_code(200); ob_start();
    try { $fn(); } catch (RedirectException $e) { ob_end_clean(); return ['redirect' => $e->path, 'code' => 302]; }
    ob_end_clean();
    return ['redirect' => null, 'code' => http_response_code()];
}

global $config;
$db = Database::connection($config['db']);
ok_it($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$db->beginTransaction();
try {
    $db->exec("INSERT INTO members (display_name, terms_accepted_at) VALUES ('Alice Synthetic', NOW())"); $alice = (int)$db->lastInsertId();
    $db->exec("INSERT INTO members (display_name, terms_accepted_at) VALUES ('Bob Synthetic', NOW())"); $bob = (int)$db->lastInsertId();
    $db->exec("INSERT INTO users (name,email,password_hash,role,is_active) VALUES ('Synthetic Admin','it-admin-" . bin2hex(random_bytes(4)) . "@example.test','x','admin',1)"); $admin = (int)$db->lastInsertId();
    $audit = new MemberAudit($db);

    // ---- preferences ----
    $prefs = new MemberPreferences($db, $audit);
    $d = $prefs->get($alice);
    ok_it($d['notify_transactional'] === true && $d['notify_marketing'] === false && $d['locale'] === 'th', 'Defaults: transactional on, marketing OFF (opt-in), Thai');
    $prefs->save($alice, true, true, 'en');
    ok_it($prefs->get($alice) === ['notify_transactional' => true, 'notify_marketing' => true, 'locale' => 'en'], 'Saved choices are read back');
    ok_it($prefs->get($bob)['notify_marketing'] === false, "One member's choice does not touch another's");
    it_throws(fn() => $prefs->save($alice, true, false, 'xx'), 'LOCALE', 'An unknown locale is refused');
    ok_it($prefs->saveDisplayName($alice, "  Alice\x00\n Two  ") && $db->query("SELECT display_name FROM members WHERE id = $alice")->fetchColumn() === 'Alice Two', 'Control characters are removed from the display name');
    ok_it(!$prefs->saveDisplayName($alice, '   ') && !$prefs->saveDisplayName($alice, str_repeat('ก', 61)), 'An empty or 61-character name is refused');

    // ---- legal documents ----
    $legal = new LegalDocuments($db, $audit);
    $v1 = $legal->createDraft('buyer_terms', 'it-1', 'Buyer terms (synthetic)', 'Body one');
    ok_it($legal->published() === [] || !in_array($v1, array_column($legal->published(), 'id'), true), 'A draft is not published');
    ok_it($legal->accept($alice, $v1) === false && !$legal->hasAccepted($alice, 'buyer_terms'), 'A draft cannot be accepted');
    $empty = $legal->createDraft('privacy', 'it-1', 'Empty (synthetic)', '   ');
    it_throws(fn() => $legal->publish($empty, $admin), 'EMPTY_BODY', 'An empty document cannot be published');
    it_throws(fn() => $legal->createDraft('nonsense', 'it', 't', 'b'), 'DOC_TYPE', 'An unknown document type is refused');
    $legal->publish($v1, $admin);
    it_throws(fn() => $legal->publish($v1, $admin), 'NOT_A_DRAFT', 'Publishing twice is refused');
    ok_it(in_array($v1, array_column($legal->pendingFor($alice), 'id'), true), 'A published document is pending for a member who has not accepted it');
    ok_it($legal->accept($alice, $v1) === true && $legal->accept($alice, $v1) === true, 'Accepting is idempotent');
    ok_it((int)$db->query("SELECT COUNT(*) FROM legal_acceptances WHERE member_id = $alice AND document_id = $v1")->fetchColumn() === 1, 'Accepting twice leaves one acceptance row');
    ok_it($legal->hasAccepted($alice, 'buyer_terms') && !$legal->hasAccepted($bob, 'buyer_terms'), 'Acceptance belongs to the member who gave it');
    $v2 = $legal->createDraft('buyer_terms', 'it-2', 'Buyer terms v2 (synthetic)', 'Body two');
    $legal->publish($v2, $admin);
    ok_it($db->query("SELECT status FROM legal_documents WHERE id = $v1")->fetchColumn() === 'retired', 'Publishing a new version retires the old one');
    ok_it($legal->accept($bob, $v1) === false, 'A retired version cannot be accepted');
    ok_it(!$legal->hasAccepted($alice, 'buyer_terms') && in_array($v2, array_column($legal->pendingFor($alice), 'id'), true), 'A new version must be accepted again');
    ok_it(count($legal->acceptances($alice)) === 1, 'The acceptance history keeps the old version');

    // ---- privacy requests ----
    $pr = new PrivacyRequests($db, $audit);
    $r1 = $pr->open($alice, 'access', 'please');
    ok_it($pr->open($alice, 'access') === $r1, 'A second open request of the same type returns the first');
    ok_it($pr->open($bob, 'access') !== $r1, "Another member's request is separate");
    it_throws(fn() => $pr->open($alice, 'sell_my_data'), 'REQUEST_TYPE', 'An unknown request type is refused');
    ok_it($db->query("SELECT due_at FROM privacy_requests WHERE id = $r1")->fetchColumn() === null, 'No deadline is invented when none is configured');
    $due = (new PrivacyRequests($db, $audit, 30))->open($alice, 'export');
    ok_it($db->query("SELECT due_at FROM privacy_requests WHERE id = $due")->fetchColumn() !== null, 'A configured number of days sets due_at');
    it_throws(fn() => $pr->transition($r1, 'received', $admin), 'ILLEGAL_TRANSITION', 'A request cannot go back to received');
    $pr->transition($r1, 'in_progress', $admin);
    $pr->transition($r1, 'completed', $admin, 'done');
    it_throws(fn() => $pr->transition($r1, 'in_progress', $admin), 'ILLEGAL_TRANSITION', 'A completed request is final');
    $row = $db->query("SELECT status, handler_user_id, responded_at FROM privacy_requests WHERE id = $r1")->fetch(PDO::FETCH_ASSOC);
    ok_it($row['status'] === 'completed' && (int)$row['handler_user_id'] === $admin && $row['responded_at'] !== null, 'The handler and the response time are recorded');
    ok_it($pr->open($alice, 'access') !== $r1, 'After completion a new request of that type can be opened');
    ok_it(array_column($pr->forMember($bob), 'id') !== array_column($pr->forMember($alice), 'id') && count($pr->forMember($bob)) === 1, "A member lists only their own requests");

    // ---- capabilities ----
    $cap = new MemberCapabilities($db, $audit);
    ok_it(!$cap->has($alice, 'seller'), 'No capability by default');
    it_throws(fn() => $cap->grant($alice, 'superadmin', $admin), 'CAPABILITY', 'A capability outside the allowlist cannot be granted');
    $cap->grant($alice, 'seller', $admin);
    ok_it($cap->has($alice, 'seller') && !$cap->has($bob, 'seller') && $cap->listFor($alice) === ['seller'], 'Granted to one member only');
    $cap->revoke($alice, 'seller', $admin);
    ok_it(!$cap->has($alice, 'seller') && (int)$db->query("SELECT COUNT(*) FROM member_capabilities WHERE member_id = $alice")->fetchColumn() === 1, 'Revoking keeps the row (history)');
    $cap->grant($alice, 'seller', $admin);
    ok_it($cap->has($alice, 'seller'), 'A revoked capability can be granted again');
    $db->exec("UPDATE members SET status = 'disabled' WHERE id = $alice");
    ok_it(!$cap->has($alice, 'seller'), 'A disabled member has no capability');
    $db->exec("UPDATE members SET status = 'active' WHERE id = $alice");

    // ---- audit ----
    $acts = array_column($audit->forMember($alice), 'action');
    foreach (['preferences.saved', 'profile.name_changed', 'legal.accepted', 'privacy.requested', 'privacy.completed', 'capability.granted:seller', 'capability.revoked:seller'] as $a) {
        ok_it(in_array($a, $acts, true), "The audit trail has $a");
    }
    $cols = array_column($db->query('SHOW COLUMNS FROM member_audit_events')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    ok_it(!array_intersect($cols, ['ip', 'ip_address', 'detail', 'body', 'user_agent']), 'The audit table has no column for an IP address or free text');
    it_throws(fn() => $audit->record($alice, 'stranger', null, 'x'), 'ACTOR_TYPE', 'An unknown actor type is refused');

    // ---- /account POST actions through the controller ----
    $_SERVER['REMOTE_ADDR'] = '10.252.' . random_int(0, 255) . '.' . random_int(1, 254);
    $_SERVER['REQUEST_URI'] = '/account';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $as = static function (?int $member, array $post, bool $token = true): void {
        $_SESSION = $member ? [MemberAuth::SESSION_MEMBER_ID => $member] : [];
        $_POST = $post + ($token ? ['_token' => Csrf::token()] : []);
    };
    $c = new AccountController();
    foreach (['preferences' => ['display_name' => 'X'], 'privacyRequest' => ['request_type' => 'access'], 'acceptLegal' => ['document_id' => (string)$v2]] as $method => $post) {
        $as(null, $post);
        ok_it(it_call(fn() => $c->$method())['redirect'] === '/account/login', "$method without a member goes to login");
    }
    $before = (int)$db->query('SELECT COUNT(*) FROM privacy_requests')->fetchColumn();
    $as($bob, ['request_type' => 'objection'], false);
    ok_it(it_call(fn() => $c->privacyRequest())['redirect'] === '/account' && (int)$db->query('SELECT COUNT(*) FROM privacy_requests')->fetchColumn() === $before, 'A privacy request without a CSRF token writes nothing');
    $as($bob, ['request_type' => 'objection']);
    it_call(fn() => $c->privacyRequest());
    ok_it((int)$db->query('SELECT COUNT(*) FROM privacy_requests')->fetchColumn() === $before + 1 && count($pr->forMember($bob)) === 2, 'With a token the request is queued for that member');
    $as($bob, ['request_type' => 'drop table']);
    it_call(fn() => $c->privacyRequest());
    ok_it((int)$db->query('SELECT COUNT(*) FROM privacy_requests')->fetchColumn() === $before + 1, 'An invalid request type writes nothing');
    $as($bob, ['display_name' => 'Bobby', 'locale' => 'en', 'notify_transactional' => '1']);
    it_call(fn() => $c->preferences());
    ok_it($prefs->get($bob) === ['notify_transactional' => true, 'notify_marketing' => false, 'locale' => 'en'], 'An unchecked marketing box saves as off');
    ok_it($db->query("SELECT display_name FROM members WHERE id = $alice")->fetchColumn() === 'Alice Two', "Bob's save did not touch Alice's name");
    $as($bob, ['display_name' => ['array'], 'locale' => 'zz']);
    it_call(fn() => $c->preferences());
    ok_it($prefs->get($bob)['locale'] === 'th', 'A non-string name and an unknown locale are handled without error');
    $as($bob, ['document_id' => (string)$v1]);
    it_call(fn() => $c->acceptLegal());
    ok_it(!$legal->hasAccepted($bob, 'buyer_terms'), 'A retired document cannot be accepted through the page');
    $as($bob, ['document_id' => (string)$v2]);
    it_call(fn() => $c->acceptLegal());
    ok_it($legal->hasAccepted($bob, 'buyer_terms'), 'The current document can be accepted through the page');

    // ---- the account page and its routes ----
    $_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = [];
    $_SESSION = [MemberAuth::SESSION_MEMBER_ID => $alice];
    ob_start(); try { $c->account(); } catch (RedirectException) { } $html = (string)ob_get_clean();
    foreach (['/account/preferences', '/account/privacy-request', 'name="notify_marketing"', 'name="request_type"', $v2 ? 'เอกสารที่รอการยอมรับ' : ''] as $needle) {
        ok_it($needle === '' || str_contains($html, $needle), "The account page has $needle");
    }
    ok_it(!str_contains($html, 'name="notify_marketing" value="1" class="mr-2 mt-1" checked') || $prefs->get($alice)['notify_marketing'], 'The marketing box is only pre-checked when the member chose it');
    $index = (string)file_get_contents(dirname(__DIR__) . '/public/index.php');
    foreach (['/account/preferences', '/account/privacy-request', '/account/legal/accept'] as $route) {
        ok_it(preg_match("#router->post\\('" . preg_quote($route, '#') . "'#", $index) === 1, "$route is a POST route");
        ok_it(preg_match("#router->get\\('" . preg_quote($route, '#') . "'#", $index) !== 1, "$route has no GET route");
    }

    // ---- deleting the account keeps the handling record, drops the personal rows ----
    $own = $pr->open($alice, 'correction');
    $_SESSION = [MemberAuth::SESSION_MEMBER_ID => $alice]; $_POST = ['_token' => Csrf::token(), 'confirm' => '1']; $_SERVER['REQUEST_METHOD'] = 'POST';
    it_call(fn() => $c->delete());
    ok_it($db->query("SELECT COUNT(*) FROM members WHERE id = $alice")->fetchColumn() == 0, 'The account is deleted');
    $kept = $db->query("SELECT member_id FROM privacy_requests WHERE id = $own")->fetch(PDO::FETCH_ASSOC);
    ok_it($kept !== false && $kept['member_id'] === null, 'The privacy request stays with no member attached');
    ok_it((int)$db->query("SELECT COUNT(*) FROM member_preferences WHERE member_id = $alice")->fetchColumn() === 0 && (int)$db->query("SELECT COUNT(*) FROM legal_acceptances WHERE member_id = $alice")->fetchColumn() === 0 && (int)$db->query("SELECT COUNT(*) FROM member_capabilities WHERE member_id = $alice")->fetchColumn() === 0, 'Preferences, acceptances and capabilities go with the account');
    ok_it((int)$db->query("SELECT COUNT(*) FROM member_audit_events WHERE action = 'account.deleted' AND actor_id = $alice AND member_id IS NULL")->fetchColumn() === 1, 'The deletion is audited without a member link');
    ok_it((int)$db->query("SELECT COUNT(*) FROM member_audit_events WHERE member_id = $alice")->fetchColumn() === 0, 'The member-linked audit rows no longer point at the deleted member');

    echo "member_identity_trust_test: PASS\n";
} finally {
    if ($db->inTransaction()) { $db->rollBack(); }
}
