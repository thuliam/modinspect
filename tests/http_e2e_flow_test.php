<?php
declare(strict_types=1);

/**
 * HTTP end-to-end proof on the ISOLATED test database (never shared DEV):
 *   real PHP server -> login (CSRF) -> download-contract CSV -> dry run -> confirm -> review queue
 *   -> human approve -> public product page.
 *
 * Invariants proven over real HTTP:
 *   - a dry run never creates review rows; confirm creates exactly the dry-run rows, all pending
 *   - imported TEST/MOCK rows are labelled MOCK_TEST in the review queue
 *   - approving MOCK_TEST evidence never produces a public price (no mock contamination)
 *
 * Not covered here (needs owner-reviewed REAL listing evidence): a positive public price read.
 */

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\Auth\AuthService;
use App\Services\Collection\ImportFieldContract;

function assert_e2e(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array{0:int,1:string,2:string} status, Location header, body */
function e2e_http(string $method, string $url, string $jar, array $fields = [], ?string $uploadField = null, ?string $uploadPath = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => $method,
    ]);
    if ($method === 'POST') {
        if ($uploadField !== null && $uploadPath !== null) {
            $fields[$uploadField] = new CURLFile($uploadPath, 'text/csv', 'owner-template-filled.csv');
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    }
    $raw = (string)curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers = substr($raw, 0, $headerSize);
    $location = preg_match('/^Location:\s*(\S+)/mi', $headers, $m) ? $m[1] : '';
    return [$status, $location, substr($raw, $headerSize)];
}

function e2e_token(string $html): string
{
    assert_e2e((bool)preg_match('/name="_token" value="([^"]+)"/', $html, $m), 'CSRF token not found in page');
    return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
}

global $config;
$db = Database::connection($config['db']);
$root = dirname(__DIR__);

// Isolated server: same test DB, APP_URL pointing at the server itself (form actions/redirects use APP_URL).
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
assert_e2e($socket !== false, 'No free port for the test server');
$port = (int)parse_url('tcp://' . stream_socket_get_name($socket, false), PHP_URL_PORT);
fclose($socket);
$base = "http://127.0.0.1:{$port}";

$envLines = [];
foreach (file($root . '/.env.testing', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    $envLines[] = str_starts_with($line, 'APP_URL=') ? "APP_URL={$base}" : $line;
}
$envFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'modinspect-e2e-' . bin2hex(random_bytes(4)) . '.env';
file_put_contents($envFile, implode("\n", $envLines) . "\n");
$jar = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'modinspect-e2e-' . bin2hex(random_bytes(4)) . '.cookies';
$csvPath = '';
$process = null;

try {
    $guard = file_get_contents($envFile);
    assert_e2e(str_contains((string)$guard, 'DB_HOST=127.0.0.1') && str_contains((string)$guard, 'DB_DATABASE=modinspect_test'), 'E2E env must point at the isolated test database');

    $process = proc_open(
        [PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $root . '/public'],
        [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/modinspect-e2e-server.log', 'a'], 2 => ['file', sys_get_temp_dir() . '/modinspect-e2e-server.log', 'a']],
        $pipes,
        $root,
        array_merge(getenv() ?: [], ['MODINSPECT_ENV_FILE' => $envFile])
    );
    assert_e2e(is_resource($process), 'Could not start the PHP test server');
    $ready = false;
    for ($i = 0; $i < 50 && !$ready; $i++) {
        usleep(100000);
        $ready = @fsockopen('127.0.0.1', $port, $e1, $e2, 0.2) !== false;
    }
    assert_e2e($ready, 'PHP test server did not start');

    // Throwaway admin (committed to the isolated test DB; unique email per run).
    $email = 'e2e-admin-' . bin2hex(random_bytes(4)) . '@example.test';
    $password = bin2hex(random_bytes(9)) . 'Aa1!';
    $admin = (new AuthService())->createUser($email, 'E2E Admin', $password, 'admin');
    assert_e2e(($admin['ok'] ?? false) === true, 'Failed to create the E2E admin');

    // 1. Login over HTTP with CSRF.
    [, , $loginPage] = e2e_http('GET', "{$base}/login", $jar);
    [$status, $location] = e2e_http('POST', "{$base}/login", $jar, ['_token' => e2e_token($loginPage), 'email' => $email, 'password' => $password]);
    assert_e2e($status === 302 && str_contains($location, '/admin'), "Login should redirect to /admin, got {$status} {$location}");
    [$status] = e2e_http('GET', "{$base}/admin/review-queue", $jar);
    assert_e2e($status === 200, 'Authenticated review queue should return 200');

    // 2. Build a contract-valid TEST CSV with unique identities.
    $suffix = bin2hex(random_bytes(4));
    $template = fopen($root . '/examples/import/market_evidence_template.csv', 'rb');
    $headers = fgetcsv($template, 0, ',', '"', '');
    $templateRow = array_combine($headers, fgetcsv($template, 0, ',', '"', ''));
    fclose($template);
    assert_e2e($headers === ImportFieldContract::CSV_HEADERS, 'examples/import CSV sample must match the importer contract exactly (regenerate it from ImportFieldContract::csvTemplate())');

    $rows = [];
    for ($i = 1; $i <= 2; $i++) {
        $row = $templateRow;
        $item = "e2e-{$suffix}-{$i}";
        $row['source_item_id'] = $item;
        $row['external_ref'] = $item;
        $row['source_listing_url'] = "https://example.test/listing/{$item}";
        $row['merchant_target_url'] = "https://merchant.example.test/item/{$item}";
        // A clean, unambiguous single-CPU listing so the deterministic validators let a human approve it.
        $row['listing_title'] = "AMD Ryzen 7 5700X3D TEST import E2E {$suffix} row {$i}";
        $row['listing_text'] = 'TEST/MOCK fixture only. Used AMD Ryzen 7 5700X3D single CPU, working normally, asking ' . (7400 + $i * 100) . ' THB.';
        $row['asking_price'] = (string)(7400 + $i * 100);
        $row['displayed_price'] = $row['asking_price'];
        $row['product_hint'] = '5700X3D';
        $row['observed_at'] = '2026-11-14 12:0' . $i . ':00';
        $snapshot = json_decode((string)$row['evidence_snapshot_json'], true) ?: [];
        $snapshot['dataset'] = 'TEST/MOCK';
        $snapshot['source_item_id'] = $item;
        $snapshot['listing_title'] = $row['listing_title'];
        $snapshot['asking_price'] = $row['asking_price'];
        $snapshot['observed_at'] = $row['observed_at'];
        $row['evidence_snapshot_json'] = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $row['evidence_hash'] = hash('sha256', (string)$row['evidence_snapshot_json']);
        $rows[] = $row;
    }
    $dir = $root . '/storage/import/test';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $csvPath = $dir . "/e2e-http-{$suffix}.csv";
    $out = fopen($csvPath, 'wb');
    fputcsv($out, ImportFieldContract::CSV_HEADERS, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($out, array_map(static fn(string $h): string => (string)($row[$h] ?? ''), ImportFieldContract::CSV_HEADERS), ',', '"', '');
    }
    fclose($out);

    $count = static fn(): int => (int)$db->query('SELECT COUNT(*) FROM price_observations')->fetchColumn();
    $before = $count();

    // 3. Dry run: must not create review rows.
    [, , $jobs] = e2e_http('GET', "{$base}/admin/collector-jobs", $jar);
    [$status] = e2e_http('POST', "{$base}/admin/collector-jobs/import-dry-run", $jar, ['_token' => e2e_token($jobs), 'dataset' => 'test', 'limit' => '10'], 'evidence_file', $csvPath);
    assert_e2e($status === 302, "Dry run should redirect, got {$status}");
    assert_e2e($count() === $before, 'Dry run must not create review rows');

    // 4. Confirm: exactly the dry-run rows become pending observations.
    [, , $jobs] = e2e_http('GET', "{$base}/admin/collector-jobs", $jar);
    assert_e2e(str_contains($jobs, 'import-confirm'), 'Confirm control should appear after a successful dry run');
    [$status] = e2e_http('POST', "{$base}/admin/collector-jobs/import-confirm", $jar, ['_token' => e2e_token($jobs)]);
    assert_e2e($status === 302, "Confirm should redirect, got {$status}");
    assert_e2e($count() === $before + 2, 'Confirm must create exactly the two dry-run rows');

    $created = $db->prepare("SELECT o.id,o.product_id,o.verified_status FROM price_observations o JOIN raw_price_observations r ON r.id=o.raw_observation_id WHERE r.raw_title LIKE :t ORDER BY o.id");
    $created->execute(['t' => "%E2E {$suffix}%"]);
    $observations = $created->fetchAll();
    assert_e2e(count($observations) === 2, 'Both imported observations should be traceable by title');
    foreach ($observations as $observation) {
        assert_e2e($observation['verified_status'] === 'pending', 'Imported rows must start pending');
    }

    // 5. Review queue data endpoint (server-side DataTables) shows them, labelled MOCK_TEST, and hides them from the default REAL view.
    [$status, , $json] = e2e_http('GET', "{$base}/admin/review-queue/data?draw=1&start=0&length=100&dataset=MOCK_TEST&status=pending", $jar);
    $payload = json_decode($json, true);
    assert_e2e($status === 200 && is_array($payload), 'Review data endpoint should return JSON');
    assert_e2e(str_contains((string)json_encode($payload['data']), "E2E {$suffix}") && str_contains((string)json_encode($payload['data']), 'MOCK_TEST'), 'Imported rows should be listed as MOCK_TEST');
    [, , $json] = e2e_http('GET', "{$base}/admin/review-queue/data?draw=1&start=0&length=100&dataset=REAL&status=pending", $jar);
    assert_e2e(!str_contains($json, "E2E {$suffix}"), 'MOCK_TEST rows must not appear in the default REAL review view');

    // 6. Human approve over HTTP, through the real form contract.
    $observationId = (int)$observations[0]['id'];
    [, , $detail] = e2e_http('GET', "{$base}/admin/review/{$observationId}", $jar);
    [$status] = e2e_http('POST', "{$base}/admin/review-queue/decision", $jar, ['_token' => e2e_token($detail), 'observation_id' => (string)$observationId, 'decision' => 'approved', 'notes' => 'E2E proof only']);
    assert_e2e($status === 302, "Decision should redirect, got {$status}");
    $state = (string)$db->query("SELECT verified_status FROM price_observations WHERE id={$observationId}")->fetchColumn();
    assert_e2e($state === 'approved', 'Observation should be approved after the human decision');

    // 7. Public read: approved MOCK_TEST evidence must NOT become a public price.
    $slug = (string)$db->query('SELECT slug FROM products WHERE id=' . (int)$observations[0]['product_id'])->fetchColumn();
    [$status, , $public] = e2e_http('GET', "{$base}/price/{$slug}", $jar);
    assert_e2e($status === 200, 'Public product page should render');
    $publicText = trim(preg_replace('/\s+/', ' ', strip_tags((string)preg_replace('#<(script|style)\b.*?</\1>#is', '', $public))) ?? '');
    assert_e2e(str_contains($public, 'ข้อมูลตลาดยังไม่เพียงพอ') || str_contains($public, 'ยังไม่มีราคาตลาด'), 'Public page must keep the insufficient-data state. Page text: ' . mb_substr($publicText, 0, 600));
    assert_e2e(!str_contains($public, "E2E {$suffix}"), 'Public page must not expose mock listing titles');

    echo "HTTP end-to-end flow test passed\n";
    echo "Login, dry run (no rows), confirm (2 pending), MOCK_TEST labelling, approve, public no-price: ok\n";
} finally {
    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }
    foreach ([$envFile, $jar, $csvPath] as $path) {
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }
}
