<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

$snapshotId = null;
$productId = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--snapshot-id=')) {
        $snapshotId = filter_var(substr($arg, 14), FILTER_VALIDATE_INT);
    } elseif (str_starts_with($arg, '--product-id=')) {
        $productId = filter_var(substr($arg, 13), FILTER_VALIDATE_INT);
    }
}

global $config;
$db = Database::connection($config['db']);

$sql = "SELECT pi.*,p.full_name FROM price_indices pi JOIN products p ON p.id=pi.product_id WHERE pi.provenance_status='recorded'";
$params = [];
if ($snapshotId) {
    $sql .= " AND pi.id=:snapshot_id";
    $params['snapshot_id'] = (int)$snapshotId;
} elseif ($productId) {
    $sql .= " AND pi.product_id=:product_id";
    $params['product_id'] = (int)$productId;
}
$sql .= " ORDER BY pi.last_calculated_at DESC, pi.id DESC LIMIT 20";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$items = [];
foreach ($stmt->fetchAll() as $row) {
    $manifest = json_decode((string)($row['calculation_manifest'] ?? ''), true) ?: [];
    $confidence = $manifest['confidence'] ?? null;
    $items[] = [
        'snapshot_id' => (int)$row['id'],
        'product' => (string)$row['full_name'],
        'price_type' => (string)$row['price_type'],
        'sample_size' => (int)$row['valid_sample_size'],
        'confidence_method_version' => (string)$row['confidence_method_version'],
        'confidence_label' => strtoupper((string)$row['confidence_label']),
        'overall_score' => (float)$row['confidence_score'],
        'components' => $confidence['components'] ?? null,
        'sources' => $confidence['components']['source_diversity']['source_distribution'] ?? null,
        'freshness' => $confidence['components']['freshness'] ?? null,
        'reasons' => $confidence['reasons'] ?? [],
    ];
}

echo json_encode(['count' => count($items), 'items' => $items], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
