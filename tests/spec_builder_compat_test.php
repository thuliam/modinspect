<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\SpecBuilderService;

/**
 * Build compatibility messages (LS-3 J-12 finding): a part whose socket or memory type is simply UNKNOWN in the catalog (an empty column) must not be
 * reported as incompatible. Found on the real catalog: the RAM "Klevv CRAS X RGB DDR4" has no memory_type, so /build told visitors it did not match a DDR4 board.
 * Known and different is an error; unknown is a "check this yourself" note.
 */
function ok_sb(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$part = static fn(int $id, string $slug, string $cat, array $extra = []): array => array_merge(
    ['id' => $id, 'slug' => $slug, 'category_name' => $cat, 'full_name' => $slug, 'q1' => 1000, 'median' => 1200, 'q3' => 1500, 'valid_sample_size' => 5, 'accepted_observation_count' => 5, 'confidence_score' => 50, 'socket' => null, 'memory_type' => null], $extra);
$cpuAm4 = $part(1, 'cpu-am4', 'CPU', ['socket' => 'AM4']);
$boardAm4 = $part(2, 'board-am4', 'Motherboard', ['socket' => 'AM4', 'memory_type' => 'DDR4']);
$boardAm5 = $part(3, 'board-am5', 'Motherboard', ['socket' => 'AM5', 'memory_type' => 'DDR5']);
$ramDdr4 = $part(4, 'ram-ddr4', 'RAM', ['memory_type' => 'DDR4']);
$ramDdr5 = $part(5, 'ram-ddr5', 'RAM', ['memory_type' => 'DDR5']);
$ramUnknown = $part(6, 'ram-unknown', 'RAM', ['memory_type' => '']);
$cpuUnknown = $part(7, 'cpu-unknown', 'CPU', ['socket' => null]);
$all = [$cpuAm4, $boardAm4, $boardAm5, $ramDdr4, $ramDdr5, $ramUnknown, $cpuUnknown];
$gen = static function (array $ids) use ($all): array {
    return (new SpecBuilderService())->generate($all, 30000.0, 'gaming', '1080p', 'used', $ids);
};

// known and equal: fine
$r = $gen(['cpu' => 1, 'motherboard' => 2, 'ram' => 4]);
ok_sb($r['compatibilityError'] === null, 'AM4 CPU + AM4/DDR4 board + DDR4 RAM is compatible: ' . json_encode($r['compatibilityError']));
// known and different: errors
$r = $gen(['cpu' => 1, 'motherboard' => 3]);
ok_sb($r['compatibilityError'] !== null && str_contains($r['compatibilityError'], 'AM4') && str_contains($r['compatibilityError'], 'AM5'), 'A socket mismatch is an error naming both sockets');
$r = $gen(['motherboard' => 2, 'ram' => 5]);
ok_sb($r['compatibilityError'] !== null && str_contains($r['compatibilityError'], 'DDR5') && str_contains($r['compatibilityError'], 'DDR4'), 'DDR5 RAM on a DDR4 board is an error naming both');
// unknown is not an error
$r = $gen(['motherboard' => 2, 'ram' => 6]);
ok_sb($r['compatibilityError'] === null, 'RAM with an unknown memory type is not reported as incompatible: ' . json_encode($r['compatibilityError']));
ok_sb(str_contains($r['notes'], 'ไม่ทราบชนิดหน่วยความจำ') , 'The unknown memory type is stated as a note to check by hand');
$r = $gen(['cpu' => 7, 'motherboard' => 2]);
ok_sb($r['compatibilityError'] === null && str_contains($r['notes'], 'ไม่ทราบ socket'), 'A CPU with an unknown socket is a note, not an error');
// an unknown never hides a real error elsewhere
$r = $gen(['cpu' => 1, 'motherboard' => 3, 'ram' => 6]);
ok_sb($r['compatibilityError'] !== null && str_contains($r['compatibilityError'], 'AM5'), 'A real socket error is still reported when the RAM type is unknown');

echo "spec_builder_compat_test: PASS\n";
