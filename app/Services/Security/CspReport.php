<?php
declare(strict_types=1);
namespace App\Services\Security;

/**
 * One log line from a browser's Content-Security-Policy violation report (LS-21: the policy is enforcing, so a blocked thing must be findable afterwards).
 * Only the directive, what was blocked and the page PATH are kept. Query strings are dropped from both addresses (a search term or a share link can sit
 * there), control characters are removed and every field is cut short, so a hostile report can neither forge log lines nor fill the log. Pure.
 */
final class CspReport
{
    public const MAX_BODY = 4096;

    /** @return ?string the log line, or null when the body is not a CSP report */
    public static function line(string $body): ?string
    {
        if ($body === '' || strlen($body) > self::MAX_BODY) { return null; }
        $j = json_decode($body, true);
        $r = is_array($j) ? ($j['csp-report'] ?? null) : null;
        if (!is_array($r)) { return null; }
        $directive = self::clean((string)($r['effective-directive'] ?? $r['violated-directive'] ?? ''), 40);
        if ($directive === '') { return null; }
        return sprintf('[modinspect-csp] directive=%s blocked=%s page=%s disposition=%s', $directive, self::where((string)($r['blocked-uri'] ?? '')), self::where((string)($r['document-uri'] ?? '')),
            self::clean((string)($r['disposition'] ?? 'enforce'), 10));
    }

    /** an address reduced to scheme, host and path (or the keyword the browser sent: inline, eval, data, ...) */
    private static function where(string $u): string
    {
        $u = trim($u);
        if ($u === '' || !str_contains($u, '/')) { return self::clean($u === '' ? '-' : $u, 20); }
        $p = parse_url($u);
        if (!is_array($p) || !isset($p['host'])) { return '-'; }
        return self::clean(($p['scheme'] ?? '?') . '://' . $p['host'] . ($p['path'] ?? ''), 160);
    }

    private static function clean(string $s, int $max): string
    {
        // printable ASCII and Thai only; then brackets, spaces and "=" become "_" so a field can never imitate the line's own markers ("[modinspect-csp]", "key=value")
        $s = (string)preg_replace('/[^\x20-\x7E\x{0E00}-\x{0E7F}]/u', '', $s);
        return mb_substr((string)preg_replace('/[\[\] =]/', '_', $s), 0, $max);
    }
}
