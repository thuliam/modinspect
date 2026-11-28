<?php
declare(strict_types=1);

namespace App\Services\Auth;

use PDO;
use PDOException;

/**
 * LS-90 D4: an extra capability an admin gives to an admin-side account, on top of its role. The list below is the only place a
 * name and what it permits are defined. Granting and revoking are audited; a revoked row is kept.
 */
final class UserCapabilities
{
    /** capability => the permissions it adds */
    public const PERMISSIONS = ['content_moderator' => ['admin.dashboard.view', 'listings.moderate', 'reports.moderate']];

    public function __construct(private PDO $db)
    {
    }

    /** @return list<string> capabilities held now; none on a host without migration 044 */
    public function listFor(int $userId): array
    {
        try {
            $s = $this->db->prepare('SELECT capability FROM user_capabilities WHERE user_id = ? AND revoked_at IS NULL ORDER BY capability');
            $s->execute([$userId]);
            return array_values(array_intersect($s->fetchAll(PDO::FETCH_COLUMN), array_keys(self::PERMISSIONS)));
        } catch (PDOException) {
            return [];
        }
    }

    /** @return array{ok:bool,error:?string} */
    public function set(int $userId, string $capability, bool $held, int $adminId): array
    {
        if (!isset(self::PERMISSIONS[$capability])) {
            return ['ok' => false, 'error' => 'CAPABILITY'];
        }
        $user = $this->db->prepare('SELECT 1 FROM users WHERE id = ?');
        $user->execute([$userId]);
        if (!$user->fetchColumn()) {
            return ['ok' => false, 'error' => 'NOT_FOUND'];
        }
        if ($held) {
            $this->db->prepare('INSERT INTO user_capabilities (user_id, capability, granted_by_user_id) VALUES (?,?,?)
                ON DUPLICATE KEY UPDATE revoked_at = NULL, granted_by_user_id = VALUES(granted_by_user_id), granted_at = NOW()')->execute([$userId, $capability, $adminId]);
        } else {
            $this->db->prepare('UPDATE user_capabilities SET revoked_at = NOW() WHERE user_id = ? AND capability = ? AND revoked_at IS NULL')->execute([$userId, $capability]);
        }
        (new AuthService())->audit($adminId, $held ? 'user_capability_granted' : 'user_capability_revoked', 'user', $userId, ['capability' => $capability]);
        return ['ok' => true, 'error' => null];
    }
}
