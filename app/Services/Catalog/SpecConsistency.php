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
