<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/**
 * LS-14 Module 7 over real HTTP on the ISOLATED test database. Without provider keys the site offers no sign-in (no header link, an honest page).
 * With a Google key: consent is required, a consented start redirects to Google with state and PKCE, a forged callback is refused, /account
 * needs a member, POSTs need the CSRF token. Nothing here talks to a real provider.
 */
function ok_mh(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$root = dirname(__DIR__);
/** @return array{0:mixed,1:int,2:string} [process, port, env file] */
function mh_server(string $root, array $extraEnv): array
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    $port = (int)parse_url('tcp://' . stream_socket_get_name($socket, false), PHP_URL_PORT);
    fclose($socket);
    $lines = [];
    foreach (file($root . '/.env.testing', FILE_IGNORE_NEW_LINES) ?: [] as $line) { $lines[] = str_starts_with($line, 'APP_URL=') ? "APP_URL=http://127.0.0.1:$port" : $line; }
    foreach ($extraEnv as $k => $v) { $lines[] = "$k=$v"; }
    $env = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'modinspect-mh-' . bin2hex(random_bytes(4)) . '.env';
    file_put_contents($env, implode("\n", $lines) . "\n");
    ok_mh(str_contains((string)file_get_contents($env), 'DB_DATABASE=modinspect_test'), 'The server must run on the isolated test database');
    $proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $root . '/public'],
        [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/modinspect-mh-server.log', 'a'], 2 => ['file', sys_get_temp_dir() . '/modinspect-mh-server.log', 'a']],
        $pipes, $root, array_merge(getenv() ?: [], ['MODINSPECT_ENV_FILE' => $env]));
    for ($i = 0, $ready = false; $i < 50 && !$ready; $i++) { usleep(100000); $ready = @fsockopen('127.0.0.1', $port, $e1, $e2, 0.2) !== false; }
    ok_mh($ready, 'PHP test server did not start');
    return [$proc, $port, $env];
}
/** @return array{code:int,body:string,location:string} */
function mh_req(string $url, string $jar, ?array $post = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $raw = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
    preg_match('/^Location:\s*(.+)$/mi', substr($raw, 0, $hs), $m);
    return ['code' => $code, 'body' => substr($raw, $hs), 'location' => trim($m[1] ?? '')];
}
$token = static fn(string $html): string => preg_match('/name="_token" value="([^"]+)"/', $html, $m) ? html_entity_decode($m[1]) : '';

$servers = [];
try {
    // 1) no keys: nothing offered
    [$proc, $port, $env] = mh_server($root, []); $servers[] = [$proc, $env];
    $b = "http://127.0.0.1:$port"; $jar = tempnam(sys_get_temp_dir(), 'mhj');
    $home = mh_req("$b/", $jar);
    ok_mh($home['code'] === 200 && !str_contains($home['body'], '/account/login'), 'Without provider keys the header has no sign-in link');
    $login = mh_req("$b/account/login", $jar);
    ok_mh($login['code'] === 200 && str_contains($login['body'], 'ยังไม่เปิดรับสมาชิก') && !str_contains($login['body'], 'name="provider"'), 'The login page says sign-in is not open and offers no provider');
    $acc = mh_req("$b/account", $jar);
    ok_mh($acc['code'] === 302 && str_ends_with($acc['location'], '/account/login'), '/account needs a member');
    proc_terminate($proc); proc_close($proc);

    // 2) Google keys present (fake values; no request leaves this machine because we never follow the redirect)
    [$proc, $port, $env] = mh_server($root, ['MEMBER_GOOGLE_CLIENT_ID' => 'test-client', 'MEMBER_GOOGLE_CLIENT_SECRET' => 'test-secret']); $servers[] = [$proc, $env];
    $b = "http://127.0.0.1:$port"; $jar = tempnam(sys_get_temp_dir(), 'mhj');
    ok_mh(str_contains(mh_req("$b/", $jar)['body'], '/account/login'), 'With a provider configured the header offers sign-in');
    $login = mh_req("$b/account/login", $jar);
    ok_mh(str_contains($login['body'], 'value="google"') && !str_contains($login['body'], 'value="facebook"') && !str_contains($login['body'], 'Apple'), 'Only the configured provider is offered');
    $t = $token($login['body']);
    $bad = mh_req("$b/account/login", $jar, ['_token' => 'forged', 'provider' => 'google', 'consent' => '1']);
    ok_mh($bad['code'] === 302 && str_ends_with($bad['location'], '/account/login'), 'A forged CSRF token goes back to the login page');
    $noConsent = mh_req("$b/account/login", $jar, ['_token' => $t, 'provider' => 'google']);
    ok_mh($noConsent['code'] === 302 && str_ends_with($noConsent['location'], '/account/login') && str_contains(mh_req("$b/account/login", $jar)['body'], 'กรุณายอมรับ'), 'Without consent there is no sign-in and the page says why');
    $t = $token(mh_req("$b/account/login", $jar)['body']);
    $go = mh_req("$b/account/login", $jar, ['_token' => $t, 'provider' => 'google', 'consent' => '1']);
    parse_str((string)parse_url($go['location'], PHP_URL_QUERY), $q);
    ok_mh($go['code'] === 302 && str_starts_with($go['location'], 'https://accounts.google.com/') && $q['client_id'] === 'test-client' && strlen($q['state'] ?? '') === 32 && ($q['code_challenge_method'] ?? '') === 'S256' && $q['redirect_uri'] === "$b/account/callback/google", 'A consented start redirects to Google with state, PKCE and the right redirect URI');
    ok_mh(!str_contains($go['location'], 'test-secret'), 'The client secret never appears in the browser redirect');
    $forged = mh_req("$b/account/callback/google?state=wrong&code=abc", $jar);
    ok_mh($forged['code'] === 302 && str_ends_with($forged['location'], '/account/login'), 'A callback with a wrong state is refused before any provider call');
    ok_mh(mh_req("$b/account/callback/apple?state=x&code=y", $jar)['code'] === 404, 'An unconfigured provider has no callback');
    ok_mh(mh_req("$b/account/logout", $jar, ['_token' => 'forged'])['code'] === 302, 'Logout without a token does nothing harmful');
} finally {
    foreach ($servers as [$p, $e]) { if (is_resource($p)) { @proc_terminate($p); @proc_close($p); } @unlink($e); }
}
echo "member_http_test: PASS\n";
