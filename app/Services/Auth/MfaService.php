<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Core\Database;
use PDO;

/**
 * LS-89 BE-3: second sign-in factor for admin accounts. OFF unless AUTH_MFA_ENABLED=true; a new enrolment also needs AUTH_MFA_KEY
 * (32 or more characters) and migration 039. The secret is stored encrypted (AES-256-GCM), recovery codes only as password hashes.
 * An enrolled user is always asked for a code while the feature is on: if the key is missing or wrong the sign-in fails (it never opens),
 * and cli/mfa_reset.php removes the enrolment. Secrets and codes are never logged.
 */
final class MfaService
{
    public const RECOVERY_CODES = 8;

    private PDO $db;
    private bool $enabled;
    private string $key;

    public function __construct(?PDO $db = null, ?array $settings = null)
    {
        global $config;
        $this->db = $db ?? Database::connection($config['db']);
        $settings ??= $config['auth_mfa'] ?? [];
        $this->enabled = (bool)($settings['enabled'] ?? false);
        $this->key = (string)($settings['key'] ?? '');
    }

    public function featureOn(): bool
    {
        return $this->enabled;
    }

    /** a user can enrol: the feature is on, the key is long enough and the table exists */
    public function canEnrol(): bool
    {
        return $this->enabled && strlen($this->key) >= 32 && $this->tableExists();
    }

    public function requiredFor(int $userId): bool
    {
        return $this->enabled && $this->isEnrolled($userId);
    }

    public function isEnrolled(int $userId): bool
    {
        $row = $this->row($userId);
        return $row !== null && $row['enabled_at'] !== null;
    }

    /** starts (or restarts) an enrolment that is not confirmed yet. @return array{ok:bool,error:?string,secret:?string,uri:?string} */
    public function begin(int $userId, string $account): array
    {
        if (!$this->canEnrol()) {
            return ['ok' => false, 'error' => 'MFA_UNAVAILABLE', 'secret' => null, 'uri' => null];
        }
        if ($this->isEnrolled($userId)) {
            return ['ok' => false, 'error' => 'MFA_ALREADY_ENABLED', 'secret' => null, 'uri' => null];
        }
        $secret = random_bytes(20);
        $this->db->prepare('INSERT INTO user_mfa (user_id, secret_encrypted) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE secret_encrypted = VALUES(secret_encrypted), enabled_at = NULL, last_used_step = NULL, recovery_codes = NULL')
            ->execute([$userId, $this->encrypt($secret)]);
        return ['ok' => true, 'error' => null] + $this->describe($secret, $account);
    }

    /** the secret of an unconfirmed enrolment, to show it again on the confirmation page. @return ?array{secret:string,uri:string} */
    public function pending(int $userId, string $account): ?array
    {
        $row = $this->row($userId);
        if ($row === null || $row['enabled_at'] !== null || !$this->canEnrol()) {
            return null;
        }
        $secret = $this->decrypt((string)$row['secret_encrypted']);
        return $secret === null ? null : $this->describe($secret, $account);
    }

    /** turns the enrolment on after one correct code. @return array{ok:bool,error:?string,recovery_codes:list<string>} shown once, never again */
    public function confirm(int $userId, string $code, ?int $now = null): array
    {
        $row = $this->canEnrol() ? $this->row($userId) : null;
        if ($row === null || $row['enabled_at'] !== null) {
            return ['ok' => false, 'error' => 'MFA_NOT_PENDING', 'recovery_codes' => []];
        }
        $secret = $this->decrypt((string)$row['secret_encrypted']);
        $step = $secret === null ? null : Totp::match($secret, $code, $now ?? time());
        if ($step === null) {
            return ['ok' => false, 'error' => 'MFA_CODE_WRONG', 'recovery_codes' => []];
        }
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $codes[] = substr(Totp::base32Encode(random_bytes(7)), 0, 10);
        }
        $hashes = array_map(static fn(string $c): string => password_hash($c, PASSWORD_DEFAULT), $codes);
        $this->db->prepare('UPDATE user_mfa SET enabled_at = NOW(), last_used_step = ?, recovery_codes = ? WHERE user_id = ? AND enabled_at IS NULL')
            ->execute([$step, json_encode($hashes), $userId]);
        (new AuthService())->audit($userId, 'auth_mfa_enabled', 'user', $userId, []);
        return ['ok' => true, 'error' => null, 'recovery_codes' => $codes];
    }

    /** accepts the current code (once) or an unused recovery code (which is then spent) */
    public function verify(int $userId, string $input, ?int $now = null): bool
    {
        $row = $this->row($userId);
        if ($row === null || $row['enabled_at'] === null) {
            return false;
        }
        $secret = $this->decrypt((string)$row['secret_encrypted']);
        if ($secret !== null) {
            $step = Totp::match($secret, $input, $now ?? time(), $row['last_used_step'] === null ? null : (int)$row['last_used_step']);
            if ($step !== null) {
                // the condition makes two simultaneous uses of one code succeed only once
                $update = $this->db->prepare('UPDATE user_mfa SET last_used_step = ? WHERE user_id = ? AND (last_used_step IS NULL OR last_used_step < ?)');
                $update->execute([$step, $userId, $step]);
                return $update->rowCount() === 1;
            }
        }
        $candidate = strtoupper(preg_replace('/[\s-]+/', '', $input) ?? '');
        if (strlen($candidate) !== 10) {
            return false;
        }
        $hashes = json_decode((string)$row['recovery_codes'], true) ?: [];
        foreach ($hashes as $i => $hash) {
            if (password_verify($candidate, (string)$hash)) {
                unset($hashes[$i]);
                $update = $this->db->prepare('UPDATE user_mfa SET recovery_codes = ? WHERE user_id = ? AND recovery_codes = ?');
                $update->execute([json_encode(array_values($hashes)), $userId, $row['recovery_codes']]);
                if ($update->rowCount() !== 1) {
                    return false;
                }
                (new AuthService())->audit($userId, 'auth_mfa_recovery_code_used', 'user', $userId, ['left' => count($hashes)]);
                return true;
            }
        }
        return false;
    }

    /** removes the enrolment. $actorId is the person doing it (the user, or null from the recovery command) */
    public function remove(int $userId, ?int $actorId, string $via): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        $stmt = $this->db->prepare('DELETE FROM user_mfa WHERE user_id = ?');
        $stmt->execute([$userId]);
        if ($stmt->rowCount() !== 1) {
            return false;
        }
        (new AuthService())->audit($actorId, 'auth_mfa_removed', 'user', $userId, ['via' => $via]);
        return true;
    }

    private function describe(string $secret, string $account): array
    {
        $base32 = Totp::base32Encode($secret);
        $uri = 'otpauth://totp/' . rawurlencode('ModInspect:' . $account) . '?secret=' . $base32 . '&issuer=ModInspect&period=' . Totp::PERIOD . '&digits=' . Totp::DIGITS;
        return ['secret' => $base32, 'uri' => $uri];
    }

    private function row(int $userId): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM user_mfa WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    private function tableExists(): bool
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_mfa'")->fetchColumn() === 1;
    }

    private function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', hash('sha256', $this->key, true), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('MFA_ENCRYPTION_FAILED');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    private function decrypt(string $stored): ?string
    {
        $raw = base64_decode($stored, true);
        if ($this->key === '' || $raw === false || strlen($raw) <= 28) {
            return null;
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', hash('sha256', $this->key, true), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? null : $plain;
    }
}
