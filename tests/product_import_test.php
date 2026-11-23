<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Catalog\GameFpsImport as F;
use App\Services\Catalog\ProductImport as P;

/** LS-67: import centre services. Product rows are classified create/update/refused without writing; apply writes in one transaction (rolled back here); game FPS plans reuse the same validator as the CLI. */
function ok_pi(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

global $config;
$db = Database::connection($config['db']);
ok_pi($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$cat = (string) $db->query("SELECT slug FROM product_categories ORDER BY id LIMIT 1")->fetchColumn();
$brand = (string) $db->query("SELECT name FROM brands ORDER BY id LIMIT 1")->fetchColumn();
$exist = (string) $db->query("SELECT slug FROM products ORDER BY id LIMIT 1")->fetchColumn();
ok_pi($cat !== '' && $brand !== '' && $exist !== '', 'the test database has a category, a brand and a product');

$good = ['category' => $cat, 'brand' => $brand, 'model_name' => 'ZZ Import Test 1', 'full_name' => 'ZZ Import Test 1', 'slug' => '', 'tdp_watt' => '65', 'release_year' => '2024', 'image_path' => 'assets/products/zz.jpg', 'is_active' => '1'];
$rows = [
    $good,
    ['slug' => $exist] + $good,
    ['category' => 'nope'] + $good,
    ['brand' => 'No Such Brand'] + $good,
    ['full_name' => ''] + $good,
    ['tdp_watt' => '65W'] + $good,
    ['image_path' => 'https://example.test/a.jpg'] + $good,
    ['image_path' => 'assets/../x.jpg'] + $good,
    ['is_active' => 'maybe'] + $good,
    $good, // same generated slug again
];
$before = (int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn();
$plan = P::plan($db, $rows);
$acts = array_column($plan, 'action');
ok_pi($acts === ['create', 'update', 'refused', 'refused', 'refused', 'refused', 'refused', 'refused', 'refused', 'refused'], 'rows are classified: ' . implode(',', $acts));
ok_pi(str_contains((string) $plan[9]['error'], 'repeated'), 'a repeated slug in one file is refused');
ok_pi((int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn() === $before, 'plan() writes nothing');
ok_pi($plan[0]['slug'] === 'zz-import-test-1', 'the slug is generated from the full name');

$db->beginTransaction();
try {
    $done = P::apply($db, $plan);
    ok_pi($done === ['created' => 1, 'updated' => 1], 'apply counts created and updated');
    $r = $db->query("SELECT tdp_watt, release_year, image_path, is_active FROM products WHERE slug='zz-import-test-1'")->fetch();
    ok_pi((int) $r['tdp_watt'] === 65 && (int) $r['release_year'] === 2024 && $r['image_path'] === 'assets/products/zz.jpg' && (int) $r['is_active'] === 1, 'the new product carries the filled columns');
    $name = (string) $db->query("SELECT model_name FROM products WHERE slug=" . $db->quote($exist))->fetchColumn();
    ok_pi($name === 'ZZ Import Test 1', 'an existing slug is updated');
} finally {
    $db->rollBack();
}
ok_pi((int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn() === $before, 'the test left no product behind');
ok_pi(str_starts_with(P::csvTemplate(), "\xEF\xBB\xBFcategory,brand,model_name") && str_starts_with(F::csvTemplate(), "\xEF\xBB\xBFproduct,game,resolution"), 'both templates start with a BOM and their header');

// game FPS plan: product match + validation, no write
$gpu = (string) $db->query("SELECT p.slug FROM products p JOIN product_categories c ON c.id=p.category_id WHERE c.slug='gpu' AND p.is_active=1 LIMIT 1")->fetchColumn();
$fp = ['product' => $gpu, 'game' => 'Counter-Strike 2', 'resolution' => '1080p', 'quality' => 'High', 'avg_fps' => '200', 'low1_fps' => '150', 'paired_part' => 'Ryzen 5 5600', 'source_name' => 'Lab', 'source_url' => 'https://example.test/x', 'license_note' => 'CC BY', 'sponsor_name' => '', 'tested_on' => ''];
$fplan = F::plan($db, [$fp, ['product' => 'no such card'] + $fp, ['avg_fps' => '0'] + $fp]);
ok_pi(array_column($fplan, 'action') === ['create', 'refused', 'refused'] && $fplan[0]['product_id'] > 0, 'FPS rows: one valid, unknown product and bad FPS refused');
if (F::tableExists($db)) {
    $db->beginTransaction();
    try { ok_pi(F::apply($db, $fplan)['created'] === 1, 'FPS apply stores only the valid row'); } finally { $db->rollBack(); }
}
echo "product_import_test: PASS\n";
