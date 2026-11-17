<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Admin\CollectionTargets as T;

/**
 * LS-13 S-1: the daily collection list also says which of the 8 PC categories still have NO priced part. A category with no priced part stays locked on /build,
 * and listings for it are the only thing that unlocks it, but the product gap list only names products that already have rows, so these categories were never
 * mentioned. For each locked category the report names up to five catalog parts to collect first (closest to a price first), with the rows still needed.
 * "Priced" is the same rule the Build page uses (SpecBuilderService::isPriced). Pure (no database).
 */
function ok_cn(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$p = static fn(string $cat, string $name, bool $priced): array => ['category_name' => $cat, 'full_name' => $name, 'median' => $priced ? 1000 : null, 'valid_sample_size' => $priced ? 5 : 0, 'accepted_observation_count' => $priced ? 5 : 0];
$products = [
    $p('CPU', 'AMD Ryzen 5 5600', true), $p('GPU', 'NVIDIA GeForce RTX 3070', true), $p('RAM', 'RAM DDR4-3200 16GB', true), $p('Storage', 'NVMe M.2 SSD 500GB', true),
    $p('Motherboard', 'ASUS Prime A620M-K', false), $p('Motherboard', 'MSI MAG B550 Tomahawk', false), $p('Motherboard', 'MSI PRO B650M-A WIFI', false),
    $p('Motherboard', 'ASRock X570 Steel Legend', false), $p('Motherboard', 'Gigabyte X670 Aorus Elite AX', false), $p('Motherboard', 'ASUS TUF Z790-PLUS WIFI D4', false), $p('Motherboard', 'MSI PRO B660M-A DDR4', false),
    $p('PSU', 'Corsair RM750x 750W 80+ Gold', false), $p('PSU', 'MSI MAG A750GL PCIE5 750W', false),
    $p('Case', 'Corsair 4000D Airflow', false),
    // cooling has no part in the catalog at all
];
$have = ['MSI MAG B550 Tomahawk' => 2, 'ASUS Prime A620M-K' => 1, 'MSI PRO B650M-A WIFI' => 1, 'ASRock X570 Steel Legend' => 0, 'Corsair RM750x 750W 80+ Gold' => 3];
$n = T::categoryNeeds($products, $have);
$by = array_column($n, null, 'category');
ok_cn(array_column($n, 'category') === ['cpu', 'motherboard', 'gpu', 'ram', 'storage', 'psu', 'case', 'cooling'], 'All 8 categories in PC order: ' . json_encode(array_column($n, 'category')));
ok_cn($by['cpu']['priced'] === 1 && $by['cpu']['candidates'] === [], 'A priced category lists no candidates');
ok_cn($by['motherboard']['priced'] === 0 && $by['motherboard']['total'] === 7 && count($by['motherboard']['candidates']) === 5, 'A locked category shows at most 5 candidates of its 7 parts');
$c = $by['motherboard']['candidates'];
ok_cn(array_column($c, 'name') === ['MSI MAG B550 Tomahawk', 'ASUS Prime A620M-K', 'MSI PRO B650M-A WIFI', 'ASRock X570 Steel Legend', 'ASUS TUF Z790-PLUS WIFI D4'], 'Closest to a price first (most rows held), then by name: ' . json_encode(array_column($c, 'name')));
ok_cn($c[0]['have'] === 2 && $c[0]['need'] === 1 && $c[3]['have'] === 0 && $c[3]['need'] === 3, 'Rows needed is 3 minus the rows held: ' . json_encode([$c[0], $c[3]]));
ok_cn($c[0]['search'] === 'MSI MAG B550 Tomahawk', 'Search words come from the catalog name only');
ok_cn($by['psu']['candidates'][0]['name'] === 'Corsair RM750x 750W 80+ Gold' && $by['psu']['candidates'][0]['need'] === 0, 'A part that already has enough rows needs 0 more');
ok_cn($by['cooling']['total'] === 0 && $by['cooling']['candidates'] === [], 'A category with no catalog part has no candidates and total 0');
ok_cn($by['case']['label'] === 'เคส' && $by['motherboard']['label'] === 'เมนบอร์ด' && $by['cooling']['label'] === 'ชุดระบายความร้อน', 'Thai labels are the page\'s own names');

$text = T::renderCategoryNeeds($n);
ok_cn(str_contains($text, 'ประกอบคอมครบชุดได้เมื่อทุกหมวดมีราคา') && str_contains($text, 'หมวดที่ยังไม่มีราคา: 4 จาก 8'), 'The text names the rule and how many of the 8 categories are locked: ' . $text);
ok_cn(str_contains($text, 'เมนบอร์ด') && str_contains($text, 'MSI MAG B550 Tomahawk') && str_contains($text, 'ขาดอีก 1 แถว') && str_contains($text, 'ค้นว่า:'), 'The text lists the candidates with rows needed and search words');
ok_cn(str_contains($text, 'ชุดระบายความร้อน') && str_contains($text, 'ยังไม่มีรุ่นในแคตตาล็อก'), 'A category with no catalog part says so');
ok_cn(str_contains($text, 'Corsair RM750x 750W 80+ Gold | ขาดอีก 0 แถว (มีแล้ว 3) | ไปตรวจในคิวตรวจ'), 'A part that already holds enough rows is sent to the review queue instead of asking for more listings: ' . $text);
ok_cn(substr_count($text, 'ไปตรวจในคิวตรวจ') === 1, 'Only the part with enough rows gets the review hint');
ok_cn(!str_contains($text, 'AMD Ryzen 5 5600'), 'Priced categories are not listed in the locked section');
ok_cn(!preg_match('/https?:|@|\b0\d{9}\b/', $text), 'The text carries no link, address or phone-like number');

// everything priced: the section says so
$all = [];
foreach (['CPU', 'GPU', 'RAM', 'Storage', 'Motherboard', 'PSU', 'Case', 'Cooling'] as $cat) { $all[] = $p($cat, "$cat part", true); }
$done = T::renderCategoryNeeds(T::categoryNeeds($all, []));
ok_cn(str_contains($done, 'ทุกหมวดมีราคาแล้ว') && !str_contains($done, 'เก็บรุ่นเหล่านี้ก่อน'), 'With every category priced the section says there is nothing blocking a complete PC: ' . $done);
echo "collection_category_needs_test: PASS\n";
