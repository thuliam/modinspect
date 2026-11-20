<?php
declare(strict_types=1);

namespace App\Services\Blog;

/**
 * LS-25: the optional "publish at" time of an article. Takes the value of an HTML datetime-local input (2026-12-01T09:30) or a plain "Y-m-d H:i" and returns the
 * database form, or null when it is empty, unreadable, in the past by more than a day (a typo that would back-date the article) or more than two years ahead.
 * Pure. A null means "use the usual rule" (now, when the status is published).
 */
final class PublishAt
{
    public static function parse(?string $in, ?\DateTimeImmutable $now = null): ?string
    {
        $in = trim((string)$in);
        if ($in === '' || !preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(?::\d{2})?$/', $in, $m)) { return null; }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $m[1] . ' ' . $m[2]);
        if ($d === false || $d->format('Y-m-d H:i') !== $m[1] . ' ' . $m[2]) { return null; }
        $now ??= new \DateTimeImmutable('now');
        if ($d < $now->modify('-1 day') || $d > $now->modify('+2 years')) { return null; }
        return $d->format('Y-m-d H:i:00');
    }
}
