<?php
declare(strict_types=1);

namespace App\Services\Market;

use App\Services\Auth\AuthService;
use App\Services\Community\ContentGuard;
use App\Services\Listing\ListingAttributes;
use PDO;
use Throwable;

/**
 * LS-90 D1: a seller's public profile and its verification. A person verifies it by hand; nothing is verified automatically.
 * No real name, phone or address is asked for, and ContentGuard refuses contact details in the free text. Changing a verified
 * profile removes the verification until someone looks again. Every change of state is recorded with who did it.
 */
final class SellerProfiles
{
    public const STATES = ['unverified', 'pending', 'verified', 'revoked'];
    /** admin decision => the state it may start from and the state it leads to */
    private const DECISIONS = [
        'verify' => ['pending', 'verified', 'seller_verified'],
        'refuse' => ['pending', 'unverified', 'seller_not_verified'],
        'revoke' => ['verified', 'revoked', 'seller_revoked'],
    ];

    public function __construct(private PDO $db)
    {
    }

    public function get(int $memberId): ?array
    {
        $s = $this->db->prepare('SELECT * FROM seller_profiles WHERE member_id = ?');
        $s->execute([$memberId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return array{ok:bool,errors:array<string,string>} */
    public function save(int $memberId, array $input): array
    {
        $text = static fn(string $k): string => is_scalar($input[$k] ?? null) ? trim((string)$input[$k]) : '';
        $name = $text('display_name');
        $about = $text('about');
        $errors = [];
        $nameCodes = ContentGuard::check($name, 2, 60);
        if ($nameCodes !== []) {
            $errors['display_name'] = implode(' ', ContentGuard::messages($nameCodes));
        }
        if ($about !== '') {
            $aboutCodes = ContentGuard::check($about, 1, 300);
            if ($aboutCodes !== []) {
                $errors['about'] = implode(' ', ContentGuard::messages($aboutCodes));
            }
        }
        $attributes = ListingAttributes::parse(['province' => $text('province')]);
        if (isset($attributes['errors']['province'])) {
            $errors['province'] = $attributes['errors']['province'];
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }
        $new = ['display_name' => $name, 'province' => $attributes['values']['province'], 'about' => $about !== '' ? $about : null];
        $this->transaction(function () use ($memberId, $new): void {
            $old = $this->lock($memberId);
            if ($old === null) {
                $this->db->prepare('INSERT INTO seller_profiles (member_id, display_name, province, about) VALUES (?,?,?,?)')->execute([$memberId, $new['display_name'], $new['province'], $new['about']]);
                return;
            }
            if ($old['display_name'] === $new['display_name'] && $old['province'] === $new['province'] && $old['about'] === $new['about']) {
                return;
            }
            $state = in_array($old['verification'], ['verified', 'pending'], true) ? 'unverified' : (string)$old['verification'];
            $this->db->prepare('UPDATE seller_profiles SET display_name = ?, province = ?, about = ?, verification = ?, verified_by = NULL, verified_at = NULL WHERE member_id = ?')
                ->execute([$new['display_name'], $new['province'], $new['about'], $state, $memberId]);
            if ($state !== $old['verification']) {
                $this->event($memberId, 'member', $memberId, (string)$old['verification'], $state, 'profile changed');
            }
        });
        return ['ok' => true, 'errors' => []];
    }

    /** the member asks to be verified @return array{ok:bool,error:?string} */
    public function requestVerification(int $memberId): array
    {
        return $this->transaction(function () use ($memberId): array {
            $p = $this->lock($memberId);
            if ($p === null) {
                return ['ok' => false, 'error' => 'NO_PROFILE'];
            }
            if (!in_array($p['verification'], ['unverified', 'revoked'], true)) {
                return ['ok' => false, 'error' => 'BAD_STATE'];
            }
            $this->db->prepare("UPDATE seller_profiles SET verification = 'pending' WHERE member_id = ?")->execute([$memberId]);
            $this->event($memberId, 'member', $memberId, (string)$p['verification'], 'pending', null);
            return ['ok' => true, 'error' => null];
        });
    }

    /** an admin verifies, refuses or revokes; a reason is required for the last two @return array{ok:bool,error:?string} */
    public function decide(int $adminId, int $memberId, string $decision, string $note = ''): array
    {
        if (!isset(self::DECISIONS[$decision])) {
            return ['ok' => false, 'error' => 'BAD_ACTION'];
        }
        $note = mb_substr(trim($note), 0, 255);
        if ($decision !== 'verify' && $note === '') {
            return ['ok' => false, 'error' => 'NOTE_REQUIRED'];
        }
        [$from, $to, $notice] = self::DECISIONS[$decision];
        $result = $this->transaction(function () use ($adminId, $memberId, $from, $to, $note): array {
            $p = $this->lock($memberId);
            if ($p === null || $p['verification'] !== $from) {
                return ['ok' => false, 'error' => 'BAD_STATE'];
            }
            $verified = $to === 'verified';
            $this->db->prepare('UPDATE seller_profiles SET verification = ?, verified_by = ?, verified_at = ' . ($verified ? 'NOW()' : 'NULL') . ' WHERE member_id = ?')
                ->execute([$to, $verified ? $adminId : null, $memberId]);
            $this->event($memberId, 'admin', $adminId, $from, $to, $note !== '' ? $note : null);
            (new AuthService())->audit($adminId, 'seller_verification_' . $to, 'seller_profile', $memberId, ['note' => $note]);
            return ['ok' => true, 'error' => null];
        });
        if ($result['ok']) {
            (new NotificationQueue($this->db))->enqueue($memberId, $notice, $memberId);
        }
        return $result;
    }

    /** @return list<array<string,mixed>> profiles waiting for a person, oldest request first */
    public function waiting(): array
    {
        return $this->db->query("SELECT * FROM seller_profiles WHERE verification = 'pending' ORDER BY updated_at, member_id LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> verified profiles, newest first */
    public function verified(): array
    {
        return $this->db->query("SELECT * FROM seller_profiles WHERE verification = 'verified' ORDER BY verified_at DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function events(int $memberId): array
    {
        $s = $this->db->prepare('SELECT actor_type, actor_id, from_state, to_state, note, created_at FROM seller_verification_events WHERE member_id = ? ORDER BY id');
        $s->execute([$memberId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    private function lock(int $memberId): ?array
    {
        $s = $this->db->prepare('SELECT * FROM seller_profiles WHERE member_id = ? FOR UPDATE');
        $s->execute([$memberId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function event(int $memberId, string $actorType, int $actorId, string $from, string $to, ?string $note): void
    {
        $this->db->prepare('INSERT INTO seller_verification_events (member_id, actor_type, actor_id, from_state, to_state, note) VALUES (?,?,?,?,?,?)')
            ->execute([$memberId, $actorType, $actorId, $from, $to, $note]);
    }

    private function transaction(callable $work): mixed
    {
        $own = !$this->db->inTransaction();
        if ($own) {
            $this->db->beginTransaction();
        }
        try {
            $result = $work();
            if ($own) {
                $this->db->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($own && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
