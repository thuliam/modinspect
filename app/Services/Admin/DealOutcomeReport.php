<?php
declare(strict_types=1);

namespace App\Services\Admin;

use PDO;
use PDOException;

/**
 * LS-29 B1: read-only summary of what visitors said happened after a Deal Checker result (table deal_outcomes). Counts and percentages only, no ids, no text.
 * It is a first-party SIGNAL about what people did, not a market price: nothing here feeds a snapshot, a confidence score or the price engine, and the admin card says so.
 * summarize() is pure; build() only SELECTs and returns null on a database that does not have the table yet.
 */
final class DealOutcomeReport
{
    /**
     * @param list<array{outcome:string,final_price:?string,result_label:string,user_price:string,suggested_price_min:?string,suggested_price_max:?string}> $rows
     * @return array{total:int,by_outcome:array<string,int>,by_label:array<string,array<string,int>>,paid:array{with_price:int,below_range:int,within_range:int,above_range:int,no_range:int,avg_below_asking_pct:?float}}
     */
    public static function summarize(array $rows): array
    {
        $by = ['bought' => 0, 'negotiated' => 0, 'passed' => 0]; $label = [];
        $paid = ['with_price' => 0, 'below_range' => 0, 'within_range' => 0, 'above_range' => 0, 'no_range' => 0, 'avg_below_asking_pct' => null];
        $gaps = [];
        foreach ($rows as $r) {
            $o = isset($by[$r['outcome']]) ? $r['outcome'] : null;
            if ($o === null) { continue; }
            $by[$o]++;
            $label[$r['result_label']][$o] = ($label[$r['result_label']][$o] ?? 0) + 1;
            if ($r['final_price'] === null || (float)$r['final_price'] <= 0) { continue; }
            $paid['with_price']++;
            $p = (float)$r['final_price']; $min = (float)($r['suggested_price_min'] ?? 0); $max = (float)($r['suggested_price_max'] ?? 0);
            if ($min > 0 && $max >= $min) { $paid[$p < $min ? 'below_range' : ($p > $max ? 'above_range' : 'within_range')]++; } else { $paid['no_range']++; }
            if ((float)$r['user_price'] > 0) { $gaps[] = ((float)$r['user_price'] - $p) / (float)$r['user_price'] * 100; }
        }
        if ($gaps !== []) { $paid['avg_below_asking_pct'] = round(array_sum($gaps) / count($gaps), 1); }
        ksort($label);
        return ['total' => array_sum($by), 'by_outcome' => $by, 'by_label' => $label, 'paid' => $paid];
    }

    /** @return array<string,mixed>|null null when migration 028 is not applied */
    public static function build(PDO $db): ?array
    {
        try {
            $rows = $db->query('SELECT o.outcome, o.final_price, d.result_label, d.user_price, d.suggested_price_min, d.suggested_price_max FROM deal_outcomes o JOIN deal_checks d ON d.id = o.deal_check_id')->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return null;
        }
        return self::summarize($rows);
    }
}
