<?php
declare(strict_types=1);

namespace App\Services\Member;

use PDO;

/** Marketplace P1-A: append-only record of member-facing actions. Stores who did what to which record; never an IP address or member-typed text. */
final class MemberAudit
{
    public function __construct(private PDO $db) {}

    public function record(?int $memberId, string $actorType, ?int $actorId, string $action, ?string $targetType = null, ?int $targetId = null): void
    {
        if (!in_array($actorType, ['member', 'admin', 'system'], true)) { throw new \InvalidArgumentException('ACTOR_TYPE'); }
        $this->db->prepare('INSERT INTO member_audit_events (member_id, actor_type, actor_id, action, target_type, target_id) VALUES (:m, :t, :a, :x, :tt, :ti)')
            ->execute(['m' => $memberId, 't' => $actorType, 'a' => $actorId, 'x' => substr($action, 0, 80), 'tt' => $targetType, 'ti' => $targetId]);
    }

    /** @return list<array<string,mixed>> */
    public function forMember(int $memberId, int $limit = 50): array
    {
        $stmt = $this->db->prepare('SELECT action, actor_type, target_type, target_id, created_at FROM member_audit_events WHERE member_id = :m ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)));
        $stmt->execute(['m' => $memberId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
