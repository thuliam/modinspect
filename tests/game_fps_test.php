<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Models\Product;
use App\Services\Catalog\GameFps as G;
use App\Services\Catalog\GameFpsImport as I;

/** LS-59: in-game FPS panel. Only CPU/GPU pages get it; rows group by resolution and game order; unknown games are dropped; no rows shows an honest empty panel; a sponsor is always labelled; a database test, rolled back. */
function ok_gf(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

ok_gf(G::supports('cpu') && G::supports('GPU') && !G::supports('ram') && !G::supports(null), 'only cpu and gpu support the panel');
$rows = [
    ['game_key' => 'cs2', 'resolution' => '1080p', 'avg_fps' => '300.0'], ['game_key' => 'cyberpunk2077', 'resolution' => '1080p', 'avg_fps' => '90.0'],
    ['game_key' => 'nope', 'resolution' => '1080p', 'avg_fps' => '1'], ['game_key' => 'cod', 'resolution' => '2160p', 'avg_fps' => '70.0'], ['game_key' => 'cod', 'resolution' => '1440p', 'avg_fps' => '120.0'],
];
$g = G::group($rows);
ok_gf(array_keys($g) === ['1080p', '1440p', '2160p'] && array_column($g['1080p'], 'game_key') === ['cyberpunk2077', 'cs2'], 'grouped by resolution then game order, unknown game dropped');

$render = static function (?array $fps, string $cat): string { $product = ['category_slug' => $cat]; $gameFps = $fps; ob_start(); require ROOT_PATH . '/app/Views/components/game-fps.php'; return (string) ob_get_clean(); };
ok_gf($render(null, 'ram') === '', 'a non CPU/GPU page draws nothing');
$empty = $render([], 'gpu');
ok_gf(str_contains($empty, 'mi-fps-empty') && str_contains($empty, 'Cyberpunk 2077') && str_contains($empty, 'ซีพียูที่ใช้คู่กัน') && !str_contains($empty, 'progress-bar'), 'no rows: an empty panel naming the games, no invented numbers');

global $config;
$db = Database::connection($config['db']);
ok_gf($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$pid = (int) $db->query("SELECT p.id FROM products p JOIN product_categories c ON c.id=p.category_id WHERE LOWER(c.slug)='gpu' LIMIT 1")->fetchColumn();
ok_gf($pid > 0, 'the test database has a GPU');
$db->beginTransaction();
try {
    $ins = $db->prepare("INSERT INTO game_fps_results (product_id, game_key, resolution, quality, avg_fps, low1_fps, paired_part, source_name, source_url, license_note, sponsor_name, retrieved_on) VALUES (:p, :g, :r, 'High', :a, :l, 'Ryzen 5 5600', 'Test Lab', 'https://example.test/fps', 'CC BY', :s, CURDATE())");
    $ins->execute(['p' => $pid, 'g' => 'cs2', 'r' => '1080p', 'a' => 240.5, 'l' => 180, 's' => 'ACME Games']);
    $ins->execute(['p' => $pid, 'g' => 'cod', 'r' => '1440p', 'a' => 100, 'l' => null, 's' => null]);
    $got = (new Product())->gameFps($pid);
    ok_gf(count($got) === 2, 'the model returns the stored rows');
    $html = $render($got, 'gpu');
    ok_gf(str_contains($html, '240.5') && str_contains($html, '1% low 180') && str_contains($html, 'คู่กับ Ryzen 5 5600') && str_contains($html, 'ACME Games') && str_contains($html, 'Test Lab') && substr_count($html, 'progress-bar') === 2 && str_contains($html, 'data-toggle="pill"'), 'rows draw bars, pairing, source, sponsor and resolution tabs');
    // LS-60: the importer shared by the admin form and the CLI
    $good = ['product' => 'x', 'game' => 'Counter-Strike 2', 'resolution' => '1080', 'quality' => 'High', 'avg_fps' => '301.26', 'low1_fps' => '200', 'paired_part' => 'RTX 4070', 'source_name' => 'Lab', 'source_url' => 'https://example.test/a', 'license_note' => 'CC BY', 'sponsor_name' => '', 'tested_on' => '2026-11-01'];
    $c = I::clean($good);
    ok_gf($c['data'] !== null && $c['data']['game_key'] === 'cs2' && $c['data']['resolution'] === '1080p' && $c['data']['avg_fps'] === 301.3 && $c['data']['sponsor_name'] === null, 'a clean row maps names and aliases');
    foreach ([['game' => 'Doom'], ['resolution' => '720p'], ['avg_fps' => '0'], ['avg_fps' => 'x'], ['low1_fps' => '400'], ['paired_part' => ''], ['source_url' => 'javascript:alert(1)'], ['license_note' => ''], ['tested_on' => '01/11/2026']] as $bad) {
        ok_gf(I::clean($bad + $good)['data'] === null, 'refused: ' . json_encode($bad));
    }
    $plist = I::products($db);
    $gpuName = (string) $db->query("SELECT full_name FROM products WHERE id=$pid")->fetchColumn(); $gpuSlug = (string) $db->query("SELECT slug FROM products WHERE id=$pid")->fetchColumn();
    ok_gf(I::matchProduct($gpuSlug, $plist)['id'] === $pid && I::matchProduct($gpuName, $plist)['id'] === $pid && I::matchProduct('no such card', $plist)['id'] === null && I::matchProduct('', $plist)['error'] !== null, 'products match by slug or exact name, never guessed');
    I::store($db, $pid, $c['data']); I::store($db, $pid, ['avg_fps' => 111.0] + $c['data']);
    $n = (int) $db->query("SELECT COUNT(*) FROM game_fps_results WHERE product_id=$pid AND game_key='cs2' AND paired_part='RTX 4070'")->fetchColumn();
    ok_gf($n === 1 && (float) $db->query("SELECT avg_fps FROM game_fps_results WHERE product_id=$pid AND game_key='cs2' AND paired_part='RTX 4070'")->fetchColumn() === 111.0, 'a repeat load updates the same row, never duplicates');
    $tmp = tempnam(sys_get_temp_dir(), 'fps') . '.csv'; file_put_contents($tmp, "\xEF\xBB\xBFproduct,game,resolution\n$gpuSlug,cod,4k\n");
    $csv = I::readFile($tmp); unlink($tmp);
    ok_gf(count($csv) === 1 && $csv[0]['game'] === 'cod' && $csv[0]['product'] === $gpuSlug, 'a CSV with a BOM is read by header name');
} finally {
    $db->rollBack();
}
echo "game_fps_test: PASS\n";
