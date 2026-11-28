<?php
declare(strict_types=1);

namespace App\Services\Pricing;

/**
 * LS-88 T1: the one rule that says which stored snapshot may be shown as a public price.
 *
 * A snapshot is public only when it is the LATEST recorded asking snapshot of the model AND every observation it was calculated from
 * still stands: still approved, still on the same model, still at the same price. If any of them was later excluded, rejected, moved
 * or changed, the model shows no price until a new snapshot is calculated. There is deliberately no fallback to an older snapshot.
 *
 * Every method returns a SQL fragment built only from the column names the caller passes in (never from user input).
 */
final class PublicSnapshot
{
    /** true when no included observation behind snapshot row $alias has changed since the snapshot was stored */
    public static function intactSql(string $alias): string
    {
        return "NOT EXISTS (SELECT 1 FROM price_snapshot_observations vm JOIN price_observations vo ON vo.id = vm.observation_id
            WHERE vm.snapshot_id = {$alias}.id AND vm.inclusion_status = 'included'
              AND (vo.verified_status <> 'approved' OR vo.product_id <> {$alias}.product_id
                   OR COALESCE(vo.price_value, vo.asking_price) <> vm.calculation_price))";
    }

    /** scalar subquery: id of the public snapshot of the product in column $productColumn, NULL when it has none */
    public static function idSql(string $productColumn): string
    {
        return "(SELECT CASE WHEN " . self::intactSql('ps') . " THEN ps.id END FROM price_indices ps
            WHERE ps.product_id = {$productColumn} AND ps.product_variant_id IS NULL AND ps.price_type = 'asking'
              AND COALESCE(ps.provenance_status, 'legacy_unavailable') <> 'legacy_unavailable'
            ORDER BY ps.last_calculated_at DESC, ps.id DESC LIMIT 1)";
    }
}
