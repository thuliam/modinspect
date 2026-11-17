<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;

/**
 * LS-5 L-6 (found in LS-4 when a failed CLI run printed part of the DEV DB password): a failed database connection must never put the password,
 * username or DSN into an exception, its previous exception, or its stack trace. PDO's own exception carries the constructor arguments in its trace,
 * so the original PDOException must not be chained.
 */
function ok_de(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$prop = new ReflectionProperty(Database::class, 'pdo');
$prop->setAccessible(true);
$saved = $prop->getValue();
$prop->setValue(null, null);
$secret = 'S3CR3T-PW-XYZ-12345';
$user = 'secret_user_abc';
$caught = null;
try {
    Database::connection(['host' => '127.0.0.1', 'port' => '1', 'database' => 'secret_db_name', 'username' => $user, 'password' => $secret]);
} catch (Throwable $e) {
    $caught = $e;
} finally {
    $prop->setValue(null, $saved);
}
ok_de($caught instanceof RuntimeException, 'A failed connection throws a RuntimeException');
ok_de($caught->getPrevious() === null, 'The original PDOException is not chained (its trace holds the credentials)');
$text = (string)$caught . "\n" . $caught->getTraceAsString();
foreach ([$secret, $user, 'secret_db_name'] as $needle) { ok_de(!str_contains($text, $needle), 'The exception text must not contain: ' . substr($needle, 0, 6) . '...'); }
ok_de(str_contains($caught->getMessage(), 'SQLSTATE') || str_contains($caught->getMessage(), 'ไม่สามารถเชื่อมต่อ'), 'The message still says the connection failed');
echo "db_error_no_secret_test: PASS\n";
