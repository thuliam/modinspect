<?php
declare(strict_types=1);

namespace App\Services\Member;

use PDO;

/**
 * LS-14 Module 7: visitor sign-in. The session keys are separate from the admin's (AuthService), so a member is never an admin and an admin
 * session is never touched. The flow: start() keeps a one-time state, PKCE verifier and nonce in the session (10 minutes) and returns the
 * provider URL; callback() takes them out again (one use), checks the state, lets the provider verify the code, then finds or creates the member
 * by (provider, subject). There is no linking by email: two providers are two accounts unless the member links them later (not built).
 */
final class MemberAuth
{
    public const SESSION_MEMBER_ID = 'member_id';
    public const SESSION_PENDING = 'member_oauth_pending';
    public const PENDING_TTL = 600;

    public function __construct(private PDO $db, private ?\App\Services\Listing\ListingPhotoStore $photos = null) {}

    public function start(OAuthProvider $p, string $redirectUri, bool $consent, ?int $now = null): string
    {
        if (!$consent) { throw new \RuntimeException('CONSENT_REQUIRED'); }
        $verifier = Jwt::b64url(random_bytes(32));
        $pending = ['provider' => $p->key(), 'state' => bin2hex(random_bytes(16)), 'verifier' => $verifier, 'nonce' => bin2hex(random_bytes(16)), 'at' => $now ?? time()];
        $_SESSION[self::SESSION_PENDING] = $pending;
        return $p->authorizeUrl($redirectUri, $pending['state'], Jwt::b64url(hash('sha256', $verifier, true)), $pending['nonce']);
    }

    /** @return array{ok:bool,error?:string,member_id?:int,created?:bool} */
    public function callback(OAuthProvider $p, string $redirectUri, ?string $state, ?string $code, ?string $providerError, ?int $now = null): array
    {
        $pending = $_SESSION[self::SESSION_PENDING] ?? null;
        unset($_SESSION[self::SESSION_PENDING]);   // one use, whatever happens next
        if ($providerError !== null && $providerError !== '') { return ['ok' => false, 'error' => 'PROVIDER_DENIED']; }
        if (!is_array($pending) || ($pending['provider'] ?? '') !== $p->key() || (($now ?? time()) - (int)($pending['at'] ?? 0)) > self::PENDING_TTL) { return ['ok' => false, 'error' => 'STATE_EXPIRED']; }
        if (!is_string($state) || !hash_equals((string)$pending['state'], $state) || !is_string($code) || $code === '' || strlen($code) > 2048) { return ['ok' => false, 'error' => 'STATE_MISMATCH']; }
        try {
            $who = $p->exchange($code, $redirectUri, (string)$pending['verifier'], (string)$pending['nonce']);
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'error' => 'PROVIDER_REJECTED'];
        }
        $find = $this->db->prepare('SELECT m.id, m.status FROM member_identities i JOIN members m ON m.id = i.member_id WHERE i.provider = :p AND i.subject = :s');
        $find->execute(['p' => $p->key(), 's' => $who['subject']]);
        $row = $find->fetch(PDO::FETCH_ASSOC);
        $created = false;
        if ($row) {
            if ($row['status'] !== 'active') { return ['ok' => false, 'error' => 'MEMBER_DISABLED']; }
            $id = (int)$row['id'];
        } else {
            $own = !$this->db->inTransaction();
            if ($own) { $this->db->beginTransaction(); }
            try {
                $this->db->prepare('INSERT INTO members (display_name, email, terms_accepted_at) VALUES (:n, :e, NOW())')
                    ->execute(['n' => $who['name'], 'e' => $who['email_verified'] ? $who['email'] : null]);
                $id = (int)$this->db->lastInsertId();
                $this->db->prepare('INSERT INTO member_identities (member_id, provider, subject, email) VALUES (:m, :p, :s, :e)')
                    ->execute(['m' => $id, 'p' => $p->key(), 's' => $who['subject'], 'e' => $who['email_verified'] ? $who['email'] : null]);
                if ($own) { $this->db->commit(); }
            } catch (\Throwable $e) {
                if ($own) { $this->db->rollBack(); }
                throw $e;
            }
            $created = true;
        }
        $this->db->prepare('UPDATE members SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $id]);
        if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); }
        $_SESSION[self::SESSION_MEMBER_ID] = $id;
        return ['ok' => true, 'member_id' => $id, 'created' => $created];
    }

    public function current(): ?array
    {
        $id = (int)($_SESSION[self::SESSION_MEMBER_ID] ?? 0);
        if ($id <= 0) { return null; }
        try {
            $stmt = $this->db->prepare("SELECT id, display_name, email, created_at FROM members WHERE id = :id AND status = 'active'");
            $stmt->execute(['id' => $id]);
            $m = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;   // a host that has not run migration 023 yet simply has no members
        }
        if (!$m) { unset($_SESSION[self::SESSION_MEMBER_ID]); return null; }
        return $m;
    }

    public function logout(): void
    {
        unset($_SESSION[self::SESSION_MEMBER_ID], $_SESSION[self::SESSION_PENDING]);
        if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); }
    }

    /** PDPA: the member deletes their own account. Identities go with it; saved builds stay but lose their owner. */
    public function deleteAccount(int $memberId): void
    {
        // the listing photos are files: the cascade removes their rows, not the files, so collect the listing ids first
        $uuids = [];
        try { $q = $this->db->prepare('SELECT uuid FROM seller_listings WHERE member_id = :id'); $q->execute(['id' => $memberId]); $uuids = $q->fetchAll(PDO::FETCH_COLUMN); } catch (\Throwable) { /* a host without migration 024 has no listings */ }
        $own = !$this->db->inTransaction();
        if ($own) { $this->db->beginTransaction(); }
        try {
            $this->db->prepare('UPDATE spec_builds SET member_id = NULL WHERE member_id = :id')->execute(['id' => $memberId]);
            $this->db->prepare('DELETE FROM members WHERE id = :id')->execute(['id' => $memberId]);
            if ($own) { $this->db->commit(); }
        } catch (\Throwable $e) {
            if ($own) { $this->db->rollBack(); }
            throw $e;
        }
        foreach ($uuids as $u) { $this->photos?->delete((string)$u); }   // only after the rows are gone for good
        $this->logout();
    }

    /** @return list<array{uuid:string,created_at:string,estimated_expected:string}> */
    public function builds(int $memberId): array
    {
        $stmt = $this->db->prepare('SELECT uuid, created_at, estimated_expected FROM spec_builds WHERE member_id = :id ORDER BY id DESC LIMIT 50');
        $stmt->execute(['id' => $memberId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
