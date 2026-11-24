<?php
declare(strict_types=1);
namespace App\Services\Push;

/**
 * LS-86: validation of what a browser sends when a visitor allows notifications. Pure. Accepts only an https endpoint and the two base64url keys of the
 * Push API (p256dh 65 bytes, auth 16 bytes), plus an optional watch list of known-shaped slugs. Anything else is refused, nothing is repaired.
 */
final class PushPayload
{
    public const MAX_WATCH = 12;   // same ceiling as WatchList::MAX

    /** @param array<string,mixed> $in @return array{ok:bool,error:?string,endpoint:string,p256dh:string,auth:string,watch:list<array{slug:string,median:?float}>} */
    public static function validate(array $in): array
    {
        $bad = static fn(string $e): array => ['ok' => false, 'error' => $e, 'endpoint' => '', 'p256dh' => '', 'auth' => '', 'watch' => []];
        $endpoint = is_string($in['endpoint'] ?? null) ? trim($in['endpoint']) : '';
        if ($endpoint === '' || strlen($endpoint) > 1024 || !str_starts_with($endpoint, 'https://') || filter_var($endpoint, FILTER_VALIDATE_URL) === false) { return $bad('endpoint'); }
        $keys = is_array($in['keys'] ?? null) ? $in['keys'] : [];
        $p = is_string($keys['p256dh'] ?? null) ? $keys['p256dh'] : '';
        $a = is_string($keys['auth'] ?? null) ? $keys['auth'] : '';
        if (preg_match('/^[A-Za-z0-9_-]{86,88}$/', $p) !== 1) { return $bad('p256dh'); }
        if (preg_match('/^[A-Za-z0-9_-]{22,24}$/', $a) !== 1) { return $bad('auth'); }
        $watch = [];
        foreach (is_array($in['watch'] ?? null) ? $in['watch'] : [] as $w) {
            if (!is_array($w) || !is_string($w['slug'] ?? null) || preg_match('/^[a-z0-9][a-z0-9-]{0,118}$/', $w['slug']) !== 1) { continue; }
            $m = isset($w['median']) && is_numeric($w['median']) && (float)$w['median'] > 0 ? round((float)$w['median'], 2) : null;
            $watch[$w['slug']] = ['slug' => $w['slug'], 'median' => $m];
            if (count($watch) >= self::MAX_WATCH) { break; }
        }
        return ['ok' => true, 'error' => null, 'endpoint' => $endpoint, 'p256dh' => $p, 'auth' => $a, 'watch' => array_values($watch)];
    }
}
