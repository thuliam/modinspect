<?php
declare(strict_types=1);

namespace App\Services\Build;

use App\Services\SpecBuilderService;

/**
 * LS-14 Module 6 (owner decision OD-2): which price a Build page part uses. A used-market price (the reviewed snapshot) always wins; when a part has
 * none, an admin-entered NEW retail price (new_price_references) is used and labelled as such; otherwise the part has no price. A new-price reference
 * is never mixed into a median, a snapshot or a confidence score: it lives only here. Pure.
 */
final class BuildPricing
{
    public const LABEL_USED = 'ราคามือสอง';
    public const LABEL_NEW_REF = 'ราคาของใหม่อ้างอิง';
    public const LABEL_NONE = 'ยังไม่มีราคา';

    /**
     * @param array $product catalog row (Product::search)
     * @param array|null $newRef latest new_price_references row for the product: price, source_url, checked_on
     * @return array{basis:string,label:string,low:float,expected:float,high:float,source_url:?string,checked_on:?string}
     */
    public static function for(array $product, ?array $newRef): array
    {
        if (SpecBuilderService::isPriced($product)) {
            return ['basis' => 'used', 'label' => self::LABEL_USED, 'low' => (float)$product['q1'], 'expected' => (float)$product['median'], 'high' => (float)$product['q3'], 'source_url' => null, 'checked_on' => null];
        }
        $p = $newRef !== null ? (float)($newRef['price'] ?? 0) : 0.0;
        if ($p > 0) {
            return ['basis' => 'new_ref', 'label' => self::LABEL_NEW_REF, 'low' => $p, 'expected' => $p, 'high' => $p, 'source_url' => (string)($newRef['source_url'] ?? ''), 'checked_on' => (string)($newRef['checked_on'] ?? '')];
        }
        return ['basis' => 'none', 'label' => self::LABEL_NONE, 'low' => 0.0, 'expected' => 0.0, 'high' => 0.0, 'source_url' => null, 'checked_on' => null];
    }

    /**
     * @param list<array{basis:string,low:float,expected:float,high:float}> $prices
     * @return array{used:int,new_ref:int,none:int,low:float,expected:float,high:float}
     */
    public static function totals(array $prices): array
    {
        $t = ['used' => 0, 'new_ref' => 0, 'none' => 0, 'low' => 0.0, 'expected' => 0.0, 'high' => 0.0];
        foreach ($prices as $p) {
            $t[$p['basis']]++;
            $t['low'] += $p['low']; $t['expected'] += $p['expected']; $t['high'] += $p['high'];
        }
        return $t;
    }

    /** One Thai sentence that says how the total was made, so a total that includes new prices or skips parts is never read as a used-market total. */
    public static function totalsNote(array $t): string
    {
        $parts = [];
        if ($t['used'] > 0) { $parts[] = $t['used'] . ' ชิ้นใช้ราคามือสอง'; }
        if ($t['new_ref'] > 0) { $parts[] = $t['new_ref'] . ' ชิ้นใช้ราคาของใหม่อ้างอิง (ของมือสองมักถูกกว่า)'; }
        if ($t['none'] > 0) { $parts[] = $t['none'] . ' ชิ้นยังไม่มีราคาและไม่ได้รวมในยอด'; }
        return $parts ? 'ยอดรวม: ' . implode(', ', $parts) : '';
    }
}
