<?php
declare(strict_types=1);

namespace App\Services\Pricing;

/**
 * LS-88 T3: how fresh the listings behind a public price are TODAY.
 *
 * The stored snapshot keeps the freshness it had on the day it was calculated (needed to reproduce its confidence). A visitor reads the page
 * later, so the freshness wording is evaluated again for the viewing date with the same deterministic rule the engine uses. Prices, the
 * stored score and the stored label are never changed here.
 */
final class PublicFreshness
{
    private const CODES = ['FRESH_OBSERVATIONS', 'MIXED_FRESHNESS', 'LIMITED_FRESHNESS', 'STALE_DATA'];

    /** @param list<string> $observedAt dates of the included listings @return array{newest_observed_at:?string,fresh_ratio:float,code:?string} */
    public static function evaluate(array $observedAt, string $today): array
    {
        if ($observedAt === []) { return ['newest_observed_at' => null, 'fresh_ratio' => 0.0, 'code' => null]; }
        $now = (new SnapshotConfidenceService())->freshness(array_map(static fn(string $d): array => ['observed_at' => $d], $observedAt), $today);
        return ['newest_observed_at' => max($observedAt), 'fresh_ratio' => (float)$now['fresh_ratio'], 'code' => $now['reasons'][0] ?? null];
    }

    /** the stored reason codes with the freshness one replaced by today's @param list<string> $reasons @return list<string> */
    public static function reasonsToday(array $reasons, ?string $code): array
    {
        if ($code === null) { return array_values($reasons); }
        return array_values(array_map(static fn($r): string => in_array($r, self::CODES, true) ? $code : (string)$r, $reasons));
    }
}
