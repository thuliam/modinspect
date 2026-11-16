<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Services\Auth\AuthService;
use PDO;

/**
 * Admin user management (LS-3 J-2): create a user, change a role, disable or enable an account, set a new password.
 * Rules that protect the system from the people running it:
 *  - nobody changes their own role or disables their own account (no accidental self-lockout);
 *  - the last active admin can never be demoted or disabled;
 *  - passwords are at least 12 characters, are hashed by AuthService, never returned, never written to the audit log;
 *  - every change is audited with the acting admin.
 * Disabling takes effect on the next request: AuthService::currentUser re-reads is_active every time. A password change does not end sessions that
 * are already open (PHP session files are not indexed by user); disable the account first when a session must be cut.
 * MFA is not part of this task.
 */
final class UserAdministration
{
    public const ROLES = ['admin', 'reviewer', 'operator', 'user'];
    private PDO $db;
    private AuthService $auth;

    public function __construct(?PDO $db = null, ?AuthService $auth = null)
    {
        global $config;
        $this->db = $db ?? Database::connection($config['db']);
        $this->auth = $auth ?? new AuthService();
    }

    /** @return list<array{id:int,name:string,email:string,role:string,is_active:int,last_login_at:?string,created_at:string}> never contains the password hash */
    public function list(): array
    {
        return $this->db->query('SELECT id,name,email,role,is_active,last_login_at,created_at FROM users ORDER BY is_active DESC, role, email')->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{ok:bool,error:?string,user_id?:int} */
    public function create(int $actorId, string $email, string $name, string $password, string $role): array
    {
        $r = $this->auth->createUser($email, $name, $password, $role, true, $actorId);
        return ($r['ok'] ?? false) ? ['ok' => true, 'error' => null, 'user_id' => (int)$r['user_id']] : ['ok' => false, 'error' => (string)($r['error'] ?? 'CREATE_FAILED')];
    }

    /** @return array{ok:bool,error:?string} */
    public function changeRole(int $actorId, int $userId, string $role): array
    {
        if (!in_array($role, self::ROLES, true)) { return ['ok' => false, 'error' => 'INVALID_ROLE']; }
        $u = $this->find($userId);
        if ($u === null) { return ['ok' => false, 'error' => 'USER_NOT_FOUND']; }
        if ($userId === $actorId) { return ['ok' => false, 'error' => 'CANNOT_CHANGE_OWN_ROLE']; }
        if ($u['role'] === $role) { return ['ok' => true, 'error' => null]; }
        if ($u['role'] === 'admin' && (int)$u['is_active'] === 1 && $this->activeAdmins($userId) < 1) { return ['ok' => false, 'error' => 'LAST_ADMIN']; }
        $this->db->prepare('UPDATE users SET role = :r, updated_at = NOW() WHERE id = :id')->execute(['r' => $role, 'id' => $userId]);
        $this->auth->audit($actorId, 'auth_user_role_changed', 'user', $userId, ['role' => $role], ['role' => $u['role']]);
        return ['ok' => true, 'error' => null];
    }

    /** @return array{ok:bool,error:?string} */
    public function setActive(int $actorId, int $userId, bool $active): array
    {
        $u = $this->find($userId);
        if ($u === null) { return ['ok' => false, 'error' => 'USER_NOT_FOUND']; }
        if (!$active && $userId === $actorId) { return ['ok' => false, 'error' => 'CANNOT_DISABLE_SELF']; }
        if ((int)$u['is_active'] === ($active ? 1 : 0)) { return ['ok' => true, 'error' => null]; }
        if (!$active && $u['role'] === 'admin' && $this->activeAdmins($userId) < 1) { return ['ok' => false, 'error' => 'LAST_ADMIN']; }
        $this->db->prepare('UPDATE users SET is_active = :a, updated_at = NOW() WHERE id = :id')->execute(['a' => $active ? 1 : 0, 'id' => $userId]);
        $this->auth->audit($actorId, $active ? 'auth_user_enabled' : 'auth_user_disabled', 'user', $userId, ['is_active' => $active ? 1 : 0], ['is_active' => (int)$u['is_active']]);
        return ['ok' => true, 'error' => null];
    }

    /** @return array{ok:bool,error:?string} */
    public function setPassword(int $actorId, int $userId, string $password): array
    {
        if (strlen($password) < 12) { return ['ok' => false, 'error' => 'PASSWORD_TOO_SHORT']; }
        $u = $this->find($userId);
        if ($u === null) { return ['ok' => false, 'error' => 'USER_NOT_FOUND']; }
        $this->db->prepare('UPDATE users SET password_hash = :h, updated_at = NOW() WHERE id = :id')
            ->execute(['h' => password_hash($password, $this->auth->passwordAlgorithm()), 'id' => $userId]);
        $this->auth->audit($actorId, 'auth_password_reset', 'user', $userId, ['email' => $u['email'], 'via' => 'admin']);
        return ['ok' => true, 'error' => null];
    }

    /** @return array{id:int,email:string,role:string,is_active:int}|null */
    private function find(int $id): ?array
    {
        $q = $this->db->prepare('SELECT id,email,role,is_active FROM users WHERE id = :id');
        $q->execute(['id' => $id]);
        $r = $q->fetch(PDO::FETCH_ASSOC);
        return $r ? ['id' => (int)$r['id'], 'email' => (string)$r['email'], 'role' => (string)$r['role'], 'is_active' => (int)$r['is_active']] : null;
    }

    private function activeAdmins(int $excludingUserId): int
    {
        $q = $this->db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND id <> :id");
        $q->execute(['id' => $excludingUserId]);
        return (int)$q->fetchColumn();
    }
}
