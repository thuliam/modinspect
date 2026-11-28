<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\Auth\AuthService;

/**
 * LS-89 BE-4: the router asks this before any /admin action runs, so a new action that forgets its own check still cannot be used by a visitor.
 * It only requires a signed-in user and answers exactly like the per-action check (redirect to the login page). Which role may do what, and the
 * CSRF token of a form, stay in each action (tests/admin_route_guard_test.php requires both).
 */
final class AdminGate extends Controller
{
    public static function covers(string $uri): bool
    {
        return $uri === '/admin' || str_starts_with($uri, '/admin/');
    }

    public function requireSignedIn(string $method): void
    {
        if ((new AuthService())->currentUser() !== null) {
            return;
        }
        if ($method === 'GET') {
            $_SESSION['auth_return_to'] = $_SERVER['REQUEST_URI'] ?? '/admin';
        }
        $this->redirect('/login');
    }
}
