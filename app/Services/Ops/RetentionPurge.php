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
     * @param array{deal_checks_days?:?int,builds_days?:?int,community_days?:?int,ratelimit_hours?:?int} $opts
     * @return array{apply:bool,results:array<string,array{period:int,unit:string,matched:int,deleted:int}>}
     */
    public static function run(PDO $db, array $opts, string $cacheDir, bool $apply, ?int $now = null): array
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
        if ($targets === [] && $hours === null && $community === null) { throw new InvalidArgumentException('Give at least one period; there is no default retention'); }

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
