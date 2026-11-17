<?php
declare(strict_types=1);

namespace App\Services\Pricing;

/**
 * Sources whose prices are retail/new-stock references, never used-market asking prices (owner decision 2026-11-17, DL-077: Priceza is a
 * separate new-price reference). A row from one of these sources is kept as evidence but excluded from the used asking cohort, whatever its
 * provenance quality or its price_type, so approving it can never move a used price or lift its confidence.
 */
final class NewPriceReferenceSources
{
    public const KEYS = ['offline_real_priceza_com'];

    public static function isReference(string $sourceKey): bool
    {
        return in_array(strtolower(trim($sourceKey)), self::KEYS, true);
    }
}
