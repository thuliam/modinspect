<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Services\Intake\IntakeValidator;
use PDO;

/**
 * LS-23 E3: how complete the catalog is, per category, in the four ways the product needs (a price, a second source, the required manufacturer specs, a new-price
 * reference). Read-only (SELECTs only). Run it before and after an import to see what the batch changed. Counts and catalog names only.
 */
final class CoverageReport
{
    /** @return list<array{category:string,models:int,with_price:int,with_two_sources:int,specs_complete:int,with_new_price:int,missing_specs:array<string,list<string>>}> */
    public static function build(PDO $db): array
    {
        $required = IntakeValidator::contract()['spec_keys']['required_per_category'];
        $products = $db->query('SELECT p.id, p.full_name, c.slug cat FROM products p JOIN product_categories c ON c.id = p.category_id WHERE p.is_active = 1 ORDER BY c.slug, p.full_name')->fetchAll(PDO::FETCH_ASSOC);
        $priced = [];
        foreach ($db->query("SELECT product_id FROM price_indices pi WHERE " . \App\Services\Pricing\PublicSnapshot::intactSql('pi') . " AND product_variant_id IS NULL AND price_type = 'asking' AND valid_sample_size > 0 AND median IS NOT NULL
            AND COALESCE(provenance_status, 'legacy_unavailable') <> 'legacy_unavailable' AND id = (SELECT MAX(id) FROM price_indices x WHERE x.product_id = pi.product_id AND x.product_variant_id IS NULL AND x.price_type = 'asking' AND COALESCE(x.provenance_status, 'legacy_unavailable') <> 'legacy_unavailable')")->fetchAll(PDO::FETCH_COLUMN) as $id) { $priced[(int)$id] = true; }
        $sources = [];
        foreach ($db->query("SELECT o.product_id, COUNT(DISTINCT r.source_id) n FROM price_observations o JOIN raw_price_observations r ON r.id = o.raw_observation_id
            WHERE o.verified_status = 'approved' AND o.price_type = 'asking' AND o.is_duplicate = 0 GROUP BY o.product_id")->fetchAll(PDO::FETCH_ASSOC) as $r) { $sources[(int)$r['product_id']] = (int)$r['n']; }
        $specs = [];
        try { foreach ($db->query('SELECT product_id, spec_key FROM product_reference_specs')->fetchAll(PDO::FETCH_ASSOC) as $r) { $specs[(int)$r['product_id']][(string)$r['spec_key']] = true; } } catch (\PDOException $e) { /* migration 020 not applied */ }
        $newPrice = [];
        try { foreach ($db->query('SELECT DISTINCT product_id FROM new_price_references')->fetchAll(PDO::FETCH_COLUMN) as $id) { $newPrice[(int)$id] = true; } } catch (\PDOException $e) { /* migration 022 not applied */ }

        $out = [];
        foreach ($products as $p) {
            $cat = (string)$p['cat']; $id = (int)$p['id'];
            $out[$cat] ??= ['category' => $cat, 'models' => 0, 'with_price' => 0, 'with_two_sources' => 0, 'specs_complete' => 0, 'with_new_price' => 0, 'missing_specs' => []];
            $out[$cat]['models']++;
            if (isset($priced[$id])) { $out[$cat]['with_price']++; }
            if (($sources[$id] ?? 0) >= 2) { $out[$cat]['with_two_sources']++; }
            if (isset($newPrice[$id])) { $out[$cat]['with_new_price']++; }
            $missing = array_values(array_diff($required[$cat] ?? [], array_keys($specs[$id] ?? [])));
            if ($missing === []) { $out[$cat]['specs_complete']++; } else { $out[$cat]['missing_specs'][(string)$p['full_name']] = $missing; }
        }
        return array_values($out);
    }
}
