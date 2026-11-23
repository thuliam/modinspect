<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Catalog\ProductFacts;
use App\Services\Catalog\PsuFormat as P;
use App\Services\Catalog\PsuImport as PI;
use App\Services\Catalog\SpecFilter as F;
use App\Services\Catalog\StorageFormat as S;
use App\Services\Catalog\StorageImport as SI;

/** LS-74: the storage and PSU formats. parse() reads only what the text states; check() refuses combinations that cannot exist; names and slugs are built from the facts (a generic class keeps its old slug); imports plan create/update/refused and write in one transaction (rolled back here); filters and product facts read the new columns. */
function ok_sp(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

// ---- storage
$s = S::parse('Samsung 970 EVO Plus 1TB NVMe PCIe 3.0 x4 M.2 2280');
ok_sp($s['interface_type'] === 'NVMe' && $s['media_type'] === 'SSD' && $s['form_factor'] === 'M.2 2280' && $s['capacity_gb'] === 1000, 'an NVMe drive is read: ' . json_encode($s));
$s = S::parse('NVMe M.2 SSD 500GB');
ok_sp($s['form_factor'] === 'M.2' && $s['capacity_gb'] === 500 && $s['media_type'] === 'SSD', 'a generic class is read');
$s = S::parse('Seagate Barracuda 2TB HDD SATA 3.5"');
ok_sp($s['media_type'] === 'HDD' && $s['interface_type'] === 'SATA' && $s['form_factor'] === '3.5"' && $s['capacity_gb'] === 2000, 'an HDD is read: ' . json_encode($s));
ok_sp(S::parse('Mystery drive')['capacity_gb'] === null && S::parse('Mystery drive')['interface_type'] === null, 'nothing is guessed');
$ok = ['interface_type' => 'NVMe', 'media_type' => 'SSD', 'form_factor' => 'M.2 2280', 'capacity_gb' => 1000];
ok_sp(S::check($ok) === null, 'a valid drive passes');
foreach ([['interface_type' => 'IDE'], ['media_type' => 'Tape'], ['form_factor' => '5"'], ['capacity_gb' => 5], ['form_factor' => '2.5"'], ['media_type' => 'HDD']] as $bad) { ok_sp(S::check($bad + $ok) !== null, 'refused: ' . json_encode($bad)); }
ok_sp(S::name('Samsung', '990 EVO Plus', $ok) === 'Samsung 990 EVO Plus 1TB' && S::name('Samsung', '990 EVO Plus 1TB', $ok) === 'Samsung 990 EVO Plus 1TB', 'branded name, capacity not repeated');
ok_sp(S::name('Generic', '', $ok) === 'NVMe M.2 SSD 1TB' && S::slug('Generic', '', $ok) === 'storage-m2-nvme-1000gb', 'generic name and the existing slug style');
ok_sp(S::slug('Generic', '', ['interface_type' => 'SATA', 'media_type' => 'SSD', 'form_factor' => '2.5"', 'capacity_gb' => 480]) === 'storage-25-sata-480gb', 'generic SATA slug');

// ---- PSU
$p = P::parse('Corsair RM750x 750W Gold สายแบบถอดได้ทั้งหมด, 80+ Gold ATX');
ok_sp($p['power_watt'] === 750 && $p['efficiency_rating'] === '80+ Gold' && $p['modular_type'] === 'Fully modular' && $p['form_factor'] === 'ATX', 'a PSU is read: ' . json_encode($p));
$p = P::parse('PSU 850W 80 PLUS Gold');
ok_sp($p['power_watt'] === 850 && $p['efficiency_rating'] === '80+ Gold' && $p['form_factor'] === null && $p['modular_type'] === null, 'form factor and cable type stay empty unless stated');
ok_sp(P::efficiency('Gold') === '80+ Gold' && P::efficiency('none') === 'None' && P::efficiency('shiny') === null && P::modular('semi') === 'Semi-modular' && P::formFactor('sfx-l') === 'SFX-L', 'spellings are normalised');
$okp = ['power_watt' => 750, 'efficiency_rating' => '80+ Gold', 'modular_type' => 'Fully modular', 'form_factor' => 'ATX'];
ok_sp(P::check($okp) === null, 'a valid PSU passes');
foreach ([['power_watt' => 50], ['power_watt' => 9000], ['efficiency_rating' => '80+ Shiny'], ['modular_type' => 'Wireless'], ['form_factor' => 'EATX']] as $bad) { ok_sp(P::check($bad + $okp) !== null, 'refused: ' . json_encode($bad)); }
ok_sp(P::name('Corsair', 'RM750x', $okp) === 'Corsair RM750x 750W' && P::name('Corsair', 'RM750x 750W', $okp) === 'Corsair RM750x 750W', 'branded PSU name, watts not repeated');
ok_sp(P::name('Generic', '', ['efficiency_rating' => '80+ Gold', 'power_watt' => 850] + $okp) === 'PSU 850W 80 PLUS Gold' && P::slug('Generic', '', ['efficiency_rating' => '80+ Gold', 'power_watt' => 850] + $okp) === 'psu-850w-gold', 'generic PSU name and the existing slug style');

// ---- imports on the test database
global $config;
$db = Database::connection($config['db']);
ok_sp($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$brand = (string) $db->query("SELECT name FROM brands WHERE name <> 'Generic' ORDER BY id LIMIT 1")->fetchColumn();
$db->exec("INSERT IGNORE INTO brands (name, slug) VALUES ('Generic', 'generic')");
$before = (int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn();
$sr = [
    ['brand' => $brand, 'series' => 'ZZ Test Drive', 'media_type' => 'ssd', 'interface_type' => 'nvme', 'form_factor' => 'M.2 2280', 'capacity_gb' => '1000', 'is_active' => '1'],
    ['brand' => 'Generic', 'series' => '', 'media_type' => 'SSD', 'interface_type' => 'SATA', 'form_factor' => '2.5"', 'capacity_gb' => '480', 'is_active' => ''],
    ['brand' => $brand, 'series' => 'ZZ Bad', 'media_type' => 'SSD', 'interface_type' => 'NVMe', 'form_factor' => '2.5"', 'capacity_gb' => '1000', 'is_active' => ''],
    ['brand' => 'No Such Brand', 'series' => 'x', 'media_type' => 'SSD', 'interface_type' => 'NVMe', 'form_factor' => 'M.2', 'capacity_gb' => '500', 'is_active' => ''],
    ['brand' => $brand, 'series' => '', 'media_type' => 'SSD', 'interface_type' => 'NVMe', 'form_factor' => 'M.2', 'capacity_gb' => '500', 'is_active' => ''],
    ['brand' => $brand, 'series' => 'ZZ Test Drive', 'media_type' => 'SSD', 'interface_type' => 'NVMe', 'form_factor' => 'M.2 2280', 'capacity_gb' => '1000', 'is_active' => ''],
];
$plan = SI::plan($db, $sr);
ok_sp(array_column($plan, 'action') === ['create', 'create', 'refused', 'refused', 'refused', 'refused'], 'storage rows are classified: ' . implode(',', array_column($plan, 'action')));
ok_sp($plan[0]['data']['full_name'] === "$brand ZZ Test Drive 1TB" && $plan[1]['data']['slug'] === 'storage-25-sata-480gb', 'storage names and slugs are built');
$pr = [
    ['brand' => $brand, 'series' => 'ZZ Test PSU', 'power_watt' => '750', 'efficiency_rating' => 'gold', 'modular_type' => 'full', 'form_factor' => 'atx', 'is_active' => '1'],
    ['brand' => 'Generic', 'series' => '', 'power_watt' => '650', 'efficiency_rating' => '80+ Bronze', 'modular_type' => 'Non-modular', 'form_factor' => 'ATX', 'is_active' => ''],
    ['brand' => $brand, 'series' => 'ZZ Weak', 'power_watt' => '50', 'efficiency_rating' => 'Gold', 'modular_type' => 'Fully modular', 'form_factor' => 'ATX', 'is_active' => ''],
    ['brand' => $brand, 'series' => '', 'power_watt' => '750', 'efficiency_rating' => 'Gold', 'modular_type' => 'Fully modular', 'form_factor' => 'ATX', 'is_active' => ''],
];
$pplan = PI::plan($db, $pr);
ok_sp(array_column($pplan, 'action') === ['create', 'create', 'refused', 'refused'], 'PSU rows are classified: ' . implode(',', array_column($pplan, 'action')));
ok_sp((int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn() === $before, 'plans write nothing');
$db->beginTransaction();
try {
    ok_sp(SI::apply($db, $plan)['created'] === 2 && PI::apply($db, $pplan)['created'] === 2, 'applies create the products');
    $r = $db->query("SELECT interface_type, media_type, form_factor, capacity_gb FROM products WHERE slug='" . $plan[0]['data']['slug'] . "'")->fetch();
    ok_sp($r['interface_type'] === 'NVMe' && $r['media_type'] === 'SSD' && (int) $r['capacity_gb'] === 1000, 'storage facts are stored as columns');
    ok_sp(SI::plan($db, [$sr[0]])[0]['action'] === 'update' && PI::plan($db, [$pr[0]])[0]['action'] === 'update', 'a repeat is an update');
    $prod = $db->query("SELECT p.*, b.name brand_name, 'psu' category_slug FROM products p JOIN brands b ON b.id=p.brand_id WHERE p.slug='" . $pplan[0]['data']['slug'] . "'")->fetch();
    $labels = array_column(ProductFacts::rows($prod, [], 2026), 'label');
    ok_sp(in_array('กำลังไฟ', $labels, true) && in_array('มาตรฐานประสิทธิภาพ', $labels, true) && in_array('ชนิดสาย', $labels, true), 'the product page facts show watts, 80 PLUS and cable type');
    $a = F::attrs($prod, []);
    ok_sp($a['watt'] === '750' && $a['eff'] === '80+ Gold' && $a['modular'] === 'Fully modular' && $a['form'] === 'ATX' && isset($a['brand']), 'PSU filters read the product columns');
} finally { $db->rollBack(); }
ok_sp((int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn() === $before, 'the test left no product behind');

$st = ['id' => 5, 'category_slug' => 'storage', 'full_name' => 'Samsung 990 EVO Plus 1TB', 'brand_name' => 'Samsung', 'interface_type' => 'NVMe', 'media_type' => 'SSD', 'form_factor' => 'M.2 2280', 'capacity_gb' => 1000];
$a = F::attrs($st, []);
ok_sp($a['iface'] === 'NVMe' && $a['media'] === 'SSD' && $a['form'] === 'M.2 2280' && $a['cap'] === '1000' && $a['brand'] === 'Samsung', 'storage filters read the product columns');
ok_sp(F::BY_CATEGORY['storage'] === ['brand', 'media', 'iface', 'form', 'cap'] && F::BY_CATEGORY['psu'] === ['brand', 'watt', 'eff', 'modular', 'form'], 'the filter lists');
ok_sp(str_starts_with(SI::csvTemplate(), "\xEF\xBB\xBFbrand,series,media_type") && str_starts_with(PI::csvTemplate(), "\xEF\xBB\xBFbrand,series,power_watt"), 'the templates have a BOM and their header');
echo "storage_psu_format_test: PASS\n";
