<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Catalog\UsedVsNew as U;

/** LS-24 A5: used against new. Pure comparison, and the product page shows it only when both a used median and a recorded new price exist. */
function ok_uv(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$now = new DateTimeImmutable('2026-11-20');
$ref = ['price' => '10000.00', 'source_url' => 'https://shop.example.co.th/p/1', 'checked_on' => '2026-11-15'];
$c = U::compare(7000.0, $ref, $now);
ok_uv($c['diff_percent'] === 30 && $c['direction'] === 'cheaper' && $c['stale'] === false, 'Used 7,000 against new 10,000 is 30% cheaper');
ok_uv(U::compare(11000.0, $ref, $now)['direction'] === 'dearer' && U::compare(10000.0, $ref, $now)['direction'] === 'same', 'Dearer and equal are said as they are');
ok_uv(U::compare(7000.0, array_merge($ref, ['checked_on' => '2026-09-01']), $now)['stale'] === true && U::compare(7000.0, array_merge($ref, ['checked_on' => 'garbage']), $now)['stale'] === true, 'An old or unreadable date is marked stale');
ok_uv(U::compare(null, $ref, $now) === null && U::compare(0.0, $ref, $now) === null && U::compare(7000.0, null, $now) === null && U::compare(7000.0, ['price' => '0'], $now) === null, 'Without both prices nothing is said');

global $config;
$db = Database::connection($config['db']);
ok_uv($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$render = static function (?array $uvn): string { $base = ''; ob_start(); require ROOT_PATH . '/app/Views/components/used-vs-new.php'; return (string)ob_get_clean(); };
ok_uv($render(null) === '', 'No comparison: the block draws nothing');
$html = $render(U::compare(7000.0, $ref, $now));
ok_uv(str_contains($html, 'ต่ำกว่าของใหม่ ฿10,000 ประมาณ 30%') && str_contains($html, 'ไม่ได้เป็นส่วนหนึ่งของช่วงราคามือสอง') && str_contains($html, 'rel="noopener nofollow"') && !str_contains($html, 'ควรซื้อ'), 'The block states the difference, says it is only a reference, and recommends nothing');
ok_uv(!str_contains($render(U::compare(7000.0, array_merge($ref, ['source_url' => 'javascript:alert(1)']), $now)), 'javascript:'), 'A non-http link is never printed');
ok_uv(str_contains($render(U::compare(7000.0, array_merge($ref, ['checked_on' => '2026-01-01']), $now)), 'นานกว่า 30 วันแล้ว'), 'A stale new price says so');
ok_uv(str_contains($render(U::compare(12000.0, $ref, $now)), 'สูงกว่าราคาของใหม่'), 'A used price above new is said plainly');

// the model reads the latest recorded new price (test DB, rolled back); the page is wired to it
$db->beginTransaction();
try {
    $pid = (int)$db->query('SELECT id FROM products WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
    $m = new \App\Models\Product();
    ok_uv($m->latestNewPrice($pid) === null, 'No new price recorded: null');
    $db->exec("INSERT INTO users (name, email, password_hash, role) VALUES ('uvn', 'uvn-" . bin2hex(random_bytes(3)) . "@example.test', 'x', 'admin')"); $u = (int)$db->lastInsertId();
    $ins = $db->prepare("INSERT INTO new_price_references (product_id, price, source_url, checked_on, created_by) VALUES (:p, :pr, 'https://shop.example.co.th/p', :d, :u)");
    $ins->execute(['p' => $pid, 'pr' => 111, 'd' => '2026-11-01', 'u' => $u]); $ins->execute(['p' => $pid, 'pr' => 222, 'd' => '2026-11-10', 'u' => $u]);
    ok_uv((float)$m->latestNewPrice($pid)['price'] === 222.0, 'The latest check date wins');
} finally {
    $db->rollBack();
}
$ctl = (string)file_get_contents(ROOT_PATH . '/app/Controllers/PriceController.php') . (string)file_get_contents(ROOT_PATH . '/app/Views/price/show.php');
ok_uv(str_contains($ctl, 'UsedVsNew::compare') && str_contains($ctl, 'used-vs-new.php') && str_contains($ctl, "UsedVsNew::compare(!empty(\$product['median']) && (int)(\$product['accepted_observation_count']??0)>0"), 'The page compares only when its own price rule holds (accepted evidence behind the median)');
echo "used_vs_new_test: PASS\n";
