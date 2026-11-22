<?php
declare(strict_types=1);

namespace App\Services\Member;

use PDO;

/**
 * Marketplace P1-A: what a member may do, kept as capabilities (a member can hold several) instead of one global role, so one account can be buyer and
 * seller. The allowlist below is the only place a capability name is defined. Granting is an administrative act and is audited with the admin's id;
 * a capability is revoked (kept with revoked_at), never deleted, so the history stays. Having the capability is necessary, not sufficient: later
 * seller steps still check the member's verification state.
 */
final class MemberCapabilities
{
    public const ALLOWED = ['seller'];

    public function __construct(private PDO $db, private MemberAudit $audit) {}

    public function has(int $memberId, string $capability): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM member_capabilities c JOIN members m ON m.id = c.member_id WHERE c.member_id = :m AND c.capability = :c AND c.revoked_at IS NULL AND m.status = 'active'");
        $stmt->execute(['m' => $memberId, 'c' => $capability]);
        return (bool)$stmt->fetchColumn();
    }

    public function grant(int $memberId, string $capability, int $adminUserId): void
    {
        $this->assertAllowed($capability);
        $this->db->prepare('INSERT INTO member_capabilities (member_id, capability, granted_by_user_id) VALUES (:m, :c, :u)
            ON DUPLICATE KEY UPDATE revoked_at = NULL, granted_by_user_id = VALUES(granted_by_user_id), granted_at = NOW()')
            ->execute(['m' => $memberId, 'c' => $capability, 'u' => $adminUserId]);
        $this->audit->record($memberId, 'admin', $adminUserId, 'capability.granted:' . $capability, 'member', $memberId);
    }

    public function revoke(int $memberId, string $capability, int $adminUserId): void
    {
        $this->assertAllowed($capability);
        $this->db->prepare('UPDATE member_capabilities SET revoked_at = NOW() WHERE member_id = :m AND capability = :c AND revoked_at IS NULL')->execute(['m' => $memberId, 'c' => $capability]);
        $this->audit->record($memberId, 'admin', $adminUserId, 'capability.revoked:' . $capability, 'member', $memberId);
    }

    /** @return list<string> */
    public function listFor(int $memberId): array
    {
        $stmt = $this->db->prepare('SELECT capability FROM member_capabilities WHERE member_id = :m AND revoked_at IS NULL ORDER BY capability');
        $stmt->execute(['m' => $memberId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return list<array<string,mixed>> members who hold a capability right now */
    public function holders(): array
    {
        return $this->db->query('SELECT c.member_id, m.display_name, m.status, c.capability, c.granted_at, c.granted_by_user_id FROM member_capabilities c JOIN members m ON m.id = c.member_id WHERE c.revoked_at IS NULL ORDER BY c.granted_at DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function memberExists(int $memberId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM members WHERE id = :m');
        $stmt->execute(['m' => $memberId]);
        return (bool)$stmt->fetchColumn();
    }

    private function assertAllowed(string $capability): void
    {
        if (!in_array($capability, self::ALLOWED, true)) { throw new \InvalidArgumentException('CAPABILITY'); }
    }
}
