<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/**
 * LS-14 3.4: cli/smoke_check.php must pass on a healthy site and FAIL (exit 1) on a broken one, otherwise it proves nothing after a deploy.
 * Healthy = the app on the isolated test database; broken = the same server asked for a base path where nothing lives (every page 404).
 */
function ok_sm(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$root = dirname(__DIR__);
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
$port = (int)parse_url('tcp://' . stream_socket_get_name($socket, false), PHP_URL_PORT);
fclose($socket);
$lines = [];
foreach (file($root . '/.env.testing', FILE_IGNORE_NEW_LINES) ?: [] as $line) { $lines[] = str_starts_with($line, 'APP_URL=') ? "APP_URL=http://127.0.0.1:$port" : $line; }
$env = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'modinspect-sm-' . bin2hex(random_bytes(4)) . '.env';
file_put_contents($env, implode("\n", $lines) . "\n");
ok_sm(str_contains((string)file_get_contents($env), 'DB_DATABASE=modinspect_test'), 'The server must run on the isolated test database');
$proc = null;
try {
    $proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $root . '/public'],
        [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/modinspect-sm-server.log', 'a'], 2 => ['file', sys_get_temp_dir() . '/modinspect-sm-server.log', 'a']],
        $pipes, $root, array_merge(getenv() ?: [], ['MODINSPECT_ENV_FILE' => $env]));
    for ($i = 0, $ready = false; $i < 50 && !$ready; $i++) { usleep(100000); $ready = @fsockopen('127.0.0.1', $port, $e1, $e2, 0.2) !== false; }
    ok_sm($ready, 'PHP test server did not start');
    $run = static function (string $base) use ($root): array { exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/cli/smoke_check.php') . ' ' . escapeshellarg($base) . ' 2>&1', $out, $code); return [$code, implode("\n", $out)]; };

    [$code, $out] = $run("http://127.0.0.1:$port");
    // the built-in server answers a few paths differently from Apache (dotted URLs), so only the pages we care about are asserted here
    ok_sm(str_contains($out, 'ok    GET /price is 200') && str_contains($out, 'ok    /admin sends a visitor to the login') && str_contains($out, 'ok    /.env is not served'), 'The healthy site passes the page, admin and secret checks: ' . $out);
    ok_sm(str_contains($out, 'ok    HEAD /price is 200'), 'HEAD is answered like GET (LS-16 B-1, checked by LS-17 A1): ' . $out);
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/cli/smoke_check.php') . ' ' . escapeshellarg("http://127.0.0.1:$port") . ' --links 2>&1', $lo);
    $lo = implode("\n", $lo);
    ok_sm(preg_match('/ok    every internal link answers \((\d+) checked\)/', $lo, $lm) === 1 && (int)$lm[1] >= 20, 'With --links every internal link of the key pages and the sitemap answers, and enough were found to mean something: ' . $lo);
    [$badCode, $bad] = $run("http://127.0.0.1:$port/nowhere");
    ok_sm($badCode === 1 && str_contains($bad, 'FAIL  GET /price is 200') && str_contains($bad, 'SMOKE FAIL'), 'A site where nothing lives fails with exit 1: ' . $bad);
    [$usage] = $run('not a url');
    ok_sm($usage === 2, 'A bad argument exits 2');
    ok_sm(!str_contains($out . $bad, 'password') && !str_contains(strtolower($out . $bad), 'cookie'), 'The script prints no credentials or cookies');
} finally {
    if (is_resource($proc)) { proc_terminate($proc); proc_close($proc); }
    @unlink($env);
}
echo "smoke_check_test: PASS\n";
