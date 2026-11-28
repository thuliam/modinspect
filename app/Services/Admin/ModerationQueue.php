<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Services\Auth\AuthorizationService;
use PDO;
use PDOException;

/**
 * LS-90 D4/D5: everything waiting for a person, in one list with how long the oldest item has waited. Read-only. A queue whose table
 * does not exist on this database is left out. A person sees only the queues their permissions cover.
 */
final class ModerationQueue
{
    /** key => [permission, admin page, SELECT returning (n, oldest)] */
    private const QUEUES = [
        'listings' => ['listings.moderate', '/admin/seller-listings', "SELECT COUNT(*) n, MIN(updated_at) oldest FROM seller_listings WHERE status = 'submitted'"],
        'listing_reports' => ['listings.moderate', '/admin/moderation#listing-reports', "SELECT COUNT(*) n, MIN(created_at) oldest FROM listing_reports WHERE status = 'open'"],
        'seller_verification' => ['listings.moderate', '/admin/moderation#sellers', "SELECT COUNT(*) n, MIN(updated_at) oldest FROM seller_profiles WHERE verification = 'pending'"],
        'forum_flags' => ['reports.moderate', '/admin/community-moderation', "SELECT COUNT(*) n, MIN(created_at) oldest FROM community_flags WHERE status = 'open'"],
        'community_reports' => ['reports.moderate', '/admin/community-reports', "SELECT COUNT(*) n, MIN(created_at) oldest FROM community_reports WHERE status = 'new'"],
        'seller_reports' => ['reports.moderate', '/admin/seller-reports', "SELECT COUNT(*) n, MIN(created_at) oldest FROM seller_reports WHERE status = 'pending'"],
        'privacy_requests' => ['privacy.handle', '/admin/privacy-requests', "SELECT COUNT(*) n, MIN(received_at) oldest FROM privacy_requests WHERE status IN ('received','in_progress')"],
        'notifications' => ['admin.dashboard.view', '', "SELECT COUNT(*) n, MIN(created_at) oldest FROM notification_queue WHERE status = 'queued'"],
    ];

    public function __construct(private PDO $db)
    {
    }

    /** @return list<array{key:string,url:string,count:int,oldest:?string,age_hours:?int}> longest waiting first */
    public function summary(?array $user, ?int $now = null): array
    {
        $auth = new AuthorizationService();
        $out = [];
        foreach (self::QUEUES as $key => [$permission, $url, $sql]) {
            if (!$auth->can($user, $permission)) {
                continue;
            }
            try {
                $row = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException) {
                continue;
            }
            $oldest = $row['oldest'] ?? null;
            $out[] = ['key' => $key, 'url' => $url, 'count' => (int)$row['n'], 'oldest' => $oldest,
                'age_hours' => $oldest === null ? null : max(0, (int)floor((($now ?? time()) - strtotime((string)$oldest)) / 3600))];
        }
        usort($out, static fn(array $a, array $b): int => [$b['age_hours'] ?? -1, $b['count']] <=> [$a['age_hours'] ?? -1, $a['count']]);
        return $out;
    }
}
