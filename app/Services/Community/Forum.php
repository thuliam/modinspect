<?php
declare(strict_types=1);

namespace App\Services\Community;

use App\Services\Auth\AuthService;
use DateTimeImmutable;
use PDO;

/**
 * LS-24 M9: the community forum (threads, replies, structured reviews, helpful votes, content flags). Closed behind COMMUNITY_FORUM_ENABLED.
 * Invariants, each one tested:
 *  - content is a SIGNAL for readers: nothing here writes a price, an observation, a snapshot or a confidence label;
 *  - only signed-in members write; the public name is a short pseudonym derived from the member id, never the name the member signed in with;
 *  - every text passes ContentGuard (no phone, e-mail, LINE id, link, account-like number, or word that brands a person);
 *  - limits per member per day, one review per member per model, no voting on or flagging of one's own content, one flag and one vote per member per item;
 *  - three different members' flags hide an item at once and put it in the moderators' queue; a person decides (uphold, dismiss), and every decision is audited;
 *  - a review summary is shown only from MIN_REVIEWS_SHOWN visible reviews and always with its count.
 */
final class Forum
{
    public const LIMITS = ['thread' => 5, 'post' => 30, 'review' => 10, 'flag' => 20];
    public const FLAGS_TO_HIDE = 3;
    public const MIN_REVIEWS_SHOWN = 5;
    public const SOURCES = ['marketplace' => 'ตลาดออนไลน์/กลุ่มซื้อขาย', 'shop' => 'ร้านค้า', 'friend' => 'คนรู้จัก', 'other' => 'อื่น ๆ'];
    public const CONDITIONS = ['like_new' => 'เหมือนใหม่', 'good' => 'ดี', 'fair' => 'พอใช้', 'poor' => 'ไม่ดี'];
    public const ISSUES = ['none' => 'ไม่มีปัญหา', 'minor' => 'มีปัญหาเล็กน้อย', 'major' => 'มีปัญหาใหญ่'];
    public const REASONS = ['pii' => 'มีข้อมูลส่วนตัว', 'accusation' => 'กล่าวหาบุคคล', 'spam' => 'สแปม', 'abuse' => 'ไม่สุภาพ', 'wrong' => 'ข้อมูลไม่ถูกต้อง'];
    private const TABLES = ['thread' => 'community_threads', 'post' => 'community_posts', 'review' => 'community_reviews'];

    public function __construct(private PDO $db) {}

    public static function enabled(array $config): bool { return !empty($config['community_forum']['enabled']); }

    /** the only name that is ever shown: stable per member, not the sign-in name */
    public static function handle(int $memberId): string { return 'สมาชิก#' . substr(md5('mi-member-' . $memberId), 0, 4); }

    /** @return array{ok:true,id:int}|array{ok:false,errors:list<string>} */
    public function createThread(int $memberId, ?int $productId, string $title, string $body, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        $e = array_merge(ContentGuard::check($title, 5, 160), ContentGuard::check($body, 10, 2000));
        if ($productId !== null && !$this->productExists($productId)) { $e[] = 'BAD_PRODUCT'; }
        if ($e !== []) { return ['ok' => false, 'errors' => array_values(array_unique($e))]; }
        if ($this->overLimit('community_threads', $memberId, self::LIMITS['thread'], $now)) { return ['ok' => false, 'errors' => ['RATE_LIMIT']]; }
        $hash = md5(mb_strtolower(trim($title . "\n" . $body), 'UTF-8'));
        if ($this->duplicate('community_threads', $memberId, $hash, $now)) { return ['ok' => false, 'errors' => ['DUPLICATE']]; }
        $this->db->prepare('INSERT INTO community_threads (member_id, product_id, title, body, body_hash, last_post_at, created_at) VALUES (:m, :p, :t, :b, :h, :n, :n2)')
            ->execute(['m' => $memberId, 'p' => $productId, 't' => trim($title), 'b' => trim($body), 'h' => $hash, 'n' => $now->format('Y-m-d H:i:s'), 'n2' => $now->format('Y-m-d H:i:s')]);
        return ['ok' => true, 'id' => (int)$this->db->lastInsertId()];
    }

    /** @return array{ok:true,id:int}|array{ok:false,errors:list<string>} */
    public function createPost(int $memberId, int $threadId, string $body, ?int $parentId = null, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        $e = ContentGuard::check($body, 2, 1500);
        if ($e !== []) { return ['ok' => false, 'errors' => $e]; }
        $t = $this->db->prepare('SELECT status FROM community_threads WHERE id = :id'); $t->execute(['id' => $threadId]); $status = $t->fetchColumn();
        if ($status === false || $status === 'hidden') { return ['ok' => false, 'errors' => ['NOT_FOUND']]; }
        if ($status === 'locked') { return ['ok' => false, 'errors' => ['LOCKED']]; }
        if ($parentId !== null) {
            $p = $this->db->prepare("SELECT 1 FROM community_posts WHERE id = :id AND thread_id = :t AND parent_id IS NULL AND status = 'visible'"); $p->execute(['id' => $parentId, 't' => $threadId]);
            if (!$p->fetchColumn()) { return ['ok' => false, 'errors' => ['BAD_PARENT']]; }
        }
        if ($this->overLimit('community_posts', $memberId, self::LIMITS['post'], $now)) { return ['ok' => false, 'errors' => ['RATE_LIMIT']]; }
        $hash = md5(mb_strtolower(trim($body), 'UTF-8'));
        if ($this->duplicate('community_posts', $memberId, $hash, $now)) { return ['ok' => false, 'errors' => ['DUPLICATE']]; }
        $own = !$this->db->inTransaction();   // join a caller's transaction instead of failing
        if ($own) { $this->db->beginTransaction(); }
        try {
            $this->db->prepare('INSERT INTO community_posts (thread_id, member_id, parent_id, body, body_hash, created_at) VALUES (:t, :m, :p, :b, :h, :n)')
                ->execute(['t' => $threadId, 'm' => $memberId, 'p' => $parentId, 'b' => trim($body), 'h' => $hash, 'n' => $now->format('Y-m-d H:i:s')]);
            $id = (int)$this->db->lastInsertId();
            $this->db->prepare('UPDATE community_threads SET post_count = post_count + 1, last_post_at = :n WHERE id = :id')->execute(['n' => $now->format('Y-m-d H:i:s'), 'id' => $threadId]);
            if ($own) { $this->db->commit(); }
        } catch (\Throwable $ex) { if ($own && $this->db->inTransaction()) { $this->db->rollBack(); } throw $ex; }
        return ['ok' => true, 'id' => $id];
    }

    /** @param array<string,mixed> $in price_paid, source_kind, condition_received, issue_30d, would_buy_again, note @return array{ok:true,id:int}|array{ok:false,errors:list<string>} */
    public function createReview(int $memberId, int $productId, array $in, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        $e = [];
        $price = str_replace(',', '', trim((string)($in['price_paid'] ?? '')));
        if (!preg_match('/^\d{1,8}$/', $price) || (int)$price < 1 || (int)$price > 10000000) { $e[] = 'BAD_PRICE'; }
        if (!isset(self::SOURCES[(string)($in['source_kind'] ?? '')])) { $e[] = 'BAD_SOURCE'; }
        if (!isset(self::CONDITIONS[(string)($in['condition_received'] ?? '')])) { $e[] = 'BAD_CONDITION'; }
        if (!isset(self::ISSUES[(string)($in['issue_30d'] ?? '')])) { $e[] = 'BAD_ISSUE'; }
        $again = (string)($in['would_buy_again'] ?? '');
        if ($again !== '1' && $again !== '0') { $e[] = 'BAD_AGAIN'; }
        $note = trim((string)($in['note'] ?? ''));
        if ($note !== '') { $e = array_merge($e, ContentGuard::check($note, 3, 500)); }
        if (!$this->productExists($productId)) { $e[] = 'BAD_PRODUCT'; }
        if ($e !== []) { return ['ok' => false, 'errors' => array_values(array_unique($e))]; }
        $x = $this->db->prepare('SELECT 1 FROM community_reviews WHERE member_id = :m AND product_id = :p'); $x->execute(['m' => $memberId, 'p' => $productId]);
        if ($x->fetchColumn()) { return ['ok' => false, 'errors' => ['ALREADY_REVIEWED']]; }
        if ($this->overLimit('community_reviews', $memberId, self::LIMITS['review'], $now)) { return ['ok' => false, 'errors' => ['RATE_LIMIT']]; }
        $this->db->prepare('INSERT INTO community_reviews (member_id, product_id, price_paid, source_kind, condition_received, issue_30d, would_buy_again, note, created_at) VALUES (:m, :p, :pp, :s, :c, :i, :a, :n, :t)')
            ->execute(['m' => $memberId, 'p' => $productId, 'pp' => (int)$price, 's' => $in['source_kind'], 'c' => $in['condition_received'], 'i' => $in['issue_30d'], 'a' => (int)$again, 'n' => $note !== '' ? $note : null, 't' => $now->format('Y-m-d H:i:s')]);
        return ['ok' => true, 'id' => (int)$this->db->lastInsertId()];
    }

    /** toggles a helpful vote; not on one's own content, not on hidden content @return array{ok:bool,voted?:bool,error?:string} */
    public function vote(int $memberId, string $type, int $id): array
    {
        $t = $this->target($type, $id);
        if ($t === null || $t['status'] === 'hidden') { return ['ok' => false, 'error' => 'NOT_FOUND']; }
        if ((int)$t['member_id'] === $memberId) { return ['ok' => false, 'error' => 'OWN_CONTENT']; }
        $has = $this->db->prepare('SELECT id FROM community_votes WHERE target_type = :t AND target_id = :i AND member_id = :m'); $has->execute(['t' => $type, 'i' => $id, 'm' => $memberId]); $vid = $has->fetchColumn();
        $tbl = self::TABLES[$type];
        if ($vid !== false) {
            $this->db->prepare('DELETE FROM community_votes WHERE id = :id')->execute(['id' => $vid]);
            $this->db->prepare("UPDATE $tbl SET helpful_count = GREATEST(helpful_count - 1, 0) WHERE id = :id")->execute(['id' => $id]);
            return ['ok' => true, 'voted' => false];
        }
        $this->db->prepare('INSERT INTO community_votes (target_type, target_id, member_id) VALUES (:t, :i, :m)')->execute(['t' => $type, 'i' => $id, 'm' => $memberId]);
        $this->db->prepare("UPDATE $tbl SET helpful_count = helpful_count + 1 WHERE id = :id")->execute(['id' => $id]);
        return ['ok' => true, 'voted' => true];
    }

    /** @return array{ok:bool,error?:string,hidden?:bool} */
    public function flag(int $memberId, string $type, int $id, string $reason, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        if (!isset(self::REASONS[$reason])) { return ['ok' => false, 'error' => 'BAD_REASON']; }
        $t = $this->target($type, $id);
        if ($t === null) { return ['ok' => false, 'error' => 'NOT_FOUND']; }
        if ((int)$t['member_id'] === $memberId) { return ['ok' => false, 'error' => 'OWN_CONTENT']; }
        if ($this->overLimit('community_flags', $memberId, self::LIMITS['flag'], $now)) { return ['ok' => false, 'error' => 'RATE_LIMIT']; }
        $has = $this->db->prepare('SELECT 1 FROM community_flags WHERE target_type = :t AND target_id = :i AND member_id = :m'); $has->execute(['t' => $type, 'i' => $id, 'm' => $memberId]);
        if ($has->fetchColumn()) { return ['ok' => false, 'error' => 'ALREADY_FLAGGED']; }
        $tbl = self::TABLES[$type];
        $this->db->prepare('INSERT INTO community_flags (target_type, target_id, member_id, reason, created_at) VALUES (:t, :i, :m, :r, :n)')->execute(['t' => $type, 'i' => $id, 'm' => $memberId, 'r' => $reason, 'n' => $now->format('Y-m-d H:i:s')]);
        $this->db->prepare("UPDATE $tbl SET flag_count = flag_count + 1 WHERE id = :id")->execute(['id' => $id]);
        $n = $this->db->prepare("SELECT flag_count FROM $tbl WHERE id = :id"); $n->execute(['id' => $id]);
        $hidden = false;
        if ((int)$n->fetchColumn() >= self::FLAGS_TO_HIDE && $t['status'] === 'visible') {
            $this->db->prepare("UPDATE $tbl SET status = 'hidden', hidden_reason = 'flags' WHERE id = :id")->execute(['id' => $id]); $hidden = true;
        }
        return ['ok' => true, 'hidden' => $hidden];
    }

    /**
     * A moderator decides on a flagged item: uphold (hide it) or dismiss (show it again, flags closed), or hide / restore / lock a thread directly. Audited.
     * @return array{ok:bool,error?:string}
     */
    public function moderate(int $adminId, string $type, int $id, string $action, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        $t = $this->target($type, $id);
        if ($t === null) { return ['ok' => false, 'error' => 'NOT_FOUND']; }
        if (!in_array($action, ['uphold', 'dismiss', 'hide', 'restore', 'lock', 'unlock'], true)) { return ['ok' => false, 'error' => 'BAD_ACTION']; }
        if (in_array($action, ['lock', 'unlock'], true) && $type !== 'thread') { return ['ok' => false, 'error' => 'BAD_ACTION']; }
        $tbl = self::TABLES[$type]; $ts = $now->format('Y-m-d H:i:s');
        if (in_array($action, ['uphold', 'hide'], true)) { $this->db->prepare("UPDATE $tbl SET status = 'hidden', hidden_reason = 'moderator' WHERE id = :id")->execute(['id' => $id]); }
        elseif (in_array($action, ['dismiss', 'restore'], true)) { $this->db->prepare("UPDATE $tbl SET status = 'visible', hidden_reason = NULL, flag_count = 0 WHERE id = :id")->execute(['id' => $id]); }
        elseif ($action === 'lock') { $this->db->prepare("UPDATE community_threads SET status = 'locked' WHERE id = :id AND status = 'visible'")->execute(['id' => $id]); }
        elseif ($action === 'unlock') { $this->db->prepare("UPDATE community_threads SET status = 'visible' WHERE id = :id AND status = 'locked'")->execute(['id' => $id]); }
        $closing = in_array($action, ['uphold', 'hide'], true) ? 'upheld' : (in_array($action, ['dismiss', 'restore'], true) ? 'dismissed' : null);
        if ($closing !== null) { $this->db->prepare("UPDATE community_flags SET status = :s, decided_by = :a, decided_at = :t WHERE target_type = :ty AND target_id = :i AND status = 'open'")->execute(['s' => $closing, 'a' => $adminId, 't' => $ts, 'ty' => $type, 'i' => $id]); }
        (new AuthService())->audit($adminId, 'community_' . $action, 'community_' . $type, $id, ['action' => $action]);
        return ['ok' => true];
    }

    /** @return list<array<string,mixed>> items with open flags (and items hidden by flags), newest flag first, with the text the moderator must read */
    public function queue(int $limit = 100): array
    {
        $rows = $this->db->query("SELECT target_type, target_id, COUNT(*) flags, MAX(created_at) last_flag, GROUP_CONCAT(DISTINCT reason ORDER BY reason) reasons FROM community_flags WHERE status = 'open' GROUP BY target_type, target_id ORDER BY last_flag DESC LIMIT " . max(1, min(300, $limit)))->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $t = $this->target((string)$r['target_type'], (int)$r['target_id'], true);
            $r['status'] = $t['status'] ?? 'gone'; $r['preview'] = $t['preview'] ?? ''; $r['handle'] = isset($t['member_id']) ? self::handle((int)$t['member_id']) : '-';
        }
        return $rows;
    }

    /** @return array{count:int,shown:bool,no_issue_pct:?int,again_pct:?int} the figures are only given from MIN_REVIEWS_SHOWN visible reviews */
    public function reviewSummary(int $productId): array
    {
        $s = $this->db->prepare("SELECT COUNT(*) n, SUM(issue_30d = 'none') ok, SUM(would_buy_again = 1) again FROM community_reviews WHERE product_id = :p AND status = 'visible'");
        $s->execute(['p' => $productId]); $r = $s->fetch(PDO::FETCH_ASSOC);
        $n = (int)($r['n'] ?? 0);
        if ($n < self::MIN_REVIEWS_SHOWN) { return ['count' => $n, 'shown' => false, 'no_issue_pct' => null, 'again_pct' => null]; }
        return ['count' => $n, 'shown' => true, 'no_issue_pct' => (int)round((int)$r['ok'] * 100 / $n), 'again_pct' => (int)round((int)$r['again'] * 100 / $n)];
    }

    /** @return list<array<string,mixed>> visible reviews of a model, most helpful first (price paid is what the reviewer says, not a market price) */
    public function reviews(int $productId, int $limit = 20): array
    {
        $s = $this->db->prepare("SELECT id, member_id, price_paid, source_kind, condition_received, issue_30d, would_buy_again, note, helpful_count, created_at FROM community_reviews WHERE product_id = :p AND status = 'visible' ORDER BY helpful_count DESC, id DESC LIMIT " . max(1, min(50, $limit)));
        $s->execute(['p' => $productId]);
        return array_map(static fn(array $r): array => $r + ['handle' => self::handle((int)$r['member_id'])], $s->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array{rows:list<array<string,mixed>>,total:int} visible and locked threads, newest activity first, optionally for one model */
    public function threads(?int $productId = null, int $page = 1, int $perPage = 15): array
    {
        $where = "t.status IN ('visible','locked')" . ($productId !== null ? ' AND t.product_id = :p' : ''); $params = $productId !== null ? ['p' => $productId] : [];
        $c = $this->db->prepare("SELECT COUNT(*) FROM community_threads t WHERE $where"); $c->execute($params); $total = (int)$c->fetchColumn();
        $perPage = max(1, min(50, $perPage)); $page = max(1, min($page, max(1, (int)ceil($total / $perPage))));
        $s = $this->db->prepare("SELECT t.id, t.member_id, t.product_id, t.title, t.status, t.post_count, t.helpful_count, t.last_post_at, t.created_at, p.full_name product_name, p.slug product_slug FROM community_threads t LEFT JOIN products p ON p.id = t.product_id WHERE $where ORDER BY t.last_post_at DESC, t.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage));
        $s->execute($params);
        return ['rows' => array_map(static fn(array $r): array => $r + ['handle' => self::handle((int)$r['member_id'])], $s->fetchAll(PDO::FETCH_ASSOC)), 'total' => $total];
    }

    /** @return ?array{thread:array<string,mixed>,posts:list<array<string,mixed>>} null when it does not exist or is hidden */
    public function thread(int $id): ?array
    {
        $t = $this->db->prepare("SELECT t.*, p.full_name product_name, p.slug product_slug FROM community_threads t LEFT JOIN products p ON p.id = t.product_id WHERE t.id = :id AND t.status IN ('visible','locked')");
        $t->execute(['id' => $id]); $thread = $t->fetch(PDO::FETCH_ASSOC);
        if (!$thread) { return null; }
        $p = $this->db->prepare("SELECT id, member_id, parent_id, body, helpful_count, created_at FROM community_posts WHERE thread_id = :id AND status = 'visible' ORDER BY created_at ASC, id ASC LIMIT 300");
        $p->execute(['id' => $id]);
        $thread['handle'] = self::handle((int)$thread['member_id']);
        return ['thread' => $thread, 'posts' => array_map(static fn(array $r): array => $r + ['handle' => self::handle((int)$r['member_id'])], $p->fetchAll(PDO::FETCH_ASSOC))];
    }

    /** @return array<string,int> */
    public function counts(): array
    {
        $out = [];
        foreach (['threads' => 'community_threads', 'posts' => 'community_posts', 'reviews' => 'community_reviews'] as $k => $t) { $out[$k] = (int)$this->db->query("SELECT COUNT(*) FROM $t")->fetchColumn(); $out[$k . '_hidden'] = (int)$this->db->query("SELECT COUNT(*) FROM $t WHERE status = 'hidden'")->fetchColumn(); }
        $out['open_flags'] = (int)$this->db->query("SELECT COUNT(*) FROM community_flags WHERE status = 'open'")->fetchColumn();
        return $out;
    }

    /** @return ?array{status:string,member_id:int,preview?:string} */
    private function target(string $type, int $id, bool $preview = false): ?array
    {
        if (!isset(self::TABLES[$type]) || $id < 1) { return null; }
        $col = $type === 'thread' ? "CONCAT(title, ' — ', body)" : ($type === 'post' ? 'body' : "CONCAT('฿', price_paid, ' ', IFNULL(note, ''))");
        $s = $this->db->prepare('SELECT status, member_id' . ($preview ? ", $col preview" : '') . ' FROM ' . self::TABLES[$type] . ' WHERE id = :id'); $s->execute(['id' => $id]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        return $r ? ['status' => (string)$r['status'], 'member_id' => (int)$r['member_id']] + ($preview ? ['preview' => mb_substr((string)$r['preview'], 0, 300, 'UTF-8')] : []) : null;
    }

    private function productExists(int $id): bool
    {
        $s = $this->db->prepare('SELECT 1 FROM products WHERE id = :id AND is_active = 1'); $s->execute(['id' => $id]);
        return (bool)$s->fetchColumn();
    }

    private function overLimit(string $table, int $memberId, int $max, DateTimeImmutable $now): bool
    {
        $s = $this->db->prepare("SELECT COUNT(*) FROM $table WHERE member_id = :m AND created_at >= :since"); $s->execute(['m' => $memberId, 'since' => $now->modify('-1 day')->format('Y-m-d H:i:s')]);
        return (int)$s->fetchColumn() >= $max;
    }

    private function duplicate(string $table, int $memberId, string $hash, DateTimeImmutable $now): bool
    {
        $s = $this->db->prepare("SELECT 1 FROM $table WHERE member_id = :m AND body_hash = :h AND created_at >= :since"); $s->execute(['m' => $memberId, 'h' => $hash, 'since' => $now->modify('-1 day')->format('Y-m-d H:i:s')]);
        return (bool)$s->fetchColumn();
    }
}
