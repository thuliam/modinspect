<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Catalog\MotherboardFormat as M;
use App\Services\Catalog\MotherboardImport as I;
use App\Services\Catalog\SpecFilter as F;

/** LS-73: the one motherboard format. Chipset is read from a model name only when standard; combinations that cannot exist are refused; the import plans create/update/refused and writes in one transaction (rolled back here); the board filters read the product columns. */
function ok_mf(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

foreach (['TUF Gaming B450-PLUS II' => 'B450', 'PRO B650M-A WIFI' => 'B650', 'Prime H610M-K D4' => 'H610', 'TUF Gaming Z790-PLUS WiFi D4' => 'Z790', 'X870E AORUS Elite' => 'X870E', 'ROG STRIX A620M-K' => 'A620'] as $name => $chip) {
    ok_mf(M::chipsetFromName($name) === $chip, "chipset of $name: " . var_export(M::chipsetFromName($name), true));
}
ok_mf(M::chipsetFromName('Mystery Board') === null && M::chipsetFromName('Gaming Board 123') === null, 'no chipset is guessed');
foreach (['atx' => 'ATX', 'mATX' => 'Micro-ATX', 'Micro ATX' => 'Micro-ATX', 'm-ATX' => 'Micro-ATX', 'ITX' => 'Mini-ITX', 'Mini ITX' => 'Mini-ITX', 'E-ATX' => 'E-ATX'] as $raw => $std) { ok_mf(M::formFactor($raw) === $std, "form factor $raw"); }
ok_mf(M::formFactor('huge') === null, 'an unknown form factor is not normalised');

$ok = ['socket' => 'AM5', 'chipset' => 'B650', 'memory_type' => 'DDR5', 'form_factor' => 'ATX'];
ok_mf(M::check($ok) === null, 'a valid board passes');
foreach ([['memory_type' => 'DDR4'], ['socket' => 'AM3'], ['chipset' => '650'], ['chipset' => ''], ['form_factor' => 'Big']] as $bad) { ok_mf(M::check($bad + $ok) !== null, 'refused: ' . json_encode($bad)); }
ok_mf(M::check(['socket' => 'LGA1700', 'memory_type' => 'DDR4'] + $ok) === null && M::check(['socket' => 'LGA1700', 'memory_type' => 'DDR5'] + $ok) === null, 'LGA1700 boards may be DDR4 or DDR5');
ok_mf(M::check(['socket' => 'AM4', 'memory_type' => 'DDR5'] + $ok) !== null, 'an AM4 board cannot be DDR5');

global $config;
$db = Database::connection($config['db']);
ok_mf($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$brand = (string) $db->query("SELECT name FROM brands ORDER BY id LIMIT 1")->fetchColumn();
$rows = [
    ['brand' => $brand, 'model_name' => 'ZZ Test B650-PLUS', 'socket' => 'am5', 'chipset' => 'b650', 'memory_type' => 'ddr5', 'form_factor' => 'ATX', 'is_active' => '1'],
    ['brand' => $brand, 'model_name' => 'ZZ Test B760M-A', 'socket' => 'LGA1700', 'chipset' => 'B760', 'memory_type' => 'DDR4', 'form_factor' => 'mATX', 'is_active' => ''],
    ['brand' => 'No Such Brand', 'model_name' => 'x', 'socket' => 'AM5', 'chipset' => 'B650', 'memory_type' => 'DDR5', 'form_factor' => 'ATX', 'is_active' => ''],
    ['brand' => $brand, 'model_name' => 'ZZ Bad', 'socket' => 'AM5', 'chipset' => 'B650', 'memory_type' => 'DDR4', 'form_factor' => 'ATX', 'is_active' => ''],
    ['brand' => $brand, 'model_name' => '', 'socket' => 'AM5', 'chipset' => 'B650', 'memory_type' => 'DDR5', 'form_factor' => 'ATX', 'is_active' => ''],
    ['brand' => $brand, 'model_name' => 'ZZ Test B650-PLUS', 'socket' => 'AM5', 'chipset' => 'B650', 'memory_type' => 'DDR5', 'form_factor' => 'ATX', 'is_active' => ''],
];
$before = (int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn();
$plan = I::plan($db, $rows);
ok_mf(array_column($plan, 'action') === ['create', 'create', 'refused', 'refused', 'refused', 'refused'], 'rows are classified: ' . implode(',', array_column($plan, 'action')));
ok_mf(str_contains((string) $plan[3]['error'], 'cannot use DDR4') && str_contains((string) $plan[5]['error'], 'repeated'), 'the reasons are given');
ok_mf($plan[1]['data']['form_factor'] === 'Micro-ATX' && $plan[0]['data']['socket'] === 'AM5' && $plan[0]['data']['chipset'] === 'B650', 'spellings are normalised');
ok_mf((int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn() === $before, 'plan() writes nothing');
$db->beginTransaction();
try {
    ok_mf(I::apply($db, $plan)['created'] === 2, 'apply creates the boards');
    ok_mf(I::plan($db, [$rows[0]])[0]['action'] === 'update', 'a repeat is an update, never a duplicate');
} finally { $db->rollBack(); }
ok_mf((int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn() === $before, 'the test left no product behind');

$board = ['id' => 7, 'category_slug' => 'motherboard', 'full_name' => 'X', 'brand_name' => 'MSI', 'socket' => 'AM5', 'chipset' => 'b650', 'memory_type' => 'DDR5', 'form_factor' => 'Micro-ATX'];
$a = F::attrs($board, ['socket' => 'AM4']);
ok_mf($a['socket'] === 'AM5' && $a['chipset'] === 'B650' && $a['form'] === 'Micro-ATX' && $a['brand'] === 'MSI' && $a['mem'] === 'DDR5', 'board filters read the product columns first');
ok_mf(F::BY_CATEGORY['motherboard'] === ['brand', 'socket', 'chipset', 'mem', 'form'], 'the board filter list');
ok_mf(str_starts_with(I::csvTemplate(), "\xEF\xBB\xBFbrand,model_name,socket,chipset"), 'the template has a BOM and its header');
echo "motherboard_format_test: PASS\n";
