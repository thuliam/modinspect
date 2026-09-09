<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Auth\AuthService;

$options = getopt('', ['email:', 'name::', 'role::', 'password-env::']);
$email = trim((string)($options['email'] ?? ''));
$name = trim((string)($options['name'] ?? $email));
$role = trim((string)($options['role'] ?? 'admin'));
$passwordEnv = trim((string)($options['password-env'] ?? ''));

if ($email === '') {
    fwrite(STDERR, "Usage: php cli/create_admin_user.php --email=admin@example.com [--name=\"Admin\"] [--role=admin|reviewer|operator] [--password-env=ENV_NAME]\n");
    exit(2);
}

$password = '';
if ($passwordEnv !== '') {
    $password = (string)(getenv($passwordEnv) ?: ($_ENV[$passwordEnv] ?? ''));
    if ($password === '') {
        fwrite(STDERR, "Password environment variable is empty: {$passwordEnv}\n");
        exit(2);
    }
} else {
    fwrite(STDOUT, "Password input may be visible in this Windows PHP shell. Prefer --password-env=ENV_NAME when possible.\n");
    fwrite(STDOUT, "Password: ");
    $password = rtrim((string)fgets(STDIN), "\r\n");
    fwrite(STDOUT, "Confirm password: ");
    $confirm = rtrim((string)fgets(STDIN), "\r\n");
    if (!hash_equals($password, $confirm)) {
        fwrite(STDERR, "Passwords do not match\n");
        exit(2);
    }
}

$result = (new AuthService())->createUser($email, $name, $password, $role);
if (($result['ok'] ?? false) !== true) {
    fwrite(STDERR, "Failed: " . ($result['error'] ?? 'UNKNOWN') . PHP_EOL);
    exit(1);
}

echo json_encode(['ok' => true, 'user_id' => $result['user_id'], 'role' => $role], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
