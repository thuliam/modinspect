<?php
declare(strict_types=1);

namespace App\Data;

final class ProductResolution
{
    public function __construct(
        public ?int $productId,
        public ?string $matchedName,
        public float $confidence,
        public string $method
    ) {
    }
}
