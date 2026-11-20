<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Models\Product;
use App\Services\Community\ContentGuard;
use App\Services\Community\Forum;
use App\Services\Http\RateLimiter;
use App\Services\Member\MemberAuth;

/**
 * LS-24 M9: the public side of the community forum. Every route answers 404 unless COMMUNITY_FORUM_ENABLED=true. Reading is open; writing, voting and flagging need a
 * signed-in member (the member system of LS-14), a CSRF token and pass a per-address rate limit. All rules live in Forum and ContentGuard; this class only moves
 * data in and out and says what went wrong in Thai. Nothing here touches a price.
 */
final class CommunityController extends Controller
{
    private const ERRORS = ['NOT_FOUND' => 'ไม่พบรายการนี้', 'LOCKED' => 'กระทู้นี้ปิดการตอบแล้ว', 'RATE_LIMIT' => 'วันนี้โพสต์ครบจำนวนแล้ว ลองใหม่พรุ่งนี้', 'DUPLICATE' => 'คุณโพสต์ข้อความนี้ไปแล้ว',
        'BAD_PRODUCT' => 'เลือกรุ่นจากรายการ', 'BAD_PARENT' => 'ตอบข้อความนี้ไม่ได้', 'BAD_PRICE' => 'ราคาที่จ่ายต้องเป็นตัวเลข 1 ถึง 10,000,000 บาท', 'BAD_SOURCE' => 'เลือกแหล่งที่ซื้อ', 'BAD_CONDITION' => 'เลือกสภาพตอนได้รับ',
        'BAD_ISSUE' => 'เลือกว่ามีปัญหาใน 30 วันหรือไม่', 'BAD_AGAIN' => 'เลือกว่าจะซื้อซ้ำหรือไม่', 'ALREADY_REVIEWED' => 'คุณรีวิวรุ่นนี้ไปแล้ว', 'OWN_CONTENT' => 'ทำรายการนี้กับโพสต์ของตัวเองไม่ได้',
        'ALREADY_FLAGGED' => 'คุณแจ้งรายการนี้ไปแล้ว', 'BAD_REASON' => 'เลือกเหตุผลที่แจ้ง'];

    public function index(): void
    {
        if (!$this->on()) { $this->notFound(); return; }
        $slug = is_string($_GET['product'] ?? null) ? $_GET['product'] : '';
        $product = $slug !== '' ? (new Product())->findBySlug($slug) : null;
        $page = is_string($_GET['page'] ?? null) && ctype_digit($_GET['page']) ? min((int)$_GET['page'], 500) : 1;
        $list = $this->forum()->threads($product ? (int)$product['id'] : null, $page);
        $this->view('community/index', ['title' => 'ชุมชน', 'list' => $list, 'product' => $product, 'page' => $page, 'member' => $this->member(), 'flash' => $this->takeFlash(), 'products' => (new Product())->search()]);
    }

    public function thread(string $id): void
    {
        if (!$this->on()) { $this->notFound(); return; }
        $t = ctype_digit($id) ? $this->forum()->thread((int)$id) : null;
        if (!$t) { $this->notFound(); return; }
        $this->view('community/thread', ['title' => (string)$t['thread']['title'], 't' => $t, 'member' => $this->member(), 'flash' => $this->takeFlash(), 'reasons' => Forum::REASONS]);
    }

    public function newThread(): void
    {
        $m = $this->gate(); if ($m === null) { return; } $pid = ctype_digit((string)($_POST['product_id'] ?? '')) && (int)$_POST['product_id'] > 0 ? (int)$_POST['product_id'] : null;
        $r = $this->forum()->createThread((int)$m['id'], $pid, (string)($_POST['title'] ?? ''), (string)($_POST['body'] ?? ''));
        if ($r['ok']) { $this->redirect('/community/thread/' . $r['id']); }
        $this->fail($r['errors'], '/community');
    }

    public function reply(string $id): void
    {
        $m = $this->gate(); if ($m === null) { return; } $tid = ctype_digit($id) ? (int)$id : 0;
        $parent = ctype_digit((string)($_POST['parent_id'] ?? '')) && (int)$_POST['parent_id'] > 0 ? (int)$_POST['parent_id'] : null;
        $r = $this->forum()->createPost((int)$m['id'], $tid, (string)($_POST['body'] ?? ''), $parent);
        if ($r['ok']) { $this->redirect('/community/thread/' . $tid . '#post-' . $r['id']); }
        $this->fail($r['errors'], '/community/thread/' . $tid);
    }

    public function review(): void
    {
        $m = $this->gate(); if ($m === null) { return; } $pid = ctype_digit((string)($_POST['product_id'] ?? '')) ? (int)$_POST['product_id'] : 0;
        $p = $pid > 0 ? (new Product())->find($pid) : null;
        $back = $p ? '/price/' . rawurlencode((string)$p['slug']) . '#reviews' : '/community';
        $r = $this->forum()->createReview((int)$m['id'], $pid, array_map(static fn($v) => is_scalar($v) ? (string)$v : '', $_POST));
        if ($r['ok']) { $this->flash('ขอบคุณที่แบ่งปันประสบการณ์'); $this->redirect($back); }
        $this->fail($r['errors'], $back);
    }

    public function vote(): void
    {
        $m = $this->gate(); if ($m === null) { return; } $type = (string)($_POST['type'] ?? ''); $id = ctype_digit((string)($_POST['id'] ?? '')) ? (int)$_POST['id'] : 0;
        $r = $this->forum()->vote((int)$m['id'], $type, $id);
        if (!$r['ok']) { $this->flash(self::ERRORS[$r['error']] ?? 'ทำรายการไม่สำเร็จ'); }
        $this->redirect($this->back());
    }

    public function flagItem(): void
    {
        $m = $this->gate(); if ($m === null) { return; } $type = (string)($_POST['type'] ?? ''); $id = ctype_digit((string)($_POST['id'] ?? '')) ? (int)$_POST['id'] : 0;
        $r = $this->forum()->flag((int)$m['id'], $type, $id, (string)($_POST['reason'] ?? ''));
        $this->flash($r['ok'] ? 'ส่งเรื่องให้ผู้ดูแลตรวจแล้ว ขอบคุณที่ช่วยดูแลชุมชน' : (self::ERRORS[$r['error']] ?? 'ทำรายการไม่สำเร็จ'));
        $this->redirect($this->back());
    }

    /** @return ?array<string,mixed> the signed-in member after every check; null when the request was already answered (404, 429, 419) */
    private function gate(): ?array
    {
        if (!$this->on()) { $this->notFound(); return null; }
        $limit = (new RateLimiter(ROOT_PATH . '/storage/cache/ratelimit'))->attempt('community-write', (string)($_SERVER['REMOTE_ADDR'] ?? ''), 40, 600);
        if (!$limit['allowed']) { http_response_code(429); header('Retry-After: ' . $limit['retry_after']); $this->view('errors/429', ['title' => 'ส่งคำขอบ่อยเกินไป', 'retryAfter' => $limit['retry_after']]); return null; }
        if (!Csrf::verify($_POST['_token'] ?? null)) { http_response_code(419); $this->view('errors/419', ['title' => 'แบบฟอร์มหมดอายุ']); return null; }
        $m = $this->member();
        if (!$m) { $this->flash('ต้องเข้าสู่ระบบก่อนร่วมสนทนา'); $this->redirect('/account/login'); }
        return $m;
    }

    private function fail(array $codes, string $back): never
    {
        $msgs = [];
        foreach ($codes as $c) { $msgs[] = self::ERRORS[$c] ?? ContentGuard::MESSAGES[$c] ?? 'ส่งไม่สำเร็จ'; }
        $this->flash(implode(' · ', array_unique($msgs)));
        $this->redirect($back);
    }

    /** where to go back to: only a path on this site (never an address from the request) */
    private function back(): string
    {
        $b = is_string($_POST['back'] ?? null) ? $_POST['back'] : '';
        return preg_match('#^/(community(?:/thread/\d+)?|price/[a-z0-9-]+)(?:\#[a-z0-9-]*)?$#', $b) === 1 ? $b : '/community';
    }

    private function flash(string $m): void { $_SESSION['community_flash'] = $m; }
    private function takeFlash(): ?string { $f = $_SESSION['community_flash'] ?? null; unset($_SESSION['community_flash']); return is_string($f) ? $f : null; }
    private function on(): bool { global $config; return Forum::enabled($config); }
    private function forum(): Forum { global $config; return new Forum(Database::connection($config['db'])); }
    private function member(): ?array { global $config; return (new MemberAuth(Database::connection($config['db'])))->current(); }
    private function notFound(): void { http_response_code(404); $this->view('errors/404', ['title' => 'ไม่พบหน้า']); }
}
