<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\Http\RateLimiter;
use App\Services\Market\ListingBrowse;
use App\Services\Member\MemberAuth;

/**
 * LS-90 D3: browsing seller listings, saving them and reporting them. Every action answers 404 and writes nothing unless
 * SELLER_LISTINGS_PUBLIC is on (HG-16). Saving and reporting need a signed-in member and a valid form token.
 */
final class MarketController extends Controller
{
    private const REPORT_ERRORS = ['NOT_FOUND' => 'ไม่พบประกาศนี้', 'BAD_REASON' => 'เลือกเหตุผลก่อน', 'OWN_LISTING' => 'รายงานประกาศของตัวเองไม่ได้', 'ALREADY_REPORTED' => 'คุณรายงานประกาศนี้ไปแล้ว'];

    public function browse(): void
    {
        if (!$this->open()) { return; }
        $filters = ListingBrowse::filters($_GET);
        $page = max(1, (int)(is_scalar($_GET['page'] ?? null) ? $_GET['page'] : 1));
        $db = $this->db();
        $this->view('listings/browse', ['title' => 'ประกาศขายคอมมือสอง', 'result' => (new ListingBrowse($db))->search($filters, $page), 'filters' => $filters,
            'categories' => $db->query('SELECT slug, name FROM product_categories ORDER BY name')->fetchAll(), 'member' => (new MemberAuth($db))->current(), 'notice' => $this->takeNotice()]);
    }

    public function saved(): void
    {
        if (!$this->open()) { return; }
        $member = $this->member();
        $browse = new ListingBrowse($this->db());
        $this->view('listings/saved', ['title' => 'ประกาศและการค้นหาที่บันทึกไว้', 'listings' => $browse->saved((int)$member['id']), 'searches' => $browse->searches((int)$member['id']), 'notice' => $this->takeNotice()]);
    }

    public function toggleSaved(string $uuid): void
    {
        if (!$this->open() || !$this->post('listing-save', 120)) { return; }
        $member = $this->member();
        $r = (new ListingBrowse($this->db()))->toggleSaved((int)$member['id'], $uuid);
        $this->notice($r['ok'] ? ($r['saved'] ? 'บันทึกประกาศแล้ว' : 'เอาออกจากรายการที่บันทึกแล้ว') : ($r['error'] === 'LIMIT' ? 'บันทึกได้ไม่เกิน ' . ListingBrowse::MAX_SAVED . ' ประกาศ' : 'ไม่พบประกาศนี้'));
        $this->redirect(($_POST['from'] ?? '') === 'saved' ? '/listings/saved' : '/listings/' . $this->safeUuid($uuid));
    }

    public function report(string $uuid): void
    {
        if (!$this->open() || !$this->post('listing-report', 10)) { return; }
        $member = $this->member();
        $text = static fn(string $k): string => is_scalar($_POST[$k] ?? null) ? (string)$_POST[$k] : '';
        $r = (new ListingBrowse($this->db()))->report((int)$member['id'], $uuid, $text('reason'), $text('note'));
        $this->notice($r['ok'] ? 'รับรายงานแล้ว ผู้ดูแลจะตรวจ' : ($r['error'] === 'BAD_NOTE' ? implode(' ', $r['errors']) : (self::REPORT_ERRORS[$r['error']] ?? 'ส่งรายงานไม่สำเร็จ')));
        $this->redirect($r['error'] === 'NOT_FOUND' ? '/listings' : '/listings/' . $this->safeUuid($uuid));
    }

    public function saveSearch(): void
    {
        if (!$this->open() || !$this->post('search-save', 30)) { return; }
        $member = $this->member();
        $r = (new ListingBrowse($this->db()))->saveSearch((int)$member['id'], $_POST);
        $this->notice($r['ok'] ? 'บันทึกการค้นหาแล้ว' : ($r['error'] === 'LIMIT' ? 'บันทึกการค้นหาได้ไม่เกิน ' . ListingBrowse::MAX_SEARCHES . ' รายการ' : 'เลือกตัวกรองอย่างน้อยหนึ่งอย่างก่อนบันทึก'));
        $filters = ListingBrowse::filters($_POST);
        $this->redirect('/listings' . ($filters ? '?' . http_build_query($filters) : ''));
    }

    public function deleteSearch(string $id): void
    {
        if (!$this->open() || !$this->post('search-save', 30)) { return; }
        $member = $this->member();
        $this->notice((new ListingBrowse($this->db()))->deleteSearch((int)$member['id'], (int)$id) ? 'ลบการค้นหาที่บันทึกแล้ว' : 'ไม่พบรายการนี้');
        $this->redirect('/listings/saved');
    }

    private function open(): bool
    {
        global $config;
        if (!empty($config['seller']['listings_public'])) { return true; }
        http_response_code(404);
        $this->view('errors/404', ['title' => 'ไม่พบหน้า']);
        return false;
    }

    /** a form token and a per-address limit for every write */
    private function post(string $bucket, int $max): bool
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            $this->view('errors/419', ['title' => 'แบบฟอร์มหมดอายุ']);
            return false;
        }
        $limit = (new RateLimiter(ROOT_PATH . '/storage/cache/ratelimit'))->attempt($bucket, (string)($_SERVER['REMOTE_ADDR'] ?? ''), $max, 600);
        if ($limit['allowed']) { return true; }
        http_response_code(429);
        header('Retry-After: ' . $limit['retry_after']);
        $this->view('errors/429', ['title' => 'ส่งคำขอบ่อยเกินไป', 'retryAfter' => $limit['retry_after']]);
        return false;
    }

    private function member(): array
    {
        $member = (new MemberAuth($this->db()))->current();
        if (!$member) { $this->redirect('/account/login'); }
        return $member;
    }

    private function safeUuid(string $uuid): string { return preg_match('/^[a-f0-9]{32}$/', $uuid) === 1 ? $uuid : ''; }
    private function db(): \PDO { global $config; return Database::connection($config['db']); }
    private function notice(string $text): void { $_SESSION['market_notice'] = $text; }
    private function takeNotice(): ?string { $n = $_SESSION['market_notice'] ?? null; unset($_SESSION['market_notice']); return is_string($n) ? $n : null; }
}
