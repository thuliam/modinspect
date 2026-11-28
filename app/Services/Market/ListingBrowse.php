<?php
declare(strict_types=1);

namespace App\Services\Market;

use App\Services\Auth\AuthService;
use App\Services\Community\ContentGuard;
use App\Services\Listing\ListingAttributes;
use PDO;
use Throwable;

/**
 * LS-90 D3: reading public listings, what a member saves, and reports about a listing. "Public" is one condition used everywhere
 * here: approved, not sold, not expired. The callers check SELLER_LISTINGS_PUBLIC before using this class.
 * An asking price in a listing is the seller's claim; nothing here writes a price (HG-17).
 */
final class ListingBrowse
{
    public const PER_PAGE = 20;
    public const MAX_SEARCHES = 20;
    public const MAX_SAVED = 200;
    public const REASONS = ['wrong_info', 'suspicious', 'already_sold', 'prohibited', 'personal_data'];
    public const SORTS = ['new' => 'l.moderated_at DESC, l.id DESC', 'price_asc' => 'l.asking_price ASC, l.id DESC', 'price_desc' => 'l.asking_price DESC, l.id DESC'];
    private const PUBLIC_SQL = "l.status = 'approved' AND l.sale_state <> 'sold' AND (l.expires_at IS NULL OR l.expires_at > NOW())";
    private const SELECT = "SELECT l.uuid, l.id, l.asking_price, l.province, l.condition_percent, l.warranty_months, l.quantity, l.sale_state, l.expires_at, l.moderated_at,
            p.full_name, p.slug, c.slug category_slug, c.name category_name, COALESCE(sp.verification, 'unverified') verification, sp.display_name seller_name,
            (SELECT file_name FROM seller_listing_photos x WHERE x.listing_id = l.id ORDER BY x.sort_order, x.id LIMIT 1) photo
        FROM seller_listings l JOIN products p ON p.id = l.product_id JOIN product_categories c ON c.id = p.category_id LEFT JOIN seller_profiles sp ON sp.member_id = l.member_id";

    public function __construct(private PDO $db)
    {
    }

    /** only known keys with usable values survive, so a filter set can be stored and replayed @return array<string,int|string> */
    public static function filters(array $in): array
    {
        $out = [];
        $text = static fn(string $k): string => is_scalar($in[$k] ?? null) ? trim((string)$in[$k]) : '';
        $q = mb_substr($text('q'), 0, 80);
        if ($q !== '') {
            $out['q'] = $q;
        }
        if (preg_match('/^[a-z0-9-]{1,40}$/', $text('category')) === 1) {
            $out['category'] = $text('category');
        }
        if (in_array($text('province'), ListingAttributes::PROVINCES, true)) {
            $out['province'] = $text('province');
        }
        $max = filter_var($text('max_price'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000000]]);
        if ($max !== false) {
            $out['max_price'] = $max;
        }
        if ($text('verified') === '1') {
            $out['verified'] = 1;
        }
        if (isset(self::SORTS[$text('sort')]) && $text('sort') !== 'new') {
            $out['sort'] = $text('sort');
        }
        return $out;
    }

    /** @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int} */
    public function search(array $filters, int $page = 1): array
    {
        $where = [self::PUBLIC_SQL];
        $params = [];
        if (isset($filters['q'])) {
            $where[] = 'p.full_name LIKE :q';
            $params['q'] = '%' . addcslashes((string)$filters['q'], '%_\\') . '%';
        }
        foreach (['category' => 'c.slug = :category', 'province' => 'l.province = :province', 'max_price' => 'l.asking_price <= :max_price'] as $key => $sql) {
            if (isset($filters[$key])) {
                $where[] = $sql;
                $params[$key] = $filters[$key];
            }
        }
        if (!empty($filters['verified'])) {
            $where[] = "sp.verification = 'verified'";
        }
        $condition = ' WHERE ' . implode(' AND ', $where);
        $count = $this->db->prepare('SELECT COUNT(*) FROM seller_listings l JOIN products p ON p.id = l.product_id JOIN product_categories c ON c.id = p.category_id LEFT JOIN seller_profiles sp ON sp.member_id = l.member_id' . $condition);
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total / self::PER_PAGE));
        $page = max(1, min($pages, $page));
        $s = $this->db->prepare(self::SELECT . $condition . ' ORDER BY ' . self::SORTS[$filters['sort'] ?? 'new'] . ' LIMIT ' . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE));
        $s->execute($params);
        return ['rows' => $s->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** the public row of one listing, or null */
    public function one(string $uuid): ?array
    {
        if (preg_match('/^[a-f0-9]{32}$/', $uuid) !== 1) {
            return null;
        }
        $s = $this->db->prepare(self::SELECT . ' WHERE ' . self::PUBLIC_SQL . ' AND l.uuid = ?');
        $s->execute([$uuid]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return array{ok:bool,saved:bool,error:?string} toggles: saves when not saved, removes when saved */
    public function toggleSaved(int $memberId, string $uuid): array
    {
        $listing = $this->one($uuid);
        $id = $listing ? (int)$listing['id'] : $this->idOf($uuid);
        if ($id === 0) {
            return ['ok' => false, 'saved' => false, 'error' => 'NOT_FOUND'];
        }
        $delete = $this->db->prepare('DELETE FROM saved_listings WHERE member_id = ? AND listing_id = ?');
        $delete->execute([$memberId, $id]);
        if ($delete->rowCount() > 0) {
            return ['ok' => true, 'saved' => false, 'error' => null];
        }
        if ($listing === null) {
            return ['ok' => false, 'saved' => false, 'error' => 'NOT_FOUND'];   // something no longer public can be removed, not added
        }
        $count = $this->db->prepare('SELECT COUNT(*) FROM saved_listings WHERE member_id = ?');
        $count->execute([$memberId]);
        if ((int)$count->fetchColumn() >= self::MAX_SAVED) {
            return ['ok' => false, 'saved' => false, 'error' => 'LIMIT'];
        }
        $this->db->prepare('INSERT IGNORE INTO saved_listings (member_id, listing_id) VALUES (?,?)')->execute([$memberId, $id]);
        return ['ok' => true, 'saved' => true, 'error' => null];
    }

    public function isSaved(int $memberId, int $listingId): bool
    {
        $s = $this->db->prepare('SELECT 1 FROM saved_listings WHERE member_id = ? AND listing_id = ?');
        $s->execute([$memberId, $listingId]);
        return (bool)$s->fetchColumn();
    }

    /** @return list<array<string,mixed>> saved listings; one that is no longer public keeps only its model name @see toggleSaved */
    public function saved(int $memberId): array
    {
        $s = $this->db->prepare('SELECT l.uuid, p.full_name, l.asking_price, (' . self::PUBLIC_SQL . ') still_public FROM saved_listings s
            JOIN seller_listings l ON l.id = s.listing_id JOIN products p ON p.id = l.product_id WHERE s.member_id = ? ORDER BY s.created_at DESC, l.id DESC');
        $s->execute([$memberId]);
        return array_map(static function (array $r): array {
            if ((int)$r['still_public'] !== 1) {
                $r['asking_price'] = null;
            }
            return $r;
        }, $s->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array{ok:bool,error:?string} */
    public function saveSearch(int $memberId, array $input): array
    {
        $filters = self::filters($input);
        unset($filters['sort']);
        if ($filters === []) {
            return ['ok' => false, 'error' => 'EMPTY'];
        }
        ksort($filters);
        $json = (string)json_encode($filters, JSON_UNESCAPED_UNICODE);
        $count = $this->db->prepare('SELECT COUNT(*) FROM saved_searches WHERE member_id = ?');
        $count->execute([$memberId]);
        if ((int)$count->fetchColumn() >= self::MAX_SEARCHES) {
            return ['ok' => false, 'error' => 'LIMIT'];
        }
        $this->db->prepare('INSERT IGNORE INTO saved_searches (member_id, filters_json, filters_hash) VALUES (?,?,?)')->execute([$memberId, $json, hash('sha256', $json)]);
        return ['ok' => true, 'error' => null];
    }

    /** @return list<array{id:int,filters:array<string,int|string>}> */
    public function searches(int $memberId): array
    {
        $s = $this->db->prepare('SELECT id, filters_json FROM saved_searches WHERE member_id = ? ORDER BY id DESC');
        $s->execute([$memberId]);
        return array_map(static fn(array $r): array => ['id' => (int)$r['id'], 'filters' => self::filters((array)json_decode((string)$r['filters_json'], true))], $s->fetchAll(PDO::FETCH_ASSOC));
    }

    public function deleteSearch(int $memberId, int $id): bool
    {
        $s = $this->db->prepare('DELETE FROM saved_searches WHERE id = ? AND member_id = ?');
        $s->execute([$id, $memberId]);
        return $s->rowCount() === 1;
    }

    /** a member reports a public listing that is not their own, once per listing @return array{ok:bool,error:?string,errors:list<string>} */
    public function report(int $memberId, string $uuid, string $reason, string $note = ''): array
    {
        $fail = static fn(string $code, array $errors = []): array => ['ok' => false, 'error' => $code, 'errors' => $errors];
        $listing = $this->one($uuid);
        if ($listing === null) {
            return $fail('NOT_FOUND');
        }
        if (!in_array($reason, self::REASONS, true)) {
            return $fail('BAD_REASON');
        }
        $own = $this->db->prepare('SELECT 1 FROM seller_listings WHERE id = ? AND member_id = ?');
        $own->execute([(int)$listing['id'], $memberId]);
        if ($own->fetchColumn()) {
            return $fail('OWN_LISTING');
        }
        $note = trim($note);
        if ($note !== '') {
            $codes = ContentGuard::check($note, 1, 255);
            if ($codes !== []) {
                return $fail('BAD_NOTE', ContentGuard::messages($codes));
            }
        }
        $insert = $this->db->prepare('INSERT IGNORE INTO listing_reports (listing_id, member_id, reason, note) VALUES (?,?,?,?)');
        $insert->execute([(int)$listing['id'], $memberId, $reason, $note !== '' ? $note : null]);
        return $insert->rowCount() === 1 ? ['ok' => true, 'error' => null, 'errors' => []] : $fail('ALREADY_REPORTED');
    }

    /** @return list<array<string,mixed>> open reports, oldest first */
    public function openReports(): array
    {
        return $this->db->query("SELECT r.id, r.reason, r.note, r.created_at, l.uuid, l.status listing_status, l.asking_price, p.full_name,
                (SELECT COUNT(*) FROM listing_reports x WHERE x.listing_id = r.listing_id AND x.status = 'open') open_for_listing
            FROM listing_reports r JOIN seller_listings l ON l.id = r.listing_id JOIN products p ON p.id = l.product_id WHERE r.status = 'open' ORDER BY r.created_at, r.id LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * A moderator closes a report. Upholding it takes the listing off the site (status rejected, with the note) and closes the other
     * open reports about it; the seller can edit and submit it again.
     * @return array{ok:bool,error:?string}
     */
    public function decideReport(int $adminId, int $reportId, string $decision, string $note = ''): array
    {
        if (!in_array($decision, ['upheld', 'dismissed'], true)) {
            return ['ok' => false, 'error' => 'BAD_ACTION'];
        }
        $note = mb_substr(trim($note), 0, 255);
        if ($decision === 'upheld' && $note === '') {
            return ['ok' => false, 'error' => 'NOTE_REQUIRED'];
        }
        $own = !$this->db->inTransaction();
        if ($own) {
            $this->db->beginTransaction();
        }
        $removed = null;
        try {
            $s = $this->db->prepare("SELECT r.listing_id, l.status, l.member_id FROM listing_reports r JOIN seller_listings l ON l.id = r.listing_id WHERE r.id = ? AND r.status = 'open' FOR UPDATE");
            $s->execute([$reportId]);
            $report = $s->fetch(PDO::FETCH_ASSOC);
            if (!$report) {
                if ($own) {
                    $this->db->rollBack();
                }
                return ['ok' => false, 'error' => 'BAD_STATE'];
            }
            $listing = (int)$report['listing_id'];
            $close = $decision === 'upheld' ? 'listing_id = ?' : 'id = ?';
            $this->db->prepare("UPDATE listing_reports SET status = ?, decided_by = ?, decided_at = NOW() WHERE status = 'open' AND $close")->execute([$decision, $adminId, $decision === 'upheld' ? $listing : $reportId]);
            if ($decision === 'upheld' && $report['status'] === 'approved') {
                $this->db->prepare("UPDATE seller_listings SET status = 'rejected', moderated_by = ?, moderated_at = NOW(), moderation_note = ? WHERE id = ?")->execute([$adminId, $note, $listing]);
                (new ListingLifecycle($this->db))->record($listing, 'admin', $adminId, 'removed_after_report', 'approved', 'rejected', $note);
                $removed = (int)$report['member_id'];
            }
            (new AuthService())->audit($adminId, 'listing_report_' . $decision, 'listing_report', $reportId, ['listing_id' => $listing, 'note' => $note]);
            if ($own) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($own && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
        if ($removed !== null) {
            (new NotificationQueue($this->db))->enqueue($removed, 'listing_removed', $listing);
        }
        return ['ok' => true, 'error' => null];
    }

    private function idOf(string $uuid): int
    {
        if (preg_match('/^[a-f0-9]{32}$/', $uuid) !== 1) {
            return 0;
        }
        $s = $this->db->prepare('SELECT id FROM seller_listings WHERE uuid = ?');
        $s->execute([$uuid]);
        return (int)$s->fetchColumn();
    }
}
