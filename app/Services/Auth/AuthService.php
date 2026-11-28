<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Core\Database;
use PDO;

final class AuthService
{
    public const SESSION_USER_ID = 'auth_user_id';
    public const SESSION_LOGIN_AT = 'auth_login_at';
    public const SESSION_LAST_SEEN_AT = 'auth_last_seen_at';
    /** LS-89: password accepted, second factor still to be given; holds no permission */
    public const SESSION_MFA_PENDING = 'auth_mfa_pending';
    private const MFA_PENDING_SECONDS = 300;
    public const IDLE_TIMEOUT_SECONDS = 1800;
    public const ABSOLUTE_LIFETIME_SECONDS = 28800;
    /**
     * Secure defaults above. AUTH_IDLE_TIMEOUT_SECONDS / AUTH_ABSOLUTE_LIFETIME_SECONDS in .env may lengthen them (bounded: 5 minutes to 30 days),
     * but ONLY when APP_ENV is local or testing: on any other environment (a missing APP_ENV counts as production) the overrides are ignored,
     * so a copied development .env can never give a production admin a 7-day session.
     */
    public static function overridesAllowed(): bool
    {
        return in_array(strtolower((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production')), ['local', 'testing'], true);
    }
    public static function idleTimeout(): int { return self::envSeconds('AUTH_IDLE_TIMEOUT_SECONDS', self::IDLE_TIMEOUT_SECONDS); }
    public static function absoluteLifetime(): int { return max(self::idleTimeout(), self::envSeconds('AUTH_ABSOLUTE_LIFETIME_SECONDS', self::ABSOLUTE_LIFETIME_SECONDS)); }
    private static function envSeconds(string $key, int $default): int
    {
        if (!self::overridesAllowed()) { return $default; }
        $v = $_ENV[$key] ?? getenv($key);
        return (is_string($v) && ctype_digit($v)) ? max(300, min(2592000, (int)$v)) : $default;
    }
    private const MAX_FAILED_ATTEMPTS = 5;
    private const ATTEMPT_WINDOW_MINUTES = 15;

    private PDO $db;

    public function __construct()
    {
        global $config;
        $this->db = Database::connection($config['db']);
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
    }

    public function currentUser(): ?array
    {
        $userId = $_SESSION[self::SESSION_USER_ID] ?? null;
        if (!$userId) {
            return null;
        }
        $now = time();
        $loginAt = (int)($_SESSION[self::SESSION_LOGIN_AT] ?? 0);
        $lastSeen = (int)($_SESSION[self::SESSION_LAST_SEEN_AT] ?? 0);
        if ($loginAt <= 0 || $lastSeen <= 0 || ($now - $lastSeen) > self::idleTimeout() || ($now - $loginAt) > self::absoluteLifetime()) {
            $this->logout(false);
            return null;
        }

        $stmt = $this->db->prepare("SELECT id,name,email,role,is_active,last_login_at,created_at FROM users WHERE id=:id LIMIT 1");
        $stmt->execute(['id' => (int)$userId]);
        $user = $stmt->fetch();
        if (!$user || (int)$user['is_active'] !== 1) {
            $this->logout(false);
            return null;
        }
        $_SESSION[self::SESSION_LAST_SEEN_AT] = $now;
        return $user;
    }

    public function can(string $permission): bool
    {
        return (new AuthorizationService())->can($this->currentUser(), $permission);
    }

    public function login(string $email, string $password, ?string $ipAddress = null): array
    {
        $email = $this->normalizeEmail($email);
        $ipAddress = $this->safeIp($ipAddress);
        if ($email === '' || $password === '') {
            $this->recordLoginAttempt($email, $ipAddress, false);
            return ['ok' => false, 'error' => 'INVALID_CREDENTIALS'];
        }
        if ($this->tooManyAttempts($email, $ipAddress)) {
            $this->recordLoginAttempt($email, $ipAddress, false);
            return ['ok' => false, 'error' => 'TOO_MANY_ATTEMPTS'];
        }

        $stmt = $this->db->prepare("SELECT * FROM users WHERE email=:email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        $ok = $user && (int)$user['is_active'] === 1 && password_verify($password, (string)$user['password_hash']);
        $this->recordLoginAttempt($email, $ipAddress, $ok);

        if (!$ok) {
            $this->audit(null, 'auth_login_failed', 'user', null, ['email_hash' => $this->emailHash($email), 'ip_address' => $ipAddress]);
            return ['ok' => false, 'error' => 'INVALID_CREDENTIALS'];
        }

        if (password_needs_rehash((string)$user['password_hash'], $this->passwordAlgorithm())) {
            $rehash = $this->db->prepare("UPDATE users SET password_hash=:hash WHERE id=:id");
            $rehash->execute(['hash' => password_hash($password, $this->passwordAlgorithm()), 'id' => (int)$user['id']]);
        }
        if ((new MfaService($this->db))->requiredFor((int)$user['id'])) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
            unset($_SESSION[self::SESSION_USER_ID], $_SESSION[self::SESSION_LOGIN_AT], $_SESSION[self::SESSION_LAST_SEEN_AT]);
            $_SESSION[self::SESSION_MFA_PENDING] = ['user_id' => (int)$user['id'], 'at' => time()];
            $this->audit((int)$user['id'], 'auth_mfa_challenge', 'user', (int)$user['id'], []);
            return ['ok' => false, 'error' => 'MFA_REQUIRED'];
        }
        return $this->startSession($user);
    }

    public function mfaPending(): bool
    {
        $pending = $_SESSION[self::SESSION_MFA_PENDING] ?? null;
        return is_array($pending) && (time() - (int)($pending['at'] ?? 0)) <= self::MFA_PENDING_SECONDS;
    }

    /** LS-89: the second step of a sign-in. Wrong codes count against the same limit as wrong passwords. */
    public function completeMfa(string $code, ?string $ipAddress = null): array
    {
        if (!$this->mfaPending()) {
            unset($_SESSION[self::SESSION_MFA_PENDING]);
            return ['ok' => false, 'error' => 'MFA_EXPIRED'];
        }
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id=:id LIMIT 1');
        $stmt->execute(['id' => (int)$_SESSION[self::SESSION_MFA_PENDING]['user_id']]);
        $user = $stmt->fetch();
        if (!$user || (int)$user['is_active'] !== 1) {
            unset($_SESSION[self::SESSION_MFA_PENDING]);
            return ['ok' => false, 'error' => 'MFA_EXPIRED'];
        }
        $email = $this->normalizeEmail((string)$user['email']);
        $ipAddress = $this->safeIp($ipAddress);
        if ($this->tooManyAttempts($email, $ipAddress)) {
            $this->recordLoginAttempt($email, $ipAddress, false);
            return ['ok' => false, 'error' => 'TOO_MANY_ATTEMPTS'];
        }
        $ok = (new MfaService($this->db))->verify((int)$user['id'], $code);
        $this->recordLoginAttempt($email, $ipAddress, $ok);
        if (!$ok) {
            $this->audit((int)$user['id'], 'auth_mfa_failed', 'user', (int)$user['id'], []);
            return ['ok' => false, 'error' => 'MFA_CODE_WRONG'];
        }
        unset($_SESSION[self::SESSION_MFA_PENDING]);
        return $this->startSession($user);
    }

    private function startSession(array $user): array
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION[self::SESSION_USER_ID] = (int)$user['id'];
        $_SESSION[self::SESSION_LOGIN_AT] = time();
        $_SESSION[self::SESSION_LAST_SEEN_AT] = time();
        $this->db->prepare("UPDATE users SET last_login_at=NOW(),updated_at=NOW() WHERE id=:id")->execute(['id' => (int)$user['id']]);
        $this->audit((int)$user['id'], 'auth_login_success', 'user', (int)$user['id'], ['role' => $user['role']]);
        return ['ok' => true, 'user' => $this->currentUser()];
    }

    public function logout(bool $audit = true): void
    {
        $userId = isset($_SESSION[self::SESSION_USER_ID]) ? (int)$_SESSION[self::SESSION_USER_ID] : null;
        unset($_SESSION[self::SESSION_USER_ID], $_SESSION[self::SESSION_LOGIN_AT], $_SESSION[self::SESSION_LAST_SEEN_AT], $_SESSION[self::SESSION_MFA_PENDING]);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
        }
        if ($audit && $userId !== null) {
            $this->audit($userId, 'auth_logout', 'user', $userId, []);
        }
    }

    public function createUser(string $email, string $name, string $password, string $role = 'admin', bool $active = true, ?int $actorId = null): array
    {
        $email = $this->normalizeEmail($email);
        $name = trim($name) !== '' ? trim($name) : $email;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'INVALID_EMAIL'];
        }
        if (!in_array($role, ['admin', 'reviewer', 'operator', 'user'], true)) {
            return ['ok' => false, 'error' => 'INVALID_ROLE'];
        }
        if (strlen($password) < 12) {
            return ['ok' => false, 'error' => 'PASSWORD_TOO_SHORT'];
        }
        $stmt = $this->db->prepare("INSERT INTO users (name,email,password_hash,role,is_active,created_at,updated_at) VALUES (:name,:email,:hash,:role,:active,NOW(),NOW())");
        try {
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'hash' => password_hash($password, $this->passwordAlgorithm()),
                'role' => $role,
                'active' => $active ? 1 : 0,
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'error' => 'EMAIL_ALREADY_EXISTS'];
            }
            throw $e;
        }
        $id = (int)$this->db->lastInsertId();
        $this->audit($actorId, 'auth_user_created', 'user', $id, ['email' => $email, 'role' => $role, 'active' => $active]);
        return ['ok' => true, 'user_id' => $id];
    }

    /** Sets a new password for an existing active user (CLI recovery path; the password never leaves the process). */
    public function resetPassword(string $email, string $password, ?int $actorId = null): array
    {
        $email = $this->normalizeEmail($email);
        if (strlen($password) < 12) {
            return ['ok' => false, 'error' => 'PASSWORD_TOO_SHORT'];
        }
        $stmt = $this->db->prepare('SELECT id FROM users WHERE email=:email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $id = (int)$stmt->fetchColumn();
        if ($id <= 0) {
            return ['ok' => false, 'error' => 'USER_NOT_FOUND'];
        }
        $this->db->prepare('UPDATE users SET password_hash=:hash, updated_at=NOW() WHERE id=:id')
            ->execute(['hash' => password_hash($password, $this->passwordAlgorithm()), 'id' => $id]);
        $this->audit($actorId ?? $id, 'auth_password_reset', 'user', $id, ['email' => $email, 'via' => 'cli']);
        return ['ok' => true, 'user_id' => $id];
    }
    public function passwordAlgorithm(): string|int|null
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }

    public function audit(?int $userId, string $action, ?string $entityType, ?int $entityId, array $after, ?array $before = null): void
    {
        $stmt = $this->db->prepare("INSERT INTO audit_logs (user_id,action,entity_type,entity_id,before_data,after_data,ip_address) VALUES (:user_id,:action,:entity_type,:entity_id,:before_data,:after_data,:ip_address)");
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_data' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
            'after_data' => json_encode($after, JSON_UNESCAPED_UNICODE),
            'ip_address' => $this->safeIp($_SERVER['REMOTE_ADDR'] ?? null),
        ]);
    }

    private function tooManyAttempts(string $email, string $ipAddress): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE email_hash=:email_hash AND ip_address=:ip_address AND success=0 AND attempted_at >= DATE_SUB(NOW(), INTERVAL " . self::ATTEMPT_WINDOW_MINUTES . " MINUTE)"
        );
        $stmt->execute(['email_hash' => $this->emailHash($email), 'ip_address' => $ipAddress]);
        return (int)$stmt->fetchColumn() >= self::MAX_FAILED_ATTEMPTS;
    }

    private function recordLoginAttempt(string $email, string $ipAddress, bool $success): void
    {
        $stmt = $this->db->prepare("INSERT INTO login_attempts (email_hash,ip_address,attempted_at,success) VALUES (:email_hash,:ip_address,NOW(),:success)");
        $stmt->execute(['email_hash' => $this->emailHash($email), 'ip_address' => $ipAddress, 'success' => $success ? 1 : 0]);
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    private function emailHash(string $email): string
    {
        return hash('sha256', $this->normalizeEmail($email));
    }

    private function safeIp(?string $ipAddress): string
    {
        $ipAddress = trim((string)$ipAddress);
        return $ipAddress !== '' && strlen($ipAddress) <= 45 ? $ipAddress : 'cli';
    }
}
