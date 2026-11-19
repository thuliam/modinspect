<?php
declare(strict_types=1);
namespace App\Services\Catalog;

/**
 * LS-16 U-1: order and filter of the price index. Pure, works on Product::search rows.
 * "Priced" is the one site-wide rule (median, a valid sample, an accepted observation), the same as CompareTable and the product card.
 * Price orders use the median and always put unpriced models last (they have no price to compare, they are not "cheapest").
 * An unknown sort key keeps the order the database gave (popular first), so a hand-edited query string never breaks the page.
 */
final class PriceList
{
    public const SORTS = ['' => 'แนะนำ', 'price_asc' => 'ราคาต่ำไปสูง', 'price_desc' => 'ราคาสูงไปต่ำ', 'samples' => 'ตัวอย่างมากสุด', 'name' => 'ชื่อ A–Z'];

    public static function isPriced(array $p): bool
    {
        return !empty($p['median']) && (int)($p['valid_sample_size'] ?? 0) > 0 && (int)($p['accepted_observation_count'] ?? 0) > 0;
    }

    /** @param list<array> $products @return list<array> */
    public static function apply(array $products, string $sort, bool $pricedOnly): array
    {
        if ($pricedOnly) { $products = array_values(array_filter($products, [self::class, 'isPriced'])); }
        if (!isset(self::SORTS[$sort]) || $sort === '') { return array_values($products); }
        // keep the incoming position as the last tie-break so equal rows never shuffle between requests
        $rows = []; foreach (array_values($products) as $i => $p) { $rows[] = [$i, $p]; }
        usort($rows, static function (array $a, array $b) use ($sort): int {
            [$ia, $pa] = $a; [$ib, $pb] = $b;
            $cmp = match ($sort) {
                'price_asc', 'price_desc' => self::priceCompare($pa, $pb, $sort === 'price_desc'),
                'samples' => (int)(self::isPriced($pb) ? $pb['valid_sample_size'] : 0) <=> (int)(self::isPriced($pa) ? $pa['valid_sample_size'] : 0),
                'name' => strnatcasecmp((string)($pa['full_name'] ?? ''), (string)($pb['full_name'] ?? '')),
            };
            return $cmp !== 0 ? $cmp : $ia <=> $ib;
        });
        return array_map(static fn(array $r): array => $r[1], $rows);
    }

    private static function priceCompare(array $a, array $b, bool $desc): int
    {
        $pa = self::isPriced($a); $pb = self::isPriced($b);
        if ($pa !== $pb) { return $pa ? -1 : 1; }   // unpriced last in both directions
        if (!$pa) { return 0; }
        $cmp = (float)$a['median'] <=> (float)$b['median'];
        return $desc ? -$cmp : $cmp;
    }
}
