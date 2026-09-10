<?php
declare(strict_types=1);

if (!defined('MODINSPECT_TESTING')) {
    define('MODINSPECT_TESTING', true);
}

$testEnvFile = (string) (getenv('MODINSPECT_ENV_FILE') ?: '.env.testing');
if (!defined('MODINSPECT_ENV_FILE')) {
    define('MODINSPECT_ENV_FILE', $testEnvFile);
}

require __DIR__ . '/support/TestDatabaseGuard.php';

$rootPath = dirname(__DIR__);
TestDatabaseGuard::assertEnvironmentFile($rootPath, (string) MODINSPECT_ENV_FILE);

require $rootPath . '/app/bootstrap.php';

global $config;
TestDatabaseGuard::assertSafe($config['db'], (string) MODINSPECT_ENV_FILE);
