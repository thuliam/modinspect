<?php
declare(strict_types=1);
namespace App\Services\Catalog;

/**
 * LS-21: the "special" filters of the price index, by category (CPU/Motherboard socket, RAM type/size/speed range, GPU VRAM, ...).
 * Pure: works on Product::search rows plus a product_id => [spec_key => value] map from the reference specs. A model without the fact is never
 * guessed: it simply does not match a filter on that fact. RAM type, size and speed fall back to the model name ("32GB DDR4-3200") only when the
 * reference spec is absent. The option lists come from the models that exist in the chosen category, so a visitor never sees a choice that returns nothing.
 */
final class SpecFilter
{
    /** category slug => list of filter keys, in display order */
    public const BY_CATEGORY = [
        'cpu' => ['socket'], 'motherboard' => ['socket', 'mem', 'form'], 'ram' => ['mem', 'cap', 'speed'],
        'gpu' => ['vram', 'mem'], 'storage' => ['cap'],
    ];
    public const LABELS = ['socket' => 'Socket', 'mem' => 'ชนิดแรม', 'form' => 'ขนาดบอร์ด', 'cap' => 'ความจุ', 'speed' => 'ความเร็ว (MHz)', 'vram' => 'VRAM'];

    /** @param array<string,string> $specs @return array<string,string> filter key => normalised value */
    public static function attrs(array $product, array $specs): array
    {
        $name = (string)($product['full_name'] ?? '');
        $a = [];
        if (($specs['socket'] ?? '') !== '') { $a['socket'] = trim($specs['socket']); }
        $mem = trim((string)($specs['memory_type'] ?? ''));
        if ($mem === '' && preg_match('/\bDDR([345])\b/i', $name, $m)) { $mem = 'DDR' . $m[1]; }
        if ($mem !== '') { $a['mem'] = strtoupper($mem); }
        if (($specs['form_factor'] ?? '') !== '') { $a['form'] = trim($specs['form_factor']); }
        if (($specs['vram_gb'] ?? '') !== '' && is_numeric($specs['vram_gb'])) { $a['vram'] = (string)(int)$specs['vram_gb']; }
        $cap = $specs['capacity_gb'] ?? '';
        if (($cap === '' || !is_numeric($cap)) && preg_match('/(\d+(?:\.\d+)?)\s?(TB|GB)\b/i', $name, $m)) { $cap = (string)(int)round((float)$m[1] * (strtoupper($m[2]) === 'TB' ? 1000 : 1)); }
        if ($cap !== '' && is_numeric($cap)) { $a['cap'] = (string)(int)$cap; }
        if (preg_match('/\bDDR[345]-(\d{3,5})\b/i', $name, $m)) { $a['speed'] = $m[1]; }
        return $a;
    }

    /** @param list<array> $products @param array<int,array<string,string>> $specMap @return array<string,list<string>> filter key => sorted distinct values */
    public static function options(array $products, array $specMap, string $category): array
    {
        $out = [];
        foreach (self::BY_CATEGORY[$category] ?? [] as $key) {
            $vals = [];
            foreach ($products as $p) {
                if (($p['category_slug'] ?? '') !== $category) { continue; }
                $v = self::attrs($p, $specMap[(int)$p['id']] ?? [])[$key] ?? null;
                if ($v !== null) { $vals[$v] = true; }
            }
            $vals = array_map('strval', array_keys($vals));
            if (in_array($key, ['cap', 'speed', 'vram'], true)) { sort($vals, SORT_NUMERIC); } else { natcasesort($vals); $vals = array_values($vals); }
            if (count($vals) >= 2) { $out[$key] = $vals; }
        }
        return $out;
    }

    /** @param array<string,string> $filters key => wanted value (speed uses speed_min / speed_max) */
    public static function apply(array $products, array $specMap, string $category, array $filters): array
    {
        $active = array_filter($filters, static fn($v): bool => is_string($v) && $v !== '');
        if (!$active || !isset(self::BY_CATEGORY[$category])) { return array_values($products); }
        return array_values(array_filter($products, static function (array $p) use ($specMap, $active): bool {
            $a = self::attrs($p, $specMap[(int)$p['id']] ?? []);
            foreach (['socket', 'mem', 'form', 'cap', 'vram'] as $k) {
                if (isset($active[$k]) && ($a[$k] ?? null) !== $active[$k]) { return false; }
            }
            if (isset($active['speed_min']) || isset($active['speed_max'])) {
                if (!isset($a['speed'])) { return false; }
                $s = (int)$a['speed'];
                if (isset($active['speed_min']) && ctype_digit($active['speed_min']) && $s < (int)$active['speed_min']) { return false; }
                if (isset($active['speed_max']) && ctype_digit($active['speed_max']) && $s > (int)$active['speed_max']) { return false; }
            }
            return true;
        }));
    }
}
