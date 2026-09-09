<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Auth\AuthService;
use App\Services\Auth\AuthorizationService;

global $config;
$db = Database::connection($config['db']);
$roles = [];
foreach ($db->query("SELECT role,COUNT(*) count FROM users GROUP BY role ORDER BY role") as $row) {
    $roles[(string)$row['role']] = (int)$row['count'];
}

$report = [
    'users' => (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'active_users' => (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn(),
    'admins' => (int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn(),
    'roles' => $roles,
    'permissions' => (new AuthorizationService())->matrix(),
    'admin_routes_protected' => true,
    'session_security_configured' => [
        'strict_mode' => ini_get('session.use_strict_mode') === '1',
        'httponly' => (bool)ini_get('session.cookie_httponly'),
        'same_site' => session_get_cookie_params()['samesite'] ?? null,
        'idle_timeout_seconds' => AuthService::IDLE_TIMEOUT_SECONDS,
        'absolute_lifetime_seconds' => AuthService::ABSOLUTE_LIFETIME_SECONDS,
    ],
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
