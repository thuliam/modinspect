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
<div class="mi-page-header">
    <div>
        <span class="mi-breadcrumb">Admin / Collection</span>
        <h1>Imports</h1>
        <p>Feed evidence files into dry-run validation, then explicitly confirm eligible rows into the review pipeline.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?= $base ?>/admin/review-queue?dataset=REAL&status=pending"><i class="fas fa-clipboard-check mr-2"></i>Review Pending</a>
</div>

<div class="card mi-admin-card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2>Feed Data Workflow</h2>
        <div>
            <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($templateCsvUrl) ?>"><i class="fas fa-file-csv mr-1"></i>Download CSV Template</a>
            <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($templateJsonUrl) ?>"><i class="fas fa-file-code mr-1"></i>Download JSON Template</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row text-center mb-3">
            <?php foreach(['1 Download template','2 Prepare CSV/JSON','3 Upload file','4 Choose dataset','5 Dry Run','6 Review summary','7 Confirm Import'] as $step): ?>
                <div class="col-md col-6 mb-2"><span class="badge badge-light border p-2 w-100"><?= htmlspecialchars($step) ?></span></div>
            <?php endforeach; ?>
        </div>

        <div class="alert alert-info">
            The template is an input format, not a data source. REAL evidence may come from owner/manual collection, supported public-source collection workflows, or files exported/prepared by ModInspect collectors. Importing never auto-approves observations or creates public prices.
        </div>

        <p class="mb-2">
            <button class="btn btn-link p-0" type="button" data-toggle="collapse" data-target="#import-field-guide" aria-expanded="false" aria-controls="import-field-guide">
                <i class="fas fa-info-circle mr-1"></i>Import Format / Field Reference
            </button>
        </p>
        <div id="import-field-guide" class="collapse">
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered mb-0">
                    <thead>
                        <tr><th>Field</th><th>Status</th><th>Meaning</th><th>Example</th><th>Notes / validation</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($fields as $field): ?>
                            <tr>
                                <td><code><?= htmlspecialchars((string)($field['name'] ?? '')) ?></code></td>
                                <td><?= htmlspecialchars((string)($field['status'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($field['meaning'] ?? '')) ?></td>
                                <td><small><?= htmlspecialchars((string)($field['example'] ?? '')) ?></small></td>
                                <td><small><?= htmlspecialchars((string)($field['notes'] ?? '')) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="alert alert-warning">
                REAL rows must have traceable source evidence. The example template uses placeholder/test markers such as EXAMPLE-ITEM-001 and is not market evidence.
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-7">
        <div class="card mi-admin-card mb-3">
            <div class="card-header"><h2>Upload And Dry Run</h2></div>
            <div class="card-body">
                <?php if(!$canImport): ?>
                    <div class="alert alert-info mb-0">Your role can inspect imports, but cannot upload or confirm evidence imports.</div>
                <?php else: ?>
                    <form method="post" enctype="multipart/form-data" action="<?= $base ?>/admin/collector-jobs/import-dry-run">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Dataset
                                    <select class="form-control" name="dataset">
                                        <option value="test">TEST / MOCK</option>
                                        <option value="real">REAL</option>
                                    </select>
                                </label>
                                <small class="form-text text-muted">REAL = traceable evidence that still goes to Human Review. TEST / MOCK = isolated engineering data.</small>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Row limit
                                    <input class="form-control" type="number" min="1" max="1000" name="limit" value="100">
                                </label>
                            </div>
                            <div class="form-group col-md-5">
                                <label>CSV / JSON file
                                    <input class="form-control-file" type="file" name="evidence_file" accept=".csv,.json" required>
                                </label>
                                <small class="form-text text-muted">Accepted files use the downloadable template headers/structure.</small>
                            </div>
                        </div>
                        <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search mr-1"></i>Dry Run</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mi-admin-card mb-3">
            <div class="card-header"><h2>Confirm Import</h2></div>
            <div class="card-body">
                <?php if(!$canImport): ?>
                    <p class="text-muted mb-0">Confirm Import requires import permission.</p>
                <?php elseif(!$pending): ?>
                    <p class="text-muted mb-0">Confirm Import unlocks after a Dry Run finds at least one eligible row for the current uploaded file.</p>
                <?php else: ?>
                    <dl class="mi-definition-grid">
                        <dt>Dry-run file</dt><dd><?= htmlspecialchars((string)($pending['name'] ?? '-')) ?></dd>
                        <dt>Dataset</dt><dd><?= htmlspecialchars(strtoupper((string)($pending['dataset'] ?? '-'))) ?></dd>
                        <dt>Eligible rows</dt><dd><?= (int)($pending['summary']['valid_rows'] ?? 0) ?></dd>
                        <dt>Dry-run time</dt><dd><?= htmlspecialchars((string)($pending['created_at'] ?? '-')) ?></dd>
                    </dl>
                    <form method="post" action="<?= $base ?>/admin/collector-jobs/import-confirm">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                        <button class="btn btn-primary js-confirm-form" data-confirm="Import the eligible rows from the saved dry-run file into the normal review pipeline?" type="submit">
                            <i class="fas fa-cloud-upload-alt mr-1"></i>Confirm Import
                        </button>
                    </form>
                    <small class="form-text text-muted">Confirmation reuses the exact saved file hash from Dry Run; changing the file requires another Dry Run.</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if(is_array($summary)): ?>
    <div class="card mi-admin-card mb-3">
        <div class="card-header"><h2>Last Import Summary</h2></div>
        <div class="card-body">
            <dl class="mi-definition-grid">
                <dt>Run</dt><dd><?= htmlspecialchars((string)($summary['run_id'] ?? '-')) ?></dd>
                <dt>Mode</dt><dd><?= !empty($summary['dry_run']) ? 'DRY RUN' : 'IMPORT' ?> / <?= htmlspecialchars((string)($summary['dataset'] ?? '-')) ?></dd>
                <dt>Rows</dt><dd>Scanned <?= (int)($summary['records_scanned'] ?? 0) ?>, eligible <?= (int)($summary['valid_rows'] ?? 0) ?>, invalid <?= (int)($summary['invalid_rows'] ?? 0) ?>, duplicates <?= (int)($summary['duplicate_rows'] ?? 0) ?></dd>
                <dt>Provenance / policy</dt><dd>Source-policy blocks <?= (int)($summary['source_policy_blocked'] ?? 0) ?>, rows that will import <?= (int)($summary['estimated_candidate_count'] ?? 0) ?></dd>
                <dt>Resolver hints</dt><dd><?= htmlspecialchars(implode(', ', array_slice((array)($summary['product_hints'] ?? []),0,8)) ?: '-') ?></dd>
                <dt>Pipeline</dt><dd>Candidates <?= (int)($summary['candidates_created'] ?? 0) ?>, evidence <?= (int)($summary['evidence_created'] ?? 0) ?>, reviews <?= (int)($summary['reviews_created'] ?? 0) ?></dd>
                <dt>Validation lanes</dt><dd>Green <?= (int)($summary['green'] ?? 0) ?>, Amber <?= (int)($summary['amber'] ?? 0) ?>, Red <?= (int)($summary['red'] ?? 0) ?></dd>
            </dl>
            <?php if(!empty($summary['source_distribution']) && is_array($summary['source_distribution'])): ?>
                <details class="audit-details mb-2"><summary>Source distribution</summary><pre><?= htmlspecialchars(json_encode($summary['source_distribution'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre></details>
            <?php endif; ?>
            <?php if(!empty($summary['row_errors'])): ?>
                <details class="audit-details" open>
                    <summary>Rows that will NOT import</summary>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered">
                            <thead><tr><th>Row</th><th>Reason</th></tr></thead>
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
            <div class="form-group col-md-3"><label>Status<select class="form-control" name="status"><option value="">All</option><?php foreach(['queued','running','completed','failed','skipped'] as $status): ?><option value="<?= $status ?>"><?= ucfirst($status) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-3"><label>Dataset<select class="form-control" name="dataset"><option value="">All</option><option value="REAL">REAL</option><option value="MOCK_TEST">MOCK_TEST</option><option value="UNKNOWN">UNKNOWN</option></select></label></div>
            <div class="form-group col-md-3"><label>Run / source<input class="form-control" name="run_id" maxlength="120" placeholder="run id"></label></div>
            <div class="form-group col-md-3"><button class="btn btn-primary btn-block" type="submit">Apply Filters</button></div>
        </div>
    </div>
</form>

<div class="card mi-table-card">
    <div class="card-header"><h2>Import / Collection Jobs</h2></div>
    <div class="table-responsive">
        <table id="jobs-table" class="table table-hover mi-admin-table mb-0 js-server-data-table" data-ajax="<?= $base ?>/admin/collector-jobs/data" data-filters="#jobs-filters" data-page-length="25">
            <thead>
                <tr><th>Import / Job</th><th>Product</th><th>Source</th><th>Rows</th><th>Status</th><th>Attempts</th><th>Created</th><th>Finished</th><th>Action</th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
