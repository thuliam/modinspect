<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/**
 * LS-14 Module 8 over real HTTP on the ISOLATED test database. The feature is off by default (every route 404, nothing linked, not in the sitemap),
 * it stays off when enabled without a real key, and when enabled and keyed the lookup answers with a count only, never echoes the number, is rate
 * limited, and needs a member (and a CSRF token) to report. Admin queue routes refuse a visitor.
 */
function ok_sh(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$root = dirname(__DIR__);
// this test fills the lookup bucket on purpose: start from empty buckets (only the files of this feature, for 127.0.0.1) and leave them empty
$sh_reset = static function () use ($root): void { foreach (['seller-check-lookup', 'seller-check-report', 'seller-check-dispute'] as $bk) { @unlink($root . '/storage/cache/ratelimit/' . $bk . '-' . substr(hash('sha256', '127.0.0.1'), 0, 24) . '.json'); } };
$sh_reset();
function sh_server(string $root, array $extra): array
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    $port = (int)parse_url('tcp://' . stream_socket_get_name($socket, false), PHP_URL_PORT);
    fclose($socket);
    $lines = [];
    foreach (file($root . '/.env.testing', FILE_IGNORE_NEW_LINES) ?: [] as $line) { $lines[] = str_starts_with($line, 'APP_URL=') ? "APP_URL=http://127.0.0.1:$port" : $line; }
    foreach ($extra as $k => $v) { $lines[] = "$k=$v"; }
    $env = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'modinspect-sh-' . bin2hex(random_bytes(4)) . '.env';
    file_put_contents($env, implode("\n", $lines) . "\n");
    if (!str_contains((string)file_get_contents($env), 'DB_DATABASE=modinspect_test')) { throw new RuntimeException('The server must run on the isolated test database'); }
    $proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $root . '/public'],
        [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/modinspect-sh-server.log', 'a'], 2 => ['file', sys_get_temp_dir() . '/modinspect-sh-server.log', 'a']],
        $pipes, $root, array_merge(getenv() ?: [], ['MODINSPECT_ENV_FILE' => $env]));
    for ($i = 0, $ready = false; $i < 50 && !$ready; $i++) { usleep(100000); $ready = @fsockopen('127.0.0.1', $port, $e1, $e2, 0.2) !== false; }
    if (!$ready) { throw new RuntimeException('PHP test server did not start'); }
    return [$proc, $port, $env];
}
function sh_req(string $url, string $jar, ?array $post = null): array
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
    $routes = ['/seller-check', '/seller-check/report', '/seller-check/dispute'];
    // 1) default: off
    [$p, $port, $env] = sh_server($root, []); $servers[] = [$p, $env]; $b = "http://127.0.0.1:$port"; $jar = tempnam(sys_get_temp_dir(), 'shj');
    foreach ($routes as $r) { ok_sh(sh_req($b . $r, $jar)['code'] === 404, "$r is 404 by default"); }
    ok_sh(!str_contains(sh_req("$b/", $jar)['body'], 'seller-check') && !str_contains(sh_req("$b/sitemap.xml", $jar)['body'], 'seller-check'), 'Nothing links to it and the sitemap does not list it');
    ok_sh(sh_req("$b/admin/seller-reports", $jar)['code'] === 302, 'The admin queue needs a sign-in');
    proc_terminate($p); proc_close($p);

    // 2) enabled but no real key: still off
    [$p, $port, $env] = sh_server($root, ['SELLER_CHECK_ENABLED' => 'true', 'SELLER_CHECK_HMAC_KEY' => 'tooshort']); $servers[] = [$p, $env]; $b = "http://127.0.0.1:$port"; $jar = tempnam(sys_get_temp_dir(), 'shj');
    foreach ($routes as $r) { ok_sh(sh_req($b . $r, $jar)['code'] === 404, "$r stays 404 when enabled without a real key"); }
    proc_terminate($p); proc_close($p);

    // 3) enabled and keyed
    [$p, $port, $env] = sh_server($root, ['SELLER_CHECK_ENABLED' => 'true', 'SELLER_CHECK_HMAC_KEY' => str_repeat('k', 48)]); $servers[] = [$p, $env]; $b = "http://127.0.0.1:$port"; $jar = tempnam(sys_get_temp_dir(), 'shj');
    $page = sh_req("$b/seller-check", $jar);
    ok_sh($page['code'] === 200 && str_contains($page['body'], 'เช็กเบอร์หรือบัญชีผู้ขาย'), 'The lookup page is there when on and keyed');
    $res = sh_req("$b/seller-check?type=phone&q=0899990001", $jar);
    ok_sh($res['code'] === 200 && str_contains($res['body'], 'ยังไม่พบรายงานที่ตรวจแล้ว') && str_contains($res['body'], 'ไม่ได้แปลว่าปลอดภัย') && !str_contains($res['body'], '0899990001'), 'A clean number: honest "not found" wording (not "safe"), and the number is not echoed back');
    ok_sh(!preg_match('/โกง|มิจฉาชีพ|scam/iu', $res['body']), 'No accusation words on the page');
    ok_sh(str_contains(sh_req("$b/seller-check?type=phone&q=123", $jar)['body'], 'หมายเลขไม่ถูกต้อง'), 'A bad number is explained, not searched');
    $last = null;
    for ($i = 0; $i < 12; $i++) { $last = sh_req("$b/seller-check?type=phone&q=08999900" . str_pad((string)$i, 2, '0', STR_PAD_LEFT), $jar); }
    ok_sh($last['code'] === 429, 'More than 10 lookups in 10 minutes from one address are rate limited');
    ok_sh(sh_req("$b/seller-check/report", $jar)['code'] === 302 && str_ends_with(sh_req("$b/seller-check/report", $jar)['location'], '/account/login'), 'Reporting needs a member');
    ok_sh(sh_req("$b/seller-check/report", $jar, ['_token' => 'x', 'q' => '0812345678'])['code'] === 302, 'A report POST without a member goes to sign-in');
    $d = sh_req("$b/seller-check/dispute", $jar);
    ok_sh($d['code'] === 200 && str_contains($d['body'], 'name="email"'), 'The dispute form is open to anyone');
    ok_sh(sh_req("$b/seller-check/dispute", $jar, ['_token' => 'forged', 'type' => 'phone', 'q' => '0812345678', 'explanation' => 'ไม่ใช่เบอร์ของฉันครับ', 'email' => 'a@example.com'])['code'] === 302, 'A dispute with a forged CSRF token is not accepted');
} finally {
    foreach ($servers as [$p, $e]) { if (is_resource($p)) { @proc_terminate($p); @proc_close($p); } @unlink($e); }
    $sh_reset();
}
echo "seller_check_http_test: PASS\n";
