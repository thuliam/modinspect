<?php
declare(strict_types=1);
namespace App\Services\Push;

/**
 * LS-86: which watched models changed enough to be worth one notification. Pure and deterministic; it only reads medians that are already published
 * (a model without a checked price is never alerted), and never creates or changes a price.
 */
final class PriceAlertPlanner
{
    public const THRESHOLD_PERCENT = 5.0;
    public const QUIET_HOURS = 24;

    /**
     * @param list<array{slug:string,median:?float}> $watch what the visitor watches and the median seen when watching began
     * @param array<string,float> $currentMedians slug => today's published median (only models that have a checked price)
     * @return list<array{slug:string,from:float,to:float,change_percent:float}>
     */
    public static function changes(array $watch, array $currentMedians): array
    {
        $out = [];
        foreach ($watch as $w) {
            $now = $currentMedians[$w['slug']] ?? null;
            if ($w['median'] === null || $now === null || $w['median'] <= 0) { continue; }
            $pct = round(($now - $w['median']) / $w['median'] * 100, 1);
            if (abs($pct) >= self::THRESHOLD_PERCENT) { $out[] = ['slug' => $w['slug'], 'from' => (float)$w['median'], 'to' => $now, 'change_percent' => $pct]; }
        }
        return $out;
    }

    /** true when this subscription may be notified again (never notified, or the quiet period has passed) */
    public static function due(?string $lastNotifiedAt, int $now): bool
    {
        if ($lastNotifiedAt === null || $lastNotifiedAt === '') { return true; }
        $t = strtotime($lastNotifiedAt);
        return $t === false || $now - $t >= self::QUIET_HOURS * 3600;
    }
}
