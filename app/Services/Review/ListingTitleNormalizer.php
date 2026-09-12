<?php
declare(strict_types=1);

namespace App\Services\Review;

final class ListingTitleNormalizer
{
    private const SYSTEM_METADATA_MARKERS = [
        'Priceza public search result',
        'merchant=',
        'public listing title captured only',
        'no phone',
        'profile photo',
        'private chat',
        'exact address stored',
    ];

    public function normalize(string $title): string
    {
        $original = trim($title);
        $position = $this->firstMetadataPosition($original);
        if ($position === null) {
            return $this->compactWhitespace($original);
        }

        $clean = trim(substr($original, 0, $position));
        $clean = rtrim($clean, " \t\n\r\0\x0B;-");
        return $this->compactWhitespace($clean);
    }

    public function containsSystemMetadata(?string $value): bool
    {
        return $this->firstMetadataPosition((string)$value) !== null;
    }

    private function firstMetadataPosition(string $value): ?int
    {
        $first = null;
        foreach (self::SYSTEM_METADATA_MARKERS as $marker) {
            $position = stripos($value, $marker);
            if ($position === false) {
                continue;
            }
            $first = $first === null ? $position : min($first, $position);
        }
        return $first;
    }

    private function compactWhitespace(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    }
}
