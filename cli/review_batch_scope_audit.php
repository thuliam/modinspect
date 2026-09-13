<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\Dashboard;
use App\Services\Provenance\ObservationProvenanceService;

global $config;

$db = Database::connection($config['db']);
$classifier = new ObservationProvenanceService();

$requestedRunId = trim((string)($_GET['run_id'] ?? ''));
$rows = $db->query(
    "SELECT o.id,o.product_id,o.price_type,o.asking_price,o.price_value,o.verified_status,o.observed_at,
            p.full_name,r.raw_title,r.raw_price_text,r.source_url_encrypted,r.external_reference_hash,
            s.source_key,s.name source_name,e.excerpt,e.content_hash,d.lane
     FROM price_observations o
     JOIN products p ON p.id=o.product_id
     LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id
     LEFT JOIN data_sources s ON s.id=r.source_id
     LEFT JOIN market_evidence e ON e.raw_observation_id=r.id
     LEFT JOIN observation_review_decisions d ON d.id=(SELECT id FROM observation_review_decisions WHERE price_observation_id=o.id ORDER BY id DESC LIMIT 1)
     WHERE o.verified_status='pending'
       AND CASE WHEN LOWER(COALESCE(s.source_key,'')) LIKE 'offline_real_%' OR LOWER(COALESCE(s.name,'')) LIKE '%offline real import%' THEN 'REAL' WHEN LOWER(COALESCE(s.source_key,'')) LIKE '%mock%' OR LOWER(COALESCE(s.source_key,'')) LIKE '%fixture%' OR LOWER(COALESCE(s.source_key,'')) LIKE '%test%' OR LOWER(COALESCE(s.name,'')) LIKE '%mock%' OR LOWER(COALESCE(s.name,'')) LIKE '%fixture%' OR LOWER(COALESCE(s.name,'')) LIKE '%test%' THEN 'MOCK_TEST' ELSE 'UNKNOWN' END='REAL'
     ORDER BY o.id ASC"
)->fetchAll();

$listingRows = [];
$batchMaxIds = [];
$groups = [];
foreach ($rows as $row) {
    $classification = $classifier->classify($row);
    if (($classification['quality'] ?? '') !== ObservationProvenanceService::LISTING_LEVEL) {
        continue;
    }
    $runId = metadata_value((string)($row['excerpt'] ?? ''), 'PROBE_RUN_ID');
    $groupKey = $runId !== '' ? $runId : '__legacy_priceza_probe_no_run_id';
    $row['_run_id'] = $runId;
    $row['_group_key'] = $groupKey;
    $row['_provenance_quality'] = $classification['quality'];
    $listingRows[] = $row;
    if (!isset($groups[$groupKey])) {
        $groups[$groupKey] = [
            'identifier' => $groupKey,
            'run_id' => $runId,
            'purpose' => group_purpose($groupKey, $runId),
            'metadata_basis' => $runId !== '' ? 'PROBE_RUN_ID evidence metadata' : 'LISTING_LEVEL evidence with no stored PROBE_RUN_ID; initial Priceza probe rows',
            'count' => 0,
            'observation_ids' => [],
            'product_distribution' => [],
        ];
    }
    $product = (string)($row['full_name'] ?? 'unknown');
    $groups[$groupKey]['count']++;
    $groups[$groupKey]['observation_ids'][] = (int)$row['id'];
    $groups[$groupKey]['product_distribution'][$product] = ($groups[$groupKey]['product_distribution'][$product] ?? 0) + 1;
    if (str_starts_with($runId, 'priceza-batch-')) {
        $batchMaxIds[$runId] = max($batchMaxIds[$runId] ?? 0, (int)$row['id']);
    }
}

arsort($batchMaxIds);
$selectedRunId = $requestedRunId !== '' ? $requestedRunId : (string)array_key_first($batchMaxIds);
$batchRows = [];
$previousCalibrationRows = [];
foreach ($listingRows as $row) {
    $runId = (string)($row['_run_id'] ?? '');
    if ($runId === $selectedRunId) {
        $batchRows[] = $row;
    } else {
        $previousCalibrationRows[] = $row;
    }
}

$dashboard = new Dashboard();
$queue = $dashboard->reviewQueue([
    'dataset' => 'REAL',
    'status' => 'pending',
    'lane' => '',
    'provenance' => ObservationProvenanceService::LISTING_LEVEL,
    'run_id' => $selectedRunId,
    'sort' => 'attention',
    'product_id' => null,
    'source_id' => null,
    'q' => '',
    'page' => 1,
    'per_page' => 25,
]);

$distribution = [];
$lanes = ['green' => 0, 'amber' => 0, 'red' => 0, 'unknown' => 0];
$detailContext = [
    'title' => 0,
    'price' => 0,
    'merchant' => 0,
    'source' => 0,
    'snapshot' => 0,
    'hash' => 0,
    'provenance' => 0,
    'condition_field_visible' => 0,
    'validation_context' => 0,
];
foreach ($batchRows as $row) {
    $product = (string)($row['full_name'] ?? 'unknown');
    $distribution[$product] = ($distribution[$product] ?? 0) + 1;
    $lane = strtolower((string)($row['lane'] ?? 'unknown'));
    if (!isset($lanes[$lane])) $lane = 'unknown';
    $lanes[$lane]++;
    $fields = metadata_fields((string)($row['excerpt'] ?? ''));
    if (trim((string)($row['raw_title'] ?? '')) !== '') $detailContext['title']++;
    if (trim((string)($row['raw_price_text'] ?? $row['asking_price'] ?? $row['price_value'] ?? '')) !== '') $detailContext['price']++;
    if (trim((string)($fields['MERCHANT'] ?? '')) !== '') $detailContext['merchant']++;
    if (trim((string)($fields['SOURCE_LISTING_URL'] ?? $row['source_url_encrypted'] ?? '')) !== '') $detailContext['source']++;
    if (trim((string)($fields['EVIDENCE_SNAPSHOT_JSON'] ?? '')) !== '') $detailContext['snapshot']++;
    if (preg_match('/^[a-f0-9]{64}$/', strtolower(trim((string)($fields['EVIDENCE_HASH'] ?? $row['content_hash'] ?? '')))) === 1) $detailContext['hash']++;
    if (($row['_provenance_quality'] ?? '') === ObservationProvenanceService::LISTING_LEVEL) $detailContext['provenance']++;
    $detailContext['condition_field_visible']++;
    if ($lane !== 'unknown') $detailContext['validation_context']++;
}
arsort($distribution);
$detailSufficient = count(array_filter($detailContext, static fn(int $count): bool => $count === count($batchRows))) === count($detailContext);

$previousProbeIdsInQueue = 0;
foreach (($queue['items'] ?? []) as $item) {
    if ((string)($item['review_purpose'] ?? '') !== 'AUTHORITATIVE BATCH') {
        $previousProbeIdsInQueue++;
    }
}

echo json_encode([
    'ok' => true,
    'read_only' => true,
    'selected_run_id' => $selectedRunId,
    'real_pending_total' => count($rows),
    'real_pending_listing_level_total' => count($listingRows),
    'groups' => array_values($groups),
    'batch_options' => $dashboard->reviewBatchOptions(),
    'latest_authoritative_batch_count' => count($batchRows),
    'previous_probe_calibration_listing_level_count' => count($previousCalibrationRows),
    'other_listing_level_pending_count' => 0,
    'review_queue_filter_total' => (int)($queue['total'] ?? 0),
    'review_queue_filter_items' => count($queue['items'] ?? []),
    'review_queue_pages' => (int)($queue['pages'] ?? 0),
    'review_queue_from' => (int)($queue['from'] ?? 0),
    'review_queue_to' => (int)($queue['to'] ?? 0),
    'previous_probe_rows_in_filtered_queue' => $previousProbeIdsInQueue,
    'product_distribution' => $distribution,
    'lanes' => $lanes,
    'review_detail_context_counts' => $detailContext,
    'review_detail_sufficient_for_price_context' => $detailSufficient,
    'observation_ids' => array_map(static fn(array $row): int => (int)$row['id'], $batchRows),
    'snapshot_count' => (int)$db->query("SELECT COUNT(*) FROM price_indices")->fetchColumn(),
    'non_pending_batch_observations' => count(array_filter($batchRows, static fn(array $row): bool => (string)($row['verified_status'] ?? '') !== 'pending')),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

function metadata_value(string $excerpt, string $key): string
{
    return metadata_fields($excerpt)[strtoupper($key)] ?? '';
}

function metadata_fields(string $excerpt): array
{
    $fields = [];
    foreach (preg_split('/\R/', $excerpt) ?: [] as $line) {
        $line = trim((string)$line);
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key !== '') {
            $fields[strtoupper($key)] = $value;
        }
    }
    return $fields;
}

function group_purpose(string $groupKey, string $runId): string
{
    if (str_starts_with($runId, 'priceza-batch-')) {
        return 'AUTHORITATIVE BATCH';
    }
    if (str_starts_with($runId, 'priceza-probe-') || $groupKey === '__legacy_priceza_probe_no_run_id') {
        return 'PROBE / CALIBRATION';
    }
    return 'CALIBRATION / OTHER';
}
