<?php
declare(strict_types=1);

namespace App\Services\Build;

use PDO;

/**
 * LS-14 Module 6: the database side of the Build page. Reads the latest new-price reference per product and the manufacturer reference specs, and
 * puts chosen parts together for BuildCompatibility and BuildStats. Fails soft: a missing table (a host that has not run migration 022 yet) gives
 * no references instead of an error page.
 */
final class BuildCatalog
{
    public function __construct(private PDO $db) {}

    /** @return array<int,array{price:string,source_url:string,checked_on:string}> latest reference per product id */
    public function newPriceRefs(): array
    {
        try {
            $rows = $this->db->query("SELECT r.product_id, r.price, r.source_url, r.checked_on FROM new_price_references r
                JOIN (SELECT product_id, MAX(id) id FROM new_price_references GROUP BY product_id) l ON l.id = r.id")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) { $out[(int)$r['product_id']] = $r; }
        return $out;
    }

    /** @param list<int> $ids @return array<int,array<string,string>> spec_key => spec_value per product id */
    public function specsFor(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) { return []; }
        try {
            $stmt = $this->db->prepare('SELECT product_id, spec_key, spec_value FROM product_reference_specs WHERE product_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
            $stmt->execute($ids);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) { $out[(int)$r['product_id']][(string)$r['spec_key']] = (string)$r['spec_value']; }
        return $out;
    }

    /** the reference specs of every given product in one query (the live preview judges all candidate parts against the same data) */
    public function allSpecs(array $products): array
    {
        return $this->specsFor(array_map(static fn(array $p): int => (int)$p['id'], $products));
    }

    /**
     * Chosen parts by role, ready for BuildCompatibility / BuildStats.
     * @param array<string,array> $byRole role => catalog row
     * @return array<string,array{product:array,specs:array<string,string>,price:float}>
     */
    public function parts(array $byRole, array $newRefs, ?array $specsById = null): array
    {
        $specs = $specsById ?? $this->specsFor(array_map(static fn(array $p): int => (int)$p['id'], $byRole));
        $out = [];
        foreach ($byRole as $role => $p) {
            $out[$role] = ['product' => $p, 'specs' => $specs[(int)$p['id']] ?? [], 'price' => BuildPricing::for($p, $newRefs[(int)$p['id']] ?? null)['expected']];
        }
        return $out;
    }

    /** BuildStats reference over every CPU and GPU in the catalog. @param list<array> $products Product::search rows */
    public function statsReference(array $products, array $newRefs, ?array $specsById = null): array
    {
        $cg = array_values(array_filter($products, static fn(array $p): bool => in_array(strtolower((string)$p['category_slug']), ['cpu', 'gpu'], true)));
        $specs = $specsById ?? $this->specsFor(array_map(static fn(array $p): int => (int)$p['id'], $cg));
        $entries = [];
        foreach ($cg as $p) {
            $entries[] = ['role' => strtolower((string)$p['category_slug']), 'product' => $p, 'specs' => $specs[(int)$p['id']] ?? [], 'price' => BuildPricing::for($p, $newRefs[(int)$p['id']] ?? null)['expected']];
        }
        return BuildStats::reference($entries);
    }
}
