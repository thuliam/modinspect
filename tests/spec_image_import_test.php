<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Catalog\ImageImport as I;
use App\Services\Catalog\SpecImport as S;

/** LS-68: reference spec and product picture importers. Plans write nothing; applies run in a rolled-back transaction here; picture files are real small PNGs written to a temp folder. */
function ok_si(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

global $config;
$db = Database::connection($config['db']);
ok_si($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$prod = $db->query("SELECT id, slug, full_name FROM products WHERE is_active = 1 ORDER BY id LIMIT 1")->fetch();
ok_si(is_array($prod), 'the test database has a product');
$slug = (string) $prod['slug'];

// ---- specs
$good = ['product' => $slug, 'spec_key' => 'test_spec_key', 'spec_value' => '42', 'unit' => 'W', 'source_url' => 'https://example.test/spec', 'date_checked' => '2026-11-01', 'review_status' => 'official_checked', 'review_note' => ''];
$rows = [$good, ['spec_key' => 'Bad Key'] + $good, ['spec_value' => ''] + $good, ['source_url' => 'ftp://x'] + $good, ['date_checked' => '2999-01-01'] + $good, ['date_checked' => '2026-02-30'] + $good, ['review_status' => 'maybe'] + $good, ['product' => 'no such model'] + $good, $good];
$plan = S::plan($db, $rows);
ok_si(array_column($plan, 'action') === ['create', 'refused', 'refused', 'refused', 'refused', 'refused', 'refused', 'refused', 'refused'], 'spec rows classified: ' . implode(',', array_column($plan, 'action')));
ok_si(str_contains((string) $plan[8]['error'], 'repeated'), 'a repeated product and key is refused');
$n0 = (int) $db->query('SELECT COUNT(*) FROM product_reference_specs')->fetchColumn();
$db->beginTransaction();
try {
    ok_si(S::apply($db, $plan) === ['created' => 1, 'updated' => 0], 'a spec is created');
    $again = S::plan($db, [['spec_value' => '43'] + $good]);
    ok_si($again[0]['action'] === 'update', 'an existing spec is planned as an update');
    S::apply($db, $again);
    ok_si((int) $db->query("SELECT COUNT(*) FROM product_reference_specs WHERE spec_key='test_spec_key'")->fetchColumn() === 1 && (string) $db->query("SELECT spec_value FROM product_reference_specs WHERE spec_key='test_spec_key'")->fetchColumn() === '43', 'a repeat updates the same row, never duplicates');
} finally { $db->rollBack(); }
ok_si((int) $db->query('SELECT COUNT(*) FROM product_reference_specs')->fetchColumn() === $n0, 'the spec test left nothing behind');

// ---- pictures: name parsing
ok_si(I::parseName('amd-ryzen-5-5600x.jpg')['slug'] === 'amd-ryzen-5-5600x' && I::parseName('amd-ryzen-5-5600x.jpg')['slot'] === 1, 'a plain name is slot 1');
ok_si(I::parseName('amd-ryzen-5-5600x_3.png')['slot'] === 3 && I::parseName('amd-ryzen-5-5600x_3.png')['slug'] === 'amd-ryzen-5-5600x', '_3 is slot 3');
ok_si(I::parseName('amd-ryzen-5-5600x_9.png')['slot'] === 1, '_9 is not a slot, so it stays part of the name');

// ---- pictures: plan and apply with real files
if (!I::tableExists($db)) { echo "spec_image_import_test: PASS (pictures table missing, picture part skipped)\n"; exit(0); }
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ls68-' . bin2hex(random_bytes(4)); mkdir($tmp);
$png = static function (string $path, int $w, int $h): void { $im = imagecreatetruecolor($w, $h); imagepng($im, $path); imagedestroy($im); };
$png("$tmp/$slug.png", 300, 300); $png("$tmp/{$slug}_2.png", 300, 300); $png("$tmp/{$slug}-2.png", 300, 300); $png("$tmp/{$slug}_5.png", 50, 50); $png("$tmp/nomodel-zzz.png", 300, 300);
file_put_contents("$tmp/{$slug}_3.png", 'not an image'); file_put_contents("$tmp/{$slug}_4.png", '');
$files = []; foreach (glob("$tmp/*") as $p) { $files[] = ['name' => basename($p), 'path' => $p]; } usort($files, static fn($a, $b) => strcmp($a['name'], $b['name']));
$meta = ['source_name' => 'Owner photos', 'license_note' => 'Owner permission', 'source_url' => '', 'alt' => 'Test'];
$ip = I::plan($db, $files, $meta); $byName = []; foreach ($ip as $x) { $byName[explode(' ', $x['name'])[0]] = $x; }
ok_si($byName["$slug.png"]['action'] === 'create' && [$byName["{$slug}-2.png"]['action'], $byName["{$slug}_2.png"]['action']] === ['create', 'refused'] && str_contains((string) $byName["{$slug}_2.png"]['error'], 'twice'), 'valid pictures are planned and a slot named twice is refused');
ok_si($byName["{$slug}_5.png"]['action'] === 'refused' && str_contains((string) $byName["{$slug}_5.png"]['error'], '200'), 'a too small picture is refused');
ok_si($byName['nomodel-zzz.png']['action'] === 'refused' && str_contains((string) $byName['nomodel-zzz.png']['error'], 'no model'), 'a file naming no model is refused');
ok_si($byName["{$slug}_3.png"]['action'] === 'refused' && $byName["{$slug}_4.png"]['action'] === 'refused', 'a fake or empty picture is refused by its real type');
ok_si(I::plan($db, $files, ['source_name' => '', 'license_note' => ''] + $meta)[0]['action'] === 'refused', 'a batch without source and licence is refused');
$dest = $tmp . DIRECTORY_SEPARATOR . 'out';
$db->beginTransaction();
try {
    $ready = array_values(array_filter($ip, static fn($x) => $x['data'] !== null && $x['slug'] === $slug));
    $done = I::apply($db, $ip, $dest);
    ok_si($done['created'] >= 2, 'apply writes the valid pictures');
    ok_si(is_file("$dest/$slug-1.png") && is_file("$dest/$slug-2.png"), 'files are copied under the slug-slot name');
    ok_si((string) $db->query("SELECT image_path FROM products WHERE id=" . (int) $prod['id'])->fetchColumn() === "assets/products/$slug-1.png", 'slot 1 becomes the main picture');
    ok_si((int) $db->query("SELECT COUNT(*) FROM product_images WHERE product_id=" . (int) $prod['id'])->fetchColumn() === 2, 'two picture rows exist');
} finally { $db->rollBack(); }
foreach (glob("$dest/*") ?: [] as $f) { @unlink($f); } @rmdir($dest); foreach (glob("$tmp/*") ?: [] as $f) { @unlink($f); } @rmdir($tmp);
echo "spec_image_import_test: PASS\n";
