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
// the session cookie lives as long as the login may (AUTH_ABSOLUTE_LIFETIME_SECONDS); without that override it ends with the browser, as before
$authLife = (isset($_ENV['AUTH_ABSOLUTE_LIFETIME_SECONDS']) && \App\Services\Auth\AuthService::overridesAllowed()) ? \App\Services\Auth\AuthService::absoluteLifetime() : 0;
if ($authLife > 0) { ini_set('session.gc_maxlifetime', (string)max((int)ini_get('session.gc_maxlifetime'), $authLife)); }
session_set_cookie_params(['lifetime' => $authLife, 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
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
            // LS-20 C3: standalone on purpose (the database or a view may be what failed): static text, brand colours, a way on. No variable reaches the page.
            echo '<!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>ระบบขัดข้อง</title>'
                . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0a0d14;color:#f1f5f9;font:16px/1.6 system-ui,"Segoe UI",Tahoma,sans-serif;padding:24px;box-sizing:border-box}main{max-width:440px}h1{font-size:24px;margin:0 0 8px}p{color:#94a3b8;margin:0 0 20px}a{display:inline-block;min-height:44px;line-height:44px;padding:0 20px;border-radius:10px;background:#bbf246;color:#0a0d14;font-weight:600;text-decoration:none}a:focus{outline:3px solid #f1f5f9;outline-offset:2px}</style>'
                . '<main><h1>ระบบขัดข้อง</h1><p>ระบบขัดข้องชั่วคราว กรุณาลองใหม่อีกครั้งในอีกสักครู่ ข้อมูลที่คุณกรอกไว้ไม่ได้ถูกบันทึก</p><a href="./">กลับหน้าแรก</a></main>';
        });
    }
    if (!headers_sent()) {
        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        if (!empty($config['pilot'])) {
            header('X-Robots-Tag: noindex, nofollow, noarchive');   // closed pilot: nothing is indexed
        }
        if (!empty($_SERVER['HTTPS'])) {
            header('Strict-Transport-Security: max-age=15552000');
        }
    }
    session_start();
}
