<?php
declare(strict_types=1);

namespace App\Services\Pricing;

use PDO;

/**
 * LS-23 C1: what a recalculation WOULD change, before anything is stored. For every active product the snapshot the engine would write today
 * (PriceSnapshotService::previewProduct) is compared with the latest recorded snapshot, so after a data import the owner sees which prices appear, move or
 * lose confidence. apply() stores a new snapshot only for products that are new or changed (an unchanged product gets no new row, so history is not
 * flooded). Neither method changes a formula, an observation or an approval; deterministic PHP still owns every number.
 *
 * Status per product: new (no recorded snapshot yet, now eligible), changed, unchanged, not_eligible (fewer than 3 usable observations; an older
 * snapshot, if any, stays as it is: it is not deleted here), none (no snapshot and not eligible).
 */
final class SnapshotRecompute
{
    private const FIELDS = ['q1' => 'q1', 'median' => 'median', 'q3' => 'q3', 'valid_sample_size' => 'valid_sample_size', 'confidence_label' => 'confidence_label', 'confidence_method_version' => 'confidence_method_version'];
    private const TEXT_FIELDS = ['confidence_label', 'confidence_method_version'];

    public function __construct(private PDO $db, private PriceSnapshotService $svc) {}

    /** @return list<array{product_id:int,name:string,status:string,changes:array<string,array{from:mixed,to:mixed}>}> */
    public function plan(string $priceType = 'asking'): array
    {
        $out = [];
        $current = $this->db->prepare("SELECT q1, median, q3, valid_sample_size, confidence_label, confidence_method_version FROM price_indices WHERE product_id = :p AND product_variant_id IS NULL AND price_type = :t
            AND COALESCE(provenance_status, 'legacy_unavailable') <> 'legacy_unavailable' ORDER BY last_calculated_at DESC, id DESC LIMIT 1");
        foreach ($this->db->query('SELECT id, full_name FROM products WHERE is_active = 1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $pid = (int)$p['id'];
            $current->execute(['p' => $pid, 't' => $priceType]); $cur = $current->fetch(PDO::FETCH_ASSOC) ?: null;
            $new = $this->svc->previewProduct($pid, $priceType);
            if ($new === null) { $out[] = ['product_id' => $pid, 'name' => (string)$p['full_name'], 'status' => $cur === null ? 'none' : 'not_eligible', 'changes' => []]; continue; }
            if ($cur === null) { $out[] = ['product_id' => $pid, 'name' => (string)$p['full_name'], 'status' => 'new', 'changes' => ['median' => ['from' => null, 'to' => (float)$new['median']], 'valid_sample_size' => ['from' => null, 'to' => (int)$new['valid_sample_size']]]]; continue; }
            $ch = [];
            foreach (self::FIELDS as $col => $key) {
                $from = in_array($col, self::TEXT_FIELDS, true) ? (string)$cur[$col] : (float)$cur[$col];
                $to = in_array($col, self::TEXT_FIELDS, true) ? (string)$new[$key] : (float)$new[$key];
                if ($from !== $to) { $ch[$col] = ['from' => $from, 'to' => $to]; }
            }
            $out[] = ['product_id' => $pid, 'name' => (string)$p['full_name'], 'status' => $ch === [] ? 'unchanged' : 'changed', 'changes' => $ch];
        }
        return $out;
    }

    /** @param list<array{product_id:int,status:string}> $plan @return int snapshots stored (only new or changed products) */
    public function apply(array $plan, string $priceType = 'asking'): int
    {
        $n = 0;
        foreach ($plan as $row) {
            if (in_array($row['status'], ['new', 'changed'], true) && $this->svc->recalculateProduct((int)$row['product_id'], $priceType) !== null) { $n++; }
        }
        return $n;
    }

    /** @param list<array{status:string}> $plan @return array<string,int> */
    public static function counts(array $plan): array
    {
        $c = ['new' => 0, 'changed' => 0, 'unchanged' => 0, 'not_eligible' => 0, 'none' => 0];
        foreach ($plan as $r) { $c[$r['status']] = ($c[$r['status']] ?? 0) + 1; }
        return $c;
    }
}
