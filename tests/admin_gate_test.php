<?php
declare(strict_types=1);

/** LS-89 BE-4: an admin route whose action has no check of its own still cannot be reached by a visitor; public routes are not affected. */
require __DIR__ . '/bootstrap.php';

use App\Core\RedirectException;
use App\Core\Router;

function assert_ag(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

final class UncheckedProbeController
{
    public static int $calls = 0;
    public function run(): void { self::$calls++; }
}

$_SESSION = [];
$router = new Router();
$router->get('/admin', [UncheckedProbeController::class, 'run']);
$router->get('/admin/forgot-the-check', [UncheckedProbeController::class, 'run']);
$router->post('/admin/forgot-the-check', [UncheckedProbeController::class, 'run']);
$router->get('/administration-guide', [UncheckedProbeController::class, 'run']);
$go = static function (string $method, string $uri) use ($router): ?string {
    $_SERVER['REQUEST_METHOD'] = $method; $_SERVER['REQUEST_URI'] = $uri;
    try { $router->dispatch($method, $uri); } catch (RedirectException $e) { return $e->path; }
    return null;
};
foreach ([['GET', '/admin'], ['GET', '/admin/forgot-the-check'], ['HEAD', '/admin/forgot-the-check'], ['POST', '/admin/forgot-the-check']] as [$method, $uri]) {
    $_SESSION = [];
    assert_ag($go($method, $uri) === '/login', "$method $uri must send a visitor to the login page");
}
assert_ag(UncheckedProbeController::$calls === 0, 'no unchecked admin action ran for a visitor');
$_SESSION = [];
$go('GET', '/admin/forgot-the-check');
assert_ag(($_SESSION['auth_return_to'] ?? '') === '/admin/forgot-the-check', 'a GET remembers where the visitor was going');
assert_ag($go('GET', '/administration-guide') === null && UncheckedProbeController::$calls === 1, 'a public address that only starts with the same letters is not an admin route');

echo "admin_gate_test: PASS\n";
