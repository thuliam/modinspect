<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\Push\PushPayload;
use App\Services\Push\SubscriptionStore;

/**
 * LS-86: subscribe and unsubscribe for price-change push. Every route answers 404 unless PUSH_ENABLED=true (default false), so nothing is reachable by default.
 * JSON in, JSON out; the CSRF token travels in the X-CSRF-Token header. Stores only what PushPayload accepts.
 */
final class PushController extends Controller
{
    public function subscribe(): void { $this->handle(true); }

    public function unsubscribe(): void { $this->handle(false); }

    private function handle(bool $subscribe): void
    {
        global $config;
        if (empty($config['push']['enabled'])) { $this->json(404, ['status' => 'error', 'message' => 'not found']); return; }
        if (!Csrf::verify(is_string($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : null)) { $this->json(419, ['status' => 'error', 'message' => 'token']); return; }
        $raw = file_get_contents('php://input');
        $in = is_string($raw) && strlen($raw) <= 8192 ? json_decode($raw, true) : null;
        if (!is_array($in)) { $this->json(400, ['status' => 'error', 'message' => 'body']); return; }
        $p = PushPayload::validate($in);
        if (!$p['ok']) { $this->json(422, ['status' => 'error', 'message' => (string)$p['error']]); return; }
        $store = new SubscriptionStore(Database::connection($config['db']));
        if ($subscribe) { $store->save($p); $this->json(200, ['status' => 'success', 'message' => 'subscribed']); return; }
        $store->remove($p['endpoint']);
        $this->json(200, ['status' => 'success', 'message' => 'unsubscribed']);
    }

    /** @param array<string,mixed> $body */
    private function json(int $code, array $body): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($body, JSON_UNESCAPED_UNICODE);
    }
}
