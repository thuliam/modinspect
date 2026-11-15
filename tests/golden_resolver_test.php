<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\ProductResolver\ProductResolver;

/**
 * Golden resolver set: near-identical models must never be confused. The catalog is in memory (same names and alias style as the
 * real Product Master) so the test is deterministic and does not depend on which seed the test database holds.
 * A title that names a model the catalog does not contain must resolve to NO product (expected null).
 */
$p = static fn(int $id, string $name, array $aliases): array => ['id' => $id, 'full_name' => $name, 'aliases' => $aliases];
$catalog = [
    $p(1, 'NVIDIA GeForce RTX 3050', ['RTX 3050', '3050', 'GeForce RTX 3050']),
    $p(2, 'NVIDIA GeForce RTX 3060', ['RTX 3060', '3060', 'GeForce RTX 3060']),
    $p(3, 'NVIDIA GeForce RTX 3060 Ti', ['RTX 3060 Ti', '3060Ti', '3060 Ti 8GB', 'GeForce 3060 Ti', 'RTX3060Ti 8G']),
    $p(4, 'NVIDIA GeForce RTX 4070', ['RTX 4070', '4070 12GB', 'GeForce 4070', 'NVIDIA 4070', 'RTX4070 12G']),
    $p(5, 'NVIDIA GeForce RTX 4070 SUPER', ['RTX 4070 SUPER', '4070 SUPER', 'GeForce RTX 4070 SUPER']),
    $p(6, 'NVIDIA GeForce RTX 4070 Ti', ['RTX 4070 Ti', '4070Ti', '4070 Ti 12GB', 'GeForce 4070 Ti', 'RTX4070Ti 12G']),
    $p(7, 'AMD Radeon RX 5700 XT', ['RX 5700 XT', '5700XT', 'RX5700XT']),
    $p(8, 'AMD Ryzen 5 5600', ['Ryzen 5 5600', 'R5 5600', '5600 AM4', 'AMD 5600', 'Ryzen5600']),
    $p(9, 'AMD Ryzen 5 5600X', ['Ryzen 5 5600X', 'R5 5600X', '5600X']),
    $p(10, 'AMD Ryzen 5 5600G', ['Ryzen 5 5600G', 'R5 5600G', '5600G']),
    $p(11, 'AMD Ryzen 7 5700X', ['Ryzen 7 5700X', 'R7 5700X', '5700X']),
    $p(12, 'AMD Ryzen 7 5700X3D', ['5700x3d', 'r7 5700 x3d', 'Ryzen 7 5700X3D', 'AMD 5700X3D', 'Ryzen5700X3D', '5700 X3D']),
    $p(13, 'Intel Core i5-12400', ['Core i5 12400', 'i5 12400', 'i5-12400', '12400 tray']),
    $p(14, 'Intel Core i5-12400F', ['12400f', 'Core i5 12400F', 'i5 12400F', 'Intel 12400F', 'i5-12400F', '12400F tray']),
];
$resolver = new ProductResolver($catalog);

// title => expected product id, or null = must not match any product
$cases = [
    'ขาย RTX 3050 8GB มือสอง' => 1,
    'การ์ดจอ RTX 3060 12GB ประกันเหลือ' => 2,
    'RTX 3060 Ti MSI Ventus 8GB' => 3,
    'RTX3060Ti 8G สวยๆ' => 3,
    'RTX 4070 Dual 12GB' => 4,
    'RTX 4070 SUPER Gaming X' => 5,
    '4070 Super 12GB มือสอง' => 5,
    'RTX 4070 Ti ASUS TUF' => 6,
    'RX 5700 XT 8GB Pulse' => 7,
    'RX 5700 8GB' => null,                 // plain RX 5700 is not in the catalog and must not become the XT
    'Ryzen 5 5600 AM4 มือสอง' => 8,
    'R5 5600X ซีพียู' => 9,
    'Ryzen 5 5600G APU' => 10,
    'Ryzen 7 5700X กล่อง' => 11,
    'R7 5700X3D ประกันเหลือ' => 12,
    'Core i5-12400 LGA1700' => 13,
    'i5 12400F tray' => 14,
    'Ryzen 5 5500' => null,                // model not in catalog
    'RTX 3070' => null,                    // model not in catalog
];

$failures = [];
foreach ($cases as $title => $expected) {
    $res = $resolver->resolve($title);
    $got = $res->productId;
    if ($expected === null ? ($got !== null && $res->confidence >= 0.9) : ($got !== $expected)) {
        $failures[] = sprintf('"%s": expected %s, got %s (confidence %.2f)', $title, $expected === null ? 'no match' : '#' . $expected, $got === null ? 'null' : '#' . $got, (float)$res->confidence);
    }
}
if ($failures) {
    fwrite(STDERR, "Golden resolver failures:\n  " . implode("\n  ", $failures) . "\n");
    throw new RuntimeException(count($failures) . ' golden resolver case(s) failed');
}
echo 'golden_resolver_test: PASS (' . count($cases) . " cases)\n";
