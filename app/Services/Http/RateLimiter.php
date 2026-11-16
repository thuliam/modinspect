<?php
declare(strict_types=1);

namespace App\Services\Http;

/**
 * Fixed-window request limiter for public POST routes (LS-3 J-4). One small JSON file per (bucket, client) under a cache directory,
 * guarded by flock, so it needs no database and no extra service (zero-cost policy). The client key is the hash of REMOTE_ADDR:
 * behind a reverse proxy that is the proxy's address, so every visitor would share one budget (documented in the runbook).
 * The clock is injectable so the behaviour can be tested without sleeping.
 */
final class RateLimiter
{
    /** @var callable():int */
    private $clock;

    public function __construct(private string $dir, ?callable $clock = null)
    {
        $this->clock = $clock ?? static fn(): int => time();
    }

    /** @return array{allowed:bool,remaining:int,retry_after:int} */
    public function attempt(string $bucket, string $client, int $max, int $windowSeconds): array
    {
        $now = ($this->clock)();
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            return ['allowed' => true, 'remaining' => $max, 'retry_after' => 0];   // fail open: an unwritable cache must not take the form down
        }
        $file = $this->dir . '/' . preg_replace('/[^a-z0-9_-]/i', '', $bucket) . '-' . substr(hash('sha256', $client), 0, 24) . '.json';
        $fh = @fopen($file, 'c+');
        if ($fh === false) { return ['allowed' => true, 'remaining' => $max, 'retry_after' => 0]; }
        try {
            flock($fh, LOCK_EX);
            $state = json_decode((string)stream_get_contents($fh), true);
            $start = is_array($state) ? (int)($state['start'] ?? 0) : 0;
            $count = is_array($state) ? (int)($state['count'] ?? 0) : 0;
            if ($start <= 0 || $now - $start >= $windowSeconds) { $start = $now; $count = 0; }
            $allowed = $count < $max;
            if ($allowed) { $count++; }
            ftruncate($fh, 0); rewind($fh);
            fwrite($fh, json_encode(['start' => $start, 'count' => $count]));
            fflush($fh);
            return ['allowed' => $allowed, 'remaining' => max(0, $max - $count), 'retry_after' => $allowed ? 0 : max(1, $start + $windowSeconds - $now)];
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /** removes limiter files whose window ended long ago; returns how many were removed */
    public function purge(int $olderThanSeconds = 86400): int
    {
        $n = 0; $now = ($this->clock)();
        foreach (glob($this->dir . '/*.json') ?: [] as $f) {
            $s = json_decode((string)@file_get_contents($f), true);
            if (!is_array($s) || $now - (int)($s['start'] ?? 0) > $olderThanSeconds) { if (@unlink($f)) { $n++; } }
        }
        return $n;
    }
}
