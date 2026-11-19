<?php
declare(strict_types=1);

namespace App\Services\Admin;

use PDO;

/**
 * LS-23 A3: the three target files that go with the data-collection master prompt. Read-only (SELECTs only).
 *   B_targets.csv          active models with no new-price reference yet (categories that block /build first)
 *   A_targets.csv          models that still need used-market listings (CollectionTargets), closest to a price first
 *   D_existing_names.csv   every active model and alias, so a collector never proposes a model that already exists
 */
final class IntakeTargets
{
    /** @return list<array{0:string,1:string,2:string}> [category, brand, product_name] */
    public static function newPriceTargets(PDO $db): array
    {
        $order = ['motherboard' => 0, 'psu' => 1, 'case' => 2, 'cooling' => 3];
        try {
            $rows = $db->query("SELECT c.slug cat, b.name brand, p.full_name name FROM products p JOIN product_categories c ON c.id=p.category_id JOIN brands b ON b.id=p.brand_id
                WHERE p.is_active=1 AND NOT EXISTS (SELECT 1 FROM new_price_references n WHERE n.product_id=p.id) ORDER BY p.full_name")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) { return []; }   // migration 022 not applied: nothing to list
        usort($rows, static fn(array $a, array $b): int => [$order[$a['cat']] ?? 9, $a['name']] <=> [$order[$b['cat']] ?? 9, $b['name']]);
        return array_map(static fn(array $r): array => [(string)$r['cat'], (string)$r['brand'], (string)$r['name']], $rows);
    }

    /** @return list<array{0:string,1:string,2:string,3:string}> [category, brand, product_name, aliases joined with " | "] */
    public static function existingNames(PDO $db): array
    {
        $aliases = [];
        foreach ($db->query('SELECT product_id, alias_text FROM product_aliases ORDER BY alias_text')->fetchAll(PDO::FETCH_ASSOC) as $a) { $aliases[(int)$a['product_id']][] = (string)$a['alias_text']; }
        $out = [];
        foreach ($db->query("SELECT p.id, c.slug cat, b.name brand, p.full_name name FROM products p JOIN product_categories c ON c.id=p.category_id JOIN brands b ON b.id=p.brand_id WHERE p.is_active=1 ORDER BY c.slug, p.full_name")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[] = [(string)$r['cat'], (string)$r['brand'], (string)$r['name'], implode(' | ', $aliases[(int)$r['id']] ?? [])];
        }
        return $out;
    }

    /** @return list<array{0:string,1:int,2:int,3:string}> [status, need, have, product_name] used-listing targets (bimodal ones need a review, not more rows) */
    public static function usedListingTargets(PDO $db): array
    {
        $b = CollectionTargets::build(CollectionTargets::gapRows($db));
        $out = [];
        foreach ($b['targets'] as $t) { if ($t['status'] !== 'bimodal' && $t['need'] > 0) { $out[] = [$t['status'], $t['need'], $t['have'], $t['name']]; } }
        return $out;
    }

    /** CSV text, UTF-8 with a BOM so Excel reads Thai correctly; a leading = + - @ is neutralised so a cell can never run as a formula */
    public static function csv(array $header, array $rows): string
    {
        $h = fopen('php://temp', 'r+');
        fputcsv($h, $header);
        foreach ($rows as $r) {
            fputcsv($h, array_map(static fn($v): string => is_string($v) && $v !== '' && strpos('=+-@', $v[0]) !== false ? "'" . $v : (string)$v, $r));
        }
        rewind($h);
        $s = (string)stream_get_contents($h);
        fclose($h);
        return "\xEF\xBB\xBF" . $s;
    }
}
