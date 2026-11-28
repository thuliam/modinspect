<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\Auth\AuthService;
use App\Services\Auth\AuthorizationService;

/** LS-89: what every admin controller shares: the signed-in user, the server-side permission check, page rendering, flash messages and the audit line. */
abstract class AdminBaseController extends Controller
{
    private ?array $user = null;

    /** the page title is written in English in the code; Lang::label shows it in the chosen language when a text exists */
    protected function render(string $view, string $title, array $data = []): void
    {
        $this->view('admin/' . $view, ['title' => \App\Core\Lang::label($title) ?? $title, 'auth_user' => $this->user()] + $data);
    }

    protected function user(): ?array
    {
        return $this->user ??= (new AuthService())->currentUser();
    }

    protected function can(string $permission): bool
    {
        return (new AuthorizationService())->can($this->user(), $permission);
    }

    /** redirects a visitor to the login page, answers 403 to a user without the permission (and records it) */
    protected function requirePermission(string $permission): bool
    {
        $auth = new AuthService();
        $user = $auth->currentUser();
        if (!$user) {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
                $_SESSION['auth_return_to'] = $_SERVER['REQUEST_URI'] ?? '/admin';
            }
            $this->redirect('/login');
        }
        $this->user = $user;
        if (!(new AuthorizationService())->can($user, $permission)) {
            $auth->audit((int)$user['id'], 'auth_unauthorized', 'permission', null, ['permission' => $permission, 'path' => $_SERVER['REQUEST_URI'] ?? null]);
            http_response_code(403);
            $this->view('errors/403', ['title' => 'ไม่มีสิทธิ์เข้าถึง', 'auth_user' => $user]);
            return false;
        }
        return true;
    }

    protected function json(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    /** the message is a fixed text in the code (English or Thai); it is shown in the language chosen when the page is displayed after the redirect */
    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => \App\Core\Lang::message($message)];
    }

    protected function baseUrl(): string
    {
        global $config;
        return (string)$config['base_url'];
    }

    protected function audit(string $action, string $entityType, ?int $entityId, array $after): void
    {
        (new AuthService())->audit((int)$this->user()['id'], $action, $entityType, $entityId, $after);
    }

    /**
     * What happened to the public prices after a change, as one message. @param list<array{product_id:int,status:string,median:?float,sample:?int}> $results
     * @return array{type:string,text:string}
     */
    protected function priceResultMessage(array $results): array
    {
        if ($results === []) {
            return ['type' => 'success', 'text' => ''];
        }
        $names = [];
        global $config;
        $db = \App\Core\Database::connection($config['db']);
        $ids = implode(',', array_map(static fn(array $r): int => (int)$r['product_id'], $results));
        foreach ($db->query("SELECT id, full_name FROM products WHERE id IN ($ids)")->fetchAll() as $p) {
            $names[(int)$p['id']] = (string)$p['full_name'];
        }
        $parts = [];
        $type = 'success';
        $counts = ['updated' => 0, 'unchanged' => 0, 'below_minimum' => 0, 'failed' => 0];
        foreach ($results as $r) {
            $counts[$r['status']]++;
            if ($r['status'] === 'failed') {
                $type = 'danger';
            } elseif ($r['status'] === 'below_minimum' && $type !== 'danger') {
                $type = 'warning';
            }
            $parts[] = t('price.r.' . $r['status'], [
                'name' => $names[(int)$r['product_id']] ?? ('#' . $r['product_id']),
                'median' => $r['median'] === null ? '-' : number_format((float)$r['median']),
                'n' => (int)($r['sample'] ?? 0),
            ]);
        }
        $text = count($results) > 4 ? t('price.r.summary', $counts) : implode(' · ', $parts);
        return ['type' => $type, 'text' => $text];
    }
}
