<?php
declare(strict_types=1);
namespace App\Services\Catalog;

/**
 * LS-77: a price row must not sit under a RAM product whose stated facts the row's own title contradicts (a 16GB kit under a 32GB class).
 * Pure: compares only facts BOTH sides state; a fact the title does not state is never a conflict. Returns the list of conflicts, empty when consistent.
 */
final class SpecConsistency
{
    /**
     * LS-77b: a title that names only another hardware category (a DDR5 kit under a Ryzen CPU, a motherboard under a CPU) is filed in the wrong category.
     * Fires only when the title states the other category and says nothing of the product's own, so a CPU+RAM bundle title is left to the bundle rules.
     */
    public static function categoryConflict(string $title, string $category): ?string
    {
        $category = strtoupper($category);
        // what a title says about the thing being sold; MOTHERBOARD is only ever the product side (its own words are broad, so it is not used to detect)
        $detect = [
            'RAM' => '/\b(ddr[345]|sodimm)\b|แรม/iu',
            'CPU' => '/\b(ryzen|core\s?i[3579]|i[3579]-\d{4,5}k?f?)\b/iu',
            'GPU' => '/\b(rtx|gtx|radeon|geforce|rx\s?\d{3,4})\b/iu',
            'STORAGE' => '/\b(nvme|ssd|hdd|m\.2)\b/iu',
            'PSU' => '/\b(psu|power\s?supply|80\s?plus)\b|พาวเวอร์ซัพพลาย/iu',
        ];
        $own = $detect + ['MOTHERBOARD' => '/\b(motherboard|mainboard|socket|lga\s?\d{3,4}|am[45]|[abhxzq]\d{2,3}m?)\b|เมนบอร์ด/iu'];
        if (!isset($own[$category]) || preg_match($own[$category], $title) === 1) { return null; }
        foreach ($detect as $other => $re) { if ($other !== $category && preg_match($re, $title) === 1) { return "title is $other, product is $category"; } }
        return null;
    }

    /**
     * @param array{memory_type?:?string,memory_speed_mhz?:?int|string,capacity_gb?:?int|string} $product the product's own RAM facts
     * @return list<string>
     */
    public static function ramConflicts(string $title, array $product): array
    {
        $f = RamFormat::parse($title);
        $out = [];
        foreach (['capacity_gb' => 'capacity', 'memory_type' => 'type', 'memory_speed_mhz' => 'speed'] as $k => $label) {
            $p = $product[$k] ?? null;
            if ($f[$k] !== null && $p !== null && $p !== '' && (string)$f[$k] !== (string)$p) { $out[] = "$label {$f[$k]} != $p"; }
        }
        return $out;
    }
}
