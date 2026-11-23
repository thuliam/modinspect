<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Catalog\ProductFacts;
use App\Services\Catalog\ProductImport;
use App\Services\Catalog\RamFormat as R;
use App\Services\Catalog\RamImport as I;

/** LS-71: the one RAM format. parse() returns only what the text states; check() refuses impossible facts; names are built from the facts; the import plans create/update/refused and writes in one transaction (rolled back here). */
function ok_rf(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$p = R::parse('Kingston FURY Beast 16GB DDR4-3200  2x8GB DDR4-3200 CL16');
ok_rf($p['memory_type'] === 'DDR4' && $p['memory_speed_mhz'] === 3200 && $p['capacity_gb'] === 16 && $p['kit_modules'] === 2 && $p['form_factor'] === null, 'a kit is read: ' . json_encode($p));
$p = R::parse('RAM DDR5-5600 16GB DDR5 5600 MHz รวม 16GB (แท่งเดี่ยวหรือชุด)');
ok_rf($p['memory_type'] === 'DDR5' && $p['memory_speed_mhz'] === 5600 && $p['capacity_gb'] === 16 && $p['kit_modules'] === null, 'a generic class is read');
$p = R::parse('Klevv CRAS X RGB DDR4 แรม DDR4 RGB (3200 MHz ขึ้นไป)');
ok_rf($p['memory_type'] === 'DDR4' && $p['memory_speed_mhz'] === null && $p['capacity_gb'] === null, 'a floor (3200 MHz and up) and a missing capacity stay missing, never guessed');
ok_rf(R::parse('Corsair Vengeance SODIMM DDR4-3200 16GB')['form_factor'] === 'SO-DIMM' && R::parse('Crucial DIMM DDR5-4800 16GB')['form_factor'] === 'DIMM', 'form factor only when the text says it');

$ok = ['memory_type' => 'DDR5', 'memory_speed_mhz' => 6000, 'capacity_gb' => 32, 'kit_modules' => 2, 'form_factor' => 'DIMM'];
ok_rf(R::check($ok) === null, 'a valid kit passes');
foreach ([['memory_type' => 'DDR6'], ['memory_speed_mhz' => 1000], ['memory_speed_mhz' => 99999], ['capacity_gb' => 7], ['kit_modules' => 3], ['form_factor' => 'LONG'], ['capacity_gb' => 4, 'kit_modules' => 8]] as $bad) {
    ok_rf(R::check($bad + $ok) !== null, 'refused: ' . json_encode($bad));
}
ok_rf(R::name('Kingston', 'FURY Beast', $ok) === 'Kingston FURY Beast DDR5-6000 32GB (2x16GB)', 'branded name: ' . R::name('Kingston', 'FURY Beast', $ok));
ok_rf(R::name('Generic', '', ['kit_modules' => null] + $ok) === 'RAM DDR5-6000 32GB', 'generic spec class name');
ok_rf(R::name('Corsair', 'Vengeance', ['form_factor' => 'SO-DIMM'] + $ok) === 'Corsair Vengeance DDR5-6000 32GB SO-DIMM (2x16GB)', 'SO-DIMM shows in the name');

global $config;
$db = Database::connection($config['db']);
ok_rf($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
ok_rf(I::ready($db), 'the test database has the RAM columns');
$brand = (string) $db->query("SELECT name FROM brands WHERE name NOT IN ('Generic') ORDER BY id LIMIT 1")->fetchColumn();
$db->exec("INSERT IGNORE INTO brands (name, slug) VALUES ('Generic', 'generic')");
$rows = [
    ['brand' => $brand, 'series' => 'Test Series', 'memory_type' => 'DDR5', 'form_factor' => 'DIMM', 'memory_speed_mhz' => '6000', 'capacity_gb' => '32', 'kit_modules' => '2', 'is_active' => '1'],
    ['brand' => 'Generic', 'series' => '', 'memory_type' => 'DDR4', 'form_factor' => 'DIMM', 'memory_speed_mhz' => '3200', 'capacity_gb' => '16', 'kit_modules' => '', 'is_active' => ''],
    ['brand' => 'No Such Brand', 'series' => 'x', 'memory_type' => 'DDR4', 'form_factor' => 'DIMM', 'memory_speed_mhz' => '3200', 'capacity_gb' => '16', 'kit_modules' => '', 'is_active' => ''],
    ['brand' => $brand, 'series' => '', 'memory_type' => 'DDR4', 'form_factor' => 'DIMM', 'memory_speed_mhz' => '3200', 'capacity_gb' => '16', 'kit_modules' => '', 'is_active' => ''],
    ['brand' => $brand, 'series' => 'Test Series', 'memory_type' => 'DDR4', 'form_factor' => '', 'memory_speed_mhz' => '3200', 'capacity_gb' => '16', 'kit_modules' => '', 'is_active' => ''],
    ['brand' => $brand, 'series' => 'Test Series', 'memory_type' => 'DDR4', 'form_factor' => 'DIMM', 'memory_speed_mhz' => '9', 'capacity_gb' => '16', 'kit_modules' => '', 'is_active' => ''],
    ['brand' => $brand, 'series' => 'Test Series', 'memory_type' => 'DDR5', 'form_factor' => 'DIMM', 'memory_speed_mhz' => '6000', 'capacity_gb' => '32', 'kit_modules' => '2', 'is_active' => '1'],
];
$before = (int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn();
$plan = I::plan($db, $rows);
ok_rf(array_column($plan, 'action') === ['create', 'create', 'refused', 'refused', 'refused', 'refused', 'refused'], 'rows are classified: ' . implode(',', array_column($plan, 'action')));
ok_rf(str_contains((string) $plan[6]['error'], 'repeated') && str_contains((string) $plan[4]['error'], 'form_factor') && str_contains((string) $plan[3]['error'], 'series'), 'the reasons are given');
ok_rf($plan[0]['data']['full_name'] === "$brand Test Series DDR5-6000 32GB (2x16GB)" && $plan[1]['data']['slug'] === 'ram-ddr4-3200-16gb', 'names and slugs are built from the facts (a generic class keeps the existing slug)');
ok_rf((int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn() === $before, 'plan() writes nothing');
$db->beginTransaction();
try {
    ok_rf(I::apply($db, $plan)['created'] >= 1, 'apply creates the products');
    $r = $db->query("SELECT memory_type, form_factor, memory_speed_mhz, capacity_gb, kit_modules FROM products WHERE slug='" . $plan[0]['data']['slug'] . "'")->fetch();
    ok_rf($r['memory_type'] === 'DDR5' && $r['form_factor'] === 'DIMM' && (int) $r['memory_speed_mhz'] === 6000 && (int) $r['capacity_gb'] === 32 && (int) $r['kit_modules'] === 2, 'the facts are stored as columns');
    $again = I::plan($db, [$rows[0]]);
    ok_rf($again[0]['action'] === 'update', 'a repeat is an update, never a duplicate');
    $prod = $db->query("SELECT * FROM products WHERE slug='" . $plan[0]['data']['slug'] . "'")->fetch();
    $facts = ProductFacts::rows($prod, [], 2026); $labels = array_column($facts, 'label');
    ok_rf(in_array('ความเร็วบัสหน่วยความจำ', $labels, true) && in_array('จำนวนแท่งในชุด', $labels, true) && in_array('ขนาด / ฟอร์มแฟกเตอร์', $labels, true), 'the product page facts show bus speed, kit and form factor');
} finally { $db->rollBack(); }
ok_rf((int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn() === $before, 'the test left no product behind');
ok_rf(str_starts_with(I::csvTemplate(), "\xEF\xBB\xBFbrand,series,memory_type,form_factor,memory_speed_mhz"), 'the RAM template has a BOM and its header');
ok_rf(in_array('memory_speed_mhz', ProductImport::COLUMNS, true), 'the general product import knows the new columns');
echo "ram_format_test: PASS\n";
