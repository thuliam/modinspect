<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Seo\SeoService;

/**
 * LS-21 (R-11) over real HTTP on the ISOLATED test database. Off by default (every route 404, nothing links to it), still off when enabled without a real key,
 * and when on and keyed: a form per report type with choices and numbers only (no text box, no contact field), CSRF, a honeypot, a rate limit, and a report
 * that changes no price. The Admin queue refuses a visitor.
 */
function ok_ch(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$root = dirname(__DIR__);
global $config;
$db = Database::connection($config['db']);
ok_ch($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$reset = static function () use ($root): void { foreach (glob($root . '/storage/cache/ratelimit/community-report-*.json') ?: [] as $f) { @unlink($f); } };
$reset();
$maxId = (int)$db->query('SELECT COALESCE(MAX(id), 0) FROM community_reports')->fetchColumn();
$slug = (string)$db->query("SELECT slug FROM products WHERE is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
ok_ch($slug !== '', 'The test catalog has a product');
$priceState = static function () use ($db): string { return json_encode([$db->query('SELECT COUNT(*), COALESCE(MAX(id), 0), COALESCE(SUM(median), 0) FROM price_indices')->fetch(PDO::FETCH_NUM), $db->query("SELECT COUNT(*), COALESCE(SUM(verified_status = 'approved'), 0) FROM price_observations")->fetch(PDO::FETCH_NUM)]); };

function cr_server(string $root, array $extra): array
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    $port = (int)parse_url('tcp://' . stream_socket_get_name($socket, false), PHP_URL_PORT);
    fclose($socket);
    $lines = [];
    foreach (file($root . '/.env.testing', FILE_IGNORE_NEW_LINES) ?: [] as $line) { $lines[] = str_starts_with($line, 'APP_URL=') ? "APP_URL=http://127.0.0.1:$port" : $line; }
    foreach ($extra as $k => $v) { $lines[] = "$k=$v"; }
    $env = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'modinspect-cr-' . bin2hex(random_bytes(4)) . '.env';
    file_put_contents($env, implode("\n", $lines) . "\n");
    if (!str_contains((string)file_get_contents($env), 'DB_DATABASE=modinspect_test')) { throw new RuntimeException('The server must run on the isolated test database'); }
    $proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $root . '/public'],
        [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/modinspect-cr-server.log', 'a'], 2 => ['file', sys_get_temp_dir() . '/modinspect-cr-server.log', 'a']],
        $pipes, $root, array_merge(getenv() ?: [], ['MODINSPECT_ENV_FILE' => $env]));
    for ($i = 0, $ready = false; $i < 50 && !$ready; $i++) { usleep(100000); $ready = @fsockopen('127.0.0.1', $port, $e1, $e2, 0.2) !== false; }
    if (!$ready) { throw new RuntimeException('PHP test server did not start'); }
    return [$proc, $port, $env];
}
function cr_req(string $url, string $jar, ?array $post = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $raw = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
    preg_match('/^Location:\s*(.+)$/mi', substr($raw, 0, $hs), $m);
    return ['code' => $code, 'body' => substr($raw, $hs), 'location' => trim($m[1] ?? '')];
}

$servers = [];
try {
    $before = $priceState();
    // 1) default: off
    [$p, $port, $env] = cr_server($root, []); $servers[] = [$p, $env]; $b = "http://127.0.0.1:$port"; $jar = tempnam(sys_get_temp_dir(), 'crj');
    ok_ch(cr_req("$b/report/$slug", $jar)['code'] === 404 && cr_req("$b/report/$slug", $jar, ['type' => 'stale'])['code'] === 404, 'The form and its POST are 404 by default');
    ok_ch(!str_contains(cr_req("$b/price/$slug", $jar)['body'], '/report/'), 'The product page does not link to it');
    ok_ch(cr_req("$b/admin/community-reports", $jar)['code'] === 302, 'The Admin queue needs a sign-in');
    proc_terminate($p); proc_close($p);

    // 2) enabled but no real key: still off
    [$p, $port, $env] = cr_server($root, ['COMMUNITY_REPORTS_ENABLED' => 'true', 'COMMUNITY_REPORTS_HMAC_KEY' => 'tooshort']); $servers[] = [$p, $env]; $b = "http://127.0.0.1:$port"; $jar = tempnam(sys_get_temp_dir(), 'crj');
    ok_ch(cr_req("$b/report/$slug", $jar)['code'] === 404 && !str_contains(cr_req("$b/price/$slug", $jar)['body'], '/report/'), 'Enabled without a real key it stays 404 and unlinked');
    proc_terminate($p); proc_close($p);

    // 3) enabled and keyed
    [$p, $port, $env] = cr_server($root, ['COMMUNITY_REPORTS_ENABLED' => 'true', 'COMMUNITY_REPORTS_HMAC_KEY' => str_repeat('k', 48)]); $servers[] = [$p, $env]; $b = "http://127.0.0.1:$port"; $jar = tempnam(sys_get_temp_dir(), 'crj');
    ok_ch(str_contains(cr_req("$b/price/$slug", $jar)['body'], "/report/$slug"), 'The product page links to the form when it is on');
    ok_ch(cr_req("$b/report/no-such-model", $jar)['code'] === 404, 'An unknown model is 404');
    $menu = cr_req("$b/report/$slug", $jar);
    ok_ch($menu['code'] === 200 && substr_count($menu['body'], '?type=') >= 5 && str_contains($menu['body'], 'ราคาที่ขายได้จริง'), 'The chooser lists the five report types');
    foreach (['wrong_product', 'price_looks_off', 'stale', 'sold_price', 'spec_wrong'] as $t) {
        $f = cr_req("$b/report/$slug?type=$t", $jar);
        ok_ch($f['code'] === 200 && str_contains($f['body'], 'name="_token"') && str_contains($f['body'], 'name="type" value="' . $t . '"') && str_contains($f['body'], 'name="website"'), "The $t form has a token, its type and the honeypot");
        ok_ch(!preg_match('/<textarea|type="(email|tel|url|file|password)"/i', $f['body']) && substr_count($f['body'], 'type="text"') === 1 && !preg_match('/name="(name|phone|email|contact|note|comment|message|address)"/i', $f['body']), "The $t form has no text box, no contact field and no upload (only the hidden honeypot is text)");
    }
    ok_ch(cr_req("$b/report/$slug?type=nonsense", $jar)['code'] === 200 && !str_contains(cr_req("$b/report/$slug?type=nonsense", $jar)['body'], 'name="type"'), 'An unknown type shows the chooser again');
    $token = static function (string $body): string { preg_match('/name="_token" value="([^"]+)"/', $body, $m); return html_entity_decode($m[1] ?? ''); };
    $tok = $token(cr_req("$b/report/$slug?type=stale", $jar)['body']);
    ok_ch($tok !== '', 'A CSRF token is issued');

    ok_ch(cr_req("$b/report/$slug", $jar, ['type' => 'stale'])['code'] === 419 && cr_req("$b/report/$slug", $jar, ['type' => 'stale', '_token' => 'forged'])['code'] === 419, 'A POST without a valid token is refused (419)');
    $n0 = (int)$db->query('SELECT COUNT(*) FROM community_reports')->fetchColumn();
    $ok = cr_req("$b/report/$slug", $jar, ['_token' => $tok, 'type' => 'stale', 'website' => '']);
    ok_ch($ok['code'] === 200 && str_contains($ok['body'], 'ได้รับรายงานแล้ว') && str_contains($ok['body'], 'ไม่เปลี่ยนราคา'), 'A valid report gets the acknowledgement that says it changes no price');
    ok_ch((int)$db->query('SELECT COUNT(*) FROM community_reports')->fetchColumn() === $n0 + 1, 'It was stored');
    $row = $db->query('SELECT * FROM community_reports ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    ok_ch($row['status'] === 'new' && $row['report_type'] === 'stale' && preg_match('/^[0-9a-f]{32}$/', $row['reporter_hash']) === 1 && !str_contains(json_encode($row), '127.0.0.1'), 'Stored as new with the anonymous code only');
    // a DIFFERENT report from the first one (an identical one would be de-duplicated even without the honeypot and prove nothing)
    $bot = cr_req("$b/report/$slug", $jar, ['_token' => $tok, 'type' => 'spec_wrong', 'spec' => 'cores', 'website' => 'http://spam.example']);
    ok_ch($bot['code'] === 200 && str_contains($bot['body'], 'ได้รับรายงานแล้ว') && (int)$db->query('SELECT COUNT(*) FROM community_reports')->fetchColumn() === $n0 + 1, 'A filled honeypot gets the normal thank-you page and nothing is stored');
    $bad = cr_req("$b/report/$slug", $jar, ['_token' => $tok, 'type' => 'sold_price', 'price' => 'abc', 'month' => '2020-01', 'condition' => 'new', 'website' => '']);
    ok_ch($bad['code'] === 200 && str_contains($bad['body'], 'alert-danger') && str_contains($bad['body'], 'ราคาต้องเป็นตัวเลข') && (int)$db->query('SELECT COUNT(*) FROM community_reports')->fetchColumn() === $n0 + 1, 'A bad field shows a plain explanation and stores nothing');
    $sold = cr_req("$b/report/$slug", $jar, ['_token' => $tok, 'type' => 'sold_price', 'price' => '7500', 'month' => date('Y-m'), 'condition' => 'good', 'warranty_months' => '3', 'has_receipt' => '1', 'website' => '', 'note' => 'call 0812345678', 'email' => 'a@b.c']);
    ok_ch($sold['code'] === 200 && str_contains($sold['body'], 'ได้รับรายงานแล้ว'), 'A sold-price report is accepted');
    $soldRow = $db->query('SELECT payload FROM community_reports WHERE report_type = \'sold_price\' ORDER BY id DESC LIMIT 1')->fetchColumn();
    ok_ch(!str_contains((string)$soldRow, '0812345678') && !str_contains((string)$soldRow, 'a@b.c') && json_decode((string)$soldRow, true)['price'] === 7500, 'Extra fields posted by hand (a phone, an email) are not stored');
    ok_ch($priceState() === $before, 'SIGNAL ONLY: no price, snapshot or observation changed by any of it');
    $last = null;
    for ($i = 0; $i < 6; $i++) { $last = cr_req("$b/report/$slug", $jar, ['_token' => $tok, 'type' => 'stale', 'website' => '']); if ($last['code'] === 429) { break; } }
    ok_ch($last['code'] === 429, 'More than 5 posts in an hour from one address are rate limited');
    ok_ch(cr_req("$b/admin/community-reports", $jar)['code'] === 302 && cr_req("$b/admin/community-reports/action", $jar, ['id' => 1, 'decision' => 'accepted'])['code'] === 302, 'A visitor cannot reach the queue or decide a report');
    ok_ch(!str_contains(cr_req("$b/sitemap.xml", $jar)['body'] . cr_req("$b/", $jar)['body'], '/report/'), 'Neither the sitemap nor the home page lists it');
} finally {
    foreach ($servers as [$p, $e]) { if (is_resource($p)) { @proc_terminate($p); @proc_close($p); } @unlink($e); }
    $db->prepare('DELETE FROM community_reports WHERE id > :m')->execute(['m' => $maxId]);
    $reset();
}
ok_ch(str_contains(SeoService::robotsTxt('https://x.test', false), 'Disallow: /report'), 'robots.txt keeps the report form out of search');
echo "community_reports_http_test: PASS\n";
