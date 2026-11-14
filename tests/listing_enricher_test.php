<?php
declare(strict_types=1);

/** Deterministic enrichment: stated facts are filled, derived facts are flagged, nothing is guessed, mixed posts are not merged. */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Research\ListingEnricher;

function check(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

// RAM: kit written without GB, bus speed, brand from product line.
$r = ListingEnricher::enrich('RAM DDR4 16*2 3600Hz fury beast RGB 5750 รวมส่ง', 'ram');
check($r['ddr_gen'] === 'ddr4' && $r['capacity_gb'] === 32 && $r['kit'] === '2x16' && $r['speed_mhz'] === 3600, 'RAM kit/speed not parsed');
check($r['brand'] === 'kingston' && in_array('BRAND_FROM_LINE', $r['enrichment_flags'], true), 'Fury Beast must map to Kingston and be flagged as derived');
check($r['spec_key'] === 'ddr4 32gb 2x16 3600' && $r['enrichment_status'] === 'complete', 'RAM spec key');

// RAM: DDR generation derived from a stated speed is flagged; with no speed and no DDR it stays missing (never guessed).
$r = ListingEnricher::enrich('Ram Corsair 8 × 2 16GB Bas 3200 รวมส่ง', 'ram');
check($r['ddr_gen'] === 'ddr4' && in_array('DDR_GEN_FROM_SPEED', $r['enrichment_flags'], true), 'DDR gen from speed must be flagged');
$r = ListingEnricher::enrich('RAM Corsair 16GB รวมส่ง', 'ram');
check($r['ddr_gen'] === null && $r['enrichment_status'] === 'missing' && str_contains($r['missing_fields'], 'ddr_gen'), 'Unknown DDR generation must stay missing');

// Misspelt brand.
check(ListingEnricher::enrich('RAM Corsir Vengeance LPX 8GB 4x2 Buss 2400', 'ram')['brand'] === 'corsair', 'Corsir typo');

// A post that mixes two products must not attach one item facts to the other.
$mixed = ListingEnricher::enrich('wd blue sn580 1tb ปก 02/2573 = 3450 Psu Fsp 650w ปก 10/71', 'psu');
check($mixed['brand'] === 'fsp' && $mixed['watt'] === 650, 'PSU brand must come from PSU brands only (not the SSD brand in the same post)');
$ambiguous = ListingEnricher::enrich('RAM Kingston Fury 16GB DDR4 3200 ... RAM ADATA 32GB DDR5 5600', 'ram');
check($ambiguous['enrichment_status'] === 'ambiguous' && $ambiguous['spec_key'] === null && $ambiguous['capacity_gb'] === null, 'Mixed RAM post must be ambiguous and blank');
check(in_array('MULTI_ITEM_CONTEXT', $ambiguous['enrichment_flags'], true), 'Ambiguous post must be flagged');

// Storage.
$r = ListingEnricher::enrich('M.2 NVMe PCIe 4.0 WD Blue SN5000 2TB = 7,390 ปก.Synnex', 'storage');
check($r['storage_type'] === 'm2_nvme' && $r['capacity_gb'] === 2000 && $r['brand'] === 'wd', 'NVMe 2TB');
$r = ListingEnricher::enrich('M.2 Samsung 512 1350 รวมส่งงดต่อ', 'storage');
check($r['capacity_gb'] === 512 && in_array('CAPACITY_UNIT_ASSUMED_GB', $r['enrichment_flags'], true), 'Bare capacity must be flagged');
$r = ListingEnricher::enrich('M.2 Wd-black Sn750 Se -ประกัน Synex 30/03/2570', 'storage');
check($r['capacity_gb'] === null && $r['enrichment_status'] === 'missing', 'Missing capacity must not be invented');

// PSU.
$r = ListingEnricher::enrich('ขาย PSU Silver Stone 850W80+ GOLD กล่องครบ', 'psu');
check($r['brand'] === 'silverstone' && $r['watt'] === 850 && $r['efficiency'] === 'gold', 'PSU 850W80+');
$r = ListingEnricher::enrich('Corsair RM850I 850W (80+gold) กล่องครบ', 'psu');
check($r['product_line'] === 'RM850i' && $r['spec_key'] === 'corsair RM850i 850w', 'PSU model line');

// Motherboard: chipset with trailing letters, socket derived and flagged.
$r = ListingEnricher::enrich('Mainboard msi pro A620AM-B EVO -ประกัน', 'motherboard');
check($r['chipset'] === 'a620' && $r['socket'] === 'am5' && $r['brand'] === 'msi', 'A620AM chipset');
check(in_array('SOCKET_FROM_CHIPSET', $r['enrichment_flags'], true), 'Socket must be flagged as derived');

// GPU: the model comes from the caller; a missing VRAM stays missing.
$r = ListingEnricher::enrich('Rog rtx 4070Ti มีประกัน', 'gpu', 'rtx 4070 ti', null);
check($r['brand'] === 'asus' && $r['enrichment_status'] === 'missing' && str_contains($r['missing_fields'], 'vram'), 'GPU without VRAM must stay missing');
$r = ListingEnricher::enrich('RTX 3080 12GB Gigabyte', 'gpu', 'rtx 3080', 12);
check($r['enrichment_status'] === 'complete', 'Complete GPU');

echo "listing_enricher_test: ok\n";
