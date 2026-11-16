<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Http\FileCache;
use App\Services\Http\RateLimiter;

/** Rate limiter and TTL cache (LS-3 J-4) with an injected clock; the cache directory lives inside the project (the Linux-parity rule) and is removed afterwards. */
function ok_rl(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
function rl_rmdir(string $d): void { foreach (array_diff(@scandir($d) ?: [], ['.', '..']) as $n) { $f = $d . '/' . $n; is_dir($f) ? rl_rmdir($f) : @unlink($f); } @rmdir($d); }

$dir = ROOT_PATH . '/storage/cache/test-ratelimit-' . bin2hex(random_bytes(4));
$now = 1_800_000_000;
$clock = static function () use (&$now): int { return $now; };
try {
    $rl = new RateLimiter($dir, $clock);
    // 3 requests per 60 s
    for ($i = 1; $i <= 3; $i++) {
        $r = $rl->attempt('deal', '203.0.113.5', 3, 60);
        ok_rl($r['allowed'] && $r['remaining'] === 3 - $i, "Request $i is allowed with " . (3 - $i) . ' left');
    }
    $r = $rl->attempt('deal', '203.0.113.5', 3, 60);
    ok_rl(!$r['allowed'] && $r['remaining'] === 0 && $r['retry_after'] === 60, 'The 4th request is refused and told to wait the whole window: ' . json_encode($r));
    $now += 25;
    $r = $rl->attempt('deal', '203.0.113.5', 3, 60);
    ok_rl(!$r['allowed'] && $r['retry_after'] === 35, 'Retry-After counts down: ' . json_encode($r));
    ok_rl($rl->attempt('deal', '203.0.113.6', 3, 60)['allowed'], 'Another client has its own budget');
    ok_rl($rl->attempt('build', '203.0.113.5', 3, 60)['allowed'], 'Another bucket has its own budget');
    $now += 35;
    $r = $rl->attempt('deal', '203.0.113.5', 3, 60);
    ok_rl($r['allowed'] && $r['remaining'] === 2, 'A new window starts after the old one ended');
    // the client address is hashed in the file name, never written as is
    $names = implode(' ', array_map('basename', glob($dir . '/*.json') ?: []));
    ok_rl(!str_contains($names, '203.0.113') && str_contains($names, 'deal-'), 'File names carry a hash, not the address');
    // purge removes stale windows only
    $now += 100000;
    $rl->attempt('deal', '203.0.113.9', 3, 60);
    ok_rl($rl->purge(86400) >= 3 && count(glob($dir . '/*.json') ?: []) === 1, 'Purge removes old files and keeps the live one');
    // an unwritable cache must not take the form down (fail open). The parent of the cache directory is a FILE, so creating it is impossible.
    @mkdir($dir, 0775, true);
    file_put_contents($dir . '/blocker', 'file');
    $blocked = new RateLimiter($dir . '/blocker/sub', $clock);
    ok_rl($rl->attempt('proof', 'y', 0, 60)['allowed'] === false, 'Control: a budget of 0 is refused when the cache works');
    ok_rl($blocked->attempt('proof', 'y', 0, 60)['allowed'] === true, 'An unusable cache directory fails open (the same budget of 0 is allowed)');

    // TTL cache
    $cache = new FileCache($dir . '/c', $clock);
    $calls = 0;
    $produce = function () use (&$calls): string { $calls++; return "value-$calls"; };
    ok_rl($cache->remember('sitemap.xml', 600, $produce) === 'value-1' && $calls === 1, 'The first call produces the value');
    $now += 599;
    ok_rl($cache->remember('sitemap.xml', 600, $produce) === 'value-1' && $calls === 1, 'Within the TTL the cached value is served');
    $now += 2;
    ok_rl($cache->remember('sitemap.xml', 600, $produce) === 'value-2' && $calls === 2, 'After the TTL the value is produced again');
    ok_rl($cache->remember('other', 600, $produce) === 'value-3', 'Keys are independent');
    ok_rl($cache->remember('../escape', 600, static fn() => 'x') === 'x' && !file_exists(ROOT_PATH . '/storage/cache/escape.cache') && !file_exists($dir . '/../escape.cache'), 'A key with path characters cannot leave the cache directory');
    $multi = new FileCache($dir . '/m', $clock);
    ok_rl($multi->remember('k', 60, static fn() => "line1\nline2\n") === "line1\nline2\n" && $multi->remember('k', 60, static fn() => 'no') === "line1\nline2\n", 'Multi-line values (XML) survive the round trip');
    $bad = new FileCache($dir . '/blocker/sub', $clock);
    ok_rl($bad->remember('k', 60, static fn() => 'fresh') === 'fresh' && $bad->remember('k', 60, static fn() => 'again') === 'again', 'An unwritable cache returns the fresh value every time and never fails');
} finally {
    rl_rmdir($dir);
}

echo "rate_limit_cache_test: PASS\n";
