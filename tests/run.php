<?php
declare(strict_types=1);

$root = dirname(__DIR__);
putenv('MODINSPECT_ENV_FILE=.env.testing');

$commands = [
    ['label' => 'tests/syntax_check.php', 'args' => ['tests/syntax_check.php']],
    ['label' => 'tests/test_database_guard_test.php', 'args' => ['tests/test_database_guard_test.php']],
    ['label' => 'tests/timezone_consistency_test.php', 'args' => ['tests/timezone_consistency_test.php']],
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
    ['label' => 'tests/owner_text_import_test.php', 'args' => ['tests/owner_text_import_test.php']],
    ['label' => 'tests/spec_class_import_test.php', 'args' => ['tests/spec_class_import_test.php']],
    ['label' => 'tests/trend_location_test.php', 'args' => ['tests/trend_location_test.php']],
    ['label' => 'tests/price_trust_test.php', 'args' => ['tests/price_trust_test.php']],
    ['label' => 'tests/ui_prune_css_test.php', 'args' => ['tests/ui_prune_css_test.php']],
    ['label' => 'tests/owner_intake_filter_test.php', 'args' => ['tests/owner_intake_filter_test.php']],
    ['label' => 'tests/gpu_vram_facts_test.php', 'args' => ['tests/gpu_vram_facts_test.php']],
    ['label' => 'tests/owner_intake_planner_test.php', 'args' => ['tests/owner_intake_planner_test.php']],
    ['label' => 'tests/confidence_gap_test.php', 'args' => ['tests/confidence_gap_test.php']],
    ['label' => 'tests/auth_session_lifetime_test.php', 'args' => ['tests/auth_session_lifetime_test.php']],
    ['label' => 'tests/second_source_intake_test.php', 'args' => ['tests/second_source_intake_test.php']],
    ['label' => 'tests/price_ladder_check_test.php', 'args' => ['tests/price_ladder_check_test.php']],
    ['label' => 'tests/data_health_report_test.php', 'args' => ['tests/data_health_report_test.php']],
    ['label' => 'tests/snapshot_history_test.php', 'args' => ['tests/snapshot_history_test.php']],
    ['label' => 'tests/admin_route_guard_test.php', 'args' => ['tests/admin_route_guard_test.php']],
    ['label' => 'tests/seo_service_test.php', 'args' => ['tests/seo_service_test.php']],
    ['label' => 'tests/price_engine_edge_cases_test.php', 'args' => ['tests/price_engine_edge_cases_test.php']],
    ['label' => 'tests/db_backup_restore_test.php', 'args' => ['tests/db_backup_restore_test.php']],
    ['label' => 'tests/health_endpoint_test.php', 'args' => ['tests/health_endpoint_test.php']],
    ['label' => 'tests/linux_parity_scan_test.php', 'args' => ['tests/linux_parity_scan_test.php']],
    ['label' => 'tests/rbac_master_data_write_test.php', 'args' => ['tests/rbac_master_data_write_test.php']],
    ['label' => 'tests/unresolved_queue_test.php', 'args' => ['tests/unresolved_queue_test.php']],
    ['label' => 'tests/user_administration_test.php', 'args' => ['tests/user_administration_test.php']],
    ['label' => 'tests/rate_limit_cache_test.php', 'args' => ['tests/rate_limit_cache_test.php']],
    ['label' => 'tests/rate_limit_http_test.php', 'args' => ['tests/rate_limit_http_test.php']],
    ['label' => 'tests/owner_intake_dryrun_test.php', 'args' => ['tests/owner_intake_dryrun_test.php']],
    ['label' => 'tests/intake_http_test.php', 'args' => ['tests/intake_http_test.php']],
    ['label' => 'tests/search_query_test.php', 'args' => ['tests/search_query_test.php']],
    ['label' => 'tests/condition_checklist_test.php', 'args' => ['tests/condition_checklist_test.php']],
    ['label' => 'tests/beta_readiness_test.php', 'args' => ['tests/beta_readiness_test.php']],
    ['label' => 'tests/heading_levels_test.php', 'args' => ['tests/heading_levels_test.php']],
    ['label' => 'tests/spec_builder_compat_test.php', 'args' => ['tests/spec_builder_compat_test.php']],
    ['label' => 'tests/unresolved_proposal_report_test.php', 'args' => ['tests/unresolved_proposal_report_test.php']],
    ['label' => 'tests/public_a11y_scan_test.php', 'args' => ['tests/public_a11y_scan_test.php']],
    ['label' => 'tests/db_error_no_secret_test.php', 'args' => ['tests/db_error_no_secret_test.php']],
    ['label' => 'tests/public_confidence_labels_test.php', 'args' => ['tests/public_confidence_labels_test.php']],
    ['label' => 'tests/owner_transcription_test.php', 'args' => ['tests/owner_transcription_test.php']],
    ['label' => 'tests/import_real_file_hazards_test.php', 'args' => ['tests/import_real_file_hazards_test.php']],
    ['label' => 'tests/owner_row_preparer_test.php', 'args' => ['tests/owner_row_preparer_test.php']],
    ['label' => 'tests/daily_flow_rehearsal_test.php', 'args' => ['tests/daily_flow_rehearsal_test.php']],
    ['label' => 'tests/calibration_capture_method_test.php', 'args' => ['tests/calibration_capture_method_test.php']],
    ['label' => 'tests/public_button_theme_test.php', 'args' => ['tests/public_button_theme_test.php']],
    ['label' => 'tests/owner_row_db_check_test.php', 'args' => ['tests/owner_row_db_check_test.php']],
    ['label' => 'tests/env_preflight_test.php', 'args' => ['tests/env_preflight_test.php']],
    ['label' => 'tests/build_reflow_css_test.php', 'args' => ['tests/build_reflow_css_test.php']],
    ['label' => 'tests/collection_targets_test.php', 'args' => ['tests/collection_targets_test.php']],
    ['label' => 'tests/retention_purge_test.php', 'args' => ['tests/retention_purge_test.php']],
    ['label' => 'tests/jquery_version_guard_test.php', 'args' => ['tests/jquery_version_guard_test.php']],
    ['label' => 'tests/main_menu_test.php', 'args' => ['tests/main_menu_test.php']],
    ['label' => 'tests/spec_builder_estimate_test.php', 'args' => ['tests/spec_builder_estimate_test.php']],
    ['label' => 'tests/spec_recommend_test.php', 'args' => ['tests/spec_recommend_test.php']],
    ['label' => 'tests/spec_radar_test.php', 'args' => ['tests/spec_radar_test.php']],
    ['label' => 'tests/perf_score_csv_validator_test.php', 'args' => ['tests/perf_score_csv_validator_test.php']],
    ['label' => 'tests/hardware_reference_migration_test.php', 'args' => ['tests/hardware_reference_migration_test.php']],
    ['label' => 'tests/cpu_gpu_spec_migration_test.php', 'args' => ['tests/cpu_gpu_spec_migration_test.php']],
    ['label' => 'tests/compare_table_test.php', 'args' => ['tests/compare_table_test.php']],
    ['label' => 'tests/condition_guide_test.php', 'args' => ['tests/condition_guide_test.php']],
    ['label' => 'tests/price_index_categories_test.php', 'args' => ['tests/price_index_categories_test.php']],
    ['label' => 'tests/hidden_modules_test.php', 'args' => ['tests/hidden_modules_test.php']],
    ['label' => 'tests/did_you_mean_test.php', 'args' => ['tests/did_you_mean_test.php']],
    ['label' => 'tests/public_reachability_test.php', 'args' => ['tests/public_reachability_test.php']],
    ['label' => 'tests/build_services_test.php', 'args' => ['tests/build_services_test.php']],
    ['label' => 'tests/new_price_reference_admin_test.php', 'args' => ['tests/new_price_reference_admin_test.php']],
    ['label' => 'tests/member_auth_test.php', 'args' => ['tests/member_auth_test.php']],
    ['label' => 'tests/member_http_test.php', 'args' => ['tests/member_http_test.php']],
    ['label' => 'tests/seller_listing_test.php', 'args' => ['tests/seller_listing_test.php']],
    ['label' => 'tests/seller_controller_test.php', 'args' => ['tests/seller_controller_test.php']],
    ['label' => 'tests/import_listing_attributes_test.php', 'args' => ['tests/import_listing_attributes_test.php']],
    ['label' => 'tests/seller_check_test.php', 'args' => ['tests/seller_check_test.php']],
    ['label' => 'tests/seller_check_http_test.php', 'args' => ['tests/seller_check_http_test.php']],
    ['label' => 'tests/admin_readonly_controls_test.php', 'args' => ['tests/admin_readonly_controls_test.php']],
    ['label' => 'tests/smoke_check_test.php', 'args' => ['tests/smoke_check_test.php']],
    ['label' => 'tests/production_error_page_test.php', 'args' => ['tests/production_error_page_test.php']],
    ['label' => 'tests/collection_category_needs_test.php', 'args' => ['tests/collection_category_needs_test.php']],
    ['label' => 'tests/pilot_mode_test.php', 'args' => ['tests/pilot_mode_test.php']],
    ['label' => 'tests/product_facts_test.php', 'args' => ['tests/product_facts_test.php']],
    ['label' => 'tests/second_source_snapshot_gate_test.php', 'args' => ['tests/second_source_snapshot_gate_test.php']],
    ['label' => 'tests/new_price_reference_test.php', 'args' => ['tests/new_price_reference_test.php']],
    ['label' => 'tests/stale_reresolve_test.php', 'args' => ['tests/stale_reresolve_test.php']],
    ['label' => 'tests/ops_kit_test.php', 'args' => ['tests/ops_kit_test.php']],
    ['label' => 'tests/duplicate_post_report_test.php', 'args' => ['tests/duplicate_post_report_test.php']],
    ['label' => 'tests/snapshot_evidence_report_test.php', 'args' => ['tests/snapshot_evidence_report_test.php']],
    ['label' => 'tests/golden_resolver_test.php', 'args' => ['tests/golden_resolver_test.php']],
    ['label' => 'tests/migration_dump_guard_test.php', 'args' => ['tests/migration_dump_guard_test.php']],
    ['label' => 'tests/admin_import_workflow_test.php', 'args' => ['tests/admin_import_workflow_test.php']],
    ['label' => 'tests/seeded_mock_smoke_test.php', 'args' => ['tests/seeded_mock_smoke_test.php']],
    ['label' => 'tests/ux_structure_test.php', 'args' => ['tests/ux_structure_test.php']],
    ['label' => 'tests/admin_assets_integrity_test.php', 'args' => ['tests/admin_assets_integrity_test.php']],
    ['label' => 'tests/http_e2e_flow_test.php', 'args' => ['tests/http_e2e_flow_test.php']],
    ['label' => 'tests/security_hardening_test.php', 'args' => ['tests/security_hardening_test.php']],
    ['label' => 'tests/secret_scan_test.php', 'args' => ['tests/secret_scan_test.php']],
    ['label' => 'tests/listing_enricher_test.php', 'args' => ['tests/listing_enricher_test.php']],
    ['label' => 'tests/range_answer_test.php', 'args' => ['tests/range_answer_test.php']],
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
