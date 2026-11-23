<?php
declare(strict_types=1);

namespace App\Services\Catalog;

/**
 * LS-74: the one format of a storage product (SSD or HDD): interface_type (NVMe, SATA), media_type (SSD, HDD), form_factor (M.2 2280, M.2 2242, M.2, 2.5", 3.5")
 * and capacity_gb (decimal, as sold: 1TB = 1000 GB) as columns of `products`. Names: "Samsung 990 EVO Plus 1TB" for a branded drive,
 * "NVMe M.2 SSD 1TB" for a generic spec class (brand Generic, priced by spec; slug storage-m2-nvme-1000gb as before).
 * parse() returns only what a text states; check() refuses combinations that cannot exist (NVMe in a 2.5" case, an HDD on NVMe).
 */
final class StorageFormat
{
    public const INTERFACES = ['NVMe', 'SATA'];
    public const MEDIA = ['SSD', 'HDD'];
    public const FORM_FACTORS = ['M.2 2280', 'M.2 2242', 'M.2 2230', 'M.2', '2.5"', '3.5"'];

    /** @return array{interface_type:?string,media_type:?string,form_factor:?string,capacity_gb:?int} */
    public static function parse(string $text): array
    {
        $t = strtoupper($text); $o = ['interface_type' => null, 'media_type' => null, 'form_factor' => null, 'capacity_gb' => null];
        if (preg_match('/\bNVME\b/', $t)) { $o['interface_type'] = 'NVMe'; } elseif (preg_match('/\bSATA\b/', $t)) { $o['interface_type'] = 'SATA'; }
        if (preg_match('/\bHDD\b|HARD DISK|HARD DRIVE/', $t)) { $o['media_type'] = 'HDD'; } elseif (preg_match('/\bSSD\b/', $t) || $o['interface_type'] === 'NVMe') { $o['media_type'] = 'SSD'; }
        if (preg_match('/M\.?2\s*(2280|2242|2230)/', $t, $m)) { $o['form_factor'] = 'M.2 ' . $m[1]; } elseif (preg_match('/\bM\.?2\b/', $t)) { $o['form_factor'] = 'M.2'; }
        elseif (preg_match('/\b2\.5\s*(?:"|IN|INCH|นิ้ว)/', $t)) { $o['form_factor'] = '2.5"'; } elseif (preg_match('/\b3\.5\s*(?:"|IN|INCH|นิ้ว)/', $t)) { $o['form_factor'] = '3.5"'; }
        if (preg_match('/\b(\d{1,2}(?:\.\d)?)\s*TB\b/', $t, $m)) { $o['capacity_gb'] = (int) round((float) $m[1] * 1000); } elseif (preg_match('/\b(\d{2,5})\s*GB\b/', $t, $m)) { $o['capacity_gb'] = (int) $m[1]; }
        return $o;
    }

    public static function check(array $f): ?string
    {
        $if = (string) ($f['interface_type'] ?? ''); $md = (string) ($f['media_type'] ?? ''); $ff = (string) ($f['form_factor'] ?? ''); $cap = $f['capacity_gb'] ?? null;
        if (!in_array($if, self::INTERFACES, true)) { return 'interface_type must be NVMe or SATA'; }
        if (!in_array($md, self::MEDIA, true)) { return 'media_type must be SSD or HDD'; }
        if (!in_array($ff, self::FORM_FACTORS, true)) { return 'form_factor must be one of ' . implode(', ', self::FORM_FACTORS); }
        if ($cap === null || $cap < 32 || $cap > 40000) { return 'capacity_gb must be 32 to 40000'; }
        if ($if === 'NVMe' && !str_starts_with($ff, 'M.2')) { return 'an NVMe drive must be M.2'; }
        if ($if === 'NVMe' && $md === 'HDD') { return 'an HDD cannot be NVMe'; }
        if ($md === 'HDD' && !in_array($ff, ['2.5"', '3.5"'], true)) { return 'an HDD must be 2.5" or 3.5"'; }
        return null;
    }

    public static function capacityText(int $gb): string { return $gb >= 1000 && $gb % 1000 === 0 ? ($gb / 1000) . 'TB' : $gb . 'GB'; }

    public static function name(string $brand, string $series, array $f): string
    {
        $cap = self::capacityText((int) $f['capacity_gb']);
        if (strtolower($brand) === 'generic' || trim($series) === '') {
            $shape = str_starts_with((string) $f['form_factor'], 'M.2') ? 'M.2 ' : ($f['form_factor'] === '2.5"' ? '2.5" ' : '3.5" ');
            return trim(($f['interface_type'] === 'NVMe' ? 'NVMe ' : 'SATA ') . $shape . $f['media_type'] . ' ' . $cap);
        }
        $s = trim($series);
        return trim($brand . ' ' . $s . (preg_match('/\b' . preg_quote($cap, '/') . '\b/i', $s) ? '' : ' ' . $cap));
    }

    /** the existing slug style of a generic class: storage-m2-nvme-1000gb; a branded drive takes the slug of its name */
    public static function slug(string $brand, string $series, array $f): string
    {
        if (strtolower($brand) === 'generic' || trim($series) === '') {
            $code = str_starts_with((string) $f['form_factor'], 'M.2') ? 'm2' : ($f['form_factor'] === '2.5"' ? '25' : '35');
            return 'storage-' . $code . '-' . strtolower((string) $f['interface_type']) . '-' . (int) $f['capacity_gb'] . 'gb' . ($f['media_type'] === 'HDD' ? '-hdd' : '');
        }
        return ProductImport::slug(self::name($brand, $series, $f));
    }

    public static function summary(array $f): string
    {
        return implode(' · ', [$f['interface_type'] . ' ' . $f['media_type'], $f['form_factor'], 'ความจุ ' . self::capacityText((int) $f['capacity_gb'])]);
    }
}
