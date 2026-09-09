<?php
declare(strict_types=1);

namespace App\Services\Validation;

use App\Data\ExtractedObservation;
use App\Data\ProductResolution;
use App\Data\ValidationResult;

final class ObservationValidator
{
    public const GENERIC_RULE_VERSION = 'generic-validation-v1';

    public function __construct(private bool $autoAcceptEnabled = false)
    {
    }

    public function validate(ExtractedObservation $extraction, ProductResolution $resolution): ValidationResult
    {
        $reasons = ['validation_version:' . self::GENERIC_RULE_VERSION];
        $rawFlags = $extraction->raw['quality_flags'] ?? [];
        if (is_array($rawFlags)) {
            $reasons = array_merge($reasons, array_map('strval', $rawFlags));
        }

        if ($extraction->askingPrice === null || $extraction->askingPrice <= 0) {
            $reasons[] = 'MISSING_PRICE';
        }
        if ($extraction->currency !== 'THB') {
            $reasons[] = 'UNSUPPORTED_CURRENCY';
        }
        if ($resolution->productId === null || $resolution->confidence < 0.80) {
            $reasons[] = 'UNRESOLVED_PRODUCT';
        }
        if ($extraction->isWantedPost || $extraction->listingType === 'wanted') {
            $reasons[] = 'WANTED';
        }
        if ($extraction->isDeposit) {
            $reasons[] = 'DEPOSIT';
        }
        if ($extraction->isWholePc || $extraction->listingType === 'whole_pc') {
            $reasons[] = 'WHOLE_PC';
        }
        if ($extraction->isDefective) {
            $reasons[] = 'DEFECTIVE';
        }
        if ($extraction->listingType === 'bundle') {
            $reasons[] = 'BUNDLE';
        }

        $categoryRules = (new ValidationRuleRegistry())->forMatchedName($resolution->matchedName);
        if ($categoryRules !== null) {
            $reasons[] = 'category_validation_version:' . $categoryRules->version();
            $reasons = array_merge($reasons, $categoryRules->flags($extraction, $resolution));
        }

        $reasons = array_values(array_unique($reasons));
        $redReasons = array_intersect($reasons, ['MISSING_PRICE', 'UNSUPPORTED_CURRENCY', 'UNRESOLVED_PRODUCT', 'WANTED', 'DEPOSIT', 'WHOLE_PC', 'DEFECTIVE', 'WRONG_PRODUCT', 'MOBILE_GPU']);
        if ($redReasons !== []) {
            return new ValidationResult('red', 'rejected', array_values($reasons));
        }

        $meaningfulReasons = array_values(array_filter(
            $reasons,
            static fn(string $reason): bool => !str_starts_with($reason, 'validation_version:')
                && !str_starts_with($reason, 'category_validation_version:')
                && $reason !== 'BOARD_PARTNER_CONTEXT'
        ));
        if ($meaningfulReasons !== [] || $extraction->confidence < 0.90 || $resolution->confidence < 0.95) {
            if ($meaningfulReasons === []) {
                $reasons[] = 'LOW_CONFIDENCE';
            }
            return new ValidationResult('amber', 'review_required', array_values(array_unique($reasons)));
        }

        $reasons[] = 'PASSES_RULES';
        return new ValidationResult('green', $this->autoAcceptEnabled ? 'accepted' : 'review_required', array_values(array_unique($reasons)));
    }
}
