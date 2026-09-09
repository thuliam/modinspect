<?php
declare(strict_types=1);

namespace App\Services\Validation;

final class ValidationRuleRegistry
{
    public function forMatchedName(?string $matchedName): ?CategoryValidationRulesInterface
    {
        $marker = $matchedName === null ? null : ModelMarker::primary($matchedName);
        return match (ModelMarker::category($marker)) {
            'cpu' => new CpuValidationRules(),
            'gpu' => new GpuValidationRules(),
            default => null,
        };
    }
}
