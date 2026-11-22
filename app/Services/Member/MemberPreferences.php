<?php
declare(strict_types=1);

namespace App\Services\Member;

use PDO;

/** Marketplace P1-A: notification choices and display name. Transactional and marketing messages are separate switches; marketing defaults to off (opt-in). */
final class MemberPreferences
{
    public const LOCALES = ['th', 'en'];

    public function __construct(private PDO $db, private MemberAudit $audit) {}

    /** @return array{notify_transactional:bool,notify_marketing:bool,locale:string} */
    public function get(int $memberId): array
    {
        $stmt = $this->db->prepare('SELECT notify_transactional, notify_marketing, locale FROM member_preferences WHERE member_id = :m');
        $stmt->execute(['m' => $memberId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ? ['notify_transactional' => (bool)$r['notify_transactional'], 'notify_marketing' => (bool)$r['notify_marketing'], 'locale' => (string)$r['locale']]
            : ['notify_transactional' => true, 'notify_marketing' => false, 'locale' => 'th'];
    }

    public function save(int $memberId, bool $transactional, bool $marketing, string $locale): void
    {
        if (!in_array($locale, self::LOCALES, true)) { throw new \InvalidArgumentException('LOCALE'); }
        $this->db->prepare('INSERT INTO member_preferences (member_id, notify_transactional, notify_marketing, locale) VALUES (:m, :t, :k, :l)
            ON DUPLICATE KEY UPDATE notify_transactional = VALUES(notify_transactional), notify_marketing = VALUES(notify_marketing), locale = VALUES(locale)')
            ->execute(['m' => $memberId, 't' => (int)$transactional, 'k' => (int)$marketing, 'l' => $locale]);
        $this->audit->record($memberId, 'member', $memberId, 'preferences.saved', 'member', $memberId);
    }

    /** Display name only: trimmed, control characters removed, 1-60 characters. Returns false when nothing usable is left. */
    public function saveDisplayName(int $memberId, string $name): bool
    {
        $clean = trim((string)preg_replace('/[\s\x00-\x1F\x7F]+/u', ' ', $name));   // control characters and runs of spaces become one space
        if ($clean === '' || mb_strlen($clean) > 60) { return false; }
        $this->db->prepare('UPDATE members SET display_name = :n WHERE id = :m')->execute(['n' => $clean, 'm' => $memberId]);
        $this->audit->record($memberId, 'member', $memberId, 'profile.name_changed', 'member', $memberId);
        return true;
    }
}
