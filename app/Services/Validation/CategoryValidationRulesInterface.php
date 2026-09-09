<?php
declare(strict_types=1);

namespace App\Services\Validation;

use App\Data\ExtractedObservation;
use App\Data\ProductResolution;

interface CategoryValidationRulesInterface
{
    public function version(): string;

    /**
     * @return array<int,string>
     */
    public function flags(ExtractedObservation $extraction, ProductResolution $resolution): array;
}
