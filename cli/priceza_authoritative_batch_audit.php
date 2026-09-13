<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Provenance\ObservationProvenanceService;

global $config;
$db = Database::connection($config['db']);
$classifier = new ObservationProvenanceService();

$selectedRunId = trim((string)($_GET['run'] ?? ''));
$allRows = fetch_batch_rows($db);

if ($selectedRunId === '') {
    $selectedRunId = latest_run_id($allRows);
}

$rowsForCounts = [];
foreach ($allRows as $row) {
    $fields = fields_from_excerpt((string)$row['excerpt']);
    $runId = (string)($fields['PROBE_RUN_ID'] ?? '');
    if ($selectedRunId === '' || $runId === $selectedRunId) {
        $row['_fields'] = $fields;
        $row['_run_id'] = $runId;
        $rowsForCounts[] = $row;
    }
}

$counts = [
    'listing_level' => 0,
    'search_result_level' => 0,
    'generic_source' => 0,
    'source_item_id' => 0,
    'redirect_url' => 0,
    'merchant' => 0,
    'title' => 0,
    'asking_price' => 0,
    'observed_at' => 0,
    'snapshot' => 0,
    'hash' => 0,
    'hash_verified' => 0,
    'title_price_merchant_verified' => 0,
    'product_resolution_success' => 0,
    'green' => 0,
    'amber' => 0,
    'red' => 0,
    'asking_price_count' => 0,
    'sold_price_count' => 0,
    'missing_condition' => 0,
    'missing_warranty' => 0,
    'merchant_target_url' => 0,
    'merchant_target_unavailable' => 0,
];
$products = [];
$sourceItemIds = [];
$merchants = [];
$rows = [];

foreach ($rowsForCounts as $row) {
    $fields = is_array($row['_fields'] ?? null) ? $row['_fields'] : fields_from_excerpt((string)$row['excerpt']);
    $snapshot = snapshot_from_fields($fields);
    $quality = $row['observation_id'] === null ? ObservationProvenanceService::GENERIC_SOURCE : (string)$classifier->classify($row)['quality'];
    if ($quality === ObservationProvenanceService::LISTING_LEVEL) {
        $counts['listing_level']++;
    } elseif ($quality === ObservationProvenanceService::SEARCH_RESULT_LEVEL) {
        $counts['search_result_level']++;
    } else {
        $counts['generic_source']++;
    }

    $sourceItemId = trim((string)($fields['SOURCE_ITEM_ID'] ?? ''));
    if ($sourceItemId !== '') {
        $sourceItemIds[$sourceItemId] = true;
    }
    $productName = (string)($row['product_name'] ?: snapshot_field($snapshot, 'product_hint'));
    if ($productName !== '') {
        $products[$productName] = ($products[$productName] ?? 0) + 1;
    }
    $merchant = trim((string)($fields['MERCHANT'] ?? ''));
    if ($merchant !== '') {
        $merchants[$merchant] = ($merchants[$merchant] ?? 0) + 1;
    }

    $hash = strtolower((string)($fields['EVIDENCE_HASH'] ?? $row['content_hash'] ?? ''));
    $snapshotJson = trim((string)($fields['EVIDENCE_SNAPSHOT_JSON'] ?? ''));
    $hashValid = preg_match('/^[a-f0-9]{64}$/', $hash) === 1;
    $hashVerified = $hashValid && $snapshotJson !== '' && hash('sha256', $snapshotJson) === $hash;
    $visibleVerified = snapshot_field($snapshot, 'listing_title') === trim((string)($fields['LISTING_TITLE'] ?? ''))
        && snapshot_field($snapshot, 'asking_price') === trim((string)($fields['ASKING_PRICE'] ?? ''))
        && snapshot_field($snapshot, 'merchant') === $merchant;

    $counts['source_item_id'] += non_empty($sourceItemId);
    $counts['redirect_url'] += str_starts_with((string)$row['source_url_encrypted'], 'https://www.priceza.com/r/redirect?id=') ? 1 : 0;
    $counts['merchant'] += non_empty($merchant);
    $counts['title'] += non_empty($fields['LISTING_TITLE'] ?? null);
    $counts['asking_price'] += non_empty($fields['ASKING_PRICE'] ?? null);
    $counts['observed_at'] += non_empty($fields['OBSERVED_AT'] ?? null);
    $counts['snapshot'] += non_empty($snapshotJson);
    $counts['hash'] += $hashValid ? 1 : 0;
    $counts['hash_verified'] += $hashVerified ? 1 : 0;
    $counts['title_price_merchant_verified'] += $visibleVerified ? 1 : 0;
    $counts['product_resolution_success'] += $row['observation_id'] !== null && $row['product_id'] !== null ? 1 : 0;
    $lane = (string)($row['lane'] ?? '');
    if (isset($counts[$lane])) {
        $counts[$lane]++;
    }
    if ((string)$row['price_type'] === 'asking') {
        $counts['asking_price_count']++;
    } elseif ((string)$row['price_type'] === 'sold') {
        $counts['sold_price_count']++;
    }
    if ($row['condition_level'] === null || (string)$row['condition_level'] === 'unknown') {
        $counts['missing_condition']++;
    }
    if ($row['warranty_months'] === null) {
        $counts['missing_warranty']++;
    }
    if (non_empty($fields['MERCHANT_TARGET_URL'] ?? null)) {
        $counts['merchant_target_url']++;
    } else {
        $counts['merchant_target_unavailable']++;
    }

    $rows[] = [
        'raw_id' => (int)$row['raw_id'],
        'observation_id' => $row['observation_id'] === null ? null : (int)$row['observation_id'],
        'product_id' => $row['product_id'] === null ? null : (int)$row['product_id'],
        'product' => $productName,
        'source_item_id' => $sourceItemId,
        'title' => $fields['LISTING_TITLE'] ?? $row['raw_title'],
        'price' => $fields['ASKING_PRICE'] ?? $row['raw_price_text'],
        'merchant' => $merchant,
        'quality' => $quality,
        'lane' => $lane,
        'decision' => $row['decision'],
        'price_type' => $row['price_type'],
        'verified_status' => $row['verified_status'],
        'quality_flags' => decode_json_array((string)($row['quality_flags'] ?? '')),
        'evidence_hash_verified' => $hashVerified,
        'title_price_merchant_verified' => $visibleVerified,
    ];
}

ksort($products);
arsort($merchants);

$oldSearchCount = (int)$db->query(
    "SELECT COUNT(*) FROM price_observations o
     LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id
     LEFT JOIN market_evidence e ON e.raw_observation_id=r.id
     WHERE o.id BETWEEN 2070 AND 2111
       AND r.source_url_encrypted LIKE 'https://www.priceza.com/s/%'
       AND (e.excerpt NOT LIKE '%SOURCE_ITEM_ID=%' OR e.excerpt IS NULL)"
)->fetchColumn();
$recordedSnapshotCount = (int)$db->query("SELECT COUNT(*) FROM price_indices WHERE provenance_status='recorded'")->fetchColumn();
$nonPendingBatch = count(array_filter($rows, static fn(array $row): bool => (string)$row['verified_status'] !== 'pending'));

echo json_encode([
    'read_only' => true,
    'run_id' => $selectedRunId !== '' ? $selectedRunId : null,
    'rows_collected' => count($rows),
    'rows_imported' => count(array_filter($rows, static fn(array $row): bool => $row['observation_id'] !== null)),
    'products_represented' => array_keys($products),
    'rows_per_product' => $products,
    'unique_source_item_id_count' => count($sourceItemIds),
    'counts' => $counts,
    'merchant_distribution' => $merchants,
    'old_42_search_result_level_count' => $oldSearchCount,
    'recorded_snapshot_count' => $recordedSnapshotCount,
    'non_pending_batch_observations' => $nonPendingBatch,
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

/**
 * @return array<int,array<string,mixed>>
 */
function fetch_batch_rows(PDO $db): array
{
    $stmt = $db->query(
        "SELECT r.id raw_id,o.id observation_id,o.product_id,o.verified_status,o.price_type,o.asking_price,o.price_value,
                o.condition_level,o.warranty_months,o.quality_flags,p.full_name product_name,
                r.raw_title,r.raw_price_text,r.source_url_encrypted,r.captured_at,r.external_reference_hash,
                e.id evidence_id,e.excerpt,e.content_hash,e.evidence_type,e.captured_at evidence_captured_at,
                d.lane,d.decision,d.reason_codes
         FROM market_evidence e
         JOIN raw_price_observations r ON r.id=e.raw_observation_id
         LEFT JOIN price_observations o ON o.raw_observation_id=r.id
         LEFT JOIN products p ON p.id=o.product_id
         LEFT JOIN observation_review_decisions d ON d.raw_observation_id=r.id
         WHERE e.excerpt LIKE '%Phase 3H-R1D authoritative REAL batch%'
         ORDER BY r.id"
    );
    return $stmt->fetchAll();
}

/**
 * @param array<int,array<string,mixed>> $rows
 */
function latest_run_id(array $rows): string
{
    $latestRawId = 0;
    $latestRunId = '';
    foreach ($rows as $row) {
        $fields = fields_from_excerpt((string)$row['excerpt']);
        $runId = trim((string)($fields['PROBE_RUN_ID'] ?? ''));
        if ($runId === '') {
            continue;
        }
        $rawId = (int)$row['raw_id'];
        if ($rawId >= $latestRawId) {
            $latestRawId = $rawId;
            $latestRunId = $runId;
        }
    }
    return $latestRunId;
}

function fields_from_excerpt(string $excerpt): array
{
    $fields = [];
    foreach (preg_split('/\R/', $excerpt) ?: [] as $line) {
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

function snapshot_from_fields(array $fields): array
{
    $snapshot = json_decode((string)($fields['EVIDENCE_SNAPSHOT_JSON'] ?? ''), true);
    return is_array($snapshot) ? $snapshot : [];
}

function snapshot_field(array $snapshot, string $field): string
{
    return trim((string)($snapshot[$field] ?? ''));
}

function decode_json_array(string $json): array
{
    $decoded = json_decode($json, true);
    return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
}

function non_empty(mixed $value): int
{
    return trim((string)$value) !== '' ? 1 : 0;
}
