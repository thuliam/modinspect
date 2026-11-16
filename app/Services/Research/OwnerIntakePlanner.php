<?php
declare(strict_types=1);

namespace App\Services\Research;

/**
 * Per-product approval plan for pending owner-text observations (used by cli/owner_intake_approve.php).
 * The rule is the existing one (below 60% or above 160% of the median is excluded; a snapshot needs 3 rows), with two additions:
 *  - the median basis also includes rows ALREADY approved for the product, so 1-2 new rows can join an established sample
 *    instead of being judged against each other;
 *  - when the excluded rows themselves form a second price cluster of 3 or more, the sample is bimodal (two different things are
 *    being priced as one model) and nothing is approved: it needs a human, not a median.
 * Pure function: no database, no clock, deterministic.
 */
final class OwnerIntakePlanner
{
    public const LOW = 0.6;
    public const HIGH = 1.6;
    public const MIN_ROWS = 3;

    /**
     * @param list<array{id:int,price:float}> $pending     pending owner-text rows of one product
     * @param list<float>                     $approved    prices already approved for that product (any source)
     * @return array{status:string,usable:int,median:float,keep:list<array{id:int,price:float}>,drop:list<array{id:int,price:float}>}
     *         status: approve | too_few | bimodal
     */
    public static function plan(array $pending, array $approved = []): array
    {
        $all = array_map(static fn(array $r): float => (float)$r['price'], $pending);
        foreach ($approved as $p) { $all[] = (float)$p; }
        $median = self::median($all);
        $keep = []; $drop = [];
        foreach ($pending as $r) {
            ((float)$r['price'] >= $median * self::LOW && (float)$r['price'] <= $median * self::HIGH) ? $keep[] = $r : $drop[] = $r;
        }
        if (count($drop) >= self::MIN_ROWS) {
            $dm = self::median(array_map(static fn(array $r): float => (float)$r['price'], $drop));
            $second = array_filter($drop, static fn(array $r): bool => (float)$r['price'] >= $dm * self::LOW && (float)$r['price'] <= $dm * self::HIGH);
            if (count($second) >= self::MIN_ROWS) { return ['status' => 'bimodal', 'usable' => count($keep), 'median' => $median, 'keep' => [], 'drop' => []]; }
        }
        $usable = count($keep) + count($approved);
        $status = (count($keep) >= 1 && $usable >= self::MIN_ROWS) ? 'approve' : 'too_few';
        return ['status' => $status, 'usable' => count($keep), 'median' => $median, 'keep' => $status === 'approve' ? $keep : [], 'drop' => $status === 'approve' ? $drop : []];
    }

    /** @param list<float> $v */
    private static function median(array $v): float
    {
        sort($v);
        $n = count($v);
        return $n === 0 ? 0.0 : ($n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2);
    }
}
