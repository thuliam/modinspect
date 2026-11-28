<?php
declare(strict_types=1);

namespace App\Services\Market;

use PDO;
use PDOException;

/**
 * LS-90 D2: what happens to a listing after moderation: how long it stays up, whether it is reserved or sold, and the history of
 * both. Sold, reserved or expired never become a price: listings are not market evidence (HG-17).
 */
final class ListingLifecycle
{
    public const SALE_STATES = ['available', 'reserved', 'sold'];
    /** from => the states the seller may move it to; sold is final */
    private const MOVES = ['available' => ['reserved', 'sold'], 'reserved' => ['available', 'sold'], 'sold' => []];
    /** a listing may be renewed this many days before it expires, or after */
    public const RENEW_WINDOW_DAYS = 14;
    public const DEFAULT_DAYS = 60;

    public function __construct(private PDO $db, private int $days = self::DEFAULT_DAYS)
    {
        $this->days = max(1, min(365, $days));
    }

    public static function fromConfig(PDO $db): self
    {
        global $config;
        return new self($db, (int)($config['seller']['expiry_days'] ?? self::DEFAULT_DAYS));
    }

    public static function expired(array $listing, ?int $now = null): bool
    {
        $until = (string)($listing['expires_at'] ?? '');
        return $until !== '' && strtotime($until) <= ($now ?? time());
    }

    /** called once when a listing is approved: it is for sale again and stays up for the configured number of days */
    public function startClock(int $listingId): void
    {
        try {
            $this->db->prepare('UPDATE seller_listings SET sale_state = \'available\', expires_at = DATE_ADD(NOW(), INTERVAL ' . $this->days . ' DAY) WHERE id = ?')->execute([$listingId]);
        } catch (PDOException $e) {
            error_log('LISTING_CLOCK_NOT_SET ' . $e->getCode());   // a host without migration 041
        }
    }

    /** the seller marks an approved listing @param array<string,mixed> $listing the seller's own row @return array{ok:bool,error:?string} */
    public function changeSale(array $listing, string $to): array
    {
        $from = (string)($listing['sale_state'] ?? 'available');
        if ($listing['status'] !== 'approved' || !in_array($to, self::MOVES[$from] ?? [], true)) {
            return ['ok' => false, 'error' => 'BAD_STATE'];
        }
        $s = $this->db->prepare("UPDATE seller_listings SET sale_state = ? WHERE id = ? AND sale_state = ? AND status = 'approved'");
        $s->execute([$to, (int)$listing['id'], $from]);
        if ($s->rowCount() !== 1) {
            return ['ok' => false, 'error' => 'BAD_STATE'];
        }
        $this->record((int)$listing['id'], 'member', (int)$listing['member_id'], 'sale_state', $from, $to);
        return ['ok' => true, 'error' => null];
    }

    /** @param array<string,mixed> $listing @return array{ok:bool,error:?string} */
    public function renew(array $listing, ?int $now = null): array
    {
        if ($listing['status'] !== 'approved' || ($listing['sale_state'] ?? '') === 'sold') {
            return ['ok' => false, 'error' => 'BAD_STATE'];
        }
        $until = (string)($listing['expires_at'] ?? '');
        if ($until === '' || strtotime($until) - ($now ?? time()) > self::RENEW_WINDOW_DAYS * 86400) {
            return ['ok' => false, 'error' => 'TOO_EARLY'];
        }
        $this->db->prepare('UPDATE seller_listings SET expires_at = DATE_ADD(NOW(), INTERVAL ' . $this->days . ' DAY) WHERE id = ?')->execute([(int)$listing['id']]);
        $this->record((int)$listing['id'], 'member', (int)$listing['member_id'], 'renewed', $until, null);
        return ['ok' => true, 'error' => null];
    }

    /** history only: it never fails the action it describes */
    public function record(int $listingId, string $actorType, ?int $actorId, string $event, ?string $from = null, ?string $to = null, ?string $note = null): void
    {
        try {
            $this->db->prepare('INSERT INTO listing_status_events (listing_id, actor_type, actor_id, event, from_value, to_value, note) VALUES (?,?,?,?,?,?,?)')
                ->execute([$listingId, $actorType, $actorId, substr($event, 0, 30), $from === null ? null : substr($from, 0, 30), $to === null ? null : substr($to, 0, 30), $note === null ? null : mb_substr($note, 0, 255)]);
        } catch (PDOException $e) {
            error_log('LISTING_EVENT_NOT_RECORDED ' . $e->getCode());
        }
    }

    /** @return list<array<string,mixed>> */
    public function history(int $listingId): array
    {
        $s = $this->db->prepare('SELECT actor_type, actor_id, event, from_value, to_value, note, created_at FROM listing_status_events WHERE listing_id = ? ORDER BY id');
        $s->execute([$listingId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }
}
