<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\Http\RateLimiter;
use App\Services\Listing\ListingPhotoStore;
use App\Services\Listing\SellerCheck;
use App\Services\Member\MemberAuth;

/**
 * LS-14 Module 8 (OD-3, ADR-007): the public lookup, the dispute form and the member's report form. Every route answers 404 unless
 * SELLER_CHECK_ENABLED=true AND SELLER_CHECK_HMAC_KEY is a real key, so the feature cannot be half-on (HG-14, HG-18).
 */
final class SellerCheckController extends Controller
{
    private const ERRORS = ['BAD_TYPE' => 'เลือกชนิดข้อมูลที่ต้องการค้น', 'BAD_IDENTIFIER' => 'หมายเลขไม่ถูกต้อง กรอกเฉพาะตัวเลขของเบอร์โทร (9-10 หลัก) หรือเลขบัญชี (8-15 หลัก)',
        'BAD_PROBLEM' => 'เลือกประเภทปัญหาจากรายการ', 'NOTE_HAS_CONTACT' => 'หมายเหตุห้ามมีเบอร์โทร ลิงก์ ไอดีติดต่อ หรือตัวเลขยาว', 'EXPLANATION_REQUIRED' => 'อธิบายอย่างน้อย 10 ตัวอักษร',
        'BAD_EMAIL' => 'อีเมลไม่ถูกต้อง'];

    public function lookup(): void
    {
        if (!$this->on()) { $this->notFound(); return; }
        $result = null; $error = null; $type = (string)($_GET['type'] ?? 'phone'); $q = (string)($_GET['q'] ?? '');
        if ($q !== '') {
            if (!$this->limit('seller-check-lookup', 10)) { return; }
            $r = $this->svc()->lookup($type, $q);
            if ($r['ok']) { $result = $r; } else { $error = self::ERRORS[$r['error']] ?? 'ค้นหาไม่ได้'; }
        }
        $this->view('seller-check/lookup', ['title' => 'เช็กเบอร์หรือบัญชีผู้ขาย', 'type' => $type, 'result' => $result, 'error' => $error, 'searched' => $q !== '']);
    }

    public function disputeForm(): void
    {
        if (!$this->on()) { $this->notFound(); return; }
        $this->view('seller-check/dispute', ['title' => 'โต้แย้งรายงาน', 'error' => null, 'done' => false, 'old' => []]);
    }

    public function dispute(): void
    {
        if (!$this->on()) { $this->notFound(); return; }
        if (!Csrf::verify($_POST['_token'] ?? null)) { $this->redirect('/seller-check/dispute'); }
        if (!$this->limit('seller-check-dispute', 5)) { return; }
        $r = $this->svc()->dispute((string)($_POST['type'] ?? ''), (string)($_POST['q'] ?? ''), (string)($_POST['explanation'] ?? ''), (string)($_POST['email'] ?? ''));
        $this->view('seller-check/dispute', ['title' => 'โต้แย้งรายงาน', 'error' => $r['ok'] ? null : (self::ERRORS[$r['error']] ?? 'ส่งไม่สำเร็จ'), 'done' => $r['ok'], 'old' => $r['ok'] ? [] : ['type' => $_POST['type'] ?? '', 'explanation' => $_POST['explanation'] ?? '', 'email' => $_POST['email'] ?? '']]);
    }

    public function reportForm(): void
    {
        if (!$this->on()) { $this->notFound(); return; }
        $this->member();
        $this->view('seller-check/report', ['title' => 'แจ้งปัญหาผู้ขาย', 'error' => null, 'done' => false, 'old' => []]);
    }

    public function report(): void
    {
        if (!$this->on()) { $this->notFound(); return; }
        $m = $this->member();
        if (!Csrf::verify($_POST['_token'] ?? null)) { $this->redirect('/seller-check/report'); }
        if (!$this->limit('seller-check-report', 5)) { return; }
        $r = $this->svc()->report((int)$m['id'], (string)($_POST['type'] ?? ''), (string)($_POST['q'] ?? ''), (string)($_POST['problem'] ?? ''), (string)($_POST['note'] ?? ''));
        if ($r['ok']) { $this->saveEvidence($r['uuid']); }
        $this->view('seller-check/report', ['title' => 'แจ้งปัญหาผู้ขาย', 'error' => $r['ok'] ? null : (self::ERRORS[$r['error']] ?? 'ส่งไม่สำเร็จ'), 'done' => $r['ok'], 'old' => $r['ok'] ? [] : ['type' => $_POST['type'] ?? '', 'problem' => $_POST['problem'] ?? '', 'note' => $_POST['note'] ?? '']]);
    }

    /** Evidence images (max 5, same checks and EXIF removal as listing photos), admin only. */
    private function saveEvidence(string $reportUuid): void
    {
        $files = $_FILES['evidence'] ?? null;
        if (!is_array($files) || !is_array($files['name'] ?? null)) { return; }
        $db = $this->db(); $store = new ListingPhotoStore(ROOT_PATH . '/storage/uploads/reports');
        $id = (int)$db->query("SELECT id FROM seller_reports WHERE uuid = " . $db->quote($reportUuid))->fetchColumn(); $n = 0;
        foreach (array_keys($files['name']) as $i) {
            if ($n >= 5) { break; }
            $r = $store->store($reportUuid, ['tmp_name' => (string)$files['tmp_name'][$i], 'error' => (int)$files['error'][$i], 'size' => (int)$files['size'][$i]], $n);
            if ($r['ok']) { $db->prepare('INSERT INTO seller_report_evidence (report_id, file_name) VALUES (:r, :f)')->execute(['r' => $id, 'f' => $r['file_name']]); $n++; }
        }
    }

    private function on(): bool { global $config; return !empty($config['seller_check']['enabled']) && strlen((string)($config['seller_check']['hmac_key'] ?? '')) >= 32; }
    private function member(): array { $m = (new MemberAuth($this->db()))->current(); if (!$m) { $this->redirect('/account/login'); } return $m; }
    private function db(): \PDO { global $config; return Database::connection($config['db']); }
    private function svc(): SellerCheck { global $config; return new SellerCheck($this->db(), (string)$config['seller_check']['hmac_key']); }
    private function notFound(): void { http_response_code(404); $this->view('errors/404', ['title' => 'ไม่พบหน้า']); }

    private function limit(string $bucket, int $max): bool
    {
        $l = (new RateLimiter(ROOT_PATH . '/storage/cache/ratelimit'))->attempt($bucket, (string)($_SERVER['REMOTE_ADDR'] ?? ''), $max, 600);
        if ($l['allowed']) { return true; }
        http_response_code(429); header('Retry-After: ' . $l['retry_after']);
        $this->view('errors/429', ['title' => 'ส่งคำขอบ่อยเกินไป', 'retryAfter' => $l['retry_after']]);
        return false;
    }
}
