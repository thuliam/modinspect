<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Collection\CoveragePlanner;

$args = $argv ?? [];
$dryRun = in_array('--dry-run', $args, true);
$createJobs = in_array('--create-jobs', $args, true);
$summaryOnly = in_array('--summary', $args, true);
$limit = 20;
$target = 20;
$freshDays = 14;
$freshTarget = 8;
$attemptCooldownMinutes = 60;

foreach ($args as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int)substr($arg, 8));
    } elseif (str_starts_with($arg, '--target=')) {
        $target = max(1, (int)substr($arg, 9));
    } elseif (str_starts_with($arg, '--fresh-days=')) {
        $freshDays = max(1, (int)substr($arg, 13));
    } elseif (str_starts_with($arg, '--fresh-target=')) {
        $freshTarget = max(1, (int)substr($arg, 15));
    } elseif (str_starts_with($arg, '--attempt-cooldown-minutes=')) {
        $attemptCooldownMinutes = max(0, (int)substr($arg, 27));
    }
}

$planner = new CoveragePlanner(null, $target, $freshDays, $freshTarget, 'coverage_mock_collection', $attemptCooldownMinutes);
$plan = $planner->plan();
$jobs = null;

if ($createJobs) {
    $jobs = $planner->createJobs($plan, $limit);
}

$output = [
    'status' => 'ok',
    'mode' => $createJobs ? 'create_jobs' : ($dryRun ? 'dry_run' : 'plan'),
    'live_provider_enabled' => false,
    'paid_provider_enabled' => false,
    'plan' => $plan,
    'jobs' => $jobs,
];

if ($summaryOnly) {
    $output['plan']['items'] = array_slice($plan['items'], 0, $limit);
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
