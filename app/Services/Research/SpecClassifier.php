<?php
declare(strict_types=1);

namespace App\Services\Research;

/**
 * Maps an enriched owner-dataset row (see ListingEnricher) to a "spec class": the price of RAM, storage and PSUs depends on
 * generation/speed/capacity or type/capacity or wattage/efficiency, not on one model name. A row is classified only when every
 * field of its class is stated in the listing; anything else returns null (left unmatched, never guessed).
 */
final class SpecClassifier
{
    private const RAM_SPEEDS = ['DDR4' => [2400, 2666, 3000, 3200, 3600, 4000], 'DDR5' => [4800, 5200, 5600, 6000, 6400, 6800, 7200]];
    private const RAM_CAPACITIES = [8, 16, 32, 64, 128];
    private const STORAGE_TYPES = ['m2_nvme' => 'NVMe M.2 SSD', 'm2_sata' => 'SATA M.2 SSD', 'sata_ssd' => 'SATA SSD 2.5"'];
    private const STORAGE_CAPACITIES = [240, 250, 256, 480, 500, 512, 960, 1000, 2000, 4000];
    private const PSU_EFFICIENCY = ['white', 'bronze', 'silver', 'gold', 'platinum'];

    /** @return array{category:string,slug:string,full_name:string,generation:?string,memory_type:?string,capacity_gb:?int,spec_summary:string}|null */
    public static function classify(array $row): ?array
    {
        $note = 'ราคาเฉลี่ยตามสเปก ไม่แยกยี่ห้อและรุ่นย่อย';
        switch ((string)($row['category'] ?? '')) {
            case 'ram':
                $gen = strtoupper((string)($row['ddr_gen'] ?? ''));
                $cap = (int)($row['capacity_gb'] ?? 0);
                $speed = (int)($row['speed_mhz'] ?? 0);
                if (!isset(self::RAM_SPEEDS[$gen]) || !in_array($speed, self::RAM_SPEEDS[$gen], true) || !in_array($cap, self::RAM_CAPACITIES, true)) {
                    return null;
                }
                return ['category' => 'ram', 'slug' => 'ram-' . strtolower($gen) . '-' . $speed . '-' . $cap . 'gb', 'full_name' => "RAM $gen-$speed {$cap}GB",
                    'generation' => $gen, 'memory_type' => $gen, 'capacity_gb' => $cap, 'spec_summary' => "$gen $speed MHz รวม {$cap}GB (แท่งเดี่ยวหรือชุด) · $note"];
            case 'storage':
                $type = (string)($row['storage_type'] ?? '');
                $cap = (int)($row['capacity_gb'] ?? 0);
                if (!isset(self::STORAGE_TYPES[$type]) || !in_array($cap, self::STORAGE_CAPACITIES, true)) {
                    return null;
                }
                $label = $cap >= 1000 ? ($cap / 1000) . 'TB' : $cap . 'GB';
                return ['category' => 'storage', 'slug' => 'storage-' . str_replace('_', '-', $type) . '-' . $cap . 'gb', 'full_name' => self::STORAGE_TYPES[$type] . ' ' . $label,
                    'generation' => null, 'memory_type' => null, 'capacity_gb' => $cap, 'spec_summary' => self::STORAGE_TYPES[$type] . " $label · $note"];
            case 'psu':
                $watt = (int)($row['watt'] ?? 0);
                $eff = strtolower((string)($row['efficiency'] ?? ''));
                if ($watt < 300 || $watt > 2000 || !in_array($eff, self::PSU_EFFICIENCY, true)) {
                    return null;
                }
                return ['category' => 'psu', 'slug' => "psu-{$watt}w-$eff", 'full_name' => "PSU {$watt}W 80 PLUS " . ucfirst($eff),
                    'generation' => null, 'memory_type' => null, 'capacity_gb' => null, 'spec_summary' => "{$watt}W ระดับ 80 PLUS " . ucfirst($eff) . " · $note"];
        }
        return null;
    }
}
