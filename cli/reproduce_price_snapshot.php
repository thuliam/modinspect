<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Pricing\PriceSnapshotService;

$snapshotId = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--snapshot-id=')) {
        $snapshotId = filter_var(substr($arg, 14), FILTER_VALIDATE_INT);
    }
}

if (!$snapshotId) {
    fwrite(STDERR, "Usage: php cli/reproduce_price_snapshot.php --snapshot-id=123\n");
    exit(2);
}

$result = (new PriceSnapshotService())->reproduceSnapshot((int)$snapshotId);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit(($result['result'] ?? '') === 'MATCH' || ($result['result'] ?? '') === 'LEGACY_PROVENANCE_UNAVAILABLE' ? 0 : 1);
