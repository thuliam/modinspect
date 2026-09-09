<?php
declare(strict_types=1);

namespace App\Data;

final class SearchCandidate
{
    public function __construct(
        public string $sourceName,
        public string $title,
        public ?string $priceText,
        public ?string $url,
        public string $capturedAt,
        public string $evidenceLevel = 'B',
        public ?string $snippet = null,
        public ?string $provider = null,
        public ?string $model = null,
        public ?string $query = null,
        public ?string $fetchedAt = null,
        public array $rawMetadata = []
    ) {
    }
}
