<?php
$authz=new App\Services\Auth\AuthorizationService();
$canImport=$authz->can($auth_user ?? null,'collection.jobs.requeue');
$summary=$last_import_summary ?? null;
$pending=is_array($pending_import ?? null) ? $pending_import : null;
$fields=is_array($import_fields ?? null) ? $import_fields : [];
$templateCsvUrl=$base.'/admin/collector-jobs/template.csv';
$templateJsonUrl=$base.'/admin/collector-jobs/template.json';
$errorLabel=static fn(string $value): string => ucwords(strtolower(str_replace('_',' ',$value)));
?>
<?php ob_start(); ?><?= htmlspecialchars(t('v.imports')) ?><?php $dxT = ob_get_clean(); ob_start(); ?><?= htmlspecialchars(t('v.feed_evidence_files_into_dry_run_val')) ?><?php $dxD = ob_get_clean(); ob_start(); ?><a class="btn btn-outline-primary" href="<?= $base ?>/admin/review-queue?dataset=REAL&status=pending"><i class="fas fa-clipboard-check mr-2"></i><?= htmlspecialchars(t('v.review_pending')) ?></a><?php $dxA = ob_get_clean(); ?>
<?= mi_page_header($base, array (  0 => 'Collection',), $dxT, $dxD, $dxA) ?>

<div class="card mi-admin-card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2><?= htmlspecialchars(t('v.feed_data_workflow')) ?></h2>
        <div>
            <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($templateCsvUrl) ?>"><i class="fas fa-file-alt mr-1"></i><?= htmlspecialchars(t('v.download_csv_template')) ?></a>
            <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($templateJsonUrl) ?>"><i class="fas fa-file-code mr-1"></i><?= htmlspecialchars(t('v.download_json_template')) ?></a>
        </div>
    </div>
    <div class="card-body">
        <div class="row text-center mb-3">
            <?php foreach([t('v.1_download_template'),t('v.2_prepare_csv_json'),t('v.3_upload_file'),t('v.4_choose_dataset'),t('v.5_dry_run'),t('v.6_review_summary'),t('v.7_confirm_import')] as $step): ?>
                <div class="col-md col-6 mb-2"><span class="badge badge-light border p-2 w-100"><?= htmlspecialchars($step) ?></span></div>
            <?php endforeach; ?>
        </div>

        <div class="alert alert-info">
            <?= htmlspecialchars(t('v.the_template_is_an_input_format_not')) ?>
        </div>

        <p class="mb-2">
            <button class="btn btn-link p-0" type="button" data-toggle="collapse" data-target="#import-field-guide" aria-expanded="false" aria-controls="import-field-guide">
                <i class="fas fa-info-circle mr-1"></i><?= htmlspecialchars(t('v.import_format_field_reference')) ?>
            </button>
        </p>
        <div id="import-field-guide" class="collapse">
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered mb-0">
                    <thead>
                        <tr><th><?= htmlspecialchars(t('v.field')) ?></th><th><?= htmlspecialchars(t('v.status')) ?></th><th><?= htmlspecialchars(t('v.meaning')) ?></th><th><?= htmlspecialchars(t('v.example')) ?></th><th><?= htmlspecialchars(t('v.notes_validation')) ?></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($fields as $field): ?>
                            <tr>
                                <td><code><?= htmlspecialchars((string)($field['name'] ?? '')) ?></code></td>
                                <td><?= htmlspecialchars(\App\Core\Lang::message((string)($field['status'] ?? ''))) ?></td>
                                <td><?= htmlspecialchars(\App\Core\Lang::message((string)($field['meaning'] ?? ''))) ?></td>
                                <td><small><?= htmlspecialchars((string)($field['example'] ?? '')) ?></small></td>
                                <td><small><?= htmlspecialchars(\App\Core\Lang::message((string)($field['notes'] ?? ''))) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="alert alert-warning">
                <?= htmlspecialchars(t('v.real_rows_must_have_traceable_source')) ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-7">
        <div class="card mi-admin-card mb-3">
            <div class="card-header"><h2><?= htmlspecialchars(t('v.upload_and_dry_run')) ?></h2></div>
            <div class="card-body">
                <?php if(!$canImport): ?>
                    <div class="alert alert-info mb-0"><?= htmlspecialchars(t('v.your_role_can_inspect_imports_but_ca')) ?></div>
                <?php else: ?>
                    <form method="post" enctype="multipart/form-data" action="<?= $base ?>/admin/collector-jobs/import-dry-run">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label><?= htmlspecialchars(t('v.dataset')) ?>
                                    <select class="form-control" name="dataset">
                                        <option value="test"><?= htmlspecialchars(t('v.test_mock')) ?></option>
                                        <option value="real"><?= htmlspecialchars(t('v.real')) ?></option>
                                    </select>
                                </label>
                                <small class="form-text text-muted"><?= htmlspecialchars(t('v.real_traceable_evidence_that_still_g')) ?></small>
                            </div>
                            <div class="form-group col-md-3">
                                <label><?= htmlspecialchars(t('v.row_limit')) ?>
                                    <input class="form-control" type="number" min="1" max="1000" name="limit" value="100">
                                </label>
                            </div>
                            <div class="form-group col-md-5">
                                <label><?= htmlspecialchars(t('v.csv_json_file')) ?>
                                    <input class="form-control-file" type="file" name="evidence_file" accept=".csv,.json" required>
                                </label>
                                <small class="form-text text-muted"><?= htmlspecialchars(t('v.accepted_files_use_the_downloadable')) ?></small>
                            </div>
                        </div>
                        <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search mr-1"></i><?= htmlspecialchars(t('v.dry_run_2')) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mi-admin-card mb-3">
            <div class="card-header"><h2><?= htmlspecialchars(t('v.confirm_import')) ?></h2></div>
            <div class="card-body">
                <?php if(!$canImport): ?>
                    <p class="text-muted mb-0"><?= htmlspecialchars(t('v.confirm_import_requires_import_permi')) ?></p>
                <?php elseif(!$pending): ?>
                    <p class="text-muted mb-0"><?= htmlspecialchars(t('v.confirm_import_unlocks_after_a_dry_r')) ?></p>
                <?php else: ?>
                    <dl class="mi-definition-grid">
                        <dt><?= htmlspecialchars(t('v.dry_run_file')) ?></dt><dd><?= htmlspecialchars((string)($pending['name'] ?? '-')) ?></dd>
                        <dt><?= htmlspecialchars(t('v.dataset')) ?></dt><dd><?= htmlspecialchars(strtoupper((string)($pending['dataset'] ?? '-'))) ?></dd>
                        <dt><?= htmlspecialchars(t('v.eligible_rows')) ?></dt><dd><?= (int)($pending['summary']['valid_rows'] ?? 0) ?></dd>
                        <dt><?= htmlspecialchars(t('v.dry_run_time')) ?></dt><dd><?= htmlspecialchars((string)($pending['created_at'] ?? '-')) ?></dd>
                    </dl>
                    <form method="post" action="<?= $base ?>/admin/collector-jobs/import-confirm">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                        <button class="btn btn-primary js-confirm-form" data-confirm="<?= htmlspecialchars(t('js.import_confirm')) ?>" type="submit">
                            <i class="fas fa-cloud-upload-alt mr-1"></i><?= htmlspecialchars(t('v.confirm_import')) ?>
                        </button>
                    </form>
                    <small class="form-text text-muted"><?= htmlspecialchars(t('v.confirmation_reuses_the_exact_saved')) ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if(is_array($summary)): ?>
    <div class="card mi-admin-card mb-3">
        <div class="card-header"><h2><?= htmlspecialchars(t('v.last_import_summary')) ?></h2></div>
        <div class="card-body">
            <dl class="mi-definition-grid">
                <dt><?= htmlspecialchars(t('v.run')) ?></dt><dd><?= htmlspecialchars((string)($summary['run_id'] ?? '-')) ?></dd>
                <dt><?= htmlspecialchars(t('v.mode')) ?></dt><dd><?= !empty($summary['dry_run']) ? t('v.dry_run_3') : t('v.import_2') ?> / <?= htmlspecialchars((string)($summary['dataset'] ?? '-')) ?></dd>
                <dt><?= htmlspecialchars(t('v.rows')) ?></dt><dd><?= htmlspecialchars(t('v.scanned')) ?> <?= (int)($summary['records_scanned'] ?? 0) ?><?= htmlspecialchars(t('v.eligible')) ?> <?= (int)($summary['valid_rows'] ?? 0) ?><?= htmlspecialchars(t('v.invalid_2')) ?> <?= (int)($summary['invalid_rows'] ?? 0) ?><?= htmlspecialchars(t('v.duplicates')) ?> <?= (int)($summary['duplicate_rows'] ?? 0) ?></dd>
                <dt><?= htmlspecialchars(t('v.provenance_policy')) ?></dt><dd><?= htmlspecialchars(t('v.source_policy_blocks')) ?> <?= (int)($summary['source_policy_blocked'] ?? 0) ?><?= htmlspecialchars(t('v.rows_that_will_import')) ?> <?= (int)($summary['estimated_candidate_count'] ?? 0) ?></dd>
                <dt><?= htmlspecialchars(t('v.resolver_hints')) ?></dt><dd><?= htmlspecialchars(implode(', ', array_slice((array)($summary['product_hints'] ?? []),0,8)) ?: '-') ?></dd>
                <dt><?= htmlspecialchars(t('v.pipeline')) ?></dt><dd><?= htmlspecialchars(t('v.candidates')) ?> <?= (int)($summary['candidates_created'] ?? 0) ?><?= htmlspecialchars(t('v.evidence_2')) ?> <?= (int)($summary['evidence_created'] ?? 0) ?><?= htmlspecialchars(t('v.reviews_2')) ?> <?= (int)($summary['reviews_created'] ?? 0) ?></dd>
                <dt><?= htmlspecialchars(t('v.validation_lanes')) ?></dt><dd><?= htmlspecialchars(t('v.green')) ?> <?= (int)($summary['green'] ?? 0) ?><?= htmlspecialchars(t('v.amber')) ?> <?= (int)($summary['amber'] ?? 0) ?><?= htmlspecialchars(t('v.red')) ?> <?= (int)($summary['red'] ?? 0) ?></dd>
            </dl>
            <?php if(!empty($summary['source_distribution']) && is_array($summary['source_distribution'])): ?>
                <details class="audit-details mb-2"><summary><?= htmlspecialchars(t('v.source_distribution')) ?></summary><pre><?= htmlspecialchars(json_encode($summary['source_distribution'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre></details>
            <?php endif; ?>
            <?php if(!empty($summary['row_errors'])): ?>
                <details class="audit-details" open>
                    <summary><?= htmlspecialchars(t('v.rows_that_will_not_import')) ?></summary>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered">
                            <thead><tr><th><?= htmlspecialchars(t('v.row')) ?></th><th><?= htmlspecialchars(t('v.reason')) ?></th></tr></thead>
                            <tbody>
                                <?php foreach((array)$summary['row_errors'] as $error): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)($error['row'] ?? 'file')) ?></td>
                                        <td>
                                            <?php foreach((array)($error['errors'] ?? []) as $reason): ?>
                                                <span class="badge badge-warning mr-1"><?= htmlspecialchars($errorLabel((string)$reason)) ?></span>
                                            <?php endforeach; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<form id="jobs-filters" class="card mi-admin-card mb-3 js-datatable-filters" data-target="#jobs-table">
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-3"><label><?= htmlspecialchars(t('v.status')) ?><select class="form-control" name="status"><option value=""><?= htmlspecialchars(t('v.all')) ?></option><?php foreach(['queued','running','completed','failed','skipped'] as $status): ?><option value="<?= $status ?>"><?= \App\Core\Lang::status((string) $status) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-3"><label><?= htmlspecialchars(t('v.dataset')) ?><select class="form-control" name="dataset"><option value=""><?= htmlspecialchars(t('v.all')) ?></option><option value="REAL"><?= htmlspecialchars(t('v.real')) ?></option><option value="MOCK_TEST"><?= htmlspecialchars(t('v.mock_test')) ?></option><option value="UNKNOWN"><?= htmlspecialchars(t('v.unknown')) ?></option></select></label></div>
            <div class="form-group col-md-3"><label><?= htmlspecialchars(t('v.run_source')) ?><input class="form-control" name="run_id" maxlength="120" placeholder="<?= htmlspecialchars(t('v.run_id')) ?>"></label></div>
            <div class="form-group col-md-3"><button class="btn btn-primary btn-block" type="submit"><?= htmlspecialchars(t('v.apply_filters')) ?></button></div>
        </div>
    </div>
</form>

<div class="card mi-table-card">
    <div class="card-header"><h2><?= htmlspecialchars(t('v.import_collection_jobs')) ?></h2></div>
    <div class="table-responsive">
        <table id="jobs-table" class="table table-hover mi-admin-table mb-0 js-server-data-table" data-source-url="<?= $base ?>/admin/collector-jobs/data" data-filters="#jobs-filters" data-page-length="25">
            <thead>
                <tr><th><?= htmlspecialchars(t('v.import_job')) ?></th><th><?= htmlspecialchars(t('v.product')) ?></th><th><?= htmlspecialchars(t('v.source')) ?></th><th><?= htmlspecialchars(t('v.rows')) ?></th><th><?= htmlspecialchars(t('v.status')) ?></th><th><?= htmlspecialchars(t('v.attempts')) ?></th><th><?= htmlspecialchars(t('v.created')) ?></th><th><?= htmlspecialchars(t('v.finished')) ?></th><th><?= htmlspecialchars(t('v.action')) ?></th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
