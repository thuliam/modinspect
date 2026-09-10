<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);
define('MODINSPECT_ENV_FILE', getenv('MODINSPECT_ENV_FILE') ?: '.env.testing');

require __DIR__ . '/support/TestDatabaseGuard.php';

$rootPath = dirname(__DIR__);
TestDatabaseGuard::assertEnvironmentFile($rootPath, (string) MODINSPECT_ENV_FILE);

$env = [];
foreach (file(TestDatabaseGuard::resolveEnvPath($rootPath, (string) MODINSPECT_ENV_FILE), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
        continue;
    }
    [$key, $value] = array_map('trim', explode('=', $line, 2));
    $env[$key] = trim($value, "\"'");
}

$dbConfig = [
    'host' => $env['DB_HOST'] ?? '',
    'port' => $env['DB_PORT'] ?? '3306',
    'database' => $env['DB_DATABASE'] ?? '',
    'username' => $env['DB_USERNAME'] ?? '',
    'password' => $env['DB_PASSWORD'] ?? '',
];
TestDatabaseGuard::assertSafe($dbConfig, (string) MODINSPECT_ENV_FILE);

$database = (string)$dbConfig['database'];
if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
    throw new RuntimeException('UNSAFE_TEST_DATABASE_BLOCKED: invalid test database name');
}

$fresh = in_array('--fresh', $argv, true);
$serverDsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $dbConfig['host'], $dbConfig['port']);
$server = new PDO($serverDsn, $dbConfig['username'], $dbConfig['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

if ($fresh) {
    $server->exec('DROP DATABASE IF EXISTS `' . $database . '`');
}
$server->exec('CREATE DATABASE IF NOT EXISTS `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

$dbDsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbConfig['host'], $dbConfig['port'], $database);
$db = new PDO($dbDsn, $dbConfig['username'], $dbConfig['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$source = is_file($rootPath . '/modinspect.sql')
    ? $rootPath . '/modinspect.sql'
    : $rootPath . '/database/schema.sql';
runSqlFile($db, $source);

if (basename($source) === 'schema.sql' && is_file($rootPath . '/database/seed.sql')) {
    runSqlFile($db, $rootPath . '/database/seed.sql');
}

echo "Test database bootstrapped: {$dbConfig['host']}/{$database}\n";

function runSqlFile(PDO $db, string $path): void
{
    $sql = (string)file_get_contents($path);
    $sql = preg_replace('/^CREATE DATABASE .*?;\\s*/mi', '', $sql) ?? $sql;
    $sql = preg_replace('/^USE\\s+`?[^`;]+`?;\\s*/mi', '', $sql) ?? $sql;
    $sql = preg_replace('/^\\s*--.*$/m', '', $sql) ?? $sql;
    $sql = preg_replace('/\\/\\*![\\s\\S]*?\\*\\//', '', $sql) ?? $sql;

    $db->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach (splitSqlStatements($sql) as $statement) {
        $trimmed = trim($statement);
        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
            continue;
        }
        $db->exec($trimmed);
    }
    $db->exec('SET FOREIGN_KEY_CHECKS=1');
}

function splitSqlStatements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = null;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $prev = $i > 0 ? $sql[$i - 1] : '';

        if (($char === "'" || $char === '"') && $prev !== '\\') {
            $quote = $quote === $char ? null : ($quote ?? $char);
        }

        if ($char === ';' && $quote === null) {
            $statements[] = $buffer;
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    if (trim($buffer) !== '') {
        $statements[] = $buffer;
    }

    return $statements;
}
