<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Provenance\ObservationProvenanceService;

global $config;
$db = Database::connection($config['db']);
$classifier = new ObservationProvenanceService();

$datasetSql = "CASE WHEN LOWER(COALESCE(s.source_key,'')) LIKE 'offline_real_%' OR LOWER(COALESCE(s.name,'')) LIKE '%offline real import%' THEN 'REAL' WHEN LOWER(COALESCE(s.source_key,'')) LIKE '%mock%' OR LOWER(COALESCE(s.source_key,'')) LIKE '%fixture%' OR LOWER(COALESCE(s.source_key,'')) LIKE '%test%' OR LOWER(COALESCE(s.name,'')) LIKE '%mock%' OR LOWER(COALESCE(s.name,'')) LIKE '%fixture%' OR LOWER(COALESCE(s.name,'')) LIKE '%test%' THEN 'MOCK_TEST' ELSE 'UNKNOWN' END";

$stmt = $db->query(
    "SELECT o.id,o.asking_price,o.price_value,o.verified_status,o.observed_at,p.full_name product_name,r.raw_title,r.raw_price_text,r.source_url_encrypted,r.external_reference_hash,r.captured_at,s.source_key,s.name source_name,s.domain source_domain,e.excerpt,e.content_hash,{$datasetSql} dataset_label
     FROM price_observations o
     JOIN products p ON p.id=o.product_id
     LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id
     LEFT JOIN data_sources s ON s.id=r.source_id
     LEFT JOIN market_evidence e ON e.raw_observation_id=r.id
     HAVING dataset_label='REAL'
     ORDER BY o.id"
);

$counts = [
    ObservationProvenanceService::LISTING_LEVEL => 0,
    ObservationProvenanceService::SEARCH_RESULT_LEVEL => 0,
    ObservationProvenanceService::GENERIC_SOURCE => 0,
];
$rows = [];

foreach ($stmt->fetchAll() as $row) {
    $quality = $classifier->classify($row);
    $counts[(string)$quality['quality']]++;
    $rows[] = [
        'observation_id' => (int)$row['id'],
        'product' => (string)$row['product_name'],
        'price' => $row['price_value'] ?? $row['asking_price'],
        'status' => (string)$row['verified_status'],
        'quality' => (string)$quality['quality'],
        'source_url_kind' => str_contains(strtolower((string)$row['source_url_encrypted']), 'priceza.com/s/') ? 'priceza_search_url' : ((string)$row['source_url_encrypted'] !== '' ? 'other_url' : 'missing'),
        'has_raw_title' => trim((string)$row['raw_title']) !== '',
        'has_raw_price_text' => trim((string)$row['raw_price_text']) !== '',
        'has_external_reference_hash' => trim((string)$row['external_reference_hash']) !== '',
        'source_item_id' => $quality['fields']['source_item_id'] ?? null,
        'merchant' => $quality['fields']['merchant'] ?? null,
    ];
}

$reviewCounts = $db->query(
    "SELECT dataset_label,verified_status,COUNT(*) count
     FROM (
        SELECT o.verified_status,{$datasetSql} dataset_label
        FROM price_observations o
        LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id
        LEFT JOIN data_sources s ON s.id=r.source_id
     ) x
     WHERE dataset_label='REAL'
     GROUP BY dataset_label,verified_status"
)->fetchAll();
$snapshotCount = (int)$db->query("SELECT COUNT(*) FROM price_indices WHERE provenance_status='recorded'")->fetchColumn();

echo json_encode([
    'read_only' => true,
    'dataset' => 'REAL',
    'total_rows' => count($rows),
    'counts' => [
        'listing_level' => $counts[ObservationProvenanceService::LISTING_LEVEL],
        'search_result_level' => $counts[ObservationProvenanceService::SEARCH_RESULT_LEVEL],
        'generic_or_insufficient' => $counts[ObservationProvenanceService::GENERIC_SOURCE],
    ],
    'review_status_counts' => $reviewCounts,
    'recorded_snapshot_count' => $snapshotCount,
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
