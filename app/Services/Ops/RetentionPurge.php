<?php
declare(strict_types=1);

namespace App\Services\Ops;

use InvalidArgumentException;
use PDO;

/**
 * LS-8 O-2: retention for the data the public tools create: Deal Checker results (deal_checks), Build results (spec_builds and, by cascade, their items)
 * and the rate-limit cache files. The owner has not chosen retention periods, so there is NO default: a target is only touched when its period is given.
 * Dry run unless $apply. Periods under the floors are refused so a typo cannot wipe the data. The audit row holds counts only, never content.
 */
final class RetentionPurge
{
    public const MIN_DAYS = 7;
    public const MIN_HOURS = 1;

    /**
     * @param array{deal_checks_days?:?int,builds_days?:?int,community_days?:?int,seller_pending_days?:?int,forum_days?:?int,ratelimit_hours?:?int} $opts
     * @return array{apply:bool,results:array<string,array{period:int,unit:string,matched:int,deleted:int}>}
     */
    public static function run(PDO $db, array $opts, string $cacheDir, bool $apply, ?int $now = null, ?string $evidenceDir = null): array
    {
        $now ??= time();
        $targets = [];
        foreach (['deal_checks' => 'deal_checks_days', 'builds' => 'builds_days'] as $name => $key) {
            if (($opts[$key] ?? null) === null) { continue; }
            $days = (int)$opts[$key];
            if ($days < self::MIN_DAYS) { throw new InvalidArgumentException("$key must be at least " . self::MIN_DAYS . ' days'); }
            $targets[$name] = $days;
        }
        $hours = null;
        if (($opts['ratelimit_hours'] ?? null) !== null) {
            $hours = (int)$opts['ratelimit_hours'];
            if ($hours < self::MIN_HOURS) { throw new InvalidArgumentException('ratelimit_hours must be at least ' . self::MIN_HOURS); }
        }
        // LS-21 (R-11, ADR-006 S4): rejected and spam community reports, counted from the day they were decided. Accepted and new reports are never purged here.
        $community = null;
        if (($opts['community_days'] ?? null) !== null) {
            $community = (int)$opts['community_days'];
            if ($community < self::MIN_DAYS) { throw new InvalidArgumentException('community_days must be at least ' . self::MIN_DAYS . ' days'); }
        }
        // LS-24 M6 (ADR-007 step 5, HG-14 sets the number): seller reports that were never verified (pending or rejected), counted from the day they were made, with their evidence files. Verified and disputed reports are never purged here.
        $sellerPending = null;
        if (($opts['seller_pending_days'] ?? null) !== null) {
            $sellerPending = (int)$opts['seller_pending_days'];
            if ($sellerPending < self::MIN_DAYS) { throw new InvalidArgumentException('seller_pending_days must be at least ' . self::MIN_DAYS . ' days'); }
        }
        // LS-24 M9: forum content that a moderator or three members' flags hid, counted from the day it was written. Visible content is never purged here.
        $forum = null;
        if (($opts['forum_days'] ?? null) !== null) {
            $forum = (int)$opts['forum_days'];
            if ($forum < self::MIN_DAYS) { throw new InvalidArgumentException('forum_days must be at least ' . self::MIN_DAYS . ' days'); }
        }
        if ($targets === [] && $hours === null && $community === null && $sellerPending === null && $forum === null) { throw new InvalidArgumentException('Give at least one period; there is no default retention'); }

        $tables = ['deal_checks' => 'deal_checks', 'builds' => 'spec_builds'];
        $results = [];
        $own = $apply && !$db->inTransaction();
        if ($own) { $db->beginTransaction(); }
        try {
            foreach ($targets as $name => $days) {
                $cut = date('Y-m-d H:i:s', $now - $days * 86400);
                $c = $db->prepare("SELECT COUNT(*) FROM {$tables[$name]} WHERE created_at < :cut");
                $c->execute(['cut' => $cut]);
                $matched = (int)$c->fetchColumn();
                $deleted = 0;
                if ($apply && $matched > 0) {
                    $d = $db->prepare("DELETE FROM {$tables[$name]} WHERE created_at < :cut");
                    $d->execute(['cut' => $cut]);
                    $deleted = $d->rowCount();
                }
                $results[$name] = ['period' => $days, 'unit' => 'days', 'matched' => $matched, 'deleted' => $deleted];
            }
            if ($community !== null) {
                $cut = date('Y-m-d H:i:s', $now - $community * 86400);
                $c = $db->prepare("SELECT COUNT(*) FROM community_reports WHERE status IN ('rejected','spam') AND decided_at IS NOT NULL AND decided_at < :cut");
                $c->execute(['cut' => $cut]);
                $matched = (int)$c->fetchColumn(); $deleted = 0;
                if ($apply && $matched > 0) {
                    $d = $db->prepare("DELETE FROM community_reports WHERE status IN ('rejected','spam') AND decided_at IS NOT NULL AND decided_at < :cut");
                    $d->execute(['cut' => $cut]);
                    $deleted = $d->rowCount();
                }
                $results['community_reports'] = ['period' => $community, 'unit' => 'days', 'matched' => $matched, 'deleted' => $deleted];
            }
            if ($sellerPending !== null) {
                $cut = date('Y-m-d H:i:s', $now - $sellerPending * 86400);
                $ids = $db->prepare("SELECT id FROM seller_reports WHERE status IN ('pending','rejected') AND created_at < :cut"); $ids->execute(['cut' => $cut]); $ids = array_map('intval', $ids->fetchAll(PDO::FETCH_COLUMN));
                $matched = count($ids); $deleted = 0;
                if ($apply && $ids !== []) {
                    $in = implode(',', $ids);
                    if ($evidenceDir !== null) { foreach ($db->query("SELECT file_name FROM seller_report_evidence WHERE report_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN) as $fn) { $p = rtrim($evidenceDir, '/\\') . '/' . basename((string)$fn); if (is_file($p)) { @unlink($p); } } }
                    $deleted = $db->exec("DELETE FROM seller_reports WHERE id IN ($in)");
                }
                $results['seller_reports_unverified'] = ['period' => $sellerPending, 'unit' => 'days', 'matched' => $matched, 'deleted' => (int)$deleted];
            }
            if ($forum !== null) {
                $cut = date('Y-m-d H:i:s', $now - $forum * 86400); $matched = 0; $deleted = 0;
                foreach (['thread' => 'community_threads', 'post' => 'community_posts', 'review' => 'community_reviews'] as $type => $tbl) {
                    $q = $db->prepare("SELECT id FROM $tbl WHERE status = 'hidden' AND created_at < :cut"); $q->execute(['cut' => $cut]); $ids = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
                    $matched += count($ids);
                    if ($apply && $ids !== []) { $in = implode(',', $ids); $db->exec("DELETE FROM community_flags WHERE target_type = '$type' AND target_id IN ($in)"); $db->exec("DELETE FROM community_votes WHERE target_type = '$type' AND target_id IN ($in)"); $deleted += (int)$db->exec("DELETE FROM $tbl WHERE id IN ($in)"); }
                }
                $results['forum_hidden'] = ['period' => $forum, 'unit' => 'days', 'matched' => $matched, 'deleted' => $deleted];
            }
            if ($hours !== null) {
                $matched = 0;
                $deleted = 0;
                foreach (glob(rtrim($cacheDir, '/\\') . '/*.json') ?: [] as $file) {
                    $mtime = @filemtime($file);
                    if ($mtime === false || $mtime >= $now - $hours * 3600) { continue; }
                    $matched++;
                    if ($apply && @unlink($file)) { $deleted++; }
                }
                $results['ratelimit'] = ['period' => $hours, 'unit' => 'hours', 'matched' => $matched, 'deleted' => $deleted];
            }
            if ($apply) {
                $counts = [];
                foreach ($results as $name => $r) { $counts[$name] = ['period' => $r['period'], 'unit' => $r['unit'], 'deleted' => $r['deleted']]; }
                $db->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, before_data, after_data) VALUES (NULL, 'retention_purge', 'retention', NULL, NULL, :after)")
                    ->execute(['after' => json_encode($counts, JSON_UNESCAPED_UNICODE)]);
            }
            if ($own) { $db->commit(); }
        } catch (\Throwable $e) {
            if ($own && $db->inTransaction()) { $db->rollBack(); }
            throw $e;
        }
        return ['apply' => $apply, 'results' => $results];
    }
}
