<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\Http\RateLimiter;
use App\Services\Member\CurlOAuthHttp;
use App\Services\Member\LegalDocuments;
use App\Services\Member\MemberAudit;
use App\Services\Member\MemberAuth;
use App\Services\Member\MemberPreferences;
use App\Services\Member\OAuthProvider;
use App\Services\Member\PrivacyRequests;
use App\Services\Member\ProviderRegistry;

/** LS-14 Module 7 (OD-4): visitor accounts. Sign-in through Google, Microsoft or Facebook only; providers without keys in .env are not offered. */
final class AccountController extends Controller
{
    private const MESSAGES = [
        'CONSENT_REQUIRED' => 'กรุณายอมรับนโยบายความเป็นส่วนตัวและเงื่อนไขก่อนเข้าสู่ระบบ',
        'PROVIDER_DENIED' => 'การเข้าสู่ระบบถูกยกเลิก',
        'STATE_EXPIRED' => 'หน้าเข้าสู่ระบบหมดอายุ กรุณาลองใหม่',
        'STATE_MISMATCH' => 'คำขอไม่ถูกต้อง กรุณาลองใหม่',
        'PROVIDER_REJECTED' => 'ยืนยันตัวตนกับผู้ให้บริการไม่สำเร็จ กรุณาลองใหม่',
        'MEMBER_DISABLED' => 'บัญชีนี้ถูกปิดการใช้งาน',
    ];

    public function login(): void
    {
        if ($this->auth()->current()) { $this->redirect('/account'); }
        $this->view('account/login', ['title' => 'เข้าสู่ระบบสมาชิก', 'providers' => $this->providers(), 'error' => $this->takeFlash()]);
    }

    public function start(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) { $this->fail('STATE_MISMATCH'); }
        if (!$this->limit('member-login', 20)) { return; }
        $p = $this->providers()[(string)($_POST['provider'] ?? '')] ?? null;
        if ($p === null) { http_response_code(404); $this->view('errors/404', ['title' => 'ไม่พบหน้า']); return; }
        try {
            $url = $this->auth()->start($p, $this->redirectUri($p), ($_POST['consent'] ?? '') === '1');
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        if (defined('MODINSPECT_TESTING')) { throw new \App\Core\RedirectException($url); }
        header('Location: ' . $url);
        exit;
    }

    public function callback(string $provider): void
    {
        if (!$this->limit('member-callback', 30)) { return; }
        $p = $this->providers()[$provider] ?? null;
        if ($p === null) { http_response_code(404); $this->view('errors/404', ['title' => 'ไม่พบหน้า']); return; }
        $r = $this->auth()->callback($p, $this->redirectUri($p), isset($_GET['state']) ? (string)$_GET['state'] : null, isset($_GET['code']) ? (string)$_GET['code'] : null, isset($_GET['error']) ? (string)$_GET['error'] : null);
        if (!$r['ok']) { $this->fail($r['error']); }
        $this->redirect('/account');
    }

    public function account(): void
    {
        $m = $this->auth()->current();
        if (!$m) { $this->redirect('/account/login'); }
        $db = $this->db(); $id = (int)$m['id'];
        $this->view('account/profile', ['title' => 'บัญชีของฉัน', 'member' => $m, 'builds' => $this->auth()->builds($id),
            'prefs' => (new MemberPreferences($db, new MemberAudit($db)))->get($id),
            'requests' => (new PrivacyRequests($db, new MemberAudit($db)))->forMember($id),
            'pendingLegal' => (new LegalDocuments($db, new MemberAudit($db)))->pendingFor($id),
            'acceptances' => (new LegalDocuments($db, new MemberAudit($db)))->acceptances($id),
            'notice' => $this->takeNotice()]);
    }

    public function logout(): void
    {
        if (Csrf::verify($_POST['_token'] ?? null)) { $this->auth()->logout(); }
        $this->redirect('/');
    }

    /** Marketplace P1-A: display name and notification choices. Marketing is opt-in: only an explicit checked box turns it on. */
    public function preferences(): void
    {
        $m = $this->memberOrBounce();
        $db = $this->db(); $audit = new MemberAudit($db); $prefs = new MemberPreferences($db, $audit);
        $ok = true;
        if (isset($_POST['display_name']) && is_string($_POST['display_name'])) { $ok = $prefs->saveDisplayName((int)$m['id'], $_POST['display_name']); }
        $locale = is_string($_POST['locale'] ?? null) && in_array($_POST['locale'], MemberPreferences::LOCALES, true) ? $_POST['locale'] : 'th';
        $prefs->save((int)$m['id'], (($_POST['notify_transactional'] ?? '') === '1'), (($_POST['notify_marketing'] ?? '') === '1'), $locale);
        $_SESSION['member_notice'] = $ok ? 'บันทึกการตั้งค่าแล้ว' : 'ชื่อที่แสดงต้องมี 1-60 ตัวอักษร การตั้งค่าอื่นบันทึกแล้ว';
        $this->redirect('/account');
    }

    /** Marketplace P1-A: a data-subject request goes into the queue for a person to handle. It deletes nothing by itself. */
    public function privacyRequest(): void
    {
        $m = $this->memberOrBounce();
        $type = is_string($_POST['request_type'] ?? null) ? $_POST['request_type'] : '';
        if (!in_array($type, PrivacyRequests::TYPES, true)) { $_SESSION['member_notice'] = 'ประเภทคำขอไม่ถูกต้อง'; $this->redirect('/account'); }
        $db = $this->db();
        (new PrivacyRequests($db, new MemberAudit($db)))->open((int)$m['id'], $type, is_string($_POST['detail'] ?? null) ? $_POST['detail'] : '');
        $_SESSION['member_notice'] = 'ส่งคำขอแล้ว ทีมงานจะตรวจสอบ';
        $this->redirect('/account');
    }

    public function acceptLegal(): void
    {
        $m = $this->memberOrBounce();
        $db = $this->db();
        $ok = (new LegalDocuments($db, new MemberAudit($db)))->accept((int)$m['id'], (int)($_POST['document_id'] ?? 0));
        $_SESSION['member_notice'] = $ok ? 'บันทึกการยอมรับแล้ว' : 'เอกสารนี้ไม่ได้เปิดใช้งาน';
        $this->redirect('/account');
    }

    public function delete(): void
    {
        $m = $this->auth()->current();
        if (!$m || !Csrf::verify($_POST['_token'] ?? null) || ($_POST['confirm'] ?? '') !== '1') { $this->redirect('/account'); }
        $db = $this->db(); (new MemberAudit($db))->record(null, 'member', (int)$m['id'], 'account.deleted', 'member', (int)$m['id']);
        $this->auth()->deleteAccount((int)$m['id']);
        $this->redirect('/?account=deleted');
    }

    /** @return array<string,OAuthProvider> */
    private function providers(): array
    {
        global $config;
        $m = $config['members'] ?? [];
        return ProviderRegistry::fromConfig($m, new CurlOAuthHttp(), ROOT_PATH . '/storage/cache/oauth', (string)($m['facebook_graph_version'] ?? 'v19.0'));
    }

    private function db(): \PDO { global $config; return Database::connection($config['db']); }

    /** a signed-in member and a valid CSRF token, or a redirect */
    private function memberOrBounce(): array
    {
        $m = $this->auth()->current();
        if (!$m) { $this->redirect('/account/login'); }
        if (!Csrf::verify($_POST['_token'] ?? null)) { $this->redirect('/account'); }
        return $m;
    }

    private function takeNotice(): ?string { $n = $_SESSION['member_notice'] ?? null; unset($_SESSION['member_notice']); return is_string($n) ? $n : null; }

    private function auth(): MemberAuth { global $config; return new MemberAuth(Database::connection($config['db']), new \App\Services\Listing\ListingPhotoStore(ROOT_PATH . '/storage/uploads/listings')); }

    private function redirectUri(OAuthProvider $p): string { global $config; return rtrim((string)$config['base_url'], '/') . '/account/callback/' . $p->key(); }

    private function limit(string $bucket, int $max): bool
    {
        $l = (new RateLimiter(ROOT_PATH . '/storage/cache/ratelimit'))->attempt($bucket, (string)($_SERVER['REMOTE_ADDR'] ?? ''), $max, 600);
        if ($l['allowed']) { return true; }
        http_response_code(429); header('Retry-After: ' . $l['retry_after']);
        $this->view('errors/429', ['title' => 'ส่งคำขอบ่อยเกินไป', 'retryAfter' => $l['retry_after']]);
        return false;
    }

    private function fail(string $code): never
    {
        $_SESSION['member_flash'] = self::MESSAGES[$code] ?? 'เข้าสู่ระบบไม่สำเร็จ';
        $this->redirect('/account/login');
    }

    private function takeFlash(): ?string { $f = $_SESSION['member_flash'] ?? null; unset($_SESSION['member_flash']); return is_string($f) ? $f : null; }
}
