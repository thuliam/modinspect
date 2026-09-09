<?php
declare(strict_types=1);

namespace App\Data;

final class SearchResultSet
{
    /**
     * @param SearchCandidate[] $candidates
     */
    public function __construct(
        public string $provider,
        public array $candidates
    ) {
    }
}
