<?php
declare(strict_types=1);

namespace App\Services\Http;

/**
 * Tiny time-to-live cache of one string per key in a cache directory (LS-3 J-4: the public sitemap runs a query per request otherwise).
 * A failed write never fails the request: the freshly produced value is returned anyway. The clock is injectable for tests.
 */
final class FileCache
{
    /** @var callable():int */
    private $clock;

    public function __construct(private string $dir, ?callable $clock = null)
    {
        $this->clock = $clock ?? static fn(): int => time();
    }

    /** @param callable():string $producer */
    public function remember(string $key, int $ttlSeconds, callable $producer): string
    {
        $file = $this->dir . '/' . preg_replace('/[^a-z0-9_.-]/i', '', $key) . '.cache';
        $now = ($this->clock)();
        if (is_file($file)) {
            $raw = (string)@file_get_contents($file);
            $nl = strpos($raw, "\n");
            if ($nl !== false && $now - (int)substr($raw, 0, $nl) < $ttlSeconds) { return substr($raw, $nl + 1); }
        }
        $value = $producer();
        if (is_dir($this->dir) || @mkdir($this->dir, 0775, true) || is_dir($this->dir)) {
            $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (@file_put_contents($tmp, $now . "\n" . $value) !== false) { @rename($tmp, $file) || @unlink($tmp); }
        }
        return $value;
    }
}
