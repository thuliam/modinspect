<?php
declare(strict_types=1);

namespace App\Services\Validation;

use App\Data\ExtractedObservation;
use App\Data\ProductResolution;

final class GpuValidationRules implements CategoryValidationRulesInterface
{
    public const VERSION = 'gpu-validation-v1';

    public function version(): string
    {
        return self::VERSION;
    }

    public function flags(ExtractedObservation $extraction, ProductResolution $resolution): array
    {
        $title = (string)($extraction->raw['title'] ?? '');
        $text = mb_strtolower($title . ' ' . (string)($extraction->raw['price_text'] ?? ''), 'UTF-8');
        $titleMarkers = array_values(array_filter(ModelMarker::all($title), static fn(string $marker): bool => ModelMarker::category($marker) === 'gpu'));
        $matchedMarker = $resolution->matchedName === null ? null : ModelMarker::primary($resolution->matchedName);
        $flags = [];

        if (preg_match('/\b(laptop|notebook|mobile|laptop\s*gpu)\b|โน้ตบุ๊ก|โน๊ตบุ๊ค/u', $text)) {
            $flags[] = 'MOBILE_GPU';
        }
        if (count($titleMarkers) > 1) {
            $flags[] = 'VARIANT_AMBIGUOUS';
        }
        if ($matchedMarker !== null && $titleMarkers !== [] && !in_array($matchedMarker, $titleMarkers, true)) {
            $flags[] = 'WRONG_PRODUCT';
        }
        if (preg_match('/\b(asus\s*tuf|asus\s*rog\s*strix|rog\s*strix|msi\s*gaming\s*x|gigabyte\s*gaming\s*oc|zotac|galax)\b/u', $text)) {
            $flags[] = 'BOARD_PARTNER_CONTEXT';
        }

        return array_values(array_unique($flags));
    }
}
