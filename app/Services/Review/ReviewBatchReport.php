<?php
declare(strict_types=1);

namespace App\Services\Review;

use PDO;

/**
 * LS-24 M4: where a person's review time buys the most. Read-only (SELECTs only). Pending asking observations are grouped by model with how many are in each lane
 * (green, amber, red), the plain spread of the pending prices (lowest, middle, highest: a description of the rows, not the price engine), how many approved rows the
 * model already has, and whether approving the green rows alone would bring it to the three samples a snapshot needs. It approves nothing and recommends no decision:
 * the person still opens each row. Models that need only a little to reach a price come first.
 */
final class ReviewBatchReport
{
    public const MIN_SAMPLES = 3;

    /** @return list<array{product_id:int,name:string,approved:int,pending:int,green:int,amber:int,red:int,min:float,median:float,max:float,reaches_with_green:bool,needs:int}> */
    public static function build(PDO $db): array
    {
        $approved = [];
        foreach ($db->query("SELECT product_id, COUNT(*) n FROM price_observations WHERE verified_status = 'approved' AND price_type = 'asking' AND is_duplicate = 0 AND is_defective = 0 AND is_deposit = 0 GROUP BY product_id")->fetchAll(PDO::FETCH_ASSOC) as $r) { $approved[(int)$r['product_id']] = (int)$r['n']; }
        $rows = $db->query("SELECT o.product_id, p.full_name, o.price_value, COALESCE(d.lane, 'amber') lane FROM price_observations o JOIN products p ON p.id = o.product_id
            LEFT JOIN observation_review_decisions d ON d.price_observation_id = o.id AND d.decision = 'review_required'
            WHERE o.verified_status = 'pending' AND o.price_type = 'asking' AND o.is_duplicate = 0 AND o.price_value IS NOT NULL AND p.is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
        $by = [];
        foreach ($rows as $r) { $by[(int)$r['product_id']]['name'] = (string)$r['full_name']; $by[(int)$r['product_id']]['rows'][] = ['lane' => (string)$r['lane'], 'price' => (float)$r['price_value']]; }
        $out = [];
        foreach ($by as $pid => $g) {
            $prices = array_column($g['rows'], 'price'); sort($prices); $n = count($prices); $m = intdiv($n, 2);
            $lane = array_count_values(array_column($g['rows'], 'lane'));
            $have = $approved[$pid] ?? 0; $green = $lane['green'] ?? 0;
            $out[] = ['product_id' => $pid, 'name' => $g['name'], 'approved' => $have, 'pending' => $n, 'green' => $green, 'amber' => $lane['amber'] ?? 0, 'red' => $lane['red'] ?? 0,
                'min' => $prices[0], 'median' => $n % 2 === 1 ? $prices[$m] : ($prices[$m - 1] + $prices[$m]) / 2, 'max' => $prices[$n - 1],
                'reaches_with_green' => $have + $green >= self::MIN_SAMPLES, 'needs' => max(0, self::MIN_SAMPLES - $have)];
        }
        usort($out, static fn(array $a, array $b): int => [$a['needs'] === 0 ? 1 : 0, $a['needs'], -$a['pending'], $a['name']] <=> [$b['needs'] === 0 ? 1 : 0, $b['needs'], -$b['pending'], $b['name']]);
        return $out;
    }
}
