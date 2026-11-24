<?php
declare(strict_types=1);
namespace App\Services\Push;

use PDO;

/** LS-86: the only place that touches push_subscriptions. Prepared statements; the endpoint is identified by its SHA-256. */
final class SubscriptionStore
{
    public function __construct(private PDO $db) {}

    /** @param array{endpoint:string,p256dh:string,auth:string,watch:list<array{slug:string,median:?float}>} $p */
    public function save(array $p): void
    {
        $st = $this->db->prepare('INSERT INTO push_subscriptions (endpoint_hash, endpoint, p256dh, auth, watch_json, is_active, failure_count) VALUES (:h,:e,:k,:a,:w,1,0)
            ON DUPLICATE KEY UPDATE p256dh=VALUES(p256dh), auth=VALUES(auth), watch_json=VALUES(watch_json), is_active=1, failure_count=0');
        $st->execute(['h' => hash('sha256', $p['endpoint']), 'e' => $p['endpoint'], 'k' => $p['p256dh'], 'a' => $p['auth'], 'w' => json_encode($p['watch'], JSON_UNESCAPED_UNICODE)]);
    }

    public function remove(string $endpoint): bool
    {
        $st = $this->db->prepare('DELETE FROM push_subscriptions WHERE endpoint_hash=:h');
        $st->execute(['h' => hash('sha256', $endpoint)]);
        return $st->rowCount() > 0;
    }

    /** @return list<array<string,mixed>> */
    public function active(): array
    {
        return $this->db->query('SELECT id, endpoint, p256dh, auth, watch_json, last_notified_at FROM push_subscriptions WHERE is_active=1 AND failure_count < 5 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markNotified(int $id): void { $this->db->prepare('UPDATE push_subscriptions SET last_notified_at=NOW(), failure_count=0 WHERE id=:i')->execute(['i' => $id]); }

    public function markGone(int $id): void { $this->db->prepare('UPDATE push_subscriptions SET is_active=0 WHERE id=:i')->execute(['i' => $id]); }

    public function markFailed(int $id): void { $this->db->prepare('UPDATE push_subscriptions SET failure_count=failure_count+1 WHERE id=:i')->execute(['i' => $id]); }
}
