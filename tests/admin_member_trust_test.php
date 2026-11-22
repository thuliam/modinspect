<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Controllers\AdminController;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\RedirectException;
use App\Services\Auth\AuthService;
use App\Services\Member\LegalDocuments;
use App\Services\Member\MemberAudit;
use App\Services\Member\MemberCapabilities;
use App\Services\Member\PrivacyRequests;

/**
 * Marketplace P1-A admin screens (privacy requests, legal documents, member capabilities) through the controller, in process, on the ISOLATED test
 * database, rolled back. Only an administrator may open or act on them (a reviewer gets 403, a visitor is sent to login); every POST needs a CSRF token;
 * publishing needs the explicit confirmation; text a member or an editor typed is escaped; actions are audited with the admin's id. Synthetic data only.
 */
function ok_am(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
/** @return array{redirect:?string,code:int,body:string} */
function am_call(callable $fn): array
{
    http_response_code(200); ob_start();
    try { $fn(); } catch (RedirectException $e) { ob_end_clean(); return ['redirect' => $e->path, 'code' => 302, 'body' => '']; }
    return ['redirect' => null, 'code' => http_response_code(), 'body' => (string)ob_get_clean()];
}

global $config;
$_SERVER['REMOTE_ADDR'] = '10.253.' . random_int(0, 255) . '.' . random_int(1, 254);
$db = Database::connection($config['db']);
ok_am($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$db->beginTransaction();
try {
    $mkUser = static function (string $role) use ($db): int {
        $db->prepare("INSERT INTO users (name,email,password_hash,role,is_active) VALUES (:n,:e,'x',:r,1)")->execute(['n' => "Synthetic $role", 'e' => "am-$role-" . bin2hex(random_bytes(4)) . '@example.test', 'r' => $role]);
        return (int)$db->lastInsertId();
    };
    $admin = $mkUser('admin'); $reviewer = $mkUser('reviewer');
    $db->exec("INSERT INTO members (display_name, terms_accepted_at) VALUES ('Mem <b>One</b>', NOW())"); $mem = (int)$db->lastInsertId();
    $as = static function (?int $user, string $method, array $post = [], array $get = [], bool $token = true): void {
        $_SESSION = $user ? [AuthService::SESSION_USER_ID => $user, AuthService::SESSION_LOGIN_AT => time(), AuthService::SESSION_LAST_SEEN_AT => time()] : [];
        $_SERVER['REQUEST_METHOD'] = $method; $_SERVER['REQUEST_URI'] = '/admin/x'; $_GET = $get;
        $_POST = $method === 'POST' ? $post + ($token ? ['_token' => Csrf::token()] : []) : [];
    };
    $c = new AdminController();
    $audit = new MemberAudit($db);
    $pages = ['privacyRequests' => 'คำขอข้อมูลส่วนบุคคล', 'legalDocuments' => 'เอกสารกฎหมาย', 'memberCapabilities' => 'สิทธิ์ของสมาชิก'];
    $posts = ['privacyRequestAction' => ['id' => '1', 'to' => 'completed'], 'legalDocumentCreate' => ['doc_type' => 'privacy', 'version' => 'x', 'title' => 't', 'body' => 'b'],
        'legalDocumentPublish' => ['id' => '1', 'confirm' => '1'], 'memberCapabilityAction' => ['do' => 'grant', 'member_id' => (string)$mem, 'capability' => 'seller']];

    // ---- who may open or act ----
    foreach ($pages as $m => $title) {
        $as(null, 'GET'); ok_am(am_call(fn() => $c->$m())['redirect'] === '/login', "$m: a visitor is sent to login");
        $as($reviewer, 'GET'); ok_am(am_call(fn() => $c->$m())['code'] === 403, "$m: a reviewer gets 403");
        $as($admin, 'GET'); $r = am_call(fn() => $c->$m()); ok_am($r['code'] === 200 && str_contains($r['body'], $title), "$m: an administrator sees the page");
    }
    foreach ($posts as $m => $post) {
        $as(null, 'POST', $post); ok_am(am_call(fn() => $c->$m())['redirect'] === '/login', "$m: a visitor is sent to login");
        $as($reviewer, 'POST', $post); ok_am(am_call(fn() => $c->$m())['code'] === 403, "$m: a reviewer gets 403 and nothing is written");
    }
    ok_am((int)$db->query('SELECT COUNT(*) FROM member_capabilities')->fetchColumn() === 0 && (int)$db->query("SELECT COUNT(*) FROM legal_documents WHERE title = 't'")->fetchColumn() === 0, 'The refused calls wrote nothing');

    // ---- CSRF ----
    foreach (['privacyRequestAction', 'legalDocumentCreate', 'legalDocumentPublish', 'memberCapabilityAction'] as $m) {
        $as($admin, 'POST', $posts[$m], [], false);
        ok_am(am_call(fn() => $c->$m())['redirect'] !== null, "$m without a token only redirects");
    }
    ok_am((int)$db->query('SELECT COUNT(*) FROM member_capabilities')->fetchColumn() === 0 && (int)$db->query("SELECT COUNT(*) FROM legal_documents WHERE title = 't'")->fetchColumn() === 0, 'No write happened without a CSRF token');

    // ---- privacy requests ----
    $pr = new PrivacyRequests($db, $audit);
    $req = $pr->open($mem, 'access', 'detail <script>alert(1)</script>');
    $as($admin, 'GET', [], ['status' => 'received']); $r = am_call(fn() => $c->privacyRequests());
    ok_am(str_contains($r['body'], '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($r['body'], '<script>alert(1)') && str_contains($r['body'], 'Mem &lt;b&gt;One&lt;/b&gt;'), 'Text the member typed is escaped on the queue page');
    $as($admin, 'POST', ['id' => (string)$req, 'to' => 'completed', 'note' => '  ']);
    am_call(fn() => $c->privacyRequestAction());
    ok_am($db->query("SELECT status FROM privacy_requests WHERE id = $req")->fetchColumn() === 'received', 'Closing a request needs a response note');
    $as($admin, 'POST', ['id' => (string)$req, 'to' => 'in_progress']);
    am_call(fn() => $c->privacyRequestAction());
    $as($admin, 'POST', ['id' => (string)$req, 'to' => 'completed', 'note' => 'sent a copy']);
    am_call(fn() => $c->privacyRequestAction());
    $row = $db->query("SELECT status, handler_user_id FROM privacy_requests WHERE id = $req")->fetch(PDO::FETCH_ASSOC);
    ok_am($row['status'] === 'completed' && (int)$row['handler_user_id'] === $admin, 'The request is closed and the admin is recorded');
    $as($admin, 'POST', ['id' => (string)$req, 'to' => 'in_progress']);
    am_call(fn() => $c->privacyRequestAction());
    ok_am($db->query("SELECT status FROM privacy_requests WHERE id = $req")->fetchColumn() === 'completed', 'A closed request cannot be reopened through the page');
    $as($admin, 'GET', [], ['status' => 'bogus']); ok_am(am_call(fn() => $c->privacyRequests())['code'] === 200, 'An unknown status filter falls back instead of failing');
    ok_am(in_array('privacy.completed', array_column($audit->forMember($mem), 'action'), true), 'The decision is in the audit trail');

    // ---- legal documents ----
    $as($admin, 'POST', ['doc_type' => 'bogus', 'version' => '1', 'title' => 'T', 'body' => 'B']); am_call(fn() => $c->legalDocumentCreate());
    $as($admin, 'POST', ['doc_type' => 'privacy', 'version' => '', 'title' => 'T', 'body' => 'B']); am_call(fn() => $c->legalDocumentCreate());
    ok_am((int)$db->query("SELECT COUNT(*) FROM legal_documents WHERE title = 'T'")->fetchColumn() === 0, 'A bad type or a missing field creates nothing');
    $as($admin, 'POST', ['doc_type' => 'privacy', 'version' => 'am-1', 'title' => 'Privacy <i>draft</i>', 'body' => "Line <script>x</script>\nTwo"]); am_call(fn() => $c->legalDocumentCreate());
    $doc = (int)$db->query("SELECT id FROM legal_documents WHERE version = 'am-1'")->fetchColumn();
    ok_am($doc > 0 && $db->query("SELECT status FROM legal_documents WHERE id = $doc")->fetchColumn() === 'draft', 'A new document is saved as a draft, not published');
    $as($admin, 'POST', ['doc_type' => 'privacy', 'version' => 'am-1', 'title' => 'dup', 'body' => 'b']); am_call(fn() => $c->legalDocumentCreate());
    ok_am((int)$db->query("SELECT COUNT(*) FROM legal_documents WHERE version = 'am-1' AND doc_type = 'privacy'")->fetchColumn() === 1, 'The same type and version cannot be created twice');
    $as($admin, 'GET', [], ['view' => (string)$doc]); $r = am_call(fn() => $c->legalDocuments());
    ok_am(str_contains($r['body'], '&lt;script&gt;x&lt;/script&gt;') && !str_contains($r['body'], '<script>x</script>') && str_contains($r['body'], 'Privacy &lt;i&gt;draft&lt;/i&gt;'), 'The document text and title are escaped when shown');
    $as($admin, 'POST', ['id' => (string)$doc]); am_call(fn() => $c->legalDocumentPublish());
    ok_am($db->query("SELECT status FROM legal_documents WHERE id = $doc")->fetchColumn() === 'draft', 'Publishing needs the confirmation box');
    $as($admin, 'POST', ['id' => (string)$doc, 'confirm' => '1']); am_call(fn() => $c->legalDocumentPublish());
    $p = $db->query("SELECT status, published_by_user_id FROM legal_documents WHERE id = $doc")->fetch(PDO::FETCH_ASSOC);
    ok_am($p['status'] === 'published' && (int)$p['published_by_user_id'] === $admin, 'With the confirmation it is published and the admin is recorded');
    $legal = new LegalDocuments($db, $audit);
    ok_am(in_array($doc, array_column($legal->pendingFor($mem), 'id'), true), 'A published document is now pending for members');

    // ---- capabilities ----
    $as($admin, 'POST', ['do' => 'grant', 'member_id' => '99999999', 'capability' => 'seller']); am_call(fn() => $c->memberCapabilityAction());
    ok_am((int)$db->query('SELECT COUNT(*) FROM member_capabilities')->fetchColumn() === 0, 'An unknown member id grants nothing');
    $as($admin, 'POST', ['do' => 'grant', 'member_id' => (string)$mem, 'capability' => 'superadmin']); am_call(fn() => $c->memberCapabilityAction());
    ok_am((int)$db->query('SELECT COUNT(*) FROM member_capabilities')->fetchColumn() === 0, 'A capability outside the allowlist is refused');
    $as($admin, 'POST', ['do' => 'grant', 'member_id' => (string)$mem, 'capability' => 'seller']); am_call(fn() => $c->memberCapabilityAction());
    $cap = new MemberCapabilities($db, $audit);
    ok_am($cap->has($mem, 'seller') && (int)$db->query("SELECT granted_by_user_id FROM member_capabilities WHERE member_id = $mem")->fetchColumn() === $admin, 'Granting works and records the admin');
    $as($admin, 'GET'); $r = am_call(fn() => $c->memberCapabilities());
    ok_am(str_contains($r['body'], 'Mem &lt;b&gt;One&lt;/b&gt;') && str_contains($r['body'], 'ถอนสิทธิ์'), 'The holder is listed with a revoke button and an escaped name');
    $as($admin, 'POST', ['do' => 'explode', 'member_id' => (string)$mem, 'capability' => 'seller']); am_call(fn() => $c->memberCapabilityAction());
    ok_am($cap->has($mem, 'seller'), 'An unknown action changes nothing');
    $as($admin, 'POST', ['do' => 'revoke', 'member_id' => (string)$mem, 'capability' => 'seller']); am_call(fn() => $c->memberCapabilityAction());
    ok_am(!$cap->has($mem, 'seller') && (int)$db->query("SELECT COUNT(*) FROM member_capabilities WHERE member_id = $mem")->fetchColumn() === 1, 'Revoking removes the access and keeps the row');
    $acts = array_column($audit->forMember($mem), 'action');
    ok_am(in_array('capability.granted:seller', $acts, true) && in_array('capability.revoked:seller', $acts, true), 'Grant and revoke are in the audit trail');

    // ---- routes and menu ----
    $index = (string)file_get_contents(dirname(__DIR__) . '/public/index.php');
    foreach (['get /admin/privacy-requests', 'post /admin/privacy-requests/action', 'get /admin/legal-documents', 'post /admin/legal-documents/create', 'post /admin/legal-documents/publish', 'get /admin/member-capabilities', 'post /admin/member-capabilities/action'] as $rt) {
        [$verb, $path] = explode(' ', $rt);
        ok_am(preg_match("#router->$verb\\('" . preg_quote($path, '#') . "'#", $index) === 1, "Route $rt exists");
    }
    $as($admin, 'GET'); $r = am_call(fn() => $c->privacyRequests());
    ok_am(str_contains($r['body'], '/admin/legal-documents') && str_contains($r['body'], '/admin/member-capabilities'), 'The admin menu lists the three pages');
    $as($reviewer, 'GET'); $r = am_call(fn() => $c->communityReports());
    ok_am(!str_contains($r['body'], '/admin/privacy-requests') && !str_contains($r['body'], '/admin/legal-documents'), 'A reviewer does not see the member pages in the menu');

    echo "admin_member_trust_test: PASS\n";
} finally {
    if ($db->inTransaction()) { $db->rollBack(); }
}
