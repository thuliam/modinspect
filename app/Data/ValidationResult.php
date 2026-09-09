<?php
declare(strict_types=1);

namespace App\Data;

final class ValidationResult
{
    public function __construct(
        public string $lane,
        public string $reviewState,
        public array $reasonCodes
    ) {
    }
}
