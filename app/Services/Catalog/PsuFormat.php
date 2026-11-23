<?php
declare(strict_types=1);

namespace App\Services\Catalog;

/**
 * LS-74: the one format of a PSU product: power_watt, efficiency_rating (80+ White to Titanium, or None), modular_type (Non-modular, Semi-modular, Fully modular) and
 * form_factor (ATX, SFX, SFX-L, TFX) as columns of `products`. Names: "Corsair RM750x 750W" for a branded unit (the wattage is added when the series does not
 * state it), "PSU 850W 80 PLUS Gold" for a generic spec class (slug psu-850w-gold as before).
 */
final class PsuFormat
{
    public const EFFICIENCY = ['None', '80+ White', '80+ Bronze', '80+ Silver', '80+ Gold', '80+ Platinum', '80+ Titanium'];
    public const MODULAR = ['Non-modular', 'Semi-modular', 'Fully modular'];
    public const FORM_FACTORS = ['ATX', 'SFX', 'SFX-L', 'TFX'];

    public static function efficiency(string $raw): ?string
    {
        $t = strtoupper($raw);
        if (preg_match('/\b(WHITE|BRONZE|SILVER|GOLD|PLATINUM|TITANIUM)\b/', $t, $m)) { return '80+ ' . ucfirst(strtolower($m[1])); }
        return in_array(strtolower(trim($raw)), ['none', 'no', '-', 'ไม่มี'], true) ? 'None' : null;
    }

    public static function modular(string $raw): ?string
    {
        $t = strtolower($raw);
        if (preg_match('/non[- ]?modular|ไม่ถอด|fixed/', $t)) { return 'Non-modular'; }
        if (preg_match('/semi|กึ่ง/', $t)) { return 'Semi-modular'; }
        if (preg_match('/full|ถอดได้ทั้งหมด/', $t)) { return 'Fully modular'; }
        return null;
    }

    public static function formFactor(string $raw): ?string
    {
        $k = strtoupper(preg_replace('/[^A-Z]/i', '', $raw) ?? '');
        return match ($k) { 'ATX' => 'ATX', 'SFX' => 'SFX', 'SFXL' => 'SFX-L', 'TFX' => 'TFX', default => null };
    }

    /** @return array{power_watt:?int,efficiency_rating:?string,modular_type:?string,form_factor:?string} only what the text states */
    public static function parse(string $text): array
    {
        $o = ['power_watt' => null, 'efficiency_rating' => null, 'modular_type' => null, 'form_factor' => null];
        if (preg_match('/\b(\d{3,4})\s*W\b/i', $text, $m)) { $o['power_watt'] = (int) $m[1]; }
        if (preg_match('/80\s*(?:\+|PLUS)\s*(white|bronze|silver|gold|platinum|titanium)/i', $text, $m)) { $o['efficiency_rating'] = '80+ ' . ucfirst(strtolower($m[1])); }
        $o['modular_type'] = self::modular($text);
        if (preg_match('/\bSFX-?L\b/i', $text)) { $o['form_factor'] = 'SFX-L'; } elseif (preg_match('/\bSFX\b/i', $text)) { $o['form_factor'] = 'SFX'; } elseif (preg_match('/\bTFX\b/i', $text)) { $o['form_factor'] = 'TFX'; } elseif (preg_match('/\bATX\b/i', $text)) { $o['form_factor'] = 'ATX'; }
        return $o;
    }

    public static function check(array $f): ?string
    {
        $w = $f['power_watt'] ?? null;
        if ($w === null || $w < 200 || $w > 2200) { return 'power_watt must be 200 to 2200'; }
        if (!in_array((string) ($f['efficiency_rating'] ?? ''), self::EFFICIENCY, true)) { return 'efficiency_rating must be None or 80+ White, Bronze, Silver, Gold, Platinum, Titanium'; }
        if (!in_array((string) ($f['modular_type'] ?? ''), self::MODULAR, true)) { return 'modular_type must be Non-modular, Semi-modular or Fully modular'; }
        if (!in_array((string) ($f['form_factor'] ?? ''), self::FORM_FACTORS, true)) { return 'form_factor must be ATX, SFX, SFX-L or TFX'; }
        return null;
    }

    public static function name(string $brand, string $series, array $f): string
    {
        $w = (int) $f['power_watt'];
        if (strtolower($brand) === 'generic' || trim($series) === '') { return 'PSU ' . $w . 'W' . ($f['efficiency_rating'] !== 'None' ? ' 80 PLUS ' . substr((string) $f['efficiency_rating'], 4) : ''); }
        $s = trim($series);
        return trim($brand . ' ' . $s . (preg_match('/\b' . $w . '\s?W\b/i', $s) ? '' : ' ' . $w . 'W'));
    }

    public static function slug(string $brand, string $series, array $f): string
    {
        if (strtolower($brand) === 'generic' || trim($series) === '') { return 'psu-' . (int) $f['power_watt'] . 'w' . ($f['efficiency_rating'] !== 'None' ? '-' . strtolower(substr((string) $f['efficiency_rating'], 4)) : ''); }
        return ProductImport::slug(self::name($brand, $series, $f));
    }

    public static function summary(array $f): string
    {
        return implode(' · ', [$f['power_watt'] . 'W', $f['efficiency_rating'] === 'None' ? 'ไม่มี 80 PLUS' : str_replace('80+', '80 PLUS', (string) $f['efficiency_rating']), $f['modular_type'], $f['form_factor']]);
    }
}
