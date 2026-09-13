<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Models\Dashboard;
use App\Services\Collection\OfflineEvidenceImportService;
use App\Services\Pricing\PriceSnapshotService;
use App\Services\Provenance\ObservationProvenanceService;

$classifier = new ObservationProvenanceService();
$listing = $classifier->classify([
    'raw_title' => 'Example item',
    'raw_price_text' => '1000',
    'source_url_encrypted' => 'https://www.priceza.com/r/redirect?id=123',
    'observed_at' => '2026-09-13 12:00:00',
    'content_hash' => str_repeat('a', 64),
    'excerpt' => implode("\n", [
        'SOURCE=priceza',
        'SOURCE_SEARCH_URL=https://www.priceza.com/s/%E0%B8%A3%E0%B8%B2%E0%B8%84%E0%B8%B2/example',
        'SOURCE_LISTING_URL=https://www.priceza.com/r/redirect?id=123',
        'SOURCE_ITEM_ID=123',
        'LISTING_TITLE=Example item',
        'ASKING_PRICE=1000',
        'OBSERVED_AT=2026-09-13 12:00:00',
        'MERCHANT=Example merchant',
        'EVIDENCE_HASH=' . str_repeat('a', 64),
        'EVIDENCE_SNAPSHOT_JSON={"source":"priceza","source_item_id":"123","listing_title":"Example item","asking_price":"1000","merchant":"Example merchant","observed_at":"2026-09-13 12:00:00"}',
    ]),
]);
$search = $classifier->classify([
    'raw_title' => 'Example item',
    'raw_price_text' => '1000',
    'source_url_encrypted' => 'https://www.priceza.com/s/ราคา/example',
]);

$base = '';
$auth_user = ['role' => 'admin'];
$back_path = '/admin/review-queue';
$products = [
    1 => ['id' => 1, 'full_name' => 'Example Product'],
];
$observation = [
    'id' => 1,
    'product_id' => 1,
    'full_name' => 'Example Product',
    'category_slug' => 'cpu',
    'dataset_label' => 'REAL',
    'lane' => 'green',
    'verified_status' => 'pending',
    'price_type' => 'asking',
    'display_price' => 1000,
    'source_name' => 'Example Source',
    'observed_at' => '2026-09-13 12:00:00',
    'display_title' => 'Example listing',
    'raw_title' => 'Example listing',
    'raw_price_text' => '1000',
    'condition_level' => 'unknown',
    'source_url_encrypted' => 'https://www.priceza.com/s/ราคา/example',
    'external_reference_hash' => str_repeat('a', 64),
    'extracted_display_title' => 'Example listing',
    'extraction_confidence' => 1,
    'classification_confidence' => 100,
    'provenance_quality' => $search,
    'warning_items' => [],
    'reason_codes_list' => [],
    'rule_result_data' => [],
    'resolution_diagnostic' => ['confidence' => 1, 'method' => 'probe'],
    'extracted_data_map' => [],
    'final_values' => [],
    'corrections' => [],
];
ob_start();
include dirname(__DIR__) . '/app/Views/admin/review-detail.php';
$viewHtml = (string)ob_get_clean();

$queue = (new Dashboard())->reviewQueue([
    'dataset' => 'REAL',
    'status' => 'pending',
    'lane' => '',
    'provenance' => ObservationProvenanceService::LISTING_LEVEL,
    'sort' => 'attention',
    'product_id' => null,
    'source_id' => null,
    'q' => '',
    'page' => 1,
    'per_page' => 25,
]);

echo json_encode([
    'ok' => true,
    'read_only' => true,
    'classes_loaded' => [
        Dashboard::class,
        OfflineEvidenceImportService::class,
        PriceSnapshotService::class,
        ObservationProvenanceService::class,
    ],
    'listing_fixture_quality' => $listing['quality'],
    'search_fixture_quality' => $search['quality'],
    'review_detail_rendered' => str_contains($viewHtml, 'Provenance: SEARCH RESULT LEVEL'),
    'review_queue_listing_level_filter_total' => $queue['total'] ?? null,
    'review_queue_listing_level_filter_items' => count($queue['items'] ?? []),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
