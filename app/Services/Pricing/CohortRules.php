<?php
declare(strict_types=1);

namespace App\Services\Pricing;

use App\Services\Provenance\ObservationProvenanceService;

/**
 * LS-89 BE-1: the one rule that says which observation may enter a price calculation.
 * The price snapshot and the monthly trend both read rows with selectSql() and filter them with exclusionReason(),
 * so a row can never count in one of them and not in the other.
 */
final class CohortRules
{
    /** the columns the rule and the calculation need; the caller appends WHERE and ORDER BY over alias o */
    public static function selectSql(): string
    {
        return "SELECT o.id observation_id,o.product_id,COALESCE(o.price_value,o.asking_price) calculation_price,o.asking_price,o.price_value,o.observed_at,o.product_variant_id,o.verified_status,o.price_type,o.listing_type,o.is_deposit,o.is_defective,o.is_duplicate,o.quality_flags,o.final_weight,r.source_id,r.raw_title,r.raw_price_text,r.source_url_encrypted,r.external_reference_hash,s.source_key,s.name source_name,s.domain source_domain,e.excerpt
             ,o.evidence_level,s.evidence_quality source_evidence_quality
             FROM price_observations o
             LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id
             LEFT JOIN data_sources s ON s.id=r.source_id
             LEFT JOIN market_evidence e ON e.raw_observation_id=r.id";
    }

    /** null when the row counts, otherwise the stored reason code (the order of the checks is part of the recorded manifest) */
    public static function exclusionReason(array $row, string $priceType): ?string
    {
        if (($row['verified_status'] ?? '') !== 'approved') {
            return 'NOT_ACCEPTED';
        }
        if (($row['price_type'] ?? '') !== $priceType) {
            return 'WRONG_PRICE_TYPE';
        }
        if ($row['product_variant_id'] !== null) {
            return 'OUTSIDE_COHORT';
        }
        if (($row['listing_type'] ?? '') !== 'single_item') {
            return 'OUTSIDE_COHORT';
        }
        $qualityFlags = json_decode((string)($row['quality_flags'] ?? '[]'), true);
        $qualityFlags = is_array($qualityFlags) ? array_map('strval', $qualityFlags) : [];
        foreach (['WRONG_PRODUCT', 'MOBILE_GPU', 'WANTED', 'WHOLE_PC', 'BUNDLE', 'DEPOSIT', 'DEFECTIVE'] as $flag) {
            if (in_array($flag, $qualityFlags, true)) {
                return $flag;
            }
        }
        if ((float)$row['calculation_price'] <= 0) {
            return 'INVALID_PRICE';
        }
        if ((int)$row['is_deposit'] === 1) {
            return 'DEPOSIT';
        }
        if ((int)$row['is_defective'] === 1) {
            return 'DEFECTIVE';
        }
        if ((int)$row['is_duplicate'] === 1) {
            return 'DUPLICATE';
        }
        if (self::isMockSource($row)) {
            return 'MOCK_SOURCE';
        }
        if (NewPriceReferenceSources::isReference((string)($row['source_key'] ?? ''))) {
            return 'NEW_PRICE_REFERENCE_SOURCE';
        }
        if (self::isRealOfflineSource($row) && !(new ObservationProvenanceService())->isListingLevel($row)) {
            return 'INSUFFICIENT_PROVENANCE';
        }
        // LS-90 T1: a listing that says the item is brand new is not a used price. Last, so the earlier reason codes are unchanged.
        if ($priceType !== 'new' && BrandNewClaim::detect((string)($row['raw_title'] ?? '') . ' ' . (string)($row['excerpt'] ?? '')) !== null) {
            return 'BRAND_NEW_CLAIM';
        }
        return null;
    }

    private static function isMockSource(array $row): bool
    {
        $sourceKey = strtolower((string)($row['source_key'] ?? ''));
        $sourceName = strtolower((string)($row['source_name'] ?? ''));
        $sourceDomain = strtolower((string)($row['source_domain'] ?? ''));
        return str_contains($sourceKey, 'mock') || str_contains($sourceName, 'mock') || str_starts_with($sourceDomain, 'mock.');
    }

    private static function isRealOfflineSource(array $row): bool
    {
        $sourceKey = strtolower((string)($row['source_key'] ?? ''));
        $sourceName = strtolower((string)($row['source_name'] ?? ''));
        return str_starts_with($sourceKey, 'offline_real_') || str_contains($sourceName, 'offline real import');
    }
}
