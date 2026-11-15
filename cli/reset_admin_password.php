<?php
declare(strict_types=1);

/**
 * Set a new password for an existing user. Recovery path when the password is forgotten.
 *   php cli/reset_admin_password.php --email=user@example.com [--password-env=ENV_NAME]
 * Without --password-env it prompts twice (input may be visible in a Windows shell). Minimum 12 characters.
 * The password is never printed or logged; the change is written to the audit log.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Auth\AuthService;

$options = getopt('', ['email:', 'password-env::']);
$email = trim((string)($options['email'] ?? ''));
if ($email === '') {
    fwrite(STDERR, "Usage: php cli/reset_admin_password.php --email=user@example.com [--password-env=ENV_NAME]\n");
    exit(2);
}
$passwordEnv = trim((string)($options['password-env'] ?? ''));
if ($passwordEnv !== '') {
    $password = (string)(getenv($passwordEnv) ?: ($_ENV[$passwordEnv] ?? ''));
} else {
    fwrite(STDOUT, "New password (min 12 characters): ");
    $password = rtrim((string)fgets(STDIN), "\r\n");
    fwrite(STDOUT, "Confirm password: ");
    if (!hash_equals($password, rtrim((string)fgets(STDIN), "\r\n"))) {
        fwrite(STDERR, "Passwords do not match\n");
        exit(2);
    }
}
$result = (new AuthService())->resetPassword($email, $password);
if (($result['ok'] ?? false) !== true) {
    fwrite(STDERR, 'Failed: ' . ($result['error'] ?? 'UNKNOWN') . PHP_EOL);
    exit(1);
}
echo "Password updated for user #{$result['user_id']}\n";
