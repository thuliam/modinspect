<?php
declare(strict_types=1);

namespace App\Services\Listing;

use App\Services\Auth\AuthService;
use App\Services\Collection\CaptureMethod;
use PDO;

/**
 * LS-14 Module 8 (owner OD-3, docs/decisions/ADR-007): reports about a phone number or bank account, verified by a person before anything is public.
 * The number is never stored: only HMAC-SHA-256(key, type|digits) and the last 4 digits. Without a key the service refuses to run. The public answer
 * is a count of VERIFIED reports by type and nothing else; a disputed report leaves the count until an admin resolves the dispute.
 */
final class SellerCheck
{
    public const PROBLEMS = ['no_delivery' => 'ชำระแล้วไม่ได้รับสินค้า', 'not_as_described' => 'สินค้าไม่ตรงกับที่ลงประกาศ', 'refused_refund' => 'ไม่คืนเงินตามที่ตกลง', 'fake_item' => 'สินค้าไม่ใช่ของแท้ตามที่แจ้ง', 'other' => 'อื่น ๆ'];
    public const TYPES = ['phone' => 'เบอร์โทร', 'bank_account' => 'เลขบัญชีธนาคาร'];
    public const NOTE = 'ข้อมูลนี้มาจากรายงานของผู้ใช้ที่ทีมงานตรวจแล้ว ไม่ใช่คำตัดสินของศาล หากคุณคือเจ้าของเบอร์หรือบัญชีนี้และเห็นว่าไม่ถูกต้อง คุณโต้แย้งได้';

    public function __construct(private PDO $db, private string $hmacKey) {}

    /** Digits only, phone in the national 0xxxxxxxxx form; null when it is not a plausible number of that type. */
    public static function normalize(string $type, string $raw): ?string
    {
        $d = preg_replace('/\D+/', '', $raw) ?? '';
        if ($type === 'phone') {
            if (str_starts_with($d, '66') && strlen($d) >= 11) { $d = '0' . substr($d, 2); }
            return preg_match('/^0[2-9]\d{7,8}$/', $d) === 1 ? $d : null;
        }
        if ($type === 'bank_account') { return preg_match('/^\d{8,15}$/', $d) === 1 ? $d : null; }
        return null;
    }

    public function hash(string $type, string $normalized): string
    {
        if (strlen($this->hmacKey) < 32) { throw new \RuntimeException('SELLER_CHECK_KEY_MISSING'); }
        return hash_hmac('sha256', $type . '|' . $normalized, $this->hmacKey);
    }

    /** @return array{ok:bool,error?:string,uuid?:string} */
    public function report(?int $memberId, string $type, string $raw, string $problem, string $note, bool $consent = true): array
    {
        if (!isset(self::TYPES[$type])) { return ['ok' => false, 'error' => 'BAD_TYPE']; }
        $n = self::normalize($type, $raw);
        if ($n === null) { return ['ok' => false, 'error' => 'BAD_IDENTIFIER']; }
        if (!isset(self::PROBLEMS[$problem])) { return ['ok' => false, 'error' => 'BAD_PROBLEM']; }
        $note = mb_substr(trim((string)preg_replace('/\s+/u', ' ', $note)), 0, 500);
        if ($note !== '' && (CaptureMethod::hasPhoneNumber($note) || preg_match('#(https?://|www\.|line\.me|@[a-z0-9_.]{3,})#i', $note) || preg_match('/\d{8,}/', $note))) { return ['ok' => false, 'error' => 'NOTE_HAS_CONTACT']; }
        $uuid = bin2hex(random_bytes(16));
        $this->db->prepare('INSERT INTO seller_reports (uuid, member_id, identifier_type, identifier_hash, identifier_last4, problem_type, note) VALUES (:u, :m, :t, :h, :l, :p, :n)')
            ->execute(['u' => $uuid, 'm' => $memberId, 't' => $type, 'h' => $this->hash($type, $n), 'l' => substr($n, -4), 'p' => $problem, 'n' => $note !== '' ? $note : null]);
        return ['ok' => true, 'uuid' => $uuid];
    }

    /** @return array{ok:bool,error?:string,count?:int,by_type?:array<string,int>} the only thing the public ever sees */
    public function lookup(string $type, string $raw): array
    {
        if (!isset(self::TYPES[$type])) { return ['ok' => false, 'error' => 'BAD_TYPE']; }
        $n = self::normalize($type, $raw);
        if ($n === null) { return ['ok' => false, 'error' => 'BAD_IDENTIFIER']; }
        $s = $this->db->prepare("SELECT problem_type, COUNT(*) c FROM seller_reports WHERE identifier_hash = :h AND status = 'verified' GROUP BY problem_type");
        $s->execute(['h' => $this->hash($type, $n)]);
        $by = []; $total = 0;
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) { $by[(string)$r['problem_type']] = (int)$r['c']; $total += (int)$r['c']; }
        return ['ok' => true, 'count' => $total, 'by_type' => $by];
    }

    /** @return array{ok:bool,error?:string} */
    public function dispute(string $type, string $raw, string $explanation, string $email): array
    {
        $n = self::normalize($type, $raw);
        if ($n === null) { return ['ok' => false, 'error' => 'BAD_IDENTIFIER']; }
        $explanation = mb_substr(trim($explanation), 0, 1000);
        if (mb_strlen($explanation) < 10) { return ['ok' => false, 'error' => 'EXPLANATION_REQUIRED']; }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 190) { return ['ok' => false, 'error' => 'BAD_EMAIL']; }
        $h = $this->hash($type, $n);
        $own = !$this->db->inTransaction();
        if ($own) { $this->db->beginTransaction(); }
        try {
            $this->db->prepare('INSERT INTO seller_report_disputes (identifier_hash, explanation, contact_email) VALUES (:h, :e, :c)')->execute(['h' => $h, 'e' => $explanation, 'c' => strtolower($email)]);
            // the reports leave the public count while the dispute is open
            $this->db->prepare("UPDATE seller_reports SET status = 'disputed' WHERE identifier_hash = :h AND status = 'verified'")->execute(['h' => $h]);
            if ($own) { $this->db->commit(); }
        } catch (\Throwable $e) { if ($own) { $this->db->rollBack(); } throw $e; }
        return ['ok' => true];
    }

    /** @return list<array> admin queue: pending reports first, then open disputes' reports */
    public function queue(int $limit = 200): array
    {
        $s = $this->db->query("SELECT r.id, r.uuid, r.identifier_type, r.identifier_last4, r.problem_type, r.note, r.status, r.created_at, r.moderation_note,
                (SELECT COUNT(*) FROM seller_report_evidence e WHERE e.report_id = r.id) evidence_count,
                (SELECT COUNT(*) FROM seller_reports o WHERE o.identifier_hash = r.identifier_hash AND o.id <> r.id) same_identifier_reports,
                (SELECT COUNT(*) FROM seller_report_disputes d WHERE d.identifier_hash = r.identifier_hash AND d.status = 'open') open_disputes
            FROM seller_reports r ORDER BY (r.status = 'pending') DESC, (r.status = 'disputed') DESC, r.id DESC LIMIT " . max(1, min(500, $limit)));
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array> open disputes with the contact email (admin only) */
    public function openDisputes(): array
    {
        $rows = $this->db->query("SELECT id, identifier_hash, explanation, contact_email, created_at, (SELECT MAX(identifier_last4) FROM seller_reports r WHERE r.identifier_hash = d.identifier_hash) last4
            FROM seller_report_disputes d WHERE status = 'open' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        $now = new \DateTimeImmutable('now');
        foreach ($rows as &$r) { $r += self::dueInfo((string)$r['created_at'], $now); }
        return $rows;
    }

    /** LS-25: DRAFT response window for a dispute (the owner and the lawyer set the real one, HG-14); the admin queue shows age and whether it is past the window. Pure. */
    public const DISPUTE_RESPONSE_DAYS = 14;

    /** @return array{age_days:int,overdue:bool} */
    public static function dueInfo(string $createdAt, \DateTimeImmutable $now): array
    {
        $c = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $createdAt);
        if ($c === false) { return ['age_days' => 0, 'overdue' => false]; }
        $days = max(0, (int)$c->diff($now)->format('%r%a'));
        return ['age_days' => $days, 'overdue' => $days > self::DISPUTE_RESPONSE_DAYS];
    }

    /** @return array{ok:bool,error?:string} verified | rejected for a report; a rejection needs a reason */
    public function moderate(int $adminId, int $reportId, string $decision, string $note = ''): array
    {
        if (!in_array($decision, ['verified', 'rejected'], true)) { return ['ok' => false, 'error' => 'BAD_ACTION']; }
        $note = mb_substr(trim($note), 0, 255);
        if ($decision === 'rejected' && $note === '') { return ['ok' => false, 'error' => 'NOTE_REQUIRED']; }
        $s = $this->db->prepare("UPDATE seller_reports SET status = :d, moderated_by = :a, moderated_at = NOW(), moderation_note = :n WHERE id = :id AND status IN ('pending','disputed')");
        $s->execute(['d' => $decision, 'a' => $adminId, 'n' => $note !== '' ? $note : null, 'id' => $reportId]);
        if ($s->rowCount() !== 1) { return ['ok' => false, 'error' => 'BAD_STATE']; }
        (new AuthService())->audit($adminId, 'seller_report_' . $decision, 'seller_report', $reportId, ['note' => $note]);
        return ['ok' => true];
    }

    /** Close a dispute (after the admin decided the reports it covers). */
    public function closeDispute(int $adminId, int $disputeId): bool
    {
        $s = $this->db->prepare("UPDATE seller_report_disputes SET status = 'closed', resolved_by = :a, resolved_at = NOW() WHERE id = :id AND status = 'open'");
        $s->execute(['a' => $adminId, 'id' => $disputeId]);
        if ($s->rowCount() === 1) { (new AuthService())->audit($adminId, 'seller_dispute_closed', 'seller_report_dispute', $disputeId, []); return true; }
        return false;
    }
}
