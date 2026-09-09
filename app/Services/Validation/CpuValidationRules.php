<?php
declare(strict_types=1);

namespace App\Services\Validation;

use App\Data\ExtractedObservation;
use App\Data\ProductResolution;

final class CpuValidationRules implements CategoryValidationRulesInterface
{
    public const VERSION = 'cpu-validation-v1';

    public function version(): string
    {
        return self::VERSION;
    }

    public function flags(ExtractedObservation $extraction, ProductResolution $resolution): array
    {
        $title = (string)($extraction->raw['title'] ?? '');
        $titleMarkers = array_values(array_filter(ModelMarker::all($title), static fn(string $marker): bool => ModelMarker::category($marker) === 'cpu'));
        $matchedMarker = $resolution->matchedName === null ? null : ModelMarker::primary($resolution->matchedName);
        $flags = [];

        if (count($titleMarkers) > 1) {
            $flags[] = 'VARIANT_AMBIGUOUS';
        }
        if ($matchedMarker !== null && $titleMarkers !== [] && !in_array($matchedMarker, $titleMarkers, true)) {
            $flags[] = 'WRONG_PRODUCT';
        }

        return array_values(array_unique($flags));
    }
}
