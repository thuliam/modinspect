<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Collection\SourceOperationsService;

$args = $argv ?? [];
$jobId = null;
$dryRun = in_array('--dry-run', $args, true);

foreach ($args as $arg) {
    if (str_starts_with($arg, '--job-id=')) {
        $jobId = max(1, (int)substr($arg, 9));
    }
}

if ($jobId === null) {
    fwrite(STDERR, "Usage: php cli/requeue_collection_job.php --job-id=123 [--dry-run]\n");
    exit(2);
}

$result = (new SourceOperationsService())->requeueFailedJob($jobId, $dryRun);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit(($result['requeued'] ?? false) || $dryRun ? 0 : 1);
