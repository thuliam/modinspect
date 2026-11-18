<?php
declare(strict_types=1);

namespace App\Services\Build;

/**
 * LS-14 Module 6: reads the few numbers the Build page needs from a part, preferring the manufacturer reference specs (product_reference_specs) and
 * falling back to the catalog columns, then to the model name itself (a PSU called "... 750W", a kit called "2x8GB"). A value it cannot find is null,
 * never a guess. Pure.
 */
final class BuildSpecs
{
    /** First number in a value such as "1650 / 1680" (the lowest stated clock is the safe one). */
    public static function num(mixed $v): ?float
    {
        if ($v === null) { return null; }
        if (is_int($v) || is_float($v)) { return $v > 0 ? (float)$v : null; }
        return preg_match('/(\d+(?:\.\d+)?)/', (string)$v, $m) === 1 && (float)$m[1] > 0 ? (float)$m[1] : null;
    }

    /** @param array<string,string> $specs */
    public static function get(array $product, array $specs, string $specKey, ?string $column = null): ?string
    {
        $v = trim((string)($specs[$specKey] ?? ''));
        if ($v === '' && $column !== null) { $v = trim((string)($product[$column] ?? '')); }
        return $v === '' ? null : $v;
    }

    public static function socket(array $product, array $specs): ?string { $s = self::get($product, $specs, 'socket', 'socket'); return $s === null ? null : strtoupper(str_replace(' ', '', $s)); }

    public static function memoryType(array $product, array $specs): ?string
    {
        $s = self::get($product, $specs, 'memory_type', 'memory_type');
        return $s !== null && preg_match('/DDR\d/i', $s, $m) === 1 ? strtoupper($m[0]) : null;
    }

    /** CPU or GPU power in watts (board power / TDP). */
    public static function powerWatt(array $product, array $specs): ?float
    {
        return self::num($specs['board_power_watt'] ?? null) ?? self::num($product['tdp_watt'] ?? null);
    }

    public static function psuWatt(array $product, array $specs): ?float
    {
        $w = self::num($specs['psu_capacity_watt'] ?? null);
        if ($w === null && preg_match('/(\d{3,4})\s*W\b/i', (string)($product['full_name'] ?? ''), $m) === 1) { $w = (float)$m[1]; }
        return $w;
    }

    public static function ramGb(array $product, array $specs): ?float
    {
        $gb = self::num($specs['capacity_gb'] ?? null) ?? self::num($product['capacity_gb'] ?? null);
        if ($gb !== null) { return $gb; }
        $name = (string)($product['full_name'] ?? '');
        if (preg_match('/(\d+)\s*x\s*(\d+)\s*GB/i', $name, $m) === 1) { return (float)$m[1] * (float)$m[2]; }
        return preg_match('/(\d+)\s*GB/i', $name, $m) === 1 ? (float)$m[1] : null;
    }

    /** Storage class from what the part says it is: NVMe 100, SATA SSD 50, hard disk 20, unknown null. */
    public static function storageClass(array $product, array $specs): ?int
    {
        $t = strtolower(($product['full_name'] ?? '') . ' ' . ($product['spec_summary'] ?? '') . ' ' . ($specs['interface'] ?? ''));
        if (str_contains($t, 'nvme')) { return 100; }
        if (str_contains($t, 'ssd') || str_contains($t, 'sata 3') && str_contains($t, 'solid')) { return 50; }
        if (str_contains($t, 'hdd') || str_contains($t, 'hard disk') || str_contains($t, 'rpm')) { return 20; }
        return null;
    }

    /** Form-factor size rank: Mini-ITX 1, Micro-ATX 2, ATX 3, E-ATX 4. */
    public static function formRank(?string $v): ?int
    {
        if ($v === null) { return null; }
        $t = strtolower(str_replace([' ', '_'], ['', '-'], $v));
        if (str_contains($t, 'e-atx') || str_contains($t, 'eatx')) { return 4; }
        if (str_contains($t, 'itx')) { return 1; }
        if (str_contains($t, 'micro') || str_contains($t, 'matx') || str_contains($t, 'm-atx')) { return 2; }
        if (str_contains($t, 'atx')) { return 3; }
        return null;
    }
}
