<?php
declare(strict_types=1);

namespace App\Services\Member;

use PDO;

/**
 * Marketplace P1-A: the data-subject request queue. A member opens a request; an administrator moves it through the states. No response deadline is
 * invented here: due_at is only set when the owner configures a number of days (HG-LEGAL decides the value). A deletion request does not delete anything;
 * the member's own "delete account" button stays the immediate path and this queue records requests that need handling by a person.
 */
final class PrivacyRequests
{
    public const TYPES = ['access', 'export', 'correction', 'deletion', 'objection'];
    private const NEXT = ['received' => ['in_progress', 'completed', 'rejected'], 'in_progress' => ['completed', 'rejected'], 'completed' => [], 'rejected' => []];

    public function __construct(private PDO $db, private MemberAudit $audit, private ?int $dueDays = null) {}

    /** One open request per member and type; asking again returns the open one instead of piling up duplicates. */
    public function open(int $memberId, string $type, string $detail = ''): int
    {
        if (!in_array($type, self::TYPES, true)) { throw new \InvalidArgumentException('REQUEST_TYPE'); }
        $dup = $this->db->prepare("SELECT id FROM privacy_requests WHERE member_id = :m AND request_type = :t AND status IN ('received','in_progress') LIMIT 1");
        $dup->execute(['m' => $memberId, 't' => $type]);
        if ($id = $dup->fetchColumn()) { return (int)$id; }
        $due = $this->dueDays !== null ? 'DATE_ADD(NOW(), INTERVAL ' . (int)$this->dueDays . ' DAY)' : 'NULL';
        $this->db->prepare('INSERT INTO privacy_requests (member_id, request_type, detail, due_at) VALUES (:m, :t, :d, ' . $due . ')')
            ->execute(['m' => $memberId, 't' => $type, 'd' => (mb_substr(trim($detail), 0, 500) ?: null)]);
        $id = (int)$this->db->lastInsertId();
        $this->audit->record($memberId, 'member', $memberId, 'privacy.requested', 'privacy_request', $id);
        return $id;
    }

    /** @return list<array<string,mixed>> */
    public function forMember(int $memberId): array
    {
        $stmt = $this->db->prepare('SELECT id, request_type, status, received_at, responded_at FROM privacy_requests WHERE member_id = :m ORDER BY id DESC LIMIT 20');
        $stmt->execute(['m' => $memberId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function queue(string $status = 'received'): array
    {
        $stmt = $this->db->prepare('SELECT r.id, r.member_id, m.display_name, r.request_type, r.status, r.detail, r.received_at, r.due_at, r.response_note FROM privacy_requests r LEFT JOIN members m ON m.id = r.member_id WHERE r.status = :s ORDER BY r.received_at LIMIT 200');
        $stmt->execute(['s' => $status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,int> */
    public function counts(): array
    {
        $out = ['received' => 0, 'in_progress' => 0, 'completed' => 0, 'rejected' => 0];
        foreach ($this->db->query('SELECT status, COUNT(*) AS n FROM privacy_requests GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $r) { $out[(string)$r['status']] = (int)$r['n']; }
        return $out;
    }

    public function transition(int $requestId, string $to, int $adminUserId, string $note = ''): void
    {
        $stmt = $this->db->prepare('SELECT status, member_id FROM privacy_requests WHERE id = :id');
        $stmt->execute(['id' => $requestId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) { throw new \RuntimeException('NOT_FOUND'); }
        if (!in_array($to, self::NEXT[$r['status']] ?? [], true)) { throw new \RuntimeException('ILLEGAL_TRANSITION'); }
        $done = in_array($to, ['completed', 'rejected'], true);
        $upd = $this->db->prepare('UPDATE privacy_requests SET status = :s, handler_user_id = :u, response_note = :n, responded_at = ' . ($done ? 'NOW()' : 'responded_at') . ' WHERE id = :id AND status = :from');
        $upd->execute(['s' => $to, 'u' => $adminUserId, 'n' => (mb_substr(trim($note), 0, 500) ?: null), 'id' => $requestId, 'from' => $r['status']]);
        if ($upd->rowCount() === 0) { throw new \RuntimeException('ILLEGAL_TRANSITION'); }   // someone else moved it first
        $this->audit->record($r['member_id'] !== null ? (int)$r['member_id'] : null, 'admin', $adminUserId, 'privacy.' . $to, 'privacy_request', $requestId);
    }
}
