<?php
declare(strict_types=1);

namespace App\Services\Catalog;

/**
 * LS-71: the one format of a RAM product. Brand is the product's brand; the facts are columns of `products`:
 * memory_type (DDR3, DDR4, DDR5), form_factor (DIMM desktop, SO-DIMM laptop), memory_speed_mhz (the memory bus, for example 3200), capacity_gb (the whole kit),
 * kit_modules (1 stick, 2 or 4 in a kit). The name is built from them, so the same facts always give the same name:
 *   "Kingston FURY Beast DDR5-6000 32GB (2x16GB)"   branded kit
 *   "RAM DDR4-3200 16GB"                             generic spec class (brand "Generic": priced by spec, not by maker)
 * Nothing here guesses: parse() returns only what the text states.
 */
final class RamFormat
{
    public const TYPES = ['DDR3' => [800, 2133], 'DDR4' => [1600, 5333], 'DDR5' => [3200, 9600]];
    public const FORM_FACTORS = ['DIMM', 'SO-DIMM'];
    public const MODULES = [1, 2, 4, 8];
    public const CAPACITIES = [2, 4, 8, 16, 24, 32, 48, 64, 96, 128, 192, 256];

    /** @return array{memory_type:?string,memory_speed_mhz:?int,capacity_gb:?int,kit_modules:?int,form_factor:?string} only the facts the text states */
    public static function parse(string $text): array
    {
        $t = strtoupper($text); $out = ['memory_type' => null, 'memory_speed_mhz' => null, 'capacity_gb' => null, 'kit_modules' => null, 'form_factor' => null];
        if (preg_match('/DDR\s?([345])\s*[- ]?\s*(\d{3,5})?/', $t, $m)) { $out['memory_type'] = 'DDR' . $m[1]; if (!empty($m[2])) { $out['memory_speed_mhz'] = (int) $m[2]; } }
        // "3200 MHz ขึ้นไป" (3200 and up) is a floor, not the speed of one product, so it is not taken
        if ($out['memory_speed_mhz'] === null && preg_match('/\b(\d{4,5})\s*(?:MHZ|MT\/S)(?!\s*ขึ้นไป)/u', $t, $m)) { $out['memory_speed_mhz'] = (int) $m[1]; }
        if (preg_match('/\b(\d)\s*X\s*(\d{1,3})\s*GB\b/', $t, $m)) { $out['kit_modules'] = (int) $m[1]; $out['capacity_gb'] = (int) $m[1] * (int) $m[2]; }
        elseif (preg_match('/\b(\d{1,3})\s*GB\b/', $t, $m)) { $out['capacity_gb'] = (int) $m[1]; }
        if (preg_match('/SO[- ]?DIMM|SODIMM/', $t)) { $out['form_factor'] = 'SO-DIMM'; } elseif (preg_match('/\bDIMM\b|UDIMM/', $t)) { $out['form_factor'] = 'DIMM'; }
        return $out;
    }

    /** @return string|null the reason the facts are not valid, or null */
    public static function check(array $f): ?string
    {
        $type = (string) ($f['memory_type'] ?? ''); $speed = $f['memory_speed_mhz'] ?? null; $cap = $f['capacity_gb'] ?? null; $mods = $f['kit_modules'] ?? null; $ff = (string) ($f['form_factor'] ?? '');
        if (!isset(self::TYPES[$type])) { return 'memory_type must be DDR3, DDR4 or DDR5'; }
        if ($ff !== '' && !in_array($ff, self::FORM_FACTORS, true)) { return 'form_factor must be DIMM or SO-DIMM'; }
        if ($speed === null || $speed < self::TYPES[$type][0] || $speed > self::TYPES[$type][1]) { return "memory speed for $type must be " . self::TYPES[$type][0] . ' to ' . self::TYPES[$type][1] . ' MHz'; }
        if ($cap === null || !in_array((int) $cap, self::CAPACITIES, true)) { return 'capacity_gb must be one of ' . implode(', ', self::CAPACITIES); }
        if ($mods !== null && !in_array((int) $mods, self::MODULES, true)) { return 'kit_modules must be 1, 2, 4 or 8'; }
        if ($mods !== null && (int) $cap % (int) $mods !== 0) { return 'capacity_gb does not divide into the kit modules'; }
        return null;
    }

    /** the standard name; $series is empty for a generic spec class */
    public static function name(string $brand, string $series, array $f): string
    {
        $spec = $f['memory_type'] . '-' . $f['memory_speed_mhz'] . ' ' . $f['capacity_gb'] . 'GB' . (($f['form_factor'] ?? '') === 'SO-DIMM' ? ' SO-DIMM' : '');
        $kit = ($f['kit_modules'] ?? null) !== null && (int) $f['kit_modules'] > 1 ? ' (' . (int) $f['kit_modules'] . 'x' . intdiv((int) $f['capacity_gb'], (int) $f['kit_modules']) . 'GB)' : '';
        if (strtolower($brand) === 'generic' || trim($series) === '') { return trim((strtolower($brand) === 'generic' ? 'RAM' : $brand) . ' ' . $spec . $kit); }
        return trim($brand . ' ' . trim($series) . ' ' . $spec . $kit);
    }

    public static function modelName(string $series, array $f): string
    {
        $spec = $f['memory_type'] . '-' . $f['memory_speed_mhz'] . ' ' . $f['capacity_gb'] . 'GB' . (($f['form_factor'] ?? '') === 'SO-DIMM' ? ' SO-DIMM' : '');
        $kit = ($f['kit_modules'] ?? null) !== null && (int) $f['kit_modules'] > 1 ? ' (' . (int) $f['kit_modules'] . 'x' . intdiv((int) $f['capacity_gb'], (int) $f['kit_modules']) . 'GB)' : '';
        return trim((trim($series) === '' ? 'RAM' : trim($series)) . ' ' . $spec . $kit);
    }

    public static function summary(array $f): string
    {
        $bits = [$f['memory_type'] . '-' . $f['memory_speed_mhz'] . ' (' . $f['memory_speed_mhz'] . ' MHz)', 'รวม ' . $f['capacity_gb'] . 'GB'];
        if (($f['kit_modules'] ?? null) !== null && (int) $f['kit_modules'] > 1) { $bits[] = $f['kit_modules'] . ' แท่ง'; } elseif (($f['kit_modules'] ?? null) === 1) { $bits[] = 'แท่งเดี่ยว'; }
        if (($f['form_factor'] ?? '') !== '') { $bits[] = $f['form_factor']; }
        return implode(' · ', $bits);
    }
}
