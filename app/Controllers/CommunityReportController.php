<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Models\Product;
use App\Services\Community\CommunityReports;
use App\Services\Http\RateLimiter;

/**
 * LS-21 (R-11, ADR-006 option A): the public "report a problem" form on a product. Every route answers 404 unless COMMUNITY_REPORTS_ENABLED=true AND a real key is
 * set (the owner approves the privacy wording first). A report is a signal: it is stored for a person to check and changes no price (CommunityReports).
 * Only choices and numbers are accepted, so there is no text box. CSRF, a honeypot and a rate limit sit in front of the insert.
 */
final class CommunityReportController extends Controller
{
    private const ERRORS = [
        'BAD_TYPE' => 'เลือกประเภทรายงานจากรายการ',
        'BAD_PRODUCT' => 'ไม่พบสินค้านี้',
        'BAD_SHOULD_BE' => 'เลือกรุ่นที่ถูกต้องจากรายการ หรือเลือก "ไม่มีในแคตตาล็อก"',
        'BAD_DIRECTION' => 'เลือกว่าช่วงราคาดูสูงไปหรือต่ำไป',
        'BAD_PRICE' => 'ราคาต้องเป็นตัวเลข 1 ถึง 10,000,000 บาท',
        'BAD_MONTH' => 'เลือกเดือนที่ขายได้ ย้อนหลังไม่เกิน 12 เดือน',
        'BAD_CONDITION' => 'เลือกสภาพสินค้าจากรายการ',
        'BAD_WARRANTY' => 'ประกันต้องเป็นตัวเลข 0 ถึง 120 เดือน หรือเว้นว่าง',
        'BAD_SPEC' => 'เลือกสเปกที่ไม่ถูกต้องจากรายการ',
        'PAYLOAD_TOO_LARGE' => 'ข้อมูลที่ส่งมายาวเกินไป',
        'PRODUCT_DAILY_LIMIT' => 'วันนี้คุณแจ้งรุ่นนี้ครบจำนวนแล้ว กรุณาลองใหม่พรุ่งนี้',
    ];

    public function form(string $slug): void
    {
        if (!$this->on()) { $this->notFound(); return; }
        $product = (new Product())->findBySlug($slug);
        if (!$product) { $this->notFound(); return; }
        $type = (string)($_GET['type'] ?? '');
        $this->page($product, isset(CommunityReports::TYPES[$type]) ? $type : null, [], null, false);
    }

    public function submit(string $slug): void
    {
        if (!$this->on()) { $this->notFound(); return; }
        $product = (new Product())->findBySlug($slug);
        if (!$product) { $this->notFound(); return; }
        $type = (string)($_POST['type'] ?? '');
        if (!Csrf::verify($_POST['_token'] ?? null)) { http_response_code(419); $this->view('errors/419', ['title' => 'แบบฟอร์มหมดอายุ']); return; }
        if (!$this->limit('community-report', 5)) { return; }
        // honeypot: a field no person sees. A bot that fills it gets the normal thank-you page and nothing is stored.
        if (trim((string)($_POST['website'] ?? '')) !== '') { $this->page($product, null, [], null, true); return; }
        $in = array_map(static fn($v) => is_scalar($v) ? (string)$v : '', $_POST);
        $r = $this->svc()->submit((int)$product['id'], $type, $in, (string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($r['ok']) { $this->page($product, null, [], null, true); return; }
        $this->page($product, isset(CommunityReports::TYPES[$type]) ? $type : null, $in, self::ERRORS[$r['error']] ?? 'ส่งรายงานไม่สำเร็จ', false);
    }

    private function page(array $product, ?string $type, array $old, ?string $error, bool $done): void
    {
        $others = [];
        if ($type === 'wrong_product') {   // the choice list for "it should be": every active model except this one
            foreach ((new Product())->search() as $p) { if ((int)$p['id'] !== (int)$product['id']) { $others[] = ['id' => (int)$p['id'], 'name' => (string)$p['full_name']]; } }
        }
        $this->view('community/report', ['title' => 'แจ้งข้อมูลที่ไม่ถูกต้อง · ' . $product['full_name'], 'product' => $product, 'type' => $type, 'old' => $old, 'error' => $error, 'done' => $done, 'others' => $others,
            'types' => CommunityReports::TYPES, 'specs' => CommunityReports::SPECS, 'conditions' => CommunityReports::CONDITIONS, 'months' => $this->months()]);
    }

    /** the last 13 months as value => Thai label (Buddhist era year), this month first */
    private function months(): array
    {
        $names = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
        $out = []; $d = new \DateTimeImmutable('first day of this month');
        for ($i = 0; $i <= 12; $i++) { $m = $d->modify("-$i months"); $out[$m->format('Y-m')] = $names[(int)$m->format('n') - 1] . ' ' . ((int)$m->format('Y') + 543); }
        return $out;
    }

    private function on(): bool { global $config; return CommunityReports::enabled($config); }
    private function svc(): CommunityReports { global $config; return new CommunityReports(Database::connection($config['db']), (string)$config['community_reports']['hmac_key']); }
    private function notFound(): void { http_response_code(404); $this->view('errors/404', ['title' => 'ไม่พบหน้า']); }

    private function limit(string $bucket, int $max): bool
    {
        $l = (new RateLimiter(ROOT_PATH . '/storage/cache/ratelimit'))->attempt($bucket, (string)($_SERVER['REMOTE_ADDR'] ?? ''), $max, 3600);
        if ($l['allowed']) { return true; }
        http_response_code(429); header('Retry-After: ' . $l['retry_after']);
        $this->view('errors/429', ['title' => 'ส่งคำขอบ่อยเกินไป', 'retryAfter' => $l['retry_after']]);
        return false;
    }
}
