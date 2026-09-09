<?php
declare(strict_types=1);

namespace App\Data;

final class SearchRequest
{
    public function __construct(
        public string $query,
        public ?int $productId = null,
        public int $limit = 10
    ) {
    }
}
