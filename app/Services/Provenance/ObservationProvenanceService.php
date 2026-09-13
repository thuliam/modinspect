<?php
declare(strict_types=1);

namespace App\Services\Provenance;

final class ObservationProvenanceService
{
    public const LISTING_LEVEL = 'LISTING_LEVEL';
    public const SEARCH_RESULT_LEVEL = 'SEARCH_RESULT_LEVEL';
    public const GENERIC_SOURCE = 'GENERIC_SOURCE';

    public function classify(array $row): array
    {
        $sourceUrl = trim((string)($row['source_url_encrypted'] ?? $row['source_url'] ?? ''));
        $rawTitle = trim((string)($row['raw_title'] ?? ''));
        $rawPrice = trim((string)($row['raw_price_text'] ?? $row['display_price'] ?? $row['asking_price'] ?? ''));
        $excerpt = (string)($row['excerpt'] ?? '');
        $hash = trim((string)($row['external_reference_hash'] ?? ''));

        $fields = $this->metadataFields($excerpt);
        $sourceListingUrl = trim((string)($fields['SOURCE_LISTING_URL'] ?? ''));
        $sourceSearchUrl = trim((string)($fields['SOURCE_SEARCH_URL'] ?? ''));
        $sourceItemId = trim((string)($fields['SOURCE_ITEM_ID'] ?? $fields['EXTERNAL_LISTING_ID'] ?? ''));
        $externalRef = trim((string)($fields['EXTERNAL_REF'] ?? $fields['SOURCE_REFERENCE'] ?? ''));
        $merchant = trim((string)($fields['MERCHANT'] ?? ''));
        $source = strtolower(trim((string)($fields['SOURCE'] ?? '')));
        $listingTitle = trim((string)($fields['LISTING_TITLE'] ?? $rawTitle));
        $askingPrice = trim((string)($fields['ASKING_PRICE'] ?? $rawPrice));
        $observedAt = trim((string)($fields['OBSERVED_AT'] ?? $row['observed_at'] ?? ''));
        $evidenceSnapshot = trim((string)($fields['EVIDENCE_SNAPSHOT_JSON'] ?? $fields['EVIDENCE_SNAPSHOT_HTML'] ?? ''));
        $evidenceHash = strtolower(trim((string)($fields['EVIDENCE_HASH'] ?? $row['content_hash'] ?? '')));
        $merchantTargetUrl = trim((string)($fields['MERCHANT_TARGET_URL'] ?? ''));

        $hasTitleAndPrice = $rawTitle !== '' && $rawPrice !== '' && $listingTitle !== '' && $askingPrice !== '';
        $itemUrl = $sourceListingUrl !== '' ? $sourceListingUrl : $sourceUrl;
        $hasExactItemReference = $sourceItemId !== ''
            || $externalRef !== ''
            || $this->isPricezaResultUrl($itemUrl)
            || ($itemUrl !== '' && !$this->isSearchUrl($itemUrl) && $this->looksLikeHttpUrl($itemUrl));

        $hasImmutableSnapshot = $evidenceSnapshot !== '' && preg_match('/^[a-f0-9]{64}$/', $evidenceHash) === 1;
        $isPriceza = $source === 'priceza'
            || str_contains(strtolower($sourceListingUrl . ' ' . $sourceSearchUrl . ' ' . $sourceUrl), 'priceza.com');
        $pricezaListingLevel = $isPriceza
            && $source === 'priceza'
            && $sourceSearchUrl !== ''
            && $sourceItemId !== ''
            && $this->isPricezaResultUrl($sourceListingUrl)
            && $merchant !== ''
            && $observedAt !== ''
            && $hasTitleAndPrice
            && $hasImmutableSnapshot;

        $genericListingLevel = !$isPriceza && $hasTitleAndPrice && $hasExactItemReference && $hasImmutableSnapshot;

        if ($pricezaListingLevel || $genericListingLevel) {
            return [
                'quality' => self::LISTING_LEVEL,
                'label' => 'LISTING LEVEL',
                'eligible_for_public_pricing' => true,
                'reason' => 'Exact listing/result item reference and immutable capture-time evidence snapshot are present.',
                'fields' => [
                    'source_listing_url' => $sourceListingUrl ?: ($this->isSearchUrl($sourceUrl) ? null : ($sourceUrl ?: null)),
                    'source_search_url' => $sourceSearchUrl ?: ($this->isSearchUrl($sourceUrl) ? $sourceUrl : null),
                    'source_item_id' => $sourceItemId ?: $this->pricezaResultId($itemUrl),
                    'external_ref' => $externalRef ?: ($hash !== '' ? substr($hash, 0, 16) : null),
                    'merchant' => $merchant ?: null,
                    'merchant_target_url' => $merchantTargetUrl ?: null,
                    'evidence_hash' => $evidenceHash ?: null,
                ],
            ];
        }

        if ($this->isSearchUrl($sourceUrl) || $sourceSearchUrl !== '' || $this->mentionsSearchResultEvidence($excerpt)) {
            return [
                'quality' => self::SEARCH_RESULT_LEVEL,
                'label' => 'SEARCH RESULT LEVEL',
                'eligible_for_public_pricing' => false,
                'reason' => 'Only a search/results page is available; the exact listing item cannot be independently traced.',
                'fields' => [
                    'source_listing_url' => null,
                    'source_search_url' => $sourceSearchUrl ?: ($sourceUrl ?: null),
                    'source_item_id' => $sourceItemId ?: null,
                    'external_ref' => $externalRef ?: ($hash !== '' ? substr($hash, 0, 16) : null),
                    'merchant' => $merchant ?: null,
                ],
            ];
        }

        return [
            'quality' => self::GENERIC_SOURCE,
            'label' => 'GENERIC SOURCE',
            'eligible_for_public_pricing' => false,
            'reason' => 'Source is known, but no exact listing/result item reference is available.',
            'fields' => [
                'source_listing_url' => null,
                'source_search_url' => null,
                'source_item_id' => $sourceItemId ?: null,
                'external_ref' => $externalRef ?: ($hash !== '' ? substr($hash, 0, 16) : null),
                'merchant' => $merchant ?: null,
            ],
        ];
    }

    public function isListingLevel(array $row): bool
    {
        return $this->classify($row)['quality'] === self::LISTING_LEVEL;
    }

    private function metadataFields(string $excerpt): array
    {
        $fields = [];
        foreach (preg_split('/\R/', $excerpt) ?: [] as $line) {
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $key = strtoupper($key);
            if ($key !== '') {
                $fields[$key] = $value;
            }
        }
        return $fields;
    }

    private function isSearchUrl(string $url): bool
    {
        $lower = strtolower($url);
        if ($lower === '') {
            return false;
        }
        return str_contains($lower, 'priceza.com/s/')
            || str_contains($lower, '/search?')
            || str_contains($lower, 'search')
            || str_contains($lower, 'q=')
            || str_contains($lower, 'keyword=');
    }

    private function isPricezaResultUrl(string $url): bool
    {
        return str_contains(strtolower($url), 'priceza.com/r/redirect?id=');
    }

    private function pricezaResultId(string $url): ?string
    {
        if (preg_match('/[?&]id=([0-9]+)/', $url, $matches) === 1) {
            return $matches[1];
        }
        return null;
    }

    private function looksLikeHttpUrl(string $url): bool
    {
        return preg_match('#^https?://#i', $url) === 1;
    }

    private function mentionsSearchResultEvidence(string $excerpt): bool
    {
        $lower = strtolower($excerpt);
        return str_contains($lower, 'search result')
            || str_contains($lower, 'search-page')
            || str_contains($lower, 'priceza public search result');
    }
}
