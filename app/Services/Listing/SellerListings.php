<?php
declare(strict_types=1);

namespace App\Services\Listing;

use App\Services\Auth\AuthService;
use PDO;

/**
 * LS-14 Module 9 (OD-5): a member's own listings. Every write checks that the listing belongs to the member. Editing a listing sends it back to
 * draft, so a change is never published without a new moderation. Moderation (approve / reject) is an admin action, audited with the actor.
 * Approval does not create an observation or change a price (HG-17 decides whether approved listings become market evidence).
 */
final class SellerListings
{
    public const MAX_PRICE = 10000000;
    private const COLUMNS = ['condition_percent', 'under_warranty', 'warranty_months', 'product_age_months', 'province', 'seller_warranty_terms', 'is_defective',
        'has_repair_history', 'shipping_option', 'shipping_cost', 'has_test_result', 'test_result_note', 'defect_codes', 'defect_note'];

    public function __construct(private PDO $db, private ?ListingPhotoStore $photos = null) {}

    /** @return list<array> */
    public function forMember(int $memberId): array
    {
        $s = $this->db->prepare("SELECT l.*, p.full_name, p.slug, (SELECT COUNT(*) FROM seller_listing_photos x WHERE x.listing_id = l.id) photo_count
            FROM seller_listings l JOIN products p ON p.id = l.product_id WHERE l.member_id = :m ORDER BY l.id DESC");
        $s->execute(['m' => $memberId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(string $uuid): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $uuid)) { return null; }
        $s = $this->db->prepare('SELECT l.*, p.full_name, p.slug, p.category_id FROM seller_listings l JOIN products p ON p.id = l.product_id WHERE l.uuid = :u');
        $s->execute(['u' => $uuid]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function owned(string $uuid, int $memberId): ?array
    {
        $r = $this->find($uuid);
        return $r && (int)$r['member_id'] === $memberId ? $r : null;
    }

    /** @return list<array> */
    public function photos(int $listingId): array
    {
        $s = $this->db->prepare('SELECT id, file_name, width, height FROM seller_listing_photos WHERE listing_id = :l ORDER BY sort_order, id');
        $s->execute(['l' => $listingId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create (uuid null) or edit the member's own listing.
     * @return array{ok:bool,uuid?:string,errors?:array<string,string>}
     */
    public function save(int $memberId, ?string $uuid, int $productId, string $price, array $input): array
    {
        $errors = [];
        $p = filter_var(str_replace(',', '', trim($price)), FILTER_VALIDATE_FLOAT);
        if ($p === false || $p < 1 || $p > self::MAX_PRICE) { $errors['asking_price'] = 'ราคาต้องอยู่ระหว่าง 1 ถึง 10,000,000 บาท'; }
        $q = $this->db->prepare('SELECT 1 FROM products WHERE id = :id AND is_active = 1');
        $q->execute(['id' => $productId]);
        if (!$q->fetchColumn()) { $errors['product_id'] = 'กรุณาเลือกรุ่นสินค้า'; }
        $attrs = ListingAttributes::parse($input);
        $errors += $attrs['errors'];
        $existing = null;
        if ($uuid !== null) {
            $existing = $this->owned($uuid, $memberId);
            if (!$existing || $existing['status'] === 'withdrawn') { return ['ok' => false, 'errors' => ['listing' => 'ไม่พบประกาศนี้ในบัญชีของคุณ']]; }
        }
        if ($errors) { return ['ok' => false, 'errors' => $errors]; }
        $vals = ['product_id' => $productId, 'asking_price' => round((float)$p, 2)];
        foreach (self::COLUMNS as $c) { $vals[$c] = $attrs['values'][$c]; }
        if ($existing === null) {
            $uuid = bin2hex(random_bytes(16));
            $cols = array_keys($vals);
            $this->db->prepare('INSERT INTO seller_listings (uuid, member_id, status, ' . implode(', ', $cols) . ') VALUES (:uuid, :member_id, \'draft\', :' . implode(', :', $cols) . ')')
                ->execute($vals + ['uuid' => $uuid, 'member_id' => $memberId]);
        } else {
            $set = implode(', ', array_map(static fn($c) => "$c = :$c", array_keys($vals)));
            // any change goes back to draft: it must be moderated again before it can be shown
            $this->db->prepare("UPDATE seller_listings SET $set, status = 'draft', moderated_by = NULL, moderated_at = NULL, moderation_note = NULL WHERE id = :id AND member_id = :member_id")
                ->execute($vals + ['id' => (int)$existing['id'], 'member_id' => $memberId]);
        }
        return ['ok' => true, 'uuid' => $uuid];
    }

    /** @return array{ok:bool,error?:string} actions: submit, withdraw, delete */
    public function act(int $memberId, string $uuid, string $action): array
    {
        $l = $this->owned($uuid, $memberId);
        if (!$l) { return ['ok' => false, 'error' => 'NOT_FOUND']; }
        if ($action === 'submit') {
            if (!in_array($l['status'], ['draft', 'rejected'], true)) { return ['ok' => false, 'error' => 'BAD_STATE']; }
            $this->db->prepare("UPDATE seller_listings SET status = 'submitted' WHERE id = :id")->execute(['id' => (int)$l['id']]);
        } elseif ($action === 'withdraw') {
            $this->db->prepare("UPDATE seller_listings SET status = 'withdrawn' WHERE id = :id")->execute(['id' => (int)$l['id']]);
        } elseif ($action === 'delete') {
            $this->db->prepare('DELETE FROM seller_listings WHERE id = :id')->execute(['id' => (int)$l['id']]);
            $this->photos?->delete($uuid);
        } else {
            return ['ok' => false, 'error' => 'BAD_ACTION'];
        }
        return ['ok' => true];
    }

    /** @param array{tmp_name:string,error:int,size:int} $file @return array{ok:bool,error?:string} */
    public function addPhoto(int $memberId, string $uuid, array $file, bool $requireUploaded = true): array
    {
        $l = $this->owned($uuid, $memberId);
        if (!$l || $l['status'] === 'withdrawn' || $this->photos === null) { return ['ok' => false, 'error' => 'NOT_FOUND']; }
        $count = count($this->photos((int)$l['id']));
        $r = $this->photos->store($uuid, $file, $count, $requireUploaded);
        if (!$r['ok']) { return $r; }
        $this->db->prepare('INSERT INTO seller_listing_photos (listing_id, file_name, width, height, bytes, sort_order) VALUES (:l, :f, :w, :h, :b, :s)')
            ->execute(['l' => (int)$l['id'], 'f' => $r['file_name'], 'w' => $r['width'], 'h' => $r['height'], 'b' => $r['bytes'], 's' => $count]);
        $this->db->prepare("UPDATE seller_listings SET status = 'draft' WHERE id = :id AND status IN ('submitted','approved')")->execute(['id' => (int)$l['id']]);
        return ['ok' => true];
    }

    public function removePhoto(int $memberId, string $uuid, int $photoId): bool
    {
        $l = $this->owned($uuid, $memberId);
        if (!$l) { return false; }
        $s = $this->db->prepare('SELECT file_name FROM seller_listing_photos WHERE id = :id AND listing_id = :l');
        $s->execute(['id' => $photoId, 'l' => (int)$l['id']]);
        $f = $s->fetchColumn();
        if (!$f) { return false; }
        $this->db->prepare('DELETE FROM seller_listing_photos WHERE id = :id')->execute(['id' => $photoId]);
        $this->photos?->delete($uuid, (string)$f);
        return true;
    }

    /** @return list<array> listings waiting for moderation first, then the latest others */
    public function moderationQueue(int $limit = 200): array
    {
        $s = $this->db->query("SELECT l.*, p.full_name, (SELECT COUNT(*) FROM seller_listing_photos x WHERE x.listing_id = l.id) photo_count
            FROM seller_listings l JOIN products p ON p.id = l.product_id WHERE l.status <> 'draft'
            ORDER BY (l.status = 'submitted') DESC, l.updated_at DESC LIMIT " . max(1, min(500, $limit)));
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param list<int> $listingIds @return array<int,list<array>> photos per listing id, in one query */
    public function photosFor(array $listingIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $listingIds))));
        if (!$ids) { return []; }
        $s = $this->db->prepare('SELECT listing_id, id, file_name, width, height FROM seller_listing_photos WHERE listing_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY sort_order, id');
        $s->execute($ids);
        $out = [];
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $p) { $out[(int)$p['listing_id']][] = $p; }
        return $out;
    }

    /** @return array{ok:bool,error?:string} */
    public function moderate(int $adminId, int $listingId, string $decision, string $note = ''): array
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) { return ['ok' => false, 'error' => 'BAD_ACTION']; }
        $note = mb_substr(trim($note), 0, 255);
        if ($decision === 'rejected' && $note === '') { return ['ok' => false, 'error' => 'NOTE_REQUIRED']; }
        $s = $this->db->prepare("UPDATE seller_listings SET status = :d, moderated_by = :a, moderated_at = NOW(), moderation_note = :n WHERE id = :id AND status = 'submitted'");
        $s->execute(['d' => $decision, 'a' => $adminId, 'n' => $note !== '' ? $note : null, 'id' => $listingId]);
        if ($s->rowCount() !== 1) { return ['ok' => false, 'error' => 'BAD_STATE']; }
        (new AuthService())->audit($adminId, 'seller_listing_' . $decision, 'seller_listing', $listingId, ['note' => $note]);
        return ['ok' => true];
    }

    /** A listing shown to everyone: approved, and only when the owner turned the public pages on (HG-16). */
    public function publicListing(string $uuid, bool $publicEnabled): ?array
    {
        if (!$publicEnabled) { return null; }
        $l = $this->find($uuid);
        return $l && $l['status'] === 'approved' ? $l : null;
    }
}
