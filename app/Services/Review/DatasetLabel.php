<?php
declare(strict_types=1);

namespace App\Services\Review;

/** LS-89: which dataset a source belongs to (REAL, MOCK_TEST or UNKNOWN), in PHP and as the matching SQL expression over alias s */
final class DatasetLabel
{
    public const REAL = 'REAL';
    public const MOCK_TEST = 'MOCK_TEST';
    public const UNKNOWN = 'UNKNOWN';

    public static function of(string $sourceKey, string $sourceName): string
    {
        $key = strtolower($sourceKey);
        $name = strtolower($sourceName);
        if (str_starts_with($key, 'offline_real_') || str_starts_with($key, 'offline_owner_text_') || str_contains($name, 'offline real import')) {
            return self::REAL;
        }
        foreach (['mock', 'fixture', 'test'] as $marker) {
            if (str_contains($key, $marker) || str_contains($name, $marker)) {
                return self::MOCK_TEST;
            }
        }
        return self::UNKNOWN;
    }

    public static function sqlExpression(): string
    {
        return "CASE WHEN LOWER(COALESCE(s.source_key,'')) LIKE 'offline_real_%' OR LOWER(COALESCE(s.source_key,'')) LIKE 'offline_owner_text_%' OR LOWER(COALESCE(s.name,'')) LIKE '%offline real import%' THEN 'REAL' WHEN LOWER(COALESCE(s.source_key,'')) LIKE '%mock%' OR LOWER(COALESCE(s.source_key,'')) LIKE '%fixture%' OR LOWER(COALESCE(s.source_key,'')) LIKE '%test%' OR LOWER(COALESCE(s.name,'')) LIKE '%mock%' OR LOWER(COALESCE(s.name,'')) LIKE '%fixture%' OR LOWER(COALESCE(s.name,'')) LIKE '%test%' THEN 'MOCK_TEST' ELSE 'UNKNOWN' END";
    }
}
