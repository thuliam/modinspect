<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Auth\AuthService;

function ok_asl(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
$keys = ['AUTH_IDLE_TIMEOUT_SECONDS', 'AUTH_ABSOLUTE_LIFETIME_SECONDS'];
$saved = []; foreach ($keys as $k) { $saved[$k] = $_ENV[$k] ?? null; unset($_ENV[$k]); putenv($k); }
try {
    ok_asl(AuthService::idleTimeout() === 1800 && AuthService::absoluteLifetime() === 28800, 'Without overrides the secure defaults (30 min idle, 8 h absolute) apply');
    $_ENV['AUTH_IDLE_TIMEOUT_SECONDS'] = '28800'; $_ENV['AUTH_ABSOLUTE_LIFETIME_SECONDS'] = '604800';
    ok_asl(AuthService::idleTimeout() === 28800 && AuthService::absoluteLifetime() === 604800, 'Overrides lengthen the session');
    $_ENV['AUTH_IDLE_TIMEOUT_SECONDS'] = '5';
    ok_asl(AuthService::idleTimeout() === 300, 'Idle timeout cannot be set below 5 minutes');
    $_ENV['AUTH_IDLE_TIMEOUT_SECONDS'] = '99999999999'; $_ENV['AUTH_ABSOLUTE_LIFETIME_SECONDS'] = '3600';
    ok_asl(AuthService::idleTimeout() === 2592000 && AuthService::absoluteLifetime() === 2592000, 'Capped at 30 days and the absolute lifetime is never shorter than the idle timeout');
    $_ENV['AUTH_IDLE_TIMEOUT_SECONDS'] = 'abc'; unset($_ENV['AUTH_ABSOLUTE_LIFETIME_SECONDS']);
    ok_asl(AuthService::idleTimeout() === 1800, 'A non-numeric value falls back to the default');
} finally {
    foreach ($keys as $k) { unset($_ENV[$k]); if ($saved[$k] !== null) { $_ENV[$k] = $saved[$k]; } }
}
echo "auth_session_lifetime_test: PASS\n";
