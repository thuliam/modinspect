<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Controllers\HealthController;
use App\Core\Database;

/** Health probe (LS-2 H-11): 200 + ok when the database answers, 503 + unavailable when it does not, and nothing else in the body. */
function ok_hc(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

function run_health(): array
{
    ob_start();
    (new HealthController())->check();
    $body = (string)ob_get_clean();
    return [http_response_code(), $body];
}

global $config;
[$code, $body] = run_health();
$j = json_decode($body, true);
ok_hc($code === 200 && $j === ['status' => 'ok', 'database' => 'ok'], 'A reachable database gives 200 and ok: ' . $code . ' ' . $body);

// make the connection fail: reset the cached PDO and point the config at a closed port (restored in finally)
$ref = new ReflectionProperty(Database::class, 'pdo');
$ref->setAccessible(true);
$saved = $ref->getValue(); $savedConfig = $config['db'];
try {
    $ref->setValue(null, null);
    $config['db']['port'] = '1';
    [$code, $body] = run_health();
    $j = json_decode($body, true);
    ok_hc($code === 503 && $j === ['status' => 'unavailable', 'database' => 'unavailable'], 'An unreachable database gives 503 and unavailable: ' . $code . ' ' . $body);
    ok_hc(!preg_match('/127\.0\.0\.1|localhost|SQLSTATE|password|Exception|\.php/i', $body), 'The failure body leaks no host, path or error text');
} finally {
    $config['db'] = $savedConfig;
    $ref->setValue(null, $saved);
    http_response_code(200);
}

echo "health_endpoint_test: PASS\n";
