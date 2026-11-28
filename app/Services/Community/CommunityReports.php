<?php
declare(strict_types=1);
namespace App\Services\Community;

use App\Services\Auth\AuthService;
use DateTimeImmutable;
use PDO;
use RuntimeException;

/**
 * LS-21 (R-11, ADR-006 option A): visitors report a wrong model, a price that looks off, old data, a spec, or (voluntarily) a price something sold for.
 * Choices and numbers only (no free text, so nothing personal can arrive), anonymous. The invariants, each one tested:
 *  - a report is a SIGNAL: nothing here writes a price, a snapshot, a confidence label or an approval;
 *  - a person decides every report (accepted / rejected / spam), and the decision is audited;
 *  - the only effect of an accepted sold-price report is a PENDING observation candidate with price_type 'sold' from a low-quality source: a reviewer must
 *    still approve it, and the public price engine ignores any observation whose price_type is not the cohort's (asking), so it cannot move a price;
 *  - reporter_hash is an HMAC of the client address under a key that changes every day (flood and duplicate control, not identity);
 *  - abuse limits: 3 reports per product per client per day, 1 candidate per client per product per week.
 */
final class CommunityReports
{
    public const TYPES = [
        'wrong_product' => ['label' => 'ชื่อรุ่นไม่ตรง', 'hint' => 'หน้านี้ระบุรุ่นหรือรุ่นย่อยผิดจากของจริง'],
        'price_looks_off' => ['label' => 'ช่วงราคาดูไม่ตรงตลาด', 'hint' => 'ช่วงราคาที่แสดงดูสูงหรือต่ำเกินไป'],
        'stale' => ['label' => 'ข้อมูลดูเก่า', 'hint' => 'ราคาหรือข้อมูลของรุ่นนี้ไม่ทันสมัยแล้ว'],
        'sold_price' => ['label' => 'ราคาที่ขายได้จริง (สมัครใจ)', 'hint' => 'รุ่นนี้ถูกขายไปในราคาเท่าไร ใช้เป็นหลักฐานประกอบเท่านั้น'],
        'spec_wrong' => ['label' => 'สเปกไม่ถูกต้อง', 'hint' => 'สเปกที่แสดงในหน้านี้ไม่ตรงกับของจริง'],
    ];
    public const SPECS = ['cores' => 'จำนวนคอร์', 'threads' => 'จำนวนเธรด', 'clock' => 'ความเร็วสัญญาณนาฬิกา', 'cache' => 'แคช', 'memory_size' => 'ความจุหน่วยความจำ',
        'memory_type' => 'ชนิดหน่วยความจำ', 'socket' => 'ซ็อกเก็ต', 'tdp' => 'กำลังไฟ (TDP)', 'release' => 'ปีที่วางจำหน่าย', 'other' => 'อื่นๆ'];
    public const CONDITIONS = ['like_new' => 'เหมือนใหม่', 'good' => 'ดี', 'fair' => 'พอใช้', 'poor' => 'ไม่ดี', 'unknown' => 'ไม่ระบุ'];
    public const STATUSES = ['new', 'accepted', 'rejected', 'spam'];
    public const PER_PRODUCT_PER_DAY = 3;
    public const CANDIDATES_PER_WEEK = 1;
    public const MAX_PRICE = 10000000;

    public function __construct(private PDO $db, private string $key)
    {
        if (strlen($key) < 32) { throw new RuntimeException('COMMUNITY_KEY_MISSING'); }
    }

    /** the feature is on only when it is enabled AND keyed, so it can never be half-on */
    public static function enabled(array $config): bool
    {
        return !empty($config['community_reports']['enabled']) && strlen((string)($config['community_reports']['hmac_key'] ?? '')) >= 32;
    }

    /** a code for flood and duplicate control: 32 hex characters, the same for one client on one day, different the next day, not reversible without the key */
    public function reporterHash(string $clientAddress, DateTimeImmutable $now): string
    {
        $dayKey = hash_hmac('sha256', $now->format('Y-m-d'), $this->key);
        return substr(hash_hmac('sha256', $clientAddress, $dayKey), 0, 32);
    }

    /**
     * Validate the structured fields of one report. Pure apart from the product check passed in.
     * @param callable(int):bool $productExists
     * @return array{ok:true,payload:array<string,mixed>}|array{ok:false,error:string}
     */
    public static function validate(string $type, array $in, int $currentProductId, callable $productExists, DateTimeImmutable $now): array
    {
        $price = static function (mixed $v, bool $required): array {
            $s = trim((string)$v);
            if ($s === '') { return $required ? [false, null] : [true, null]; }
            if (!preg_match('/^\d{1,8}$/', str_replace(',', '', $s))) { return [false, null]; }
            $n = (int)str_replace(',', '', $s);
            return ($n >= 1 && $n <= self::MAX_PRICE) ? [true, $n] : [false, null];
        };
        switch ($type) {
            case 'wrong_product':
                $s = trim((string)($in['should_be'] ?? ''));
                if ($s === 'not_in_catalog') { return ['ok' => true, 'payload' => ['should_be' => 'not_in_catalog']]; }
                if (!preg_match('/^\d{1,10}$/', $s) || (int)$s === $currentProductId || !$productExists((int)$s)) { return ['ok' => false, 'error' => 'BAD_SHOULD_BE']; }
                return ['ok' => true, 'payload' => ['should_be_product_id' => (int)$s]];
            case 'price_looks_off':
                $d = (string)($in['direction'] ?? '');
                if (!in_array($d, ['higher', 'lower'], true)) { return ['ok' => false, 'error' => 'BAD_DIRECTION']; }
                [$ok, $p] = $price($in['price'] ?? '', false);
                if (!$ok) { return ['ok' => false, 'error' => 'BAD_PRICE']; }
                return ['ok' => true, 'payload' => ['direction' => $d] + ($p !== null ? ['seen_price' => $p] : [])];
            case 'stale':
                return ['ok' => true, 'payload' => []];
            case 'sold_price':
                [$ok, $p] = $price($in['price'] ?? '', true);
                if (!$ok) { return ['ok' => false, 'error' => 'BAD_PRICE']; }
                $month = (string)($in['month'] ?? '');
                if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month)) { return ['ok' => false, 'error' => 'BAD_MONTH']; }
                $this_month = $now->format('Y-m'); $oldest = $now->modify('first day of this month')->modify('-12 months')->format('Y-m');
                if ($month > $this_month || $month < $oldest) { return ['ok' => false, 'error' => 'BAD_MONTH']; }
                $c = (string)($in['condition'] ?? '');
                if (!isset(self::CONDITIONS[$c])) { return ['ok' => false, 'error' => 'BAD_CONDITION']; }
                $w = trim((string)($in['warranty_months'] ?? ''));
                if ($w !== '' && (!preg_match('/^\d{1,3}$/', $w) || (int)$w > 120)) { return ['ok' => false, 'error' => 'BAD_WARRANTY']; }
                return ['ok' => true, 'payload' => ['price' => $p, 'month' => $month, 'condition' => $c, 'warranty_months' => $w === '' ? null : (int)$w,
                    'has_box' => !empty($in['has_box']), 'has_receipt' => !empty($in['has_receipt'])]];
            case 'spec_wrong':
                $s = (string)($in['spec'] ?? '');
                if (!isset(self::SPECS[$s])) { return ['ok' => false, 'error' => 'BAD_SPEC']; }
                return ['ok' => true, 'payload' => ['spec' => $s]];
        }
        return ['ok' => false, 'error' => 'BAD_TYPE'];
    }

    /** @return array{ok:true,id:?int,duplicate:bool}|array{ok:false,error:string} */
    public function submit(int $productId, string $type, array $in, string $clientAddress, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        if (!isset(self::TYPES[$type])) { return ['ok' => false, 'error' => 'BAD_TYPE']; }
        if (!$this->productExists($productId)) { return ['ok' => false, 'error' => 'BAD_PRODUCT']; }
        $v = self::validate($type, $in, $productId, fn(int $id): bool => $this->productExists($id), $now);
        if (!$v['ok']) { return $v; }
        $json = json_encode($v['payload'] === [] ? new \stdClass() : $v['payload'], JSON_UNESCAPED_UNICODE);
        if (strlen((string)$json) > 400) { return ['ok' => false, 'error' => 'PAYLOAD_TOO_LARGE']; }
        $hash = $this->reporterHash($clientAddress, $now);

        // the same report again: accepted silently, stored once (compared as data in PHP: MariaDB has no CAST AS JSON and MySQL reorders JSON keys)
        $same = $this->db->prepare('SELECT payload FROM community_reports WHERE reporter_hash = :h AND product_id = :p AND report_type = :t');
        $same->execute(['h' => $hash, 'p' => $productId, 't' => $type]);
        $want = $v['payload']; ksort($want);
        foreach ($same->fetchAll(PDO::FETCH_COLUMN) as $old) { $o = json_decode((string)$old, true); if (is_array($o)) { ksort($o); if ($o === $want) { return ['ok' => true, 'id' => null, 'duplicate' => true]; } } }
        $today = $this->db->prepare('SELECT COUNT(*) FROM community_reports WHERE reporter_hash = :h AND product_id = :p');
        $today->execute(['h' => $hash, 'p' => $productId]);
        if ((int)$today->fetchColumn() >= self::PER_PRODUCT_PER_DAY) { return ['ok' => false, 'error' => 'PRODUCT_DAILY_LIMIT']; }

        // the public snapshot the visitor was looking at (what they saw); none when the product has no public price
        $snap = $this->db->prepare('SELECT ' . \App\Services\Pricing\PublicSnapshot::idSql(':p'));
        $snap->execute(['p' => $productId]); $snapshotId = $snap->fetchColumn();
        $ins = $this->db->prepare('INSERT INTO community_reports (report_type, product_id, snapshot_id, payload, reporter_hash, created_at) VALUES (:t, :p, :s, :j, :h, :c)');
        $ins->execute(['t' => $type, 'p' => $productId, 's' => $snapshotId ? (int)$snapshotId : null, 'j' => $json, 'h' => $hash, 'c' => $now->format('Y-m-d H:i:s')]);
        return ['ok' => true, 'id' => (int)$this->db->lastInsertId(), 'duplicate' => false];
    }

    /** @return list<array<string,mixed>> reports with the product and the snapshot the visitor saw; oldest first for the new ones, newest first otherwise */
    public function queue(string $status = 'new', int $limit = 200): array
    {
        $where = in_array($status, self::STATUSES, true) ? 'WHERE r.status = ' . $this->db->quote($status) : '';
        $order = $status === 'new' ? 'ASC' : 'DESC';
        $rows = $this->db->query("SELECT r.*, p.full_name, p.slug, pi.q1 seen_q1, pi.median seen_median, pi.q3 seen_q3,
                (SELECT COUNT(*) FROM community_reports x WHERE x.product_id = r.product_id AND x.status = 'new') product_new_reports
            FROM community_reports r JOIN products p ON p.id = r.product_id LEFT JOIN price_indices pi ON pi.id = r.snapshot_id
            $where ORDER BY r.id $order LIMIT " . max(1, min(500, $limit)))->fetchAll(PDO::FETCH_ASSOC);
        $ids = [];
        foreach ($rows as &$r) { $r['payload'] = json_decode((string)$r['payload'], true) ?: []; if (isset($r['payload']['should_be_product_id'])) { $ids[(int)$r['payload']['should_be_product_id']] = true; } }
        unset($r);
        if ($ids) {   // LS-22: show the model name the reporter pointed at, one query for the whole page
            $names = []; $q = $this->db->query('SELECT id, full_name FROM products WHERE id IN (' . implode(',', array_map('intval', array_keys($ids))) . ')');
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $p) { $names[(int)$p['id']] = (string)$p['full_name']; }
            foreach ($rows as &$r) { $id = (int)($r['payload']['should_be_product_id'] ?? 0); if ($id > 0 && isset($names[$id])) { $r['should_be_name'] = $names[$id]; } }
            unset($r);
        }
        return $rows;
    }

    /** @return array<string,int> */
    public function counts(): array
    {
        $out = array_fill_keys(self::STATUSES, 0);
        foreach ($this->db->query('SELECT status, COUNT(*) n FROM community_reports GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $r) { $out[(string)$r['status']] = (int)$r['n']; }
        return $out;
    }

    /** products with the most open reports: an admin signal for what to look at first, never a vote */
    public function productsNeedingAttention(int $limit = 5): array
    {
        return $this->db->query("SELECT p.id, p.full_name, p.slug, COUNT(*) new_reports FROM community_reports r JOIN products p ON p.id = r.product_id WHERE r.status = 'new' GROUP BY p.id, p.full_name, p.slug ORDER BY new_reports DESC, p.full_name LIMIT " . max(1, min(20, $limit)))->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{ok:bool,error?:string} */
    public function moderate(int $adminId, int $reportId, string $decision, string $note = '', ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        $map = ['accepted' => 'accepted', 'rejected' => 'rejected', 'spam' => 'spam'];
        if (!isset($map[$decision])) { return ['ok' => false, 'error' => 'BAD_ACTION']; }
        $note = mb_substr(trim($note), 0, 255);
        if ($decision !== 'accepted' && $note === '') { return ['ok' => false, 'error' => 'NOTE_REQUIRED']; }
        $s = $this->db->prepare("UPDATE community_reports SET status = :d, moderator_id = :a, moderation_note = :n, decided_at = :t WHERE id = :id AND status = 'new'");
        $s->execute(['d' => $decision, 'a' => $adminId, 'n' => $note !== '' ? $note : null, 't' => $now->format('Y-m-d H:i:s'), 'id' => $reportId]);
        if ($s->rowCount() !== 1) { return ['ok' => false, 'error' => 'BAD_STATE']; }
        (new AuthService())->audit($adminId, 'community_report_' . $decision, 'community_report', $reportId, ['note' => $note]);
        return ['ok' => true];
    }

    /**
     * An accepted sold-price report becomes a PENDING observation candidate for the review queue (raw row, evidence row, observation, review decision), at
     * the lowest evidence level, from the low-quality 'community_report' source, with price_type 'sold'. Never approved here. Once per report; one per client
     * per product per week.
     * @return array{ok:bool,error?:string,observation_id?:int}
     */
    public function createCandidate(int $adminId, int $reportId, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        $st = $this->db->prepare('SELECT * FROM community_reports WHERE id = :id'); $st->execute(['id' => $reportId]); $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) { return ['ok' => false, 'error' => 'NOT_FOUND']; }
        if ($r['report_type'] !== 'sold_price') { return ['ok' => false, 'error' => 'NOT_A_SOLD_PRICE']; }
        if ($r['status'] !== 'accepted') { return ['ok' => false, 'error' => 'NOT_ACCEPTED']; }
        if ($r['candidate_observation_id'] !== null) { return ['ok' => false, 'error' => 'ALREADY_CANDIDATE']; }
        $p = json_decode((string)$r['payload'], true);
        if (!is_array($p) || (int)($p['price'] ?? 0) < 1 || !isset(self::CONDITIONS[(string)($p['condition'] ?? '')]) || !preg_match('/^\d{4}-\d{2}$/', (string)($p['month'] ?? ''))) { return ['ok' => false, 'error' => 'BAD_PAYLOAD']; }
        $cap = $this->db->prepare('SELECT COUNT(*) FROM community_reports WHERE reporter_hash = :h AND product_id = :p AND candidate_observation_id IS NOT NULL AND decided_at >= :since');
        $cap->execute(['h' => $r['reporter_hash'], 'p' => (int)$r['product_id'], 'since' => $now->modify('-7 days')->format('Y-m-d H:i:s')]);
        if ((int)$cap->fetchColumn() >= self::CANDIDATES_PER_WEEK) { return ['ok' => false, 'error' => 'CANDIDATE_CAP']; }

        $own = !$this->db->inTransaction();
        if ($own) { $this->db->beginTransaction(); }
        try {
            $sid = $this->db->query("SELECT id FROM data_sources WHERE source_key = 'community_report'")->fetchColumn();
            if ($sid === false) {
                $this->db->exec("INSERT INTO data_sources (source_key, name, source_type, access_method, allowed_collection_method, risk_level, reliability_score, evidence_quality, freshness_expectation_days, terms_note) VALUES ('community_report', 'Community report (checked by a person)', 'user', 'manual', 'manual', 'high', 20.00, 'low', 90, 'Anonymous structured report accepted by a moderator.')");
                $sid = $this->db->lastInsertId();
            }
            $when = $now->format('Y-m-d H:i:s'); $observed = $p['month'] . '-01 00:00:00';
            $excerpt = 'รายงานจากผู้ใช้ (ไม่ระบุตัวตน) ตรวจโดยผู้ดูแล: ขายได้ ฿' . number_format((int)$p['price']) . ' เดือน ' . $p['month'] . ' สภาพ ' . self::CONDITIONS[$p['condition']]
                . (($p['warranty_months'] ?? null) !== null ? ' ประกัน ' . (int)$p['warranty_months'] . ' เดือน' : '') . (!empty($p['has_box']) ? ' มีกล่อง' : '') . (!empty($p['has_receipt']) ? ' มีใบเสร็จ' : '');
            $this->db->prepare("INSERT INTO raw_price_observations (source_id, external_reference_hash, raw_title, raw_price_text, raw_condition_text, captured_at, processing_status) VALUES (:s, :h, :t, :pt, :ct, :c, 'review')")
                ->execute(['s' => (int)$sid, 'h' => hash('sha256', 'community-report:' . $reportId), 't' => 'Community report #' . $reportId, 'pt' => (string)(int)$p['price'], 'ct' => $p['condition'], 'c' => $r['created_at']]);
            $rawId = (int)$this->db->lastInsertId();
            $this->db->prepare("INSERT INTO market_evidence (raw_observation_id, source_id, evidence_level, evidence_type, excerpt, content_hash, captured_at, is_public) VALUES (:r, :s, 'E', 'snippet', :x, :h, :c, 0)")
                ->execute(['r' => $rawId, 's' => (int)$sid, 'x' => $excerpt, 'h' => hash('sha256', (string)$r['payload']), 'c' => $r['created_at']]);
            $this->db->prepare("INSERT INTO price_observations (raw_observation_id, product_id, price_type, asking_price, price_value, currency, condition_level, warranty_months, has_box, has_receipt, listing_type, evidence_level, classification_confidence, quality_flags, verified_status, observed_at)
                VALUES (:r, :p, 'sold', NULL, :v, 'THB', :cl, :w, :b, :rc, 'single_item', 1, 0, :q, 'pending', :o)")
                ->execute(['r' => $rawId, 'p' => (int)$r['product_id'], 'v' => (int)$p['price'], 'cl' => $p['condition'], 'w' => $p['warranty_months'] ?? null, 'b' => empty($p['has_box']) ? 0 : 1, 'rc' => empty($p['has_receipt']) ? 0 : 1,
                    'q' => json_encode(['COMMUNITY_REPORT'], JSON_UNESCAPED_UNICODE), 'o' => $observed]);
            $obsId = (int)$this->db->lastInsertId();
            $this->db->prepare("INSERT INTO observation_review_decisions (price_observation_id, raw_observation_id, lane, decision, reason_codes, notes) VALUES (:o, :r, 'amber', 'review_required', :c, :n)")
                ->execute(['o' => $obsId, 'r' => $rawId, 'c' => json_encode(['COMMUNITY_REPORT'], JSON_UNESCAPED_UNICODE), 'n' => 'Sold price reported by a visitor; a reviewer decides.']);
            $this->db->prepare('UPDATE community_reports SET candidate_observation_id = :o WHERE id = :id')->execute(['o' => $obsId, 'id' => $reportId]);
            (new AuthService())->audit($adminId, 'community_report_candidate', 'community_report', $reportId, ['observation_id' => $obsId]);
            if ($own) { $this->db->commit(); }
            return ['ok' => true, 'observation_id' => $obsId];
        } catch (\Throwable $e) {
            if ($own && $this->db->inTransaction()) { $this->db->rollBack(); }
            throw $e;
        }
    }

    private function productExists(int $id): bool
    {
        if ($id < 1) { return false; }
        $s = $this->db->prepare('SELECT COUNT(*) FROM products WHERE id = :id AND is_active = 1'); $s->execute(['id' => $id]);
        return (int)$s->fetchColumn() === 1;
    }
}
