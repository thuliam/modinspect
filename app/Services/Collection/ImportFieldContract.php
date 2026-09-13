<?php
declare(strict_types=1);

namespace App\Services\Collection;

final class ImportFieldContract
{
    public const CSV_HEADERS = [
        'source',
        'source_search_url',
        'source_listing_url',
        'source_item_id',
        'external_ref',
        'merchant',
        'merchant_target_url',
        'listing_title',
        'listing_text',
        'asking_price',
        'observed_at',
        'currency',
        'source_domain',
        'condition_hint',
        'warranty_hint',
        'evidence_snapshot_json',
        'evidence_hash',
        'ingestion_note',
        'notes',
        'source_type',
        'source_url',
        'source_reference',
        'title',
        'displayed_price',
        'external_listing_id',
        'product_hint',
        'probe_run_id',
    ];

    /**
     * @return array<int,array<string,string>>
     */
    public static function fields(): array
    {
        return [
            ['name' => 'source', 'status' => 'Recommended', 'meaning' => 'Marketplace/source label shown in provenance.', 'example' => 'priceza', 'notes' => 'Used as metadata; source_domain/URL determine imported data source key.'],
            ['name' => 'source_search_url', 'status' => 'Optional', 'meaning' => 'Search/result page used to find the listing.', 'example' => 'https://www.priceza.com/s/amd-ryzen-7-5700x3d', 'notes' => 'Useful context, but REAL rows still need listing-level reference.'],
            ['name' => 'source_listing_url', 'status' => 'Conditionally required for REAL', 'meaning' => 'Exact listing/result-item URL or redirect URL.', 'example' => 'https://www.priceza.com/r/redirect?id=EXAMPLE-ITEM-001', 'notes' => 'REAL needs this or a source item/reference field.'],
            ['name' => 'source_item_id', 'status' => 'Conditionally required for REAL', 'meaning' => 'Stable source item/result identifier.', 'example' => 'EXAMPLE-ITEM-001', 'notes' => 'Preferred listing-level identity for public-source evidence.'],
            ['name' => 'external_ref', 'status' => 'Optional reference', 'meaning' => 'External row/listing reference supplied by the collection process.', 'example' => 'EXAMPLE-REF-001', 'notes' => 'Can satisfy REAL listing-level reference when source_item_id is unavailable.'],
            ['name' => 'merchant', 'status' => 'Optional', 'meaning' => 'Visible public seller/store name.', 'example' => 'Example Store', 'notes' => 'Do not include phone numbers, private chat, or private address.'],
            ['name' => 'merchant_target_url', 'status' => 'Optional', 'meaning' => 'Public merchant destination URL if safely available.', 'example' => 'https://merchant.example.test/item/EXAMPLE-ITEM-001', 'notes' => 'Not required when source item identity and evidence snapshot are enough.'],
            ['name' => 'listing_title', 'status' => 'Required if listing_text/title absent', 'meaning' => 'Exact visible listing/result title.', 'example' => 'EXAMPLE TEMPLATE AMD Ryzen 7 5700X3D', 'notes' => 'Preferred title field; importer also accepts title.'],
            ['name' => 'listing_text', 'status' => 'Required if no title field', 'meaning' => 'Visible listing description or item text.', 'example' => 'Example placeholder row, asking 7500 THB.', 'notes' => 'Maximum 2000 characters.'],
            ['name' => 'asking_price', 'status' => 'Recommended', 'meaning' => 'Visible asking/listing price text.', 'example' => '7500', 'notes' => 'Importer also accepts displayed_price. Currency defaults to THB.'],
            ['name' => 'observed_at', 'status' => 'Required', 'meaning' => 'Capture timestamp.', 'example' => '2026-09-13 10:30:00', 'notes' => 'Must be parseable and not more than one day in the future.'],
            ['name' => 'currency', 'status' => 'Optional', 'meaning' => 'Currency code.', 'example' => 'THB', 'notes' => 'Only THB or blank is currently supported.'],
            ['name' => 'source_domain', 'status' => 'Optional', 'meaning' => 'Source host/domain.', 'example' => 'www.priceza.com', 'notes' => 'Used to group offline import data sources.'],
            ['name' => 'condition_hint', 'status' => 'Optional', 'meaning' => 'Condition hint from visible evidence.', 'example' => 'good', 'notes' => 'Current extractor derives condition from title/text; this is retained as row context only.'],
            ['name' => 'warranty_hint', 'status' => 'Optional', 'meaning' => 'Warranty hint from visible evidence.', 'example' => '6 months', 'notes' => 'Current extractor derives warranty from title/text; this is retained as row context only.'],
            ['name' => 'evidence_snapshot_json', 'status' => 'Recommended for authoritative REAL', 'meaning' => 'Immutable capture-time evidence fragment.', 'example' => '{"source_item_id":"EXAMPLE-ITEM-001","listing_title":"EXAMPLE TEMPLATE AMD Ryzen 7 5700X3D","asking_price":"7500"}', 'notes' => 'Preserve only public evidence needed for auditability.'],
            ['name' => 'evidence_hash', 'status' => 'Recommended for authoritative REAL', 'meaning' => 'Checksum/hash of the evidence snapshot.', 'example' => '0000000000000000000000000000000000000000000000000000000000000000', 'notes' => 'Used for auditability; current importer preserves it in evidence metadata.'],
            ['name' => 'ingestion_note', 'status' => 'Optional', 'meaning' => 'Short note about collection/import context.', 'example' => 'Template example only; not market evidence.', 'notes' => 'Keep this separate from title/listing text.'],
            ['name' => 'notes', 'status' => 'Optional', 'meaning' => 'Administrative note.', 'example' => 'EXAMPLE / TEMPLATE ONLY', 'notes' => 'Included in private evidence excerpt.'],
            ['name' => 'source_type', 'status' => 'Optional/system context', 'meaning' => 'Source type marker.', 'example' => 'marketplace', 'notes' => 'REAL dry-run rejects mock/test/fixture markers.'],
            ['name' => 'source_url', 'status' => 'Legacy optional', 'meaning' => 'Legacy source URL fallback.', 'example' => 'https://www.priceza.com/r/redirect?id=EXAMPLE-ITEM-001', 'notes' => 'Prefer source_listing_url for new files.'],
            ['name' => 'source_reference', 'status' => 'Legacy optional', 'meaning' => 'Legacy external reference fallback.', 'example' => 'EXAMPLE-REF-001', 'notes' => 'Prefer source_item_id or external_ref for new files.'],
            ['name' => 'title', 'status' => 'Legacy optional', 'meaning' => 'Legacy title fallback.', 'example' => 'EXAMPLE TEMPLATE AMD Ryzen 7 5700X3D', 'notes' => 'Prefer listing_title for new files.'],
            ['name' => 'displayed_price', 'status' => 'Legacy optional', 'meaning' => 'Legacy visible price fallback.', 'example' => '7500', 'notes' => 'Prefer asking_price for new files.'],
            ['name' => 'external_listing_id', 'status' => 'Legacy optional', 'meaning' => 'Legacy external listing ID fallback.', 'example' => 'EXAMPLE-ITEM-001', 'notes' => 'Prefer source_item_id for new files.'],
            ['name' => 'product_hint', 'status' => 'Optional', 'meaning' => 'Human-readable model hint for reporting.', 'example' => '5700X3D', 'notes' => 'Resolution still uses deterministic title/alias matching.'],
            ['name' => 'probe_run_id', 'status' => 'Optional operational metadata', 'meaning' => 'Collection/probe run identifier, if present.', 'example' => 'EXAMPLE-RUN-001', 'notes' => 'Useful for review scoping; do not fabricate it.'],
        ];
    }

    /**
     * @return array<string,string>
     */
    public static function exampleRecord(): array
    {
        $snapshot = [
            'template' => 'EXAMPLE / TEMPLATE ONLY',
            'source' => 'example_market',
            'source_item_id' => 'EXAMPLE-ITEM-001',
            'listing_title' => 'EXAMPLE TEMPLATE AMD Ryzen 7 5700X3D',
            'asking_price' => '7500',
            'merchant' => 'Example Store',
            'observed_at' => '2026-09-13 10:30:00',
        ];

        return [
            'source' => 'example_market',
            'source_search_url' => 'https://example.test/search?q=5700x3d',
            'source_listing_url' => 'https://example.test/listing/EXAMPLE-ITEM-001',
            'source_item_id' => 'EXAMPLE-ITEM-001',
            'external_ref' => 'EXAMPLE-REF-001',
            'merchant' => 'Example Store',
            'merchant_target_url' => 'https://merchant.example.test/item/EXAMPLE-ITEM-001',
            'listing_title' => 'EXAMPLE TEMPLATE AMD Ryzen 7 5700X3D',
            'listing_text' => 'EXAMPLE / TEMPLATE ONLY. Replace this with visible listing text before import. Asking 7500 THB.',
            'asking_price' => '7500',
            'observed_at' => '2026-09-13 10:30:00',
            'currency' => 'THB',
            'source_domain' => 'example.test',
            'condition_hint' => '',
            'warranty_hint' => '',
            'evidence_snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '',
            'evidence_hash' => str_repeat('0', 64),
            'ingestion_note' => 'EXAMPLE / TEMPLATE ONLY. Replace before import.',
            'notes' => 'EXAMPLE / TEMPLATE ONLY. Not a business observation.',
            'source_type' => 'template_test',
            'source_url' => 'https://example.test/listing/EXAMPLE-ITEM-001',
            'source_reference' => 'EXAMPLE-REF-001',
            'title' => 'EXAMPLE TEMPLATE AMD Ryzen 7 5700X3D',
            'displayed_price' => '7500',
            'external_listing_id' => 'EXAMPLE-ITEM-001',
            'product_hint' => '5700X3D',
            'probe_run_id' => 'EXAMPLE-RUN-001',
        ];
    }

    public static function csvTemplate(): string
    {
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return '';
        }
        fputcsv($out, self::CSV_HEADERS);
        $row = self::exampleRecord();
        fputcsv($out, array_map(static fn(string $header): string => $row[$header] ?? '', self::CSV_HEADERS));
        rewind($out);
        $contents = stream_get_contents($out);
        fclose($out);
        return (string)$contents;
    }

    public static function jsonTemplate(): string
    {
        return json_encode([
            'template' => 'EXAMPLE / TEMPLATE ONLY - replace values before import',
            'records' => [self::exampleRecord()],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}
