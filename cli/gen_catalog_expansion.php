<?php
declare(strict_types=1);

/**
 * Generates database/migrations/013_catalog_expansion_owner_dataset.sql.
 * Models are the GPU/CPU model keys that appear 3+ times in the owner's Facebook dataset and were missing from the catalogue.
 * Only facts that are stable and public (generation, year, core counts, memory size, socket, rated TDP) are written; nothing is guessed.
 * Run once: php cli/gen_catalog_expansion.php  (the SQL file is the reviewed artefact; apply it with cli/migrate.php).
 */
$gpus = [ // [brand, model_name, generation, year, spec, tdp, aliases[]]
    ['NVIDIA', 'GeForce RTX 5090', 'Blackwell', 2025, '32GB GDDR7, flagship', 575, ['RTX 5090', '5090', 'GeForce RTX 5090']],
    ['NVIDIA', 'GeForce RTX 5080', 'Blackwell', 2025, '16GB GDDR7, high end', 360, ['RTX 5080', '5080', 'GeForce RTX 5080']],
    ['NVIDIA', 'GeForce RTX 5070 Ti', 'Blackwell', 2025, '16GB GDDR7', 300, ['RTX 5070 Ti', '5070 Ti', 'GeForce RTX 5070 Ti']],
    ['NVIDIA', 'GeForce RTX 5070', 'Blackwell', 2025, '12GB GDDR7', 250, ['RTX 5070', '5070', 'GeForce RTX 5070']],
    ['NVIDIA', 'GeForce RTX 5060 Ti', 'Blackwell', 2025, '8GB / 16GB GDDR7 versions', 180, ['RTX 5060 Ti', '5060 Ti', 'GeForce RTX 5060 Ti']],
    ['NVIDIA', 'GeForce RTX 5060', 'Blackwell', 2025, '8GB GDDR7', 145, ['RTX 5060', '5060', 'GeForce RTX 5060']],
    ['NVIDIA', 'GeForce RTX 4090', 'Ada Lovelace', 2022, '24GB GDDR6X, flagship', 450, ['RTX 4090', '4090', 'GeForce RTX 4090']],
    ['NVIDIA', 'GeForce RTX 4070 SUPER', 'Ada Lovelace', 2024, '12GB GDDR6X', 220, ['RTX 4070 SUPER', '4070 SUPER', 'GeForce RTX 4070 SUPER']],
    ['NVIDIA', 'GeForce RTX 4060', 'Ada Lovelace', 2023, '8GB GDDR6, 1080p gaming', 115, ['RTX 4060', '4060', 'GeForce RTX 4060']],
    ['NVIDIA', 'GeForce RTX 3090', 'Ampere', 2020, '24GB GDDR6X', 350, ['RTX 3090', '3090', 'GeForce RTX 3090']],
    ['NVIDIA', 'GeForce RTX 3080 Ti', 'Ampere', 2021, '12GB GDDR6X', 350, ['RTX 3080 Ti', '3080 Ti', 'GeForce RTX 3080 Ti']],
    ['NVIDIA', 'GeForce RTX 3070 Ti', 'Ampere', 2021, '8GB GDDR6X', 290, ['RTX 3070 Ti', '3070 Ti', 'GeForce RTX 3070 Ti']],
    ['NVIDIA', 'GeForce RTX 3060', 'Ampere', 2021, '12GB GDDR6 (an 8GB version also exists)', 170, ['RTX 3060', '3060', 'GeForce RTX 3060']],
    ['NVIDIA', 'GeForce RTX 3050', 'Ampere', 2022, '8GB GDDR6 (a 6GB version also exists)', 130, ['RTX 3050', '3050', 'GeForce RTX 3050']],
    ['NVIDIA', 'GeForce GTX 1650', 'Turing', 2019, '4GB GDDR5 (later GDDR6 versions exist)', 75, ['GTX 1650', '1650', 'GeForce GTX 1650']],
    ['AMD', 'Radeon RX 9070 XT', 'RDNA 4', 2025, '16GB GDDR6', 304, ['RX 9070 XT', '9070 XT', 'Radeon RX 9070 XT']],
    ['AMD', 'Radeon RX 9060 XT', 'RDNA 4', 2025, '8GB / 16GB GDDR6 versions', 160, ['RX 9060 XT', '9060 XT', 'Radeon RX 9060 XT']],
    ['AMD', 'Radeon RX 6500 XT', 'RDNA 2', 2022, '4GB GDDR6 (8GB board versions exist)', 107, ['RX 6500 XT', '6500 XT', 'Radeon RX 6500 XT']],
];
$cpus = [ // [brand, model_name, generation, year, spec, tdp, socket, aliases[]]
    ['Intel', 'Core i9-13900K', 'Raptor Lake', 2022, '24 cores (8P+16E) / 32 threads, LGA1700', 125, 'LGA1700', ['i9 13900K', '13900K', 'Core i9-13900K']],
    ['Intel', 'Core i7-14700KF', 'Raptor Lake Refresh', 2023, '20 cores (8P+12E) / 28 threads, LGA1700, no iGPU', 125, 'LGA1700', ['i7 14700KF', '14700KF', 'Core i7-14700KF']],
    ['Intel', 'Core i5-14400F', 'Raptor Lake Refresh', 2024, '10 cores (6P+4E) / 16 threads, LGA1700, no iGPU', 65, 'LGA1700', ['i5 14400F', '14400F', 'Core i5-14400F']],
    ['Intel', 'Core i5-14400', 'Raptor Lake Refresh', 2024, '10 cores (6P+4E) / 16 threads, LGA1700, with iGPU', 65, 'LGA1700', ['i5 14400', '14400', 'Core i5-14400']],
    ['Intel', 'Core i5-13500', 'Raptor Lake', 2023, '14 cores (6P+8E) / 20 threads, LGA1700, with iGPU', 65, 'LGA1700', ['i5 13500', '13500', 'Core i5-13500']],
    ['Intel', 'Core i3-14100F', 'Raptor Lake Refresh', 2024, '4 cores / 8 threads, LGA1700, no iGPU', 58, 'LGA1700', ['i3 14100F', '14100F', 'Core i3-14100F']],
    ['Intel', 'Core i3-13100F', 'Raptor Lake', 2023, '4 cores / 8 threads, LGA1700, no iGPU', 58, 'LGA1700', ['i3 13100F', '13100F', 'Core i3-13100F']],
    ['AMD', 'Ryzen 9 9800X3D', 'Zen 5', 2024, '8 cores / 16 threads, AM5, 3D V-Cache', 120, 'AM5', ['Ryzen 9 9800X3D', 'R7 9800X3D', '9800X3D']],
    ['AMD', 'Ryzen 7 9700X', 'Zen 5', 2024, '8 cores / 16 threads, AM5', 65, 'AM5', ['Ryzen 7 9700X', 'R7 9700X', '9700X']],
    ['AMD', 'Ryzen 9 7900X', 'Zen 4', 2022, '12 cores / 24 threads, AM5', 170, 'AM5', ['Ryzen 9 7900X', 'R9 7900X', '7900X']],
    ['AMD', 'Ryzen 5 7500F', 'Zen 4', 2023, '6 cores / 12 threads, AM5, no iGPU', 65, 'AM5', ['Ryzen 5 7500F', 'R5 7500F', '7500F']],
    ['AMD', 'Ryzen 5 8500G', 'Zen 4', 2024, '6 cores (2 Zen 4 + 4 Zen 4c) / 12 threads, AM5, with iGPU', 65, 'AM5', ['Ryzen 5 8500G', 'R5 8500G', '8500G']],
    ['AMD', 'Ryzen 7 5700X', 'Zen 3', 2022, '8 cores / 16 threads, AM4', 65, 'AM4', ['Ryzen 7 5700X', 'R7 5700X', '5700X']],
    ['AMD', 'Ryzen 5 5600X', 'Zen 3', 2020, '6 cores / 12 threads, AM4', 65, 'AM4', ['Ryzen 5 5600X', 'R5 5600X', '5600X']],
    ['AMD', 'Ryzen 5 5500', 'Zen 3', 2022, '6 cores / 12 threads, AM4, no iGPU', 65, 'AM4', ['Ryzen 5 5500', 'R5 5500', '5500']],
    ['AMD', 'Ryzen 7 2700X', 'Zen+', 2018, '8 cores / 16 threads, AM4', 105, 'AM4', ['Ryzen 7 2700X', 'R7 2700X', '2700X']],
    ['AMD', 'Ryzen 5 2600X', 'Zen+', 2018, '6 cores / 12 threads, AM4', 95, 'AM4', ['Ryzen 5 2600X', 'R5 2600X', '2600X']],
    ['AMD', 'Ryzen 3 3400G', 'Zen+', 2019, '4 cores / 8 threads, AM4, with Vega 11 graphics', 65, 'AM4', ['Ryzen 3 3400G', 'R3 3400G', '3400G']],
];
$extra = [ // brands and lines the owner named, each with one real product line so the brand shows up truthfully
    ['psu', 'Thermaltake', 'Toughpower GF3 750W', 'Toughpower GF3', 2022, '750W, 80 PLUS Gold, fully modular', null, null, ['Thermaltake Toughpower GF3 750W', 'Toughpower GF3 750W', 'Toughpower GF3']],
    ['ram', 'Klevv', 'CRAS X RGB DDR4', 'CRAS X', 2021, 'DDR4 RGB kits (3200 MHz and up)', null, null, ['Klevv CRAS X RGB', 'CRAS X RGB', 'KLEVV CRAS X']],
];
$slug = static fn(string $s): string => trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');
$norm = static fn(string $s): string => (string)preg_replace('/[^a-z0-9]+/u', '', mb_strtolower($s, 'UTF-8'));
$q = static fn(string $s): string => "'" . str_replace("'", "''", $s) . "'";
$seen = [];
$sql = "-- Catalogue expansion from the owner's Facebook dataset (models seen 3+ times that were missing). Additive and idempotent.\n"
     . "-- Generated by cli/gen_catalog_expansion.php. Brands, products and aliases only: no prices, no observations.\n\n";
foreach (['Klevv'] as $b) {
    $sql .= "INSERT IGNORE INTO brands (name,slug) VALUES (" . $q($b) . "," . $q($slug($b)) . ");\n";
}
$sql .= "\n";
$emit = static function (string $cat, string $brand, string $model, ?string $gen, int $year, string $spec, ?int $tdp, ?string $socket, array $aliases) use (&$sql, &$seen, $slug, $norm, $q): void {
    $full = ($brand === 'NVIDIA' || $brand === 'AMD' || $brand === 'Intel' || $brand === 'Thermaltake' || $brand === 'Klevv') ? $brand . ' ' . $model : $model;
    $s = $slug($full);
    $sql .= "INSERT INTO products (category_id,brand_id,model_name,slug,full_name,generation,release_year,spec_summary,tdp_watt,socket,is_popular,is_active,created_at,updated_at)\n"
          . "SELECT c.id,b.id," . $q($model) . "," . $q($s) . "," . $q($full) . "," . ($gen ? $q($gen) : 'NULL') . ",$year," . $q($spec) . "," . ($tdp ?? 'NULL') . "," . ($socket ? $q($socket) : 'NULL') . ",0,1,NOW(),NOW()\n"
          . "FROM product_categories c JOIN brands b ON b.name=" . $q($brand) . " WHERE c.slug=" . $q($cat) . " AND NOT EXISTS (SELECT 1 FROM products WHERE slug=" . $q($s) . ");\n";
    foreach ($aliases as $i => $a) {
        $n = $norm($a);
        if ($n === '' || isset($seen[$n])) { continue; }
        $seen[$n] = true;
        $sql .= "INSERT IGNORE INTO product_aliases (product_id,alias_text,normalized_alias,confidence) SELECT id," . $q($a) . "," . $q($n) . "," . ($i === 0 ? 100 : 98) . " FROM products WHERE slug=" . $q($s) . ";\n";
    }
    $sql .= "\n";
};
foreach ($gpus as [$b, $m, $g, $y, $sp, $t, $al]) { $emit('gpu', $b, $m, $g, $y, $sp, $t, null, $al); }
foreach ($cpus as [$b, $m, $g, $y, $sp, $t, $so, $al]) { $emit('cpu', $b, $m, $g, $y, $sp, $t, $so, $al); }
foreach ($extra as [$cat, $b, $m, $g, $y, $sp, $t, $so, $al]) { $emit($cat, $b, $m, $g, $y, $sp, $t, $so, $al); }
$file = dirname(__DIR__) . '/database/migrations/013_catalog_expansion_owner_dataset.sql';
file_put_contents($file, $sql);
echo "wrote $file (" . strlen($sql) . " bytes, " . count($seen) . " aliases)\n";
