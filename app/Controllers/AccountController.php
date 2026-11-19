<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\Http\RateLimiter;
use App\Services\Member\CurlOAuthHttp;
use App\Services\Member\MemberAuth;
use App\Services\Member\OAuthProvider;
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
        $this->view('account/profile', ['title' => 'บัญชีของฉัน', 'member' => $m, 'builds' => $this->auth()->builds((int)$m['id'])]);
    }

    public function logout(): void
    {
        if (Csrf::verify($_POST['_token'] ?? null)) { $this->auth()->logout(); }
        $this->redirect('/');
    }

    public function delete(): void
    {
        $m = $this->auth()->current();
        if (!$m || !Csrf::verify($_POST['_token'] ?? null) || ($_POST['confirm'] ?? '') !== '1') { $this->redirect('/account'); }
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
