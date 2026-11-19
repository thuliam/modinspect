<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;

/**
 * LS-15 over real HTTP on the ISOLATED test database: the live preview endpoint (POST /build/preview), the save flow it feeds, and the promise that a
 * saved page shows exactly the numbers the live page showed. Also: CSRF is required, junk input is ignored, an incompatible set cannot be saved
 * (the server decides, not the browser), and the preview writes nothing.
 */
function ok_bh(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

global $config;
$db = Database::connection($config['db']);
ok_bh($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$cpu = $db->query("SELECT p.id, p.full_name, p.socket FROM products p JOIN product_categories c ON c.id = p.category_id WHERE c.name = 'CPU' AND p.is_active = 1 AND p.socket <> '' ORDER BY p.id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$other = $db->prepare("SELECT p.id, p.socket FROM products p JOIN product_categories c ON c.id = p.category_id WHERE c.name = 'Motherboard' AND p.is_active = 1 AND p.socket <> '' AND p.socket <> :s ORDER BY p.id LIMIT 1");
$other->execute(['s' => $cpu['socket']]); $wrongBoard = $other->fetch(PDO::FETCH_ASSOC);
ok_bh($cpu && $wrongBoard, 'The test catalog needs a CPU and a motherboard with a different socket');

$root = dirname(__DIR__);
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
$port = (int)parse_url('tcp://' . stream_socket_get_name($socket, false), PHP_URL_PORT);
fclose($socket);
$lines = [];
foreach (file($root . '/.env.testing', FILE_IGNORE_NEW_LINES) ?: [] as $line) { $lines[] = str_starts_with($line, 'APP_URL=') ? "APP_URL=http://127.0.0.1:$port" : $line; }
$env = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'modinspect-bph-' . bin2hex(random_bytes(4)) . '.env';
file_put_contents($env, implode("\n", $lines) . "\n");
$proc = null; $savedUuid = null;
$req = static function (string $url, string $jar, ?array $post = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $raw = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
    preg_match('/^Location:\s*(.+)$/mi', substr($raw, 0, $hs), $m);
    return ['code' => $code, 'body' => substr($raw, $hs), 'head' => substr($raw, 0, $hs), 'location' => trim($m[1] ?? '')];
};
$rows = static fn() => [(int)$db->query('SELECT COUNT(*) FROM spec_builds')->fetchColumn(), (int)$db->query('SELECT COUNT(*) FROM price_observations')->fetchColumn(), (int)$db->query('SELECT COUNT(*) FROM price_indices')->fetchColumn()];
try {
    $proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $root . '/public'],
        [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/modinspect-bph-server.log', 'a'], 2 => ['file', sys_get_temp_dir() . '/modinspect-bph-server.log', 'a']],
        $pipes, $root, array_merge(getenv() ?: [], ['MODINSPECT_ENV_FILE' => $env]));
    for ($i = 0, $ready = false; $i < 50 && !$ready; $i++) { usleep(100000); $ready = @fsockopen('127.0.0.1', $port, $e1, $e2, 0.2) !== false; }
    ok_bh($ready, 'PHP test server did not start');
    $b = "http://127.0.0.1:$port"; $jar = tempnam(sys_get_temp_dir(), 'bhj');
    @unlink($root . '/storage/cache/ratelimit/build-' . substr(hash('sha256', '127.0.0.1'), 0, 24) . '.json');

    $page = $req("$b/build", $jar);
    ok_bh($page['code'] === 200 && preg_match('/name="_token" value="([^"]+)"/', $page['body'], $m) === 1, 'The Build page loads and carries a CSRF token');
    $token = html_entity_decode($m[1]);
    ok_bh(substr_count($page['body'], 'mi-part-col') >= 8 && str_contains($page['body'], 'id="parts-list"') && str_contains($page['body'], 'id="summary-pane"') && str_contains($page['body'], 'build\/preview'), 'The page has the parts list, the picker cards, the summary pane and the preview address');
    ok_bh(substr_count($page['body'], '<main') === 1, 'The page has exactly one main landmark');

    $before = $rows();
    $r = $req("$b/build/preview", $jar, ['_token' => $token, 'budget' => '25000', 'parts' => ['cpu' => (string)$cpu['id']]]);
    $j = json_decode($r['body'], true);
    ok_bh($r['code'] === 200 && str_contains($r['head'], 'application/json') && str_contains($r['head'], 'no-store') && is_array($j), 'The preview answers JSON that is never cached');
    ok_bh($j['chosen'] === 1 && str_contains($j['summary_html'], htmlspecialchars($cpu['full_name'])) && str_contains($j['summary_html'], 'รายการสเปกและราคา') && is_array($j['candidates']) && array_key_exists('motherboard', $j['candidates']), 'It returns the summary of the chosen part and marks the motherboards that do not fit');
    ok_bh(($j['candidates']['motherboard'][(string)$wrongBoard['id']][0] ?? '') === 'fail', 'A motherboard with another socket is marked "fail" with a reason: ' . json_encode($j['candidates']['motherboard'][(string)$wrongBoard['id']] ?? null, JSON_UNESCAPED_UNICODE));
    ok_bh($rows() === $before, 'The preview wrote nothing (no saved build, observation or snapshot)');

    // input handling
    $none = json_decode($req("$b/build/preview", $jar, ['_token' => $token, 'budget' => 'abc', 'parts' => ['cpu' => 'abc', 'gpu' => '-1', 'case' => '1e3', 'ram' => ['x']]])['body'], true);
    ok_bh($none['chosen'] === 0, 'Non-numeric, negative and array ids are ignored');
    ok_bh($req("$b/build/preview", $jar, ['budget' => '1000'])['code'] === 419 && $req("$b/build/preview", $jar, ['_token' => 'forged'])['code'] === 419, 'No token or a forged token is refused (419)');
    $fresh = tempnam(sys_get_temp_dir(), 'bhj');
    ok_bh($req("$b/build/preview", $fresh, ['_token' => $token])['code'] === 419, 'A token from another session is refused');
    ok_bh($req("$b/build/preview", $jar, [])['code'] === 419 && $req("$b/build/preview", $jar)['code'] === 404, 'An empty POST is refused (419) and a GET is not an endpoint (404)');

    // a blocked set cannot be saved, by the server
    $blocked = json_decode($req("$b/build/preview", $jar, ['_token' => $token, 'budget' => '25000', 'parts' => ['cpu' => (string)$cpu['id'], 'motherboard' => (string)$wrongBoard['id']]])['body'], true);
    ok_bh($blocked['blocked'] === true, 'CPU plus a board with another socket is blocked in the preview');
    $rowsBefore = $rows();
    $save = $req("$b/build", $jar, ['_token' => $token, 'budget' => '25000', 'intended_use' => 'gaming', 'resolution' => '1080p', 'mix_preference' => 'used', 'parts' => ['cpu' => (string)$cpu['id'], 'motherboard' => (string)$wrongBoard['id']]]);
    ok_bh($save['code'] === 200 && $rows() === $rowsBefore, 'The server refuses to save that set even though a browser could post it (no redirect, no row)');

    // a good set saves, and the saved page shows the same numbers as the live page
    $live = json_decode($req("$b/build/preview", $jar, ['_token' => $token, 'budget' => '25000', 'parts' => ['cpu' => (string)$cpu['id']]])['body'], true);
    $save = $req("$b/build", $jar, ['_token' => $token, 'budget' => '25000', 'intended_use' => 'gaming', 'resolution' => '1080p', 'mix_preference' => 'used', 'parts' => ['cpu' => (string)$cpu['id']]]);
    ok_bh($save['code'] === 302 && preg_match('#/build/([0-9a-f-]{36})$#', $save['location'], $um) === 1, 'A good set is saved and redirects to its own page: ' . $save['code'] . ' ' . $save['location']);
    $savedUuid = $um[1];
    $saved = $req("$b/build/$savedUuid", $jar);
    ok_bh($saved['code'] === 200 && str_contains($saved['body'], 'mi-summary') && str_contains($saved['body'], htmlspecialchars($cpu['full_name'])), 'The saved page shows the summary and the chosen part');
    preg_match('/class="mi-summary"[^>]*data-total="([0-9.]+)"/', $live['summary_html'], $lt); preg_match('/class="mi-summary"[^>]*data-total="([0-9.]+)"/', $saved['body'], $st);
    ok_bh(isset($lt[1], $st[1]) && $lt[1] === $st[1], 'The saved page total equals the live total (' . ($lt[1] ?? '?') . ' vs ' . ($st[1] ?? '?') . ')');
    ok_bh($req("$b/build/" . str_repeat('0', 8) . '-0000-0000-0000-' . str_repeat('0', 12), $jar)['code'] === 404, 'An unknown saved build is a 404');

    // the save-time guard (BuildCompatibility::blocked) covers more than the older socket check inside SpecBuilderService: a DDR5 kit with a CPU that
    // supports only DDR4 and NO motherboard chosen. The test database has no reference specs, so two marked rows are added and removed afterwards.
    $ram = (int)$db->query("SELECT p.id FROM products p JOIN product_categories c ON c.id = p.category_id WHERE c.name = 'RAM' AND p.is_active = 1 ORDER BY p.id LIMIT 1")->fetchColumn();
    ok_bh($ram > 0, 'The test catalog needs a RAM product');
    $marker = 'test://build-preview-http-' . bin2hex(random_bytes(3));
    $addSpec = $db->prepare("INSERT INTO product_reference_specs (product_id, spec_key, spec_value, source_url, date_checked, review_status) VALUES (:p, :k, :v, :u, CURDATE(), 'official_checked')");
    $addSpec->execute(['p' => $cpu['id'], 'k' => 'memory_support', 'v' => 'DDR4', 'u' => $marker]);
    $addSpec->execute(['p' => $ram, 'k' => 'memory_type', 'v' => 'DDR5', 'u' => $marker]);
    $mem = json_decode($req("$b/build/preview", $jar, ['_token' => $token, 'budget' => '25000', 'parts' => ['cpu' => (string)$cpu['id'], 'ram' => (string)$ram]])['body'], true);
    ok_bh($mem['blocked'] === true && str_contains($mem['summary_html'], 'DDR5'), 'DDR5 RAM with a DDR4-only CPU is blocked in the preview even with no motherboard chosen');
    $rowsBefore = $rows();
    $save = $req("$b/build", $jar, ['_token' => $token, 'budget' => '25000', 'intended_use' => 'gaming', 'resolution' => '1080p', 'mix_preference' => 'used', 'parts' => ['cpu' => (string)$cpu['id'], 'ram' => (string)$ram]]);
    ok_bh($save['code'] === 200 && $rows() === $rowsBefore, 'The server refuses to save it: the guard that mirrors the preview runs at save time (no redirect, no row)');
} finally {
    if (isset($marker)) { $db->prepare('DELETE FROM product_reference_specs WHERE source_url = :u')->execute(['u' => $marker]); }
    if ($savedUuid !== null) {
        $id = $db->prepare('SELECT id FROM spec_builds WHERE uuid = :u'); $id->execute(['u' => $savedUuid]);
        if ($bid = $id->fetchColumn()) { $db->prepare('DELETE FROM spec_build_items WHERE spec_build_id = :i')->execute(['i' => $bid]); $db->prepare('DELETE FROM spec_builds WHERE id = :i')->execute(['i' => $bid]); }
    }
    if (is_resource($proc)) { proc_terminate($proc); proc_close($proc); }
    @unlink($env);
}
echo "build_preview_http_test: PASS\n";
