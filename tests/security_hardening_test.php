<?php
declare(strict_types=1);

/**
 * Security regression guard (2026-11-14 review).
 *  1. Static: root .htaccess blocks everything but /public; /public holds no secrets/dumps.
 *  2. Static: every routed /admin method is RBAC-guarded and every POST route verifies CSRF.
 *  3. Unit: upload size cap is enforced before a file is stored.
 *  4. HTTP (real PHP server, APP_ENV=production, isolated DB): security headers, no X-Powered-By,
 *     and a hostile condition_level on the public Deal Checker is normalised instead of crashing.
 */

require __DIR__ . '/bootstrap.php';

use App\Controllers\AdminController;

function assert_sec(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);

// ---------------------------------------------------------------- 1. web exposure (static)
$htaccess = (string)@file_get_contents($root . '/.htaccess');
assert_sec($htaccess !== '', 'Project root .htaccess is missing: .env, .git, *.sql and docs would be web-reachable when the docroot is the project folder');
assert_sec(str_contains($htaccess, 'RewriteRule ^public(/|$) - [L]'), '.htaccess must allow /public');
assert_sec((bool)preg_match('/RewriteRule \^ - \[F,L\]/', $htaccess), '.htaccess must forbid everything outside /public');
assert_sec(str_contains($htaccess, 'Options -Indexes'), '.htaccess must disable directory listings');

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/public', FilesystemIterator::SKIP_DOTS)) as $file) {
    $name = strtolower($file->getFilename());
    assert_sec(!preg_match('/^\.env|\.(sql|zip|log|bak|sqlite|gz|tar)$|^phpinfo/', $name), 'Sensitive-looking file inside public/: ' . $file->getPathname());
}

// ---------------------------------------------------------------- 2. RBAC + CSRF on every route (static)
$index = (string)file_get_contents($root . '/public/index.php');
preg_match_all('/\$router->(get|post)\(\'([^\']+)\',\[(\w+)::class,\'(\w+)\'\]\)/', $index, $routes, PREG_SET_ORDER);
assert_sec(count($routes) >= 50, 'Route table not parsed');

$body = static function (string $code, string $method): string {
    if (!preg_match('/function\s+' . preg_quote($method, '/') . '\s*\([^)]*\)[^{]*\{/', $code, $m, PREG_OFFSET_CAPTURE)) {
        return '';
    }
    $start = $m[0][1] + strlen($m[0][0]);
    $depth = 1;
    $length = strlen($code);
    for ($i = $start; $i < $length && $depth > 0; $i++) {
        $depth += $code[$i] === '{' ? 1 : ($code[$i] === '}' ? -1 : 0);
    }
    return substr($code, $start, $i - $start);
};
$sources = [];
$missing = [];
// A handler may delegate to private helpers of the same controller ($this->helper(...)); the guard may live there.
$effectiveBody = function (string $controller, string $method, int $depth = 0) use (&$sources, $body, &$effectiveBody): string {
    $code = $body($sources[$controller], $method);
    if ($depth < 2 && preg_match_all('/\$this->([A-Za-z_]\w*)\(/', $code, $calls)) {
        foreach (array_unique($calls[1]) as $helper) {
            if (!in_array($helper, ['render', 'view', 'json', 'redirect', 'flash', 'baseUrl', 'user', 'csrf'], true)) {
                $code .= "\n" . $effectiveBody($controller, $helper, $depth + 1);
            }
        }
    }
    return $code;
};
foreach ($routes as [, $verb, $path, $controller, $method]) {
    $sources[$controller] ??= (string)file_get_contents($root . "/app/Controllers/{$controller}.php");
    assert_sec($body($sources[$controller], $method) !== '', "Route handler not found: {$controller}::{$method}");
    $code = $effectiveBody($controller, $method);
    if (str_starts_with($path, '/admin') && !str_contains($code, 'requirePermission')) {
        $missing[] = "no RBAC guard: {$verb} {$path}";
    }
    if ($verb === 'post' && !str_contains($code, 'Csrf::verify')) {
        $missing[] = "no CSRF check: post {$path}";
    }
}
assert_sec($missing === [], "Unguarded routes:\n" . implode("\n", $missing));

// ---------------------------------------------------------------- 3. upload size cap (unit)
$method = new ReflectionMethod(AdminController::class, 'storedUploadPath');
$method->setAccessible(true);
$tmp = tempnam(sys_get_temp_dir(), 'mi-up');
file_put_contents($tmp, "a,b\n1,2\n");
$tooBig = $method->invoke(new AdminController(), ['error' => UPLOAD_ERR_OK, 'name' => 'x.csv', 'size' => 5242881, 'tmp_name' => $tmp]);
assert_sec($tooBig === '', 'An upload above 5 MB must be refused before it is stored');
$badExt = $method->invoke(new AdminController(), ['error' => UPLOAD_ERR_OK, 'name' => 'x.php', 'size' => 10, 'tmp_name' => $tmp]);
assert_sec($badExt === '', 'Only csv/json uploads are accepted');
@unlink($tmp);

// ---------------------------------------------------------------- 4. HTTP checks against a real server
global $config;
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
assert_sec($socket !== false, 'No free port');
$port = (int)parse_url('tcp://' . stream_socket_get_name($socket, false), PHP_URL_PORT);
fclose($socket);
$base = "http://127.0.0.1:{$port}";
$lines = [];
foreach (file($root . '/.env.testing', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    $lines[] = str_starts_with($line, 'APP_URL=') ? "APP_URL={$base}" : (str_starts_with($line, 'APP_ENV=') ? 'APP_ENV=production' : $line);
}
$envFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'modinspect-sec-' . bin2hex(random_bytes(4)) . '.env';
file_put_contents($envFile, implode("\n", $lines) . "\n");
$jar = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'modinspect-sec-' . bin2hex(random_bytes(4)) . '.cookies';
$process = null;

$http = static function (string $method, string $url, array $fields = []) use ($jar): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30, CURLOPT_CUSTOMREQUEST => $method]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    }
    $raw = (string)curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $size = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [$status, substr($raw, 0, $size), substr($raw, $size)];
};

try {
    $guard = (string)file_get_contents($envFile);
    assert_sec(str_contains($guard, 'DB_HOST=127.0.0.1') && str_contains($guard, 'DB_DATABASE=modinspect_test'), 'Security test env must point at the isolated test DB');
    $process = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $root . '/public'], [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/modinspect-sec-server.log', 'a'], 2 => ['file', sys_get_temp_dir() . '/modinspect-sec-server.log', 'a']], $pipes, $root, array_merge(getenv() ?: [], ['MODINSPECT_ENV_FILE' => $envFile]));
    assert_sec(is_resource($process), 'Could not start the PHP test server');
    $ready = false;
    for ($i = 0; $i < 50 && !$ready; $i++) {
        usleep(100000);
        $ready = @fsockopen('127.0.0.1', $port, $e1, $e2, 0.2) !== false;
    }
    assert_sec($ready, 'PHP test server did not start');

    [$status, $headers] = $http('GET', "{$base}/");
    assert_sec($status === 200, "Home should return 200, got {$status}");
    assert_sec(stripos($headers, 'X-Content-Type-Options: nosniff') !== false, 'Missing X-Content-Type-Options');
    assert_sec(stripos($headers, 'X-Frame-Options: SAMEORIGIN') !== false, 'Missing X-Frame-Options');
    assert_sec(stripos($headers, 'Referrer-Policy:') !== false, 'Missing Referrer-Policy');
    assert_sec(stripos($headers, 'X-Powered-By') === false, 'X-Powered-By must not be sent');

    // Public Deal Checker: a hostile condition_level is normalised (previously an ENUM error -> 500).
    [, , $form] = $http('GET', "{$base}/deal-checker");
    assert_sec((bool)preg_match('/name="_token" value="([^"]+)"/', $form, $t), 'Deal Checker CSRF token missing');
    assert_sec((bool)preg_match('/<option value="(\d+)"/', $form, $p), 'Deal Checker product option missing');
    [$status, $headers] = $http('POST', "{$base}/deal-checker", ['_token' => html_entity_decode($t[1]), 'product_id' => $p[1], 'user_price' => '8000', 'condition_level' => "<script>alert(1)</script>", 'warranty_months' => '99999']);
    assert_sec($status === 302 && str_contains($headers, '/deal-checker/result/'), "Deal Checker must accept the request with a normalised condition, got {$status}");
    $db = \App\Core\Database::connection($config['db']);
    $row = $db->query('SELECT condition_level,warranty_months FROM deal_checks ORDER BY id DESC LIMIT 1')->fetch();
    assert_sec($row['condition_level'] === 'unknown' && (int)$row['warranty_months'] === 120, 'Condition must fall back to unknown and warranty clamp to 120 months');

    echo "Security hardening test passed\n";
    echo 'Routes audited for RBAC/CSRF: ' . count($routes) . "\n";
} finally {
    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }
    foreach ([$envFile, $jar] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
