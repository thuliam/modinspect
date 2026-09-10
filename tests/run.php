<?php
declare(strict_types=1);

$root = dirname(__DIR__);
putenv('MODINSPECT_ENV_FILE=.env.testing');

$commands = [
    ['label' => 'tests/syntax_check.php', 'args' => ['tests/syntax_check.php']],
    ['label' => 'tests/test_database_guard_test.php', 'args' => ['tests/test_database_guard_test.php']],
    ['label' => 'tests/phase0_pipeline_test.php', 'args' => ['tests/phase0_pipeline_test.php']],
    ['label' => 'tests/phase1_catalog_test.php', 'args' => ['tests/phase1_catalog_test.php']],
    ['label' => 'tests/coverage_planner_test.php', 'args' => ['tests/coverage_planner_test.php']],
    ['label' => 'tests/scheduled_runner_test.php', 'args' => ['tests/scheduled_runner_test.php']],
    ['label' => 'tests/scheduled_runner_dry_run_limit_test.php', 'args' => ['tests/scheduled_runner_dry_run_limit_test.php']],
    ['label' => 'tests/collection_operations_test.php', 'args' => ['tests/collection_operations_test.php']],
    ['label' => 'tests/live_provider_infra_test.php', 'args' => ['tests/live_provider_infra_test.php']],
    ['label' => 'tests/snapshot_loop_test.php', 'args' => ['tests/snapshot_loop_test.php']],
    ['label' => 'tests/price_snapshot_test.php', 'args' => ['tests/price_snapshot_test.php']],
    ['label' => 'tests/review_decision_test.php', 'args' => ['tests/review_decision_test.php']],
    ['label' => 'tests/ui_integration_test.php', 'args' => ['tests/ui_integration_test.php']],
    ['label' => 'tests/stability_bug_hunt_test.php', 'args' => ['tests/stability_bug_hunt_test.php']],
    ['label' => 'tests/snapshot_provenance_test.php', 'args' => ['tests/snapshot_provenance_test.php']],
    ['label' => 'tests/review_correction_test.php', 'args' => ['tests/review_correction_test.php']],
    ['label' => 'tests/category_validation_test.php', 'args' => ['tests/category_validation_test.php']],
    ['label' => 'tests/auth_rbac_test.php', 'args' => ['tests/auth_rbac_test.php']],
    ['label' => 'tests/price_confidence_test.php', 'args' => ['tests/price_confidence_test.php']],
    ['label' => 'tests/review_calibration_test.php', 'args' => ['tests/review_calibration_test.php']],
    ['label' => 'tests/offline_import_test.php', 'args' => ['tests/offline_import_test.php']],
    ['label' => 'tests/seeded_mock_smoke_test.php', 'args' => ['tests/seeded_mock_smoke_test.php']],
    ['label' => 'tests/ux_structure_test.php', 'args' => ['tests/ux_structure_test.php']],
    ['label' => 'cli/evaluate_validation_quality.php', 'args' => ['cli/evaluate_validation_quality.php']],
    ['label' => 'cli/auth_status.php', 'args' => ['cli/auth_status.php']],
    ['label' => 'cli/price_confidence_report.php', 'args' => ['cli/price_confidence_report.php']],
    ['label' => 'cli/review_calibration_report.php', 'args' => ['cli/review_calibration_report.php']],
    ['label' => 'cli/import_market_evidence.php dry-run', 'args' => ['cli/import_market_evidence.php', '--file=examples/import/market_evidence_template.json', '--dataset=test', '--dry-run']],
    ['label' => 'cli/data_integrity_check.php', 'args' => ['cli/data_integrity_check.php']],
];

$failed = [];
foreach ($commands as $item) {
    $parts = [escapeshellarg(PHP_BINARY)];
    foreach ($item['args'] as $index => $arg) {
        $parts[] = escapeshellarg($index === 0 ? $root . '/' . $arg : $arg);
    }
    $command = implode(' ', $parts);
    $output = [];
    exec($command, $output, $exitCode);
    echo "== {$item['label']} ==\n";
    echo implode(PHP_EOL, $output) . PHP_EOL;

    if ($exitCode !== 0) {
        $failed[] = $item['label'];
        break;
    }
}

if ($failed) {
    fwrite(STDERR, 'Regression failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo "Sequential regression suite passed\n";
