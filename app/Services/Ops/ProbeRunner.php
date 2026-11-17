<?php
declare(strict_types=1);

namespace App\Services\Ops;

/**
 * Post-deploy / uptime probe (LS-4 K-4). The fetcher is injected so the rules are testable without a network:
 * fetcher(path) returns [httpStatus, body, seconds]. Rules mirror the smoke checks in docs/DEPLOYMENT_RUNBOOK_HOSTATOM.md section 5.
 */
final class ProbeRunner
{
    public const PAGES = ['/', '/price', '/deal-checker', '/methodology', '/login', '/robots.txt', '/sitemap.xml'];

    /** @param callable(string):array{0:int,1:string,2:float} $fetch */
    public function __construct(private $fetch, private float $slowSeconds = 3.0) {}

    /** @return array{ok:bool,failures:list<string>,checked:int} */
    public function run(): array
    {
        $f = []; $n = 0;
        [$code, $body] = ($this->fetch)('/health'); $n++;
        $json = json_decode($body, true);
        if ($code !== 200 || ($json['status'] ?? null) !== 'ok') { $f[] = "/health answered $code"; }
        foreach (self::PAGES as $p) {
            [$code, $body, $t] = ($this->fetch)($p); $n++;
            if ($code !== 200) { $f[] = "$p answered $code"; continue; }
            if ($t > $this->slowSeconds) { $f[] = sprintf('%s took %.1fs', $p, $t); }
            if (preg_match('/PHP (Warning|Notice|Deprecated)|Fatal error|Stack trace/i', $body)) { $f[] = "$p shows a PHP error"; }
        }
        [$code] = ($this->fetch)('/admin'); $n++;
        if (!in_array($code, [301, 302, 303], true)) { $f[] = "/admin answered $code instead of a redirect to login"; }
        foreach (['/.env', '/app/bootstrap.php', '/composer.json'] as $p) {
            [$code] = ($this->fetch)($p); $n++;
            if (!in_array($code, [403, 404], true)) { $f[] = "$p must be 403 or 404 but answered $code"; }
        }
        return ['ok' => $f === [], 'failures' => $f, 'checked' => $n];
    }
}
