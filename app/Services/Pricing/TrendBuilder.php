<?php
declare(strict_types=1);

namespace App\Services\Pricing;

use PDO;
use Throwable;

/**
 * LS-89 BE-1: monthly asking-price trend buckets (by observed_at month), built from the same rows the price snapshot accepts (CohortRules).
 * A month needs at least $min listings; quartiles use linear interpolation. A rebuild replaces the stored months of the models it covers.
 */
final class TrendBuilder
{
    public const MIN_SAMPLE = 3;

    public function __construct(private PDO $db)
    {
    }

    /** @return array<int, array<string, array{q1:float,median:float,q3:float,low:float,high:float,n:int}>> product id => period start => bucket */
    public function buckets(?int $productId = null, int $min = self::MIN_SAMPLE): array
    {
        $sql = CohortRules::selectSql() . " WHERE o.verified_status='approved' AND o.price_type='asking'";
        $params = [];
        if ($productId !== null) {
            $sql .= ' AND o.product_id=:product_id';
            $params['product_id'] = $productId;
        }
        $stmt = $this->db->prepare($sql . ' ORDER BY o.product_id, o.id');
        $stmt->execute($params);

        $prices = [];
        $seen = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int)$row['observation_id'];
            if (isset($seen[$id]) || CohortRules::exclusionReason($row, 'asking') !== null) {
                continue;
            }
            $seen[$id] = true;
            $period = substr((string)$row['observed_at'], 0, 7) . '-01';
            $prices[(int)$row['product_id']][$period][] = (float)$row['calculation_price'];
        }

        $out = [];
        foreach ($prices as $pid => $periods) {
            ksort($periods);
            foreach ($periods as $period => $list) {
                if (count($list) < max(2, $min)) {
                    continue;
                }
                sort($list);
                $out[$pid][$period] = [
                    'q1' => round(self::quantile($list, .25), 2),
                    'median' => round(self::quantile($list, .5), 2),
                    'q3' => round(self::quantile($list, .75), 2),
                    'low' => min($list),
                    'high' => max($list),
                    'n' => count($list),
                ];
            }
        }
        return $out;
    }

    /** replaces the stored months (all models, or one) in one transaction */
    public function rebuild(?int $productId = null, int $min = self::MIN_SAMPLE): array
    {
        $buckets = $this->buckets($productId, $min);
        $own = !$this->db->inTransaction();
        if ($own) {
            $this->db->beginTransaction();
        }
        try {
            if ($productId === null) {
                $this->db->exec('DELETE FROM price_trend_points');
            } else {
                $this->db->prepare('DELETE FROM price_trend_points WHERE product_id=?')->execute([$productId]);
            }
            $insert = $this->db->prepare("INSERT INTO price_trend_points (product_id,period_start,period_type,q1,median,q3,low_price,high_price,sample_size) VALUES (?,?,'month',?,?,?,?,?,?)");
            $points = 0;
            foreach ($buckets as $pid => $periods) {
                foreach ($periods as $period => $b) {
                    $insert->execute([$pid, $period, $b['q1'], $b['median'], $b['q3'], $b['low'], $b['high'], $b['n']]);
                    $points++;
                }
            }
            if ($own) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($own && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
        return ['points' => $points, 'products' => count($buckets)];
    }

    /** what rebuild() would change, without writing: months changed, added and removed */
    public function diff(int $min = self::MIN_SAMPLE): array
    {
        $stored = [];
        foreach ($this->db->query('SELECT product_id, period_start, median, sample_size FROM price_trend_points')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $stored[$r['product_id'] . ' ' . $r['period_start']] = [round((float)$r['median'], 2), (int)$r['sample_size']];
        }
        $next = [];
        foreach ($this->buckets(null, $min) as $pid => $periods) {
            foreach ($periods as $period => $b) {
                $next[$pid . ' ' . $period] = [$b['median'], $b['n']];
            }
        }
        $changed = [];
        foreach ($next as $key => $value) {
            if (isset($stored[$key]) && $stored[$key] !== $value) {
                $changed[$key] = ['from' => $stored[$key], 'to' => $value];
            }
        }
        return [
            'stored' => count($stored),
            'rebuilt' => count($next),
            'changed' => $changed,
            'added' => array_diff_key($next, $stored),
            'removed' => array_diff_key($stored, $next),
        ];
    }

    /** linear-percentile quantile of a sorted list */
    public static function quantile(array $sorted, float $p): float
    {
        $n = count($sorted);
        if ($n === 1) {
            return (float)$sorted[0];
        }
        $pos = ($n - 1) * $p;
        $lo = (int)floor($pos);
        $hi = (int)ceil($pos);
        return $sorted[$lo] + ($sorted[$hi] - $sorted[$lo]) * ($pos - $lo);
    }
}
