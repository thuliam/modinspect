<?php
declare(strict_types=1);

final class TestDatabaseGuard
{
    public const CLOUD_HOST = 'thsv16.hostatom.com';
    public const CLOUD_DATABASE = 'thuliam_modinspect';

    public static function assertEnvironmentFile(string $rootPath, string $envFile): void
    {
        $path = self::resolveEnvPath($rootPath, $envFile);
        if (!is_file($path)) {
            throw new RuntimeException('TEST_ENV_FILE_MISSING: .env.testing is required for automated tests');
        }
    }

    public static function assertSafe(array $dbConfig, string $envFile): void
    {
        if (!defined('MODINSPECT_TESTING') || MODINSPECT_TESTING !== true) {
            throw new RuntimeException('UNSAFE_TEST_DATABASE_BLOCKED: MODINSPECT_TESTING is not enabled');
        }

        if (basename(str_replace('\\', '/', $envFile)) !== '.env.testing') {
            throw new RuntimeException('UNSAFE_TEST_DATABASE_BLOCKED: automated tests must load .env.testing');
        }

        $host = strtolower(trim((string)($dbConfig['host'] ?? '')));
        $database = strtolower(trim((string)($dbConfig['database'] ?? '')));

        if ($host === '' || $database === '') {
            throw new RuntimeException('UNSAFE_TEST_DATABASE_BLOCKED: test database host/name is empty');
        }

        if ($host === self::CLOUD_HOST || $database === self::CLOUD_DATABASE) {
            throw new RuntimeException('UNSAFE_TEST_DATABASE_BLOCKED: shared Cloud DEV database detected');
        }

        if (!str_ends_with($database, '_test')) {
            throw new RuntimeException('UNSAFE_TEST_DATABASE_BLOCKED: test database name must end with _test');
        }
    }

    public static function resolveEnvPath(string $rootPath, string $envFile): string
    {
        $normalized = str_replace('\\', '/', $envFile);
        if (preg_match('/^[A-Za-z]:\//', $normalized) || str_starts_with($normalized, '/')) {
            return $normalized;
        }

        return rtrim($rootPath, '/\\') . '/' . ltrim($normalized, '/');
    }
}
