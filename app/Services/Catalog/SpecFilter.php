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
        // LS-72 RAM: brand, type, form factor, size, modules in the kit, bus speed. LS-73 motherboard: brand, socket, chipset, memory, form factor.
        'cpu' => ['socket'], 'motherboard' => ['brand', 'socket', 'chipset', 'mem', 'form'], 'ram' => ['brand', 'mem', 'form', 'cap', 'kit', 'speed'],
        'gpu' => ['vram', 'chip'], 'storage' => ['cap', 'iface'], 'psu' => ['watt'], 'cooling' => ['ctype'],
    ];
    public const LABELS = ['socket' => 'Socket', 'mem' => 'ชนิดแรม', 'form' => 'ขนาดบอร์ด', 'cap' => 'ความจุ', 'speed' => 'ความเร็ว (MHz)', 'vram' => 'VRAM', 'iface' => 'อินเทอร์เฟซ', 'watt' => 'กำลังไฟ (W)', 'chip' => 'ชิปกราฟิก', 'brand' => 'ยี่ห้อ', 'kit' => 'จำนวนแท่ง', 'chipset' => 'ชิปเซ็ต', 'ctype' => 'แบบระบายความร้อน'];

    /** the label of a filter; form factor means the board size on a motherboard and DIMM/SO-DIMM on RAM */
    public static function label(string $key, string $category = ''): string
    {
        if ($key === 'form' && $category === 'ram') { return 'ฟอร์มแฟกเตอร์'; }
        return self::LABELS[$key] ?? $key;
    }

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
        if (isset($specs['speed_mhz']) && ctype_digit(trim($specs['speed_mhz']))) { $a['speed'] = (string)(int)$specs['speed_mhz']; }   // LS-23: a collected spec beats the name
        // LS-22: facts read from the model name only when it says them outright; otherwise the model simply has no value (LS-23: a collected spec is used first)
        if (preg_match('/\b(NVMe|SATA)\b/i', $name, $m)) { $a['iface'] = strtoupper($m[1]) === 'NVME' ? 'NVMe' : 'SATA'; } elseif (preg_match('/\bM\.2\b/i', $name)) { $a['iface'] = 'NVMe'; }
        if (($product['category_slug'] ?? '') === 'psu' && preg_match('/\b(\d{3,4})\s?W\b/i', $name, $m)) { $a['watt'] = (string)(int)$m[1]; }
        if (($product['category_slug'] ?? '') === 'gpu') {
            if (preg_match('/\b(NVIDIA|GeForce|RTX|GTX)\b/i', $name)) { $a['chip'] = 'NVIDIA'; } elseif (preg_match('/\b(AMD|Radeon|RX)\b/i', $name)) { $a['chip'] = 'AMD'; } elseif (preg_match('/\b(Intel|Arc)\b/i', $name)) { $a['chip'] = 'Intel'; }
        }
        if (isset($specs['interface']) && preg_match('/nvme|sata/i', $specs['interface'], $im)) { $a['iface'] = strtoupper($im[0]) === 'NVME' ? 'NVMe' : 'SATA'; }
        if (isset($specs['psu_capacity_watt']) && ctype_digit(trim($specs['psu_capacity_watt']))) { $a['watt'] = (string)(int)$specs['psu_capacity_watt']; }
        if (isset($specs['cooler_type']) && in_array(strtolower(trim($specs['cooler_type'])), ['air', 'aio'], true)) { $a['ctype'] = strtolower(trim($specs['cooler_type'])) === 'aio' ? 'AIO' : 'Air'; }
        if (($product['category_slug'] ?? '') === 'cooling' && !isset($a['ctype'])) {
            if (preg_match('/\b(AIO|Liquid|Water)\b/i', $name)) { $a['ctype'] = 'AIO'; } elseif (preg_match('/\b(Air|Tower)\b/i', $name)) { $a['ctype'] = 'Air'; }
        }
        // LS-73: motherboard facts are columns of the product too; they come first, the reference specs and the name fill what a column lacks
        if (($product['category_slug'] ?? '') === 'motherboard') {
            if (trim((string)($product['socket'] ?? '')) !== '') { $a['socket'] = trim((string)$product['socket']); }
            if (trim((string)($product['chipset'] ?? '')) !== '') { $a['chipset'] = strtoupper(trim((string)$product['chipset'])); }
            if (trim((string)($product['memory_type'] ?? '')) !== '') { $a['mem'] = strtoupper(trim((string)$product['memory_type'])); }
            if (trim((string)($product['form_factor'] ?? '')) !== '') { $a['form'] = trim((string)$product['form_factor']); }
            if (trim((string)($product['brand_name'] ?? '')) !== '') { $a['brand'] = trim((string)$product['brand_name']); }
        }
        // LS-72: RAM facts are columns of the product (migration 034 and the RAM format); they come first, the reference specs and the name only fill what a column lacks
        if (($product['category_slug'] ?? '') === 'ram') {
            if (trim((string)($product['memory_type'] ?? '')) !== '') { $a['mem'] = strtoupper(trim((string)$product['memory_type'])); }
            if (trim((string)($product['form_factor'] ?? '')) !== '') { $a['form'] = strtoupper(trim((string)$product['form_factor'])); }
            if ((int)($product['capacity_gb'] ?? 0) > 0) { $a['cap'] = (string)(int)$product['capacity_gb']; }
            if ((int)($product['memory_speed_mhz'] ?? 0) > 0) { $a['speed'] = (string)(int)$product['memory_speed_mhz']; }
            if ((int)($product['kit_modules'] ?? 0) > 0) { $a['kit'] = (string)(int)$product['kit_modules']; }
            if (trim((string)($product['brand_name'] ?? '')) !== '') { $a['brand'] = trim((string)$product['brand_name']); }
        }
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
            if (in_array($key, ['cap', 'speed', 'vram', 'watt', 'kit'], true)) { sort($vals, SORT_NUMERIC); } else { natcasesort($vals); $vals = array_values($vals); }
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
            foreach (['socket', 'mem', 'form', 'cap', 'vram', 'iface', 'watt', 'chip', 'ctype', 'brand', 'kit', 'chipset'] as $k) {
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
