<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Services\Http\RateLimiter;
use App\Services\Security\CspReport;

/** LS-21: receives browser Content-Security-Policy violation reports (public POST by design: browsers send it without a form or a token). Writes one line to the server log, stores nothing. */
final class SecurityController extends Controller
{
    public function cspReport(): void
    {
        http_response_code(204);
        $limit = (new RateLimiter(ROOT_PATH . '/storage/cache/ratelimit'))->attempt('csp-report', (string)($_SERVER['REMOTE_ADDR'] ?? ''), 60, 600);
        if (!$limit['allowed']) { return; }
        $line = CspReport::line((string)file_get_contents('php://input', false, null, 0, CspReport::MAX_BODY + 1));
        if ($line !== null) { error_log($line); }
    }
}
