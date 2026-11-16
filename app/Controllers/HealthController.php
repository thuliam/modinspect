<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/**
 * Liveness/readiness probe for a host or uptime monitor (LS-2 H-11). Public, so it reveals almost nothing:
 * only "ok" or "unavailable" and whether the database answers. No versions, paths, counts, hostnames or error text.
 */
final class HealthController extends Controller
{
    public function check(): void
    {
        global $config;
        $dbOk = false;
        try {
            $dbOk = Database::connection($config['db'])->query('SELECT 1')->fetchColumn() !== false;
        } catch (\Throwable) {
            $dbOk = false;
        }
        http_response_code($dbOk ? 200 : 503);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['status' => $dbOk ? 'ok' : 'unavailable', 'database' => $dbOk ? 'ok' : 'unavailable']), "\n";
    }
}
