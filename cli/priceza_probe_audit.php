<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Provenance\ObservationProvenanceService;

global $config;
$db = Database::connection($config['db']);
$classifier = new ObservationProvenanceService();

$selectedRunId = trim((string)($_GET['run'] ?? ''));
$includeAll = isset($_GET['all']) && (string)$_GET['all'] === '1';
$allRows = fetch_probe_rows($db);

if (!$includeAll && $selectedRunId === '') {
    $selectedRunId = latest_probe_run_id($allRows);
}

$rowsForCounts = [];
foreach ($allRows as $row) {
    $fields = fields_from_excerpt((string)$row['excerpt']);
    $probeRunId = (string)($fields['PROBE_RUN_ID'] ?? '');
    if ($includeAll || $selectedRunId === '' || $probeRunId === $selectedRunId) {
        $row['_fields'] = $fields;
        $row['_probe_run_id'] = $probeRunId;
        $rowsForCounts[] = $row;
    }
}

$rows = [];
$failedRows = [];
$counts = [
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
    'merchant_target_url' => 0,
    'merchant_target_unavailable' => 0,
    'listing_level' => 0,
    'search_result_level' => 0,
    'generic_source' => 0,
    'product_resolution_success' => 0,
];
$products = [];
$sourceItemIds = [];

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

    $hash = strtolower((string)($fields['EVIDENCE_HASH'] ?? $row['content_hash'] ?? ''));
    $snapshotJson = trim((string)($fields['EVIDENCE_SNAPSHOT_JSON'] ?? ''));
    $hashValid = preg_match('/^[a-f0-9]{64}$/', $hash) === 1;
    $hashVerified = $hashValid && $snapshotJson !== '' && hash('sha256', $snapshotJson) === $hash;
    $visibleVerified = snapshot_field($snapshot, 'listing_title') === trim((string)($fields['LISTING_TITLE'] ?? ''))
        && snapshot_field($snapshot, 'asking_price') === trim((string)($fields['ASKING_PRICE'] ?? ''))
        && snapshot_field($snapshot, 'merchant') === trim((string)($fields['MERCHANT'] ?? ''));

    $counts['source_item_id'] += non_empty($sourceItemId);
    $counts['redirect_url'] += str_starts_with((string)$row['source_url_encrypted'], 'https://www.priceza.com/r/redirect?id=') ? 1 : 0;
    $counts['merchant'] += non_empty($fields['MERCHANT'] ?? null);
    $counts['title'] += non_empty($fields['LISTING_TITLE'] ?? null);
    $counts['asking_price'] += non_empty($fields['ASKING_PRICE'] ?? null);
    $counts['observed_at'] += non_empty($fields['OBSERVED_AT'] ?? null);
    $counts['snapshot'] += non_empty($snapshotJson);
    $counts['hash'] += $hashValid ? 1 : 0;
    $counts['hash_verified'] += $hashVerified ? 1 : 0;
    $counts['title_price_merchant_verified'] += $visibleVerified ? 1 : 0;
    $counts['product_resolution_success'] += $row['observation_id'] !== null ? 1 : 0;
    if (non_empty($fields['MERCHANT_TARGET_URL'] ?? null)) {
        $counts['merchant_target_url']++;
    } else {
        $counts['merchant_target_unavailable']++;
    }
    if (!empty($row['product_name'])) {
        $products[(string)$row['product_name']] = true;
    } elseif (snapshot_field($snapshot, 'product_hint') !== '') {
        $products[snapshot_field($snapshot, 'product_hint')] = true;
    }

    $reasonCodes = decode_json_array((string)($row['reason_codes'] ?? ''));
    $detail = [
        'raw_id' => (int)$row['raw_id'],
        'observation_id' => $row['observation_id'] === null ? null : (int)$row['observation_id'],
        'product' => $row['product_name'] ?: snapshot_field($snapshot, 'product_hint'),
        'source_item_id' => $sourceItemId !== '' ? $sourceItemId : null,
        'title' => $fields['LISTING_TITLE'] ?? $row['raw_title'],
        'price' => $fields['ASKING_PRICE'] ?? $row['raw_price_text'],
        'merchant' => $fields['MERCHANT'] ?? null,
        'source_listing_url' => $fields['SOURCE_LISTING_URL'] ?? $row['source_url_encrypted'],
        'merchant_target_url_captured' => non_empty($fields['MERCHANT_TARGET_URL'] ?? null) === 1,
        'quality' => $quality,
        'lane' => $row['lane'],
        'decision' => $row['decision'],
        'reason_codes' => $reasonCodes,
        'classification_reason' => classification_reason($quality, $row['observation_id'], $reasonCodes),
        'probe_run_id' => $row['_probe_run_id'] ?? '',
        'has_snapshot' => $snapshotJson !== '',
        'evidence_hash' => $hash,
        'evidence_hash_verified' => $hashVerified,
        'title_price_merchant_verified' => $visibleVerified,
    ];
    $rows[] = $detail;
    if ($quality === ObservationProvenanceService::GENERIC_SOURCE) {
        $failedRows[] = $detail;
    }
}

$oldSearchCount = (int)$db->query(
    "SELECT COUNT(*) FROM price_observations o
     LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id
     LEFT JOIN market_evidence e ON e.raw_observation_id=r.id
     WHERE o.id BETWEEN 2070 AND 2111
       AND r.source_url_encrypted LIKE 'https://www.priceza.com/s/%'
       AND (e.excerpt NOT LIKE '%SOURCE_ITEM_ID=%' OR e.excerpt IS NULL)"
)->fetchColumn();
$snapshotCount = (int)$db->query("SELECT COUNT(*) FROM price_indices WHERE provenance_status='recorded'")->fetchColumn();
$nonPending = (int)$db->query("SELECT COUNT(*) FROM price_observations WHERE id >= 2112 AND verified_status <> 'pending'")->fetchColumn();

echo json_encode([
    'read_only' => true,
    'scope' => $includeAll ? 'all_probe_rows' : ($selectedRunId !== '' ? 'probe_run_id' : 'all_probe_rows_no_run_id_available'),
    'probe_run_id' => $selectedRunId !== '' ? $selectedRunId : null,
    'probe_rows_collected' => count($rows),
    'products_represented' => array_keys($products),
    'unique_source_item_id_count' => count($sourceItemIds),
    'counts' => $counts,
    'old_42_search_result_level_count' => $oldSearchCount,
    'recorded_snapshot_count' => $snapshotCount,
    'non_pending_new_observations' => $nonPending,
    'failed_rows' => $failedRows,
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

/**
 * @return array<int,array<string,mixed>>
 */
function fetch_probe_rows(PDO $db): array
{
    $stmt = $db->query(
        "SELECT r.id raw_id,o.id observation_id,o.verified_status,o.asking_price,o.price_value,p.full_name product_name,
                r.raw_title,r.raw_price_text,r.source_url_encrypted,r.captured_at,r.external_reference_hash,
                e.id evidence_id,e.excerpt,e.content_hash,e.evidence_type,e.captured_at evidence_captured_at,
                d.lane,d.decision,d.reason_codes
         FROM market_evidence e
         JOIN raw_price_observations r ON r.id=e.raw_observation_id
         LEFT JOIN price_observations o ON o.raw_observation_id=r.id
         LEFT JOIN products p ON p.id=o.product_id
         LEFT JOIN observation_review_decisions d ON d.raw_observation_id=r.id
         WHERE e.excerpt LIKE '%Phase 3H-R1C authoritative provenance probe%'
         ORDER BY r.id"
    );
    return $stmt->fetchAll();
}

/**
 * @param array<int,array<string,mixed>> $rows
 */
function latest_probe_run_id(array $rows): string
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

function classification_reason(string $quality, mixed $observationId, array $reasonCodes): string
{
    if ($quality === ObservationProvenanceService::LISTING_LEVEL) {
        return 'LISTING_LEVEL contract satisfied after product resolution and validation.';
    }
    if ($observationId === null && $reasonCodes !== []) {
        return 'No price_observation was created because validation returned ' . implode(',', $reasonCodes) . '.';
    }
    if ($quality === ObservationProvenanceService::SEARCH_RESULT_LEVEL) {
        return 'Only search-result page provenance is available.';
    }
    return 'Source evidence is insufficient for authoritative listing-level provenance.';
}

function non_empty(mixed $value): int
{
    return trim((string)$value) !== '' ? 1 : 0;
}
