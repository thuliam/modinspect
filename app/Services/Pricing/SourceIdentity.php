<?php
declare(strict_types=1);

namespace App\Services\Pricing;

/**
 * LS-88 T2: which "source" a price row counts as when we say a price comes from several sources.
 *
 * The unit is the platform the listing came from, not the import batch. facebook.com, www.facebook.com and every owner-collected Facebook
 * text batch are ONE source: splitting an import into batches does not make the evidence more independent. Owner text batches that name no
 * platform come from the same collector, so together they are one source too.
 */
final class SourceIdentity
{
    private const SAME_PLATFORM = ['fb.com' => 'facebook.com'];

    public static function key(?string $domain, ?string $name, ?string $sourceKey, int|string|null $sourceId = null): string
    {
        $host = preg_replace('/^(?:www|m|web)\./', '', strtolower(trim((string)$domain))) ?? '';
        if ($host !== '') { return self::SAME_PLATFORM[$host] ?? $host; }

        $key = strtolower(trim((string)$sourceKey));
        if (str_starts_with($key, 'offline_owner_text_')) { return str_contains($key, 'facebook') ? 'facebook.com' : 'owner_text'; }

        $name = strtolower(trim((string)$name));
        if ($name !== '') { return $name; }
        return $sourceId === null || $sourceId === '' ? 'unknown' : (string)$sourceId;
    }

    /** @param array<string,mixed> $row a row carrying source_domain, source_name, source_key and source_id */
    public static function ofRow(array $row): string
    {
        return self::key($row['source_domain'] ?? null, $row['source_name'] ?? null, $row['source_key'] ?? null, $row['source_id'] ?? null);
    }
}
