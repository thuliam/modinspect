<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Collection\SourceOperationsService;

$args = $argv ?? [];
$sourceId = null;
$action = null;
$reason = null;
$incidentType = 'other';
$dryRun = in_array('--dry-run', $args, true);

foreach ($args as $arg) {
    if (str_starts_with($arg, '--source-id=')) {
        $sourceId = max(1, (int)substr($arg, 12));
    } elseif (str_starts_with($arg, '--action=')) {
        $action = substr($arg, 9);
    } elseif (str_starts_with($arg, '--reason=')) {
        $reason = substr($arg, 9);
    } elseif (str_starts_with($arg, '--incident-type=')) {
        $incidentType = substr($arg, 16);
    }
}

if ($sourceId === null || $action === null || $reason === null) {
    fwrite(STDERR, "Usage: php cli/source_control.php --source-id=1 --action=pause|disable|incident --reason=\"...\" [--incident-type=blocked] [--dry-run]\n");
    exit(2);
}

$service = new SourceOperationsService();
$result = match ($action) {
    'pause' => $service->pauseSource($sourceId, $reason, $incidentType, $dryRun),
    'disable' => $service->disableSource($sourceId, $reason, $dryRun),
    'incident' => $dryRun
        ? ['source_id' => $sourceId, 'status' => 'dry_run', 'incident_type' => $incidentType, 'message' => $reason, 'errors' => []]
        : $service->recordIncident($sourceId, $incidentType, $reason),
    default => ['source_id' => $sourceId, 'status' => 'rejected', 'errors' => ['unknown_action']],
};

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit(($result['errors'] ?? []) === [] ? 0 : 1);
