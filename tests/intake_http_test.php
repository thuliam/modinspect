<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Auth\AuthService;

/**
 * Real-HTTP proof of the admin intake screen (LS-3 J-3 first slice) on the ISOLATED test database:
 * an admin signs in, sees the approval plan, uploads a synthetic sanitised CSV and gets counts back; a reviewer is refused;
 * a non-CSV and a bad-header file are rejected; and the dry run writes nothing and leaves no temp file behind.
 */
function ok_ih(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

function ih_http(string $method, string $url, string $jar, array $fields = [], ?string $uploadField = null, ?string $uploadPath = null, string $uploadName = 'file.csv'): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_COOKIE => 'mi_lang=en', CURLOPT_TIMEOUT => 60, CURLOPT_CUSTOMREQUEST => $method]);
    if ($method === 'POST') {
        if ($uploadField !== null && $uploadPath !== null) { $fields[$uploadField] = new CURLFile($uploadPath, 'text/csv', $uploadName); }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    }
    $raw = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
    $h = substr($raw, 0, $hs);
    return [$code, preg_match('/^Location:\s*(\S+)/mi', $h, $m) ? $m[1] : '', substr($raw, $hs)];
}
function ih_token(string $html): string
{
    ok_ih((bool)preg_match('/name="_token" value="([^"]+)"/', $html, $m), 'CSRF token not found');
    return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
}

global $config;
$db = Database::connection($config['db']);
$root = dirname(__DIR__);
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
ok_ih($socket !== false, 'No free port');
$port = (int)parse_url('tcp://' . stream_socket_get_name($socket, false), PHP_URL_PORT);
fclose($socket);
$base = "http://127.0.0.1:{$port}";
$envLines = [];
foreach (file($root . '/.env.testing', FILE_IGNORE_NEW_LINES) ?: [] as $line) { $envLines[] = str_starts_with($line, 'APP_URL=') ? "APP_URL={$base}" : $line; }
$envFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'modinspect-ih-' . bin2hex(random_bytes(4)) . '.env';
file_put_contents($envFile, implode("\n", $envLines) . "\n");
ok_ih(str_contains((string)file_get_contents($envFile), 'DB_DATABASE=modinspect_test'), 'The server must run on the isolated test database');
$jarA = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'modinspect-ih-a-' . bin2hex(random_bytes(4)) . '.cookies';
$jarR = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'modinspect-ih-r-' . bin2hex(random_bytes(4)) . '.cookies';
$dir = $root . '/storage/import/test';
if (!is_dir($dir)) { mkdir($dir, 0777, true); }
$csv = $dir . '/intake-http-' . bin2hex(random_bytes(4)) . '.csv'; $bad = $dir . '/intake-http-bad-' . bin2hex(random_bytes(4)) . '.csv'; $txt = $dir . '/intake-http-' . bin2hex(random_bytes(4)) . '.txt';
$cols = ['row_id', 'note_date', 'category', 'model_key', 'variant_key', 'title', 'asking_price_thb', 'listing_type', 'bundle_total_line', 'wanted_or_trade', 'defective_hint', 'price_anomaly', 'enrichment_status', 'missing_fields'];
$fh = fopen($csv, 'wb'); fputcsv($fh, $cols, ',', '"', '');
foreach ([['H-1', '2026-11-10', 'gpu', 'rtx 3070', '', 'RTX 3070 8GB Dual มือสอง http-unique-a1', '8100', 'single_item', 'no', 'no', 'no', '', 'complete', ''], ['H-2', '2026-11-10', 'gpu', 'rtx 3070', '', 'RTX 3070 8GB Ventus มือสอง http-unique-b2', '8300', 'single_item', 'no', 'no', 'no', '', 'complete', '']] as $r) { fputcsv($fh, $r, ',', '"', ''); }
fclose($fh);
file_put_contents($bad, "foo,bar\n1,2\n"); file_put_contents($txt, "not a csv");
$uploads = $root . '/storage/import_uploads';
$leftBefore = count(glob($uploads . '/intake-*') ?: []);
$process = null;
$tables = ['raw_price_observations', 'price_observations', 'market_evidence', 'observation_review_decisions', 'collector_jobs', 'data_sources', 'products', 'product_aliases'];
$count = static function () use ($db, $tables): array { $o = []; foreach ($tables as $t) { $o[$t] = (int)$db->query("SELECT COUNT(*) FROM $t")->fetchColumn(); } return $o; };
try {
    $process = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $root . '/public'],
        [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/modinspect-ih-server.log', 'a'], 2 => ['file', sys_get_temp_dir() . '/modinspect-ih-server.log', 'a']],
        $pipes, $root, array_merge(getenv() ?: [], ['MODINSPECT_ENV_FILE' => $envFile]));
    ok_ih(is_resource($process), 'Could not start the PHP test server');
    $ready = false;
    for ($i = 0; $i < 50 && !$ready; $i++) { usleep(100000); $ready = @fsockopen('127.0.0.1', $port, $e1, $e2, 0.2) !== false; }
    ok_ih($ready, 'PHP test server did not start');

    $pw = bin2hex(random_bytes(9)) . 'Aa1!'; $tag = bin2hex(random_bytes(4));
    $auth = new AuthService();
    $adminEmail = "ih-admin-$tag@example.test"; $revEmail = "ih-rev-$tag@example.test";
    ok_ih(($auth->createUser($adminEmail, 'IH Admin', $pw, 'admin')['ok'] ?? false) && ($auth->createUser($revEmail, 'IH Reviewer', $pw, 'reviewer')['ok'] ?? false), 'Fixture users');
    $login = static function (string $jar, string $email) use ($base, $pw): void {
        [, , $page] = ih_http('GET', "$base/login", $jar);
        [$code, $loc] = ih_http('POST', "$base/login", $jar, ['_token' => ih_token($page), 'email' => $email, 'password' => $pw]);
        ok_ih($code === 302 && str_contains($loc, '/admin'), "Login of $email must redirect to /admin, got $code $loc");
    };
    $login($jarA, $adminEmail); $login($jarR, $revEmail);

    // reviewer: refused on both
    [$code] = ih_http('GET', "$base/admin/intake", $jarR);
    ok_ih($code === 403, "A reviewer must get 403 on the intake page, got $code");
    // admin: the plan page
    [$code, , $page] = ih_http('GET', "$base/admin/intake", $jarA);
    ok_ih($code === 200 && str_contains($page, 'Approval plan for pending owner-text rows') && str_contains($page, 'Dry run a sanitised file'), 'The admin sees the plan and the dry-run form');
    $token = ih_token($page);
    // reviewer cannot post either (token from the admin page is invalid for the reviewer session, the permission check comes first anyway)
    [$code] = ih_http('POST', "$base/admin/intake/dry-run", $jarR, ['_token' => $token, 'category' => 'gpu'], 'file', $csv);
    ok_ih($code === 403, "A reviewer must get 403 on the dry-run POST, got $code");

    $before = $count();
    [$code, , $res] = ih_http('POST', "$base/admin/intake/dry-run", $jarA, ['_token' => $token, 'category' => 'gpu'], 'file', $csv);
    ok_ih($code === 200 && str_contains($res, 'Dry run result'), "A valid file gives the result block, got $code");
    ok_ih((bool)preg_match('/(\d+) rows pass the intake rules/', $res, $m) && (int)$m[1] === 2, 'Two rows pass the intake rules');
    ok_ih(!str_contains($res, 'http-unique-a1') && !str_contains($res, 'RTX 3070 8GB Dual'), 'The result shows counts only, no listing text');
    ok_ih($count() === $before, 'The upload dry run wrote nothing: ' . json_encode(array_diff_assoc($count(), $before)));
    ok_ih(count(glob($uploads . '/intake-*') ?: []) === $leftBefore, 'No uploaded or temporary file is left behind');

    [, , $res] = ih_http('POST', "$base/admin/intake/dry-run", $jarA, ['_token' => $token, 'category' => 'gpu'], 'file', $bad, 'bad.csv');
    ok_ih(str_contains($res, 'missing columns') && !str_contains($res, 'Dry run result'), 'A file with the wrong header is rejected with the missing columns');
    [, , $res] = ih_http('POST', "$base/admin/intake/dry-run", $jarA, ['_token' => $token, 'category' => 'gpu'], 'file', $txt, 'notes.txt');
    ok_ih(str_contains($res, 'Only .csv files are accepted'), 'A non-CSV file is rejected');
    [$code] = ih_http('POST', "$base/admin/intake/dry-run", $jarA, ['_token' => 'bad', 'category' => 'gpu'], 'file', $csv);
    ok_ih($code === 302 || $code === 419, "A bad CSRF token is refused, got $code");
    ok_ih(count(glob($uploads . '/intake-*') ?: []) === $leftBefore, 'Still no file left behind after the rejected uploads');
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    foreach ([$csv, $bad, $txt, $envFile, $jarA, $jarR] as $f) { @unlink($f); }
}

echo "intake_http_test: PASS\n";
