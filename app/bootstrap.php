<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

$envFileName = defined('MODINSPECT_ENV_FILE')
    ? (string) MODINSPECT_ENV_FILE
    : (string) ($_ENV['MODINSPECT_ENV_FILE'] ?? getenv('MODINSPECT_ENV_FILE') ?: '.env');
$normalizedEnvFile = str_replace('\\', '/', $envFileName);
$envFile = preg_match('/^[A-Za-z]:\//', $normalizedEnvFile) || str_starts_with($normalizedEnvFile, '/')
    ? $normalizedEnvFile
    : ROOT_PATH . '/' . ltrim($normalizedEnvFile, '/');
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $_ENV[$key] = trim($value, "\"'");
    }
}

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $file = ROOT_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require $file;
});

$config = require ROOT_PATH . '/config/app.php';
date_default_timezone_set('Asia/Bangkok');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
if (PHP_SAPI !== 'cli') {
    // Only local/testing show PHP errors. Anything else (a missing APP_ENV counts as production) logs them and
    // returns a generic 500 page, so paths, SQL and stack traces never reach a visitor.
    $appEnv = strtolower((string)($_ENV['APP_ENV'] ?? 'production'));
    $debugEnv = in_array($appEnv, ['local', 'testing'], true);
    ini_set('display_errors', $debugEnv ? '1' : '0');
    ini_set('log_errors', '1');
    if (!$debugEnv) {
        set_exception_handler(static function (\Throwable $e): void {
            error_log(sprintf('[modinspect] %s: %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=utf-8');
            }
            echo '<!doctype html><meta charset="utf-8"><title>ระบบขัดข้อง</title><p>ระบบขัดข้องชั่วคราว กรุณาลองใหม่อีกครั้ง</p>';
        });
    }
    if (!headers_sent()) {
        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        if (!empty($_SERVER['HTTPS'])) {
            header('Strict-Transport-Security: max-age=15552000');
        }
    }
    session_start();
}
