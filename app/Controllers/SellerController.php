<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Models\Product;
use App\Services\Http\RateLimiter;
use App\Services\Listing\ListingPhotoStore;
use App\Services\Listing\SellerListings;
use App\Services\Market\ListingBrowse;
use App\Services\Market\ListingLifecycle;
use App\Services\Market\SellerProfiles;
use App\Services\Member\MemberAuth;

/**
 * LS-14 Module 9 (OD-5): the seller's side. Only a signed-in member reaches /seller; every listing action is checked against the member who owns
 * it (SellerListings). The public listing page exists but answers 404 until the owner sets SELLER_LISTINGS_PUBLIC=true (HG-16).
 */
final class SellerController extends Controller
{
    private const PHOTO_ERRORS = ['TOO_MANY' => 'มีรูปครบ 10 รูปแล้ว', 'TOO_LARGE' => 'รูปต้องไม่เกิน 5 MB', 'NOT_AN_IMAGE' => 'รับเฉพาะรูป JPG, PNG หรือ WebP', 'UPLOAD_FAILED' => 'อัปโหลดไม่สำเร็จ', 'STORAGE' => 'บันทึกรูปไม่สำเร็จ', 'NOT_FOUND' => 'ไม่พบประกาศนี้'];

    public function index(): void
    {
        $m = $this->member();
        $this->view('seller/index', ['title' => 'ประกาศขายของฉัน', 'listings' => $this->svc()->forMember((int)$m['id']), 'notice' => $this->takeNotice(), 'marketOpen' => $this->publicEnabled()]);
    }

    public function form(string $uuid = ''): void
    {
        $m = $this->member();
        $listing = $uuid !== '' ? $this->svc()->owned($uuid, (int)$m['id']) : null;
        if ($uuid !== '' && !$listing) { $this->notFound(); return; }
        $products = (new Product())->search();
        $this->view('seller/form', ['title' => $listing ? 'แก้ไขประกาศ' : 'ลงประกาศขาย', 'listing' => $listing, 'products' => $products, 'errors' => [], 'old' => $listing ?? [],
            'photos' => $listing ? $this->svc()->photos((int)$listing['id']) : [], 'notice' => $this->takeNotice(), 'marketOpen' => $this->publicEnabled()]);
    }

    public function save(): void
    {
        $m = $this->member();
        if (!Csrf::verify($_POST['_token'] ?? null)) { $this->notice('แบบฟอร์มหมดอายุ กรุณาลองใหม่'); $this->redirect('/seller'); }
        if (!$this->limit('seller-save', 60)) { return; }
        $uuid = isset($_POST['uuid']) && $_POST['uuid'] !== '' ? (string)$_POST['uuid'] : null;
        $r = $this->svc()->save((int)$m['id'], $uuid, (int)($_POST['product_id'] ?? 0), (string)($_POST['asking_price'] ?? ''), $_POST);
        if (!$r['ok']) {
            $listing = $uuid ? $this->svc()->owned($uuid, (int)$m['id']) : null;
            $this->view('seller/form', ['title' => 'ลงประกาศขาย', 'listing' => $listing, 'products' => (new Product())->search(), 'errors' => $r['errors'], 'old' => $_POST,
                'photos' => $listing ? $this->svc()->photos((int)$listing['id']) : [], 'notice' => null]);
            return;
        }
        $this->notice($uuid ? 'บันทึกการแก้ไขแล้ว ประกาศกลับเป็นฉบับร่าง กดส่งตรวจอีกครั้งเมื่อพร้อม' : 'สร้างประกาศแล้ว เพิ่มรูปได้สูงสุด 10 รูป แล้วกดส่งตรวจ');
        $this->redirect('/seller/listings/' . $r['uuid']);
    }

    public function photos(string $uuid): void
    {
        $m = $this->member();
        if (!Csrf::verify($_POST['_token'] ?? null)) { $this->notice('แบบฟอร์มหมดอายุ กรุณาลองใหม่'); $this->redirect('/seller'); }
        if (!$this->limit('seller-photo', 60)) { return; }
        $files = $_FILES['photos'] ?? null; $done = 0; $errors = [];
        if (is_array($files) && is_array($files['name'] ?? null)) {
            foreach (array_keys($files['name']) as $i) {
                $r = $this->svc()->addPhoto((int)$m['id'], $uuid, ['tmp_name' => (string)$files['tmp_name'][$i], 'error' => (int)$files['error'][$i], 'size' => (int)$files['size'][$i]]);
                if ($r['ok']) { $done++; } else { $errors[] = self::PHOTO_ERRORS[$r['error']] ?? 'อัปโหลดไม่สำเร็จ'; if ($r['error'] === 'TOO_MANY') { break; } }
            }
        }
        $this->notice($done . ' รูปถูกเพิ่ม' . ($errors ? ' · ' . implode(' · ', array_unique($errors)) : ''));
        $this->redirect('/seller/listings/' . $uuid);
    }

    public function action(string $uuid): void
    {
        $m = $this->member();
        if (!Csrf::verify($_POST['_token'] ?? null)) { $this->notice('แบบฟอร์มหมดอายุ กรุณาลองใหม่'); $this->redirect('/seller'); }
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'remove_photo') {
            $this->svc()->removePhoto((int)$m['id'], $uuid, (int)($_POST['photo_id'] ?? 0));
            $this->notice('ลบรูปแล้ว'); $this->redirect('/seller/listings/' . $uuid);
        }
        $r = $this->svc()->act((int)$m['id'], $uuid, $action);
        $this->notice($r['ok'] ? ['submit' => 'ส่งประกาศให้ทีมงานตรวจแล้ว', 'withdraw' => 'ถอนประกาศแล้ว', 'delete' => 'ลบประกาศแล้ว'][$action] : 'ทำรายการไม่สำเร็จ');
        $this->redirect($r['ok'] && $action === 'delete' ? '/seller' : '/seller/listings/' . $uuid);
    }

    /** A photo: to its owner, to an admin, or to anyone when the listing is public. Never cached by shared caches. */
    public function photo(string $uuid, string $file): void
    {
        $l = $this->svc()->find($uuid);
        $member = (new MemberAuth($this->db()))->current();
        $admin = (new \App\Services\Auth\AuthService())->currentUser();
        $allowed = $l && (($member && (int)$member['id'] === (int)$l['member_id']) || ($admin && (new \App\Services\Auth\AuthorizationService())->can($admin, 'listings.moderate'))
            || $this->svc()->publicListing($uuid, $this->publicEnabled()) !== null);
        $path = $allowed ? $this->store()->path($uuid, $file) : null;
        if ($path === null) { $this->notFound(); return; }
        header('Content-Type: image/jpeg'); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, max-age=3600'); header('Content-Length: ' . filesize($path));
        readfile($path);
    }

    public function show(string $uuid): void
    {
        $l = $this->svc()->publicListing($uuid, $this->publicEnabled());
        if (!$l) { $this->notFound(); return; }
        $market = null;
        $member = null;
        $saved = false;
        try {
            $db = $this->db();
            $browse = new ListingBrowse($db);
            $market = $browse->one($uuid);
            $member = (new MemberAuth($db))->current();
            $saved = $member !== null && $browse->isSaved((int)$member['id'], (int)$l['id']);
        } catch (\PDOException $e) {
            error_log('LISTING_EXTRAS_UNAVAILABLE ' . $e->getCode());   // a host without migrations 040-042 still shows the listing
        }
        $notice = $_SESSION['market_notice'] ?? null;
        unset($_SESSION['market_notice']);
        $this->view('seller/show', ['title' => (string)$l['full_name'], 'listing' => $l, 'photos' => $this->svc()->photos((int)$l['id']),
            'market' => $market, 'member' => $member, 'saved' => $saved, 'notice' => is_string($notice) ? $notice : null]);
    }

    /** LS-90 D2: reserved, sold, available again, or renew. Closed with the public listings (SELLER_LISTINGS_PUBLIC). */
    public function sale(string $uuid): void
    {
        if (!$this->marketOpen()) { return; }
        $m = $this->member();
        if (!Csrf::verify($_POST['_token'] ?? null)) { $this->notice('แบบฟอร์มหมดอายุ กรุณาลองใหม่'); $this->redirect('/seller'); }
        $listing = $this->svc()->owned($uuid, (int)$m['id']);
        if (!$listing) { $this->notFound(); return; }
        $to = is_scalar($_POST['to'] ?? null) ? (string)$_POST['to'] : '';
        $life = ListingLifecycle::fromConfig($this->db());
        $r = $to === 'renew' ? $life->renew($listing) : $life->changeSale($listing, $to);
        $this->notice($r['ok'] ? ($to === 'renew' ? 'ต่ออายุประกาศแล้ว' : 'อัปเดตสถานะการขายแล้ว')
            : ($r['error'] === 'TOO_EARLY' ? 'ต่ออายุได้เมื่อเหลือไม่เกิน ' . ListingLifecycle::RENEW_WINDOW_DAYS . ' วัน' : 'ทำรายการนี้ในสถานะปัจจุบันไม่ได้'));
        $this->redirect('/seller/listings/' . $uuid);
    }

    /** LS-90 D1: the seller's profile */
    public function profile(): void
    {
        if (!$this->marketOpen()) { return; }
        $m = $this->member();
        $profile = (new SellerProfiles($this->db()))->get((int)$m['id']);
        $this->view('seller/profile', ['title' => 'โปรไฟล์ผู้ขาย', 'profile' => $profile, 'errors' => [], 'old' => $profile ?? [], 'notice' => $this->takeNotice()]);
    }

    public function saveProfile(): void
    {
        if (!$this->marketOpen()) { return; }
        $m = $this->member();
        if (!Csrf::verify($_POST['_token'] ?? null)) { $this->notice('แบบฟอร์มหมดอายุ กรุณาลองใหม่'); $this->redirect('/seller/profile'); }
        if (!$this->limit('seller-profile', 20)) { return; }
        $profiles = new SellerProfiles($this->db());
        $r = $profiles->save((int)$m['id'], $_POST);
        if (!$r['ok']) {
            $this->view('seller/profile', ['title' => 'โปรไฟล์ผู้ขาย', 'profile' => $profiles->get((int)$m['id']), 'errors' => $r['errors'], 'old' => array_filter($_POST, 'is_scalar'), 'notice' => null]);
            return;
        }
        $this->notice('บันทึกโปรไฟล์แล้ว');
        $this->redirect('/seller/profile');
    }

    public function verifyProfile(): void
    {
        if (!$this->marketOpen()) { return; }
        $m = $this->member();
        if (!Csrf::verify($_POST['_token'] ?? null)) { $this->notice('แบบฟอร์มหมดอายุ กรุณาลองใหม่'); $this->redirect('/seller/profile'); }
        if (!$this->limit('seller-profile', 20)) { return; }
        $r = (new SellerProfiles($this->db()))->requestVerification((int)$m['id']);
        $this->notice($r['ok'] ? 'ส่งคำขอยืนยันแล้ว' : 'ส่งคำขอในสถานะปัจจุบันไม่ได้');
        $this->redirect('/seller/profile');
    }

    private function marketOpen(): bool
    {
        if ($this->publicEnabled()) { return true; }
        $this->notFound();
        return false;
    }

    private function member(): array
    {
        $m = (new MemberAuth($this->db()))->current();
        if (!$m) { $this->redirect('/account/login'); }
        return $m;
    }

    private function publicEnabled(): bool { global $config; return (bool)($config['seller']['listings_public'] ?? false); }
    private function db(): \PDO { global $config; return Database::connection($config['db']); }
    private function store(): ListingPhotoStore { return new ListingPhotoStore(ROOT_PATH . '/storage/uploads/listings'); }
    private function svc(): SellerListings { return new SellerListings($this->db(), $this->store()); }
    private function notFound(): void { http_response_code(404); $this->view('errors/404', ['title' => 'ไม่พบหน้า']); }
    private function notice(string $t): void { $_SESSION['seller_notice'] = $t; }
    private function takeNotice(): ?string { $n = $_SESSION['seller_notice'] ?? null; unset($_SESSION['seller_notice']); return is_string($n) ? $n : null; }

    private function limit(string $bucket, int $max): bool
    {
        $l = (new RateLimiter(ROOT_PATH . '/storage/cache/ratelimit'))->attempt($bucket, (string)($_SERVER['REMOTE_ADDR'] ?? ''), $max, 600);
        if ($l['allowed']) { return true; }
        http_response_code(429); header('Retry-After: ' . $l['retry_after']);
        $this->view('errors/429', ['title' => 'ส่งคำขอบ่อยเกินไป', 'retryAfter' => $l['retry_after']]);
        return false;
    }
}
