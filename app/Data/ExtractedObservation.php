<?php
declare(strict_types=1);

namespace App\Data;

final class ExtractedObservation
{
    public function __construct(
        public ?float $askingPrice,
        public string $currency,
        public string $listingType,
        public string $conditionLevel,
        public ?int $warrantyMonths,
        public ?bool $hasBox,
        public ?bool $hasReceipt,
        public bool $isWantedPost,
        public bool $isDeposit,
        public bool $isDefective,
        public bool $isWholePc,
        public float $confidence,
        public array $raw = []
    ) {
    }
}
