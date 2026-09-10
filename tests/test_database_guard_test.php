<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function assert_guard(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

global $config;

$db = $config['db'];
assert_guard(strtolower((string)$db['host']) !== TestDatabaseGuard::CLOUD_HOST, 'Test DB host must not be Cloud DEV');
assert_guard(strtolower((string)$db['database']) !== TestDatabaseGuard::CLOUD_DATABASE, 'Test DB name must not be Cloud DEV');
assert_guard(str_ends_with(strtolower((string)$db['database']), '_test'), 'Test DB name must end with _test');

$appEnv = read_guard_env(dirname(__DIR__) . '/.env');
assert_guard(($appEnv['DB_HOST'] ?? '') === TestDatabaseGuard::CLOUD_HOST, 'Application .env should remain pointed at Cloud DEV host');
assert_guard(($appEnv['DB_DATABASE'] ?? '') === TestDatabaseGuard::CLOUD_DATABASE, 'Application .env should remain pointed at Cloud DEV database');

$blocked = 0;
foreach ([
    ['host' => TestDatabaseGuard::CLOUD_HOST, 'database' => 'modinspect_test'],
    ['host' => '127.0.0.1', 'database' => TestDatabaseGuard::CLOUD_DATABASE],
    ['host' => '127.0.0.1', 'database' => 'modinspect'],
] as $unsafe) {
    try {
        TestDatabaseGuard::assertSafe($unsafe, '.env.testing');
    } catch (RuntimeException $e) {
        if (str_contains($e->getMessage(), 'UNSAFE_TEST_DATABASE_BLOCKED')) {
            $blocked++;
        }
    }
}

assert_guard($blocked === 3, 'Unsafe test database simulations must be blocked');

$missingBlocked = false;
try {
    TestDatabaseGuard::assertEnvironmentFile(dirname(__DIR__), '.env.testing.missing');
} catch (RuntimeException $e) {
    $missingBlocked = str_contains($e->getMessage(), 'TEST_ENV_FILE_MISSING');
}
assert_guard($missingBlocked, 'Missing test environment must fail closed');

echo "Test database guard test passed\n";

function read_guard_env(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException('Application .env missing');
    }

    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $values[$key] = trim($value, "\"'");
    }

    return $values;
}

