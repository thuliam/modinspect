<?php
declare(strict_types=1);

namespace App\Services\Market;

use PDO;
use PDOException;

/**
 * LS-90 D5: notifications for members, delivered only to a local log file. Nothing is e-mailed or pushed. A queued row holds a member
 * id, a kind from the list below and the id it is about; no address and no text written by a person.
 */
final class NotificationQueue
{
    public const KINDS = ['listing_approved', 'listing_rejected', 'listing_removed', 'seller_verified', 'seller_revoked', 'seller_not_verified'];

    public function __construct(private PDO $db)
    {
    }

    /** never fails the action that raised it: a host without migration 043 simply queues nothing */
    public function enqueue(int $memberId, string $kind, ?int $subjectId = null): bool
    {
        if (!in_array($kind, self::KINDS, true)) {
            throw new \InvalidArgumentException('NOTIFICATION_KIND');
        }
        try {
            $this->db->prepare('INSERT INTO notification_queue (member_id, kind, subject_id) VALUES (?,?,?)')->execute([$memberId, $kind, $subjectId]);
            return true;
        } catch (PDOException $e) {
            error_log('NOTIFICATION_NOT_QUEUED ' . $e->getCode());
            return false;
        }
    }

    /** writes the waiting rows to the log file and marks them; returns how many */
    public function deliverToLog(string $logFile, int $limit = 200): int
    {
        $rows = $this->db->query("SELECT id, member_id, kind, subject_id, created_at FROM notification_queue WHERE status = 'queued' ORDER BY id LIMIT " . max(1, min(1000, $limit)))->fetchAll(PDO::FETCH_ASSOC);
        $mark = $this->db->prepare("UPDATE notification_queue SET status = 'logged', processed_at = NOW() WHERE id = ? AND status = 'queued'");
        $done = 0;
        foreach ($rows as $r) {
            $mark->execute([(int)$r['id']]);
            if ($mark->rowCount() !== 1) {
                continue;   // another run took it
            }
            $line = date('c') . " notification={$r['id']} kind={$r['kind']} member={$r['member_id']} subject=" . ($r['subject_id'] ?? '-') . " queued_at={$r['created_at']}" . PHP_EOL;
            if (file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX) === false) {
                $this->db->prepare("UPDATE notification_queue SET status = 'queued', processed_at = NULL WHERE id = ?")->execute([(int)$r['id']]);
                throw new \RuntimeException('NOTIFICATION_LOG_NOT_WRITABLE');
            }
            $done++;
        }
        return $done;
    }
}
