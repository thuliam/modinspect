<?php
declare(strict_types=1);

/**
 * Route guard proof (LS-2 H-7).
 *  1. Static: every /admin route handler checks a permission server-side; every POST handler (admin and public) also verifies the CSRF token;
 *     every permission name used exists in the authorization matrix (a typo would silently deny or, worse, be copied around).
 *  2. Dynamic (isolated test DB, real PHP server, no session): every /admin route answers with a redirect to the login page, never a page or data.
 */

require __DIR__ . '/bootstrap.php';

function ok_rg(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$root = dirname(__DIR__);
$index = (string)file_get_contents($root . '/public/index.php');
preg_match_all('/\$router->(get|post)\(\'([^\']+)\',\[(\w+)::class,\'(\w+)\'\]\)/', $index, $m, PREG_SET_ORDER);
ok_rg(count($m) > 30, 'Routes were parsed from public/index.php (' . count($m) . ')');

$methodBody = static function (string $class, string $method) use ($root): ?string {
    $file = $root . '/app/Controllers/' . $class . '.php';
    if (!is_file($file)) { return null; }
    $src = (string)file_get_contents($file);
    if (!preg_match('/function\s+' . preg_quote($method, '/') . '\s*\(/', $src, $mm, PREG_OFFSET_CAPTURE)) { return null; }
    $start = strpos($src, '{', $mm[0][1]);
    if ($start === false) { return null; }
    $depth = 0; $len = strlen($src);
    for ($i = $start; $i < $len; $i++) {
        if ($src[$i] === '{') { $depth++; } elseif ($src[$i] === '}') { $depth--; if ($depth === 0) { return substr($src, $start, $i - $start + 1); } }
    }
    return null;
};

$matrix = (string)file_get_contents($root . '/app/Services/Auth/AuthorizationService.php');
// permissions that exist only through the admin wildcard on purpose; anything else must be listed for a non-admin role (a typo fails here)
$adminOnly = ['audit.view', 'products.manage', 'users.manage', 'privacy.handle', 'legal.manage', 'members.manage'];
// a handler may delegate to private helpers of the same controller: follow `$this->helper(` up to 2 levels for the guard calls
$collect = static function (string $class, string $body, int $depth) use (&$collect, $methodBody): string {
    $all = $body;
    if ($depth > 0 && preg_match_all('/\$this->(\w+)\(/', $body, $calls)) {
        foreach (array_unique($calls[1]) as $callee) {
            if (in_array($callee, ['view', 'render', 'redirect', 'json', 'flash', 'user'], true)) { continue; }
            $b = $methodBody($class, $callee);
            if ($b !== null) { $all .= "\n" . $collect($class, $b, $depth - 1); }
        }
    }
    return $all;
};
$adminRoutes = []; $problems = [];
foreach ($m as [, $verb, $path, $class, $method]) {
    $own = $methodBody($class, $method);
    if ($own === null) { $problems[] = "$verb $path: handler $class::$method not found"; continue; }
    $body = $collect($class, $own, 2);
    $isAdmin = str_starts_with($path, '/admin');
    if ($isAdmin) {
        $adminRoutes[] = [$verb, $path];
        if (!preg_match('/requirePermission\(\s*\'([a-z0-9_.]+)\'/', $body, $pm)) { $problems[] = "$verb $path: no requirePermission() in $class::$method"; }
        elseif (!in_array($pm[1], $adminOnly, true) && !str_contains($matrix, "'" . $pm[1] . "'")) { $problems[] = "$verb $path: permission '{$pm[1]}' is not in the authorization matrix"; }
    }
    if ($verb === 'post' && !str_contains($body, 'Csrf::verify')) {
        // login/logout verify inside the auth service call; accept only an explicit, named exception
        // /csp-report (LS-21) is posted by the browser itself, with no form and no token, so it cannot carry CSRF. It is exempt ONLY while it keeps its
        // compensating controls: a rate limit, and nothing stored or read (no database, session, cookie or request fields). tests/csp_report_test.php pins the same.
        if ($path === '/csp-report') {
            if (!str_contains($body, 'RateLimiter') || preg_match('/Database|\$_SESSION|\$_COOKIE|\$_POST|\$_GET|setcookie|INSERT|UPDATE/i', $body)) { $problems[] = "$verb $path: exempt from CSRF only with a rate limit and no storage or session access"; }
        } elseif (!in_array($path, ['/login', '/logout'], true) || !str_contains($body, 'Csrf')) { $problems[] = "$verb $path: no CSRF verification in $class::$method"; }
    }
}
ok_rg($problems === [], "Route guard problems:\n  " . implode("\n  ", $problems));
ok_rg(count($adminRoutes) >= 30, 'At least 30 admin routes are guarded (' . count($adminRoutes) . ')');

// 2. dynamic sweep without a session
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
ok_rg($socket !== false, 'No free port for the test server');
$port = (int)parse_url('tcp://' . stream_socket_get_name($socket, false), PHP_URL_PORT);
fclose($socket);
$base = "http://127.0.0.1:{$port}";
$envLines = [];
foreach (file($root . '/.env.testing', FILE_IGNORE_NEW_LINES) ?: [] as $line) { $envLines[] = str_starts_with($line, 'APP_URL=') ? "APP_URL={$base}" : $line; }
$envFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'modinspect-guard-' . bin2hex(random_bytes(4)) . '.env';
file_put_contents($envFile, implode("\n", $envLines) . "\n");
$guard = (string)file_get_contents($envFile);
ok_rg(str_contains($guard, 'DB_DATABASE=modinspect_test'), 'The sweep must run on the isolated test database');
$process = null;
try {
    $process = proc_open(
        [PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $root . '/public'],
        [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/modinspect-guard-server.log', 'a'], 2 => ['file', sys_get_temp_dir() . '/modinspect-guard-server.log', 'a']],
        $pipes, $root, array_merge(getenv() ?: [], ['MODINSPECT_ENV_FILE' => $envFile]));
    ok_rg(is_resource($process), 'Could not start the PHP test server');
    $ready = false;
    for ($i = 0; $i < 50 && !$ready; $i++) { usleep(100000); $ready = @fsockopen('127.0.0.1', $port, $e1, $e2, 0.2) !== false; }
    ok_rg($ready, 'PHP test server did not start');

    $bad = [];
    $skipped = 0;
    foreach ($adminRoutes as [$verb, $path]) {
        // the PHP built-in server sets SCRIPT_NAME to the request path for URLs that end in .csv/.json, which breaks the app's base-path logic (a harness artifact:
        // on Apache the same URL answers 302 to the login page). Those routes are covered by the static scan above, which follows the handler into its helper.
        if (str_contains(basename($path), '.')) { $skipped++; continue; }
        $url = $base . str_replace('{id}', '1', $path);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20, CURLOPT_CUSTOMREQUEST => strtoupper($verb)]);
        if ($verb === 'post') { curl_setopt($ch, CURLOPT_POSTFIELDS, ['_token' => 'none']); }
        $raw = (string)curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
        $loc = preg_match('/^Location:\s*(\S+)/mi', substr($raw, 0, $hs), $lm) ? $lm[1] : '';
        $deniedPage = in_array($status, [401, 403, 419], true);
        $toLogin = in_array($status, [301, 302, 303, 307], true) && str_contains($loc, '/login');
        if (!$toLogin && !$deniedPage) { $bad[] = strtoupper($verb) . " $path -> $status"; }
    }
    ok_rg($bad === [], "Unauthenticated requests that were not turned away:\n  " . implode("\n  ", $bad));
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    @unlink($envFile);
}

echo 'admin_route_guard_test: PASS (' . count($adminRoutes) . ' admin routes statically guarded; ' . (count($adminRoutes) - $skipped) . " swept unauthenticated, $skipped dotted routes left to the static scan)\n";
