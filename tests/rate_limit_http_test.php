<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/**
 * Real-HTTP proof (LS-3 J-4) on the ISOLATED test database: the public Deal Checker POST allows 20 requests per client and answers the 21st with
 * 429 + Retry-After, the Build POST allows 30, other pages are never limited, and the refusal page leaks nothing. Requests carry no CSRF token on purpose:
 * the limiter sits in front of the CSRF check, so refused-before-CSRF and 419-after-CSRF are both visible and nothing is ever written.
 */
function ok_rh(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$root = dirname(__DIR__);
$limiterDir = $root . '/storage/cache/ratelimit';
$clientFile = static fn(string $bucket): string => $limiterDir . '/' . $bucket . '-' . substr(hash('sha256', '127.0.0.1'), 0, 24) . '.json';
$reset = static function () use ($clientFile): void { foreach (['deal-checker', 'build'] as $b) { @unlink($clientFile($b)); } };
$reset();

$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
ok_rh($socket !== false, 'No free port for the test server');
$port = (int)parse_url('tcp://' . stream_socket_get_name($socket, false), PHP_URL_PORT);
fclose($socket);
$base = "http://127.0.0.1:{$port}";
$envLines = [];
foreach (file($root . '/.env.testing', FILE_IGNORE_NEW_LINES) ?: [] as $line) { $envLines[] = str_starts_with($line, 'APP_URL=') ? "APP_URL={$base}" : $line; }
$envFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'modinspect-rl-' . bin2hex(random_bytes(4)) . '.env';
file_put_contents($envFile, implode("\n", $envLines) . "\n");
ok_rh(str_contains((string)file_get_contents($envFile), 'DB_DATABASE=modinspect_test'), 'The server must run on the isolated test database');
$process = null;
$post = static function (string $path) use ($base): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20, CURLOPT_POST => true, CURLOPT_POSTFIELDS => ['_token' => 'none']]);
    $raw = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
    return [$code, substr($raw, 0, $hs), substr($raw, $hs)];
};
try {
    $process = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $root . '/public'],
        [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/modinspect-rl-server.log', 'a'], 2 => ['file', sys_get_temp_dir() . '/modinspect-rl-server.log', 'a']],
        $pipes, $root, array_merge(getenv() ?: [], ['MODINSPECT_ENV_FILE' => $envFile]));
    ok_rh(is_resource($process), 'Could not start the PHP test server');
    $ready = false;
    for ($i = 0; $i < 50 && !$ready; $i++) { usleep(100000); $ready = @fsockopen('127.0.0.1', $port, $e1, $e2, 0.2) !== false; }
    ok_rh($ready, 'PHP test server did not start');

    for ($i = 1; $i <= 20; $i++) {
        [$code] = $post('/deal-checker');
        ok_rh($code === 419, "Deal Checker request $i must pass the limiter and fail the CSRF check (419), got $code");
    }
    [$code, $headers, $body] = $post('/deal-checker');
    ok_rh($code === 429, "The 21st Deal Checker request must be refused with 429, got $code");
    ok_rh(preg_match('/^Retry-After:\s*(\d+)/mi', $headers, $m) === 1 && (int)$m[1] >= 1 && (int)$m[1] <= 600, 'The refusal carries a sensible Retry-After header');
    ok_rh(!preg_match('/SQLSTATE|Exception|Stack trace|(Warning|Notice|Fatal error):|modinspect_test|storage\/cache|C:\\\\/i', $body) && str_contains($body, 'ส่งคำขอบ่อยเกินไป'), 'The refusal page shows its message and leaks no error text, database name or path');
    // other pages and the other bucket are not affected
    $ch = curl_init($base . '/price'); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]); curl_exec($ch);
    ok_rh(curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200, 'A page is never limited'); curl_close($ch);
    [$code] = $post('/build');
    ok_rh($code !== 429, 'The Build bucket is separate from the Deal Checker bucket');
    for ($i = 2; $i <= 30; $i++) { $post('/build'); }
    [$code] = $post('/build');
    ok_rh($code === 429, "The 31st Build request must be refused with 429, got $code");
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    @unlink($envFile);
    $reset();
}

echo "rate_limit_http_test: PASS (20 allowed then 429 on Deal Checker, 30 then 429 on Build)\n";
