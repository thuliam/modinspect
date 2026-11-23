<?php
declare(strict_types=1);
namespace App\Services\Catalog;

/**
 * LS-76: RAM, storage and PSU posts name a SPEC (DDR4-3200 16GB, NVMe 1TB, 750W Gold), not one model, and the site prices them by spec class (brand Generic).
 * Pure: reads the facts from a title with the same parsers as the import centre and returns the slug of the generic class they describe, or null when the title
 * does not state every fact the class needs. Nothing is guessed: a missing fact means no class. The caller looks the slug up among the existing active products.
 */
final class SpecClassResolver
{
    public static function slugFor(string $title): ?string
    {
        $r = RamFormat::parse($title);
        if ($r['memory_type'] !== null && $r['memory_speed_mhz'] !== null && $r['capacity_gb'] !== null && RamFormat::check($r + ['form_factor' => $r['form_factor'] ?? 'DIMM']) === null) {
            $ff = $r['form_factor'] ?? 'DIMM';
            return ProductImport::slug(RamFormat::name('Generic', '', ['kit_modules' => null, 'form_factor' => $ff] + $r));
        }
        $s = StorageFormat::parse($title);
        if ($s['interface_type'] !== null && $s['media_type'] !== null && $s['form_factor'] !== null && $s['capacity_gb'] !== null && StorageFormat::check($s) === null) {
            return StorageFormat::slug('Generic', '', $s);
        }
        $p = PsuFormat::parse($title);
        if ($p['power_watt'] !== null && $p['efficiency_rating'] !== null && preg_match('/\b(psu|power ?supply|พาวเวอร์)/i', $title)) {
            $f = $p + ['modular_type' => $p['modular_type'] ?? 'Non-modular', 'form_factor' => $p['form_factor'] ?? 'ATX'];
            if (PsuFormat::check($f) === null) { return PsuFormat::slug('Generic', '', $f); }
        }
        return null;
    }
}
