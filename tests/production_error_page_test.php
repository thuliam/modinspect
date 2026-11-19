<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/**
 * LS-14 3.3: in production mode an unexpected failure must answer a generic 500 and leak nothing. The server runs with APP_ENV=production and a
 * database password that is wrong on purpose, so every database page throws. The response must carry no SQL state, host, user name, file path,
 * stack trace or the secret itself, and the real cause goes to the server log only. Any page, including an unknown address, is the same clean 500 while the database is down.
 */
function ok_pe(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$root = dirname(__DIR__);
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
$port = (int)parse_url('tcp://' . stream_socket_get_name($socket, false), PHP_URL_PORT);
fclose($socket);
$secret = 'WRONG-PASSWORD-' . bin2hex(random_bytes(6));
$lines = [];
foreach (file($root . '/.env.testing', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    if (str_starts_with($line, 'APP_URL=')) { $line = "APP_URL=http://127.0.0.1:$port"; }
    if (str_starts_with($line, 'APP_ENV=') || str_starts_with($line, 'DB_PASSWORD=')) { continue; }
    $lines[] = $line;
}
$lines[] = 'APP_ENV=production'; $lines[] = "DB_PASSWORD=$secret";
$env = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'modinspect-pe-' . bin2hex(random_bytes(4)) . '.env';
file_put_contents($env, implode("\n", $lines) . "\n");
$log = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'modinspect-pe-' . bin2hex(random_bytes(4)) . '.log';
$proc = null;
try {
    $proc = proc_open([PHP_BINARY, '-d', 'error_log=' . $log, '-S', "127.0.0.1:$port", '-t', $root . '/public'],
        [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/modinspect-pe-server.log', 'a'], 2 => ['file', sys_get_temp_dir() . '/modinspect-pe-server.log', 'a']],
        $pipes, $root, array_merge(getenv() ?: [], ['MODINSPECT_ENV_FILE' => $env]));
    for ($i = 0, $ready = false; $i < 50 && !$ready; $i++) { usleep(100000); $ready = @fsockopen('127.0.0.1', $port, $e1, $e2, 0.2) !== false; }
    ok_pe($ready, 'PHP test server did not start');
    $get = static function (string $path) use ($port): array {
        $ch = curl_init("http://127.0.0.1:$port$path");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 30]);
        $raw = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
        return ['code' => $code, 'head' => substr($raw, 0, $hs), 'body' => substr($raw, $hs)];
    };
    $dbUser = '';
    foreach ($lines as $l) { if (str_starts_with($l, 'DB_USERNAME=')) { $dbUser = substr($l, 12); } }
    foreach (['/price', '/'] as $path) {
        $r = $get($path);
        ok_pe($r['code'] === 500, "$path answers 500 when the database is unreachable, got {$r['code']}");
        $all = $r['head'] . $r['body'];
        ok_pe(!str_contains($all, $secret), "$path does not show the password");
        ok_pe(!preg_match('/SQLSTATE|PDOException|Stack trace|#0 |\.php:\d+|on line \d+|Fatal error|Uncaught/', $all), "$path shows no SQL state, trace or file location");
        ok_pe(!str_contains($all, str_replace('\\', '/', $root)) && !str_contains($all, $root), "$path shows no server path");
        ok_pe($dbUser === '' || !str_contains($all, "'$dbUser'@"), "$path shows no database user");
        ok_pe(str_contains($r['body'], 'ระบบขัดข้อง'), "$path shows the generic Thai failure page");
        ok_pe(!preg_match('/X-Powered-By/i', $r['head']), "$path does not announce PHP");
    }
    usleep(200000);
    $logged = is_file($log) ? (string)file_get_contents($log) : '';
    ok_pe(str_contains($logged, '[modinspect]') && !str_contains($logged, $secret), 'The real cause is in the server log, and even the log does not contain the password');
    // with the database down even the 404 page cannot render (the header looks up the signed-in user): it must be the same clean 500, not a PHP error
    $nf = $get('/this-page-does-not-exist');
    ok_pe($nf['code'] === 500 && !str_contains($nf['head'] . $nf['body'], $root) && !str_contains($nf['body'], 'Warning') && !str_contains($nf['head'] . $nf['body'], $secret), 'An unknown address with the database down is also the clean generic 500');
} finally {
    if (is_resource($proc)) { proc_terminate($proc); proc_close($proc); }
    @unlink($env); @unlink($log);
}
echo "production_error_page_test: PASS\n";
