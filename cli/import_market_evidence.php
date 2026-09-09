<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Collection\OfflineEvidenceImportService;

$file = null;
$dataset = 'test';
$dryRun = false;
$limit = 100;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--file=')) {
        $file = substr($arg, 7);
    } elseif (str_starts_with($arg, '--dataset=')) {
        $dataset = substr($arg, 10);
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    } elseif (str_starts_with($arg, '--limit=')) {
        $value = filter_var(substr($arg, 8), FILTER_VALIDATE_INT);
        if ($value !== false) {
            $limit = (int)$value;
        }
    }
}

if (!$file) {
    fwrite(STDERR, "Usage: php cli/import_market_evidence.php --file=examples/import/market_evidence_template.json [--dataset=test|real] [--dry-run] [--limit=100]\n");
    exit(2);
}

$summary = (new OfflineEvidenceImportService())->import($file, $dataset, $dryRun, $limit);
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit(empty($summary['row_errors']) || (($summary['valid_rows'] ?? 0) > 0) ? 0 : 1);
