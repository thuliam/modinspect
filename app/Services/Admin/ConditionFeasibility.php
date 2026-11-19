<?php
declare(strict_types=1);

namespace App\Services\Admin;

use PDO;

/**
 * LS-23 C3: can a used price honestly be split by condition facts (warranty left, original box, receipt)? Read-only analysis for the owner's decision. It changes no price,
 * no formula and no snapshot. For each fact it asks, per product: are there at least MIN_GROUP approved asking observations WITH the fact and at least MIN_GROUP
 * WITHOUT it? Only then is a median gap meaningful, and the report says how many products reach that. A gap between two medians is a description of the sample, not a
 * rule, and it is never turned into baht adjustments here.
 */
final class ConditionFeasibility
{
    public const MIN_GROUP = 3;
    public const FACTS = ['warranty' => 'ประกันเหลือ', 'box' => 'มีกล่อง', 'receipt' => 'มีใบเสร็จ'];

    /**
     * @param list<array{product_id:int,price:float,warranty:?int,box:?int,receipt:?int}> $rows approved asking observations
     * @return array<string,array{products_with_data:int,products_splittable:int,examples:list<array{product_id:int,with:int,without:int,median_with:float,median_without:float,gap_percent:float}>}>
     */
    public static function summarize(array $rows): array
    {
        $by = [];
        foreach ($rows as $r) { $by[$r['product_id']][] = $r; }
        $out = [];
        foreach (array_keys(self::FACTS) as $fact) {
            $withData = 0; $split = 0; $ex = [];
            foreach ($by as $pid => $list) {
                $has = []; $no = [];
                foreach ($list as $r) {
                    $v = $fact === 'warranty' ? ($r['warranty'] === null ? null : ((int)$r['warranty'] > 0 ? 1 : 0)) : $r[$fact];
                    if ($v === null) { continue; }
                    if ((int)$v === 1) { $has[] = (float)$r['price']; } else { $no[] = (float)$r['price']; }
                }
                if ($has === [] && $no === []) { continue; }
                $withData++;
                if (count($has) >= self::MIN_GROUP && count($no) >= self::MIN_GROUP) {
                    $split++;
                    $mw = self::median($has); $mn = self::median($no);
                    $ex[] = ['product_id' => (int)$pid, 'with' => count($has), 'without' => count($no), 'median_with' => $mw, 'median_without' => $mn, 'gap_percent' => $mn > 0 ? round(($mw / $mn - 1) * 100, 1) : 0.0];
                }
            }
            usort($ex, static fn(array $a, array $b): int => ($b['with'] + $b['without']) <=> ($a['with'] + $a['without']));
            $out[$fact] = ['products_with_data' => $withData, 'products_splittable' => $split, 'examples' => array_slice($ex, 0, 5)];
        }
        return $out;
    }

    /** @return list<array{product_id:int,price:float,warranty:?int,box:?int,receipt:?int}> */
    public static function rows(PDO $db): array
    {
        $out = [];
        foreach ($db->query("SELECT product_id, price_value, warranty_months, has_box, has_receipt FROM price_observations
            WHERE verified_status = 'approved' AND price_type = 'asking' AND is_duplicate = 0 AND is_defective = 0 AND is_deposit = 0 AND price_value IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[] = ['product_id' => (int)$r['product_id'], 'price' => (float)$r['price_value'], 'warranty' => $r['warranty_months'] === null ? null : (int)$r['warranty_months'],
                'box' => $r['has_box'] === null ? null : (int)$r['has_box'], 'receipt' => $r['has_receipt'] === null ? null : (int)$r['has_receipt']];
        }
        return $out;
    }

    private static function median(array $v): float
    {
        sort($v); $n = count($v); $m = intdiv($n, 2);
        return $n % 2 === 1 ? $v[$m] : ($v[$m - 1] + $v[$m]) / 2;
    }
}
