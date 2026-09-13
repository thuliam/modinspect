<?php
$authz=new App\Services\Auth\AuthorizationService();
$canImport=$authz->can($auth_user ?? null,'collection.jobs.requeue');
$summary=$last_import_summary ?? null;
?>
<div class="mi-page-header">
    <div>
        <span class="mi-breadcrumb">Admin / Collection</span>
        <h1>Imports</h1>
        <p>Offline evidence feed, dry-run validation, import jobs, and retry controls.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?= $base ?>/admin/review-queue?dataset=REAL&status=pending"><i class="fas fa-clipboard-check mr-2"></i>Review Pending</a>
</div>

<div class="card mi-admin-card mb-3">
    <div class="card-header"><h2>Feed Evidence File</h2></div>
    <div class="card-body">
        <?php if(!$canImport): ?>
            <div class="alert alert-info mb-0">Your role can inspect imports, but cannot upload or confirm evidence imports.</div>
        <?php else: ?>
            <form method="post" enctype="multipart/form-data" class="form-row align-items-end">
                <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                <div class="form-group col-md-3">
                    <label>Dataset
                        <select class="form-control" name="dataset">
                            <option value="test">TEST / MOCK</option>
                            <option value="real">REAL</option>
                        </select>
                    </label>
                </div>
                <div class="form-group col-md-2"><label>Row limit<input class="form-control" type="number" min="1" max="1000" name="limit" value="100"></label></div>
                <div class="form-group col-md-4"><label>CSV / JSON file<input class="form-control-file" type="file" name="evidence_file" accept=".csv,.json" required></label></div>
                <div class="form-group col-md-3">
                    <button class="btn btn-outline-primary" formaction="<?= $base ?>/admin/collector-jobs/import-dry-run" type="submit">Dry Run</button>
                    <button class="btn btn-primary js-confirm-form" data-confirm="Import this file into the normal review pipeline?" formaction="<?= $base ?>/admin/collector-jobs/import-confirm" type="submit">Confirm Import</button>
                </div>
            </form>
            <p class="text-muted mb-0">REAL rows must carry sufficient provenance and still enter human review. This workflow does not auto-approve observations.</p>
        <?php endif; ?>
    </div>
</div>

<?php if(is_array($summary)): ?>
    <div class="card mi-admin-card mb-3">
        <div class="card-header"><h2>Last Import Summary</h2></div>
        <div class="card-body">
            <dl class="mi-definition-grid">
                <dt>Run</dt><dd><?= htmlspecialchars((string)($summary['run_id'] ?? '-')) ?></dd>
                <dt>Mode</dt><dd><?= !empty($summary['dry_run']) ? 'DRY RUN' : 'IMPORT' ?> / <?= htmlspecialchars((string)($summary['dataset'] ?? '-')) ?></dd>
                <dt>Rows</dt><dd>Scanned <?= (int)($summary['records_scanned'] ?? 0) ?>, valid <?= (int)($summary['valid_rows'] ?? 0) ?>, invalid <?= (int)($summary['invalid_rows'] ?? 0) ?>, duplicates <?= (int)($summary['duplicate_rows'] ?? 0) ?></dd>
                <dt>Pipeline</dt><dd>Candidates <?= (int)($summary['candidates_created'] ?? 0) ?>, evidence <?= (int)($summary['evidence_created'] ?? 0) ?>, reviews <?= (int)($summary['reviews_created'] ?? 0) ?></dd>
                <dt>Lanes</dt><dd>Green <?= (int)($summary['green'] ?? 0) ?>, Amber <?= (int)($summary['amber'] ?? 0) ?>, Red <?= (int)($summary['red'] ?? 0) ?></dd>
            </dl>
            <?php if(!empty($summary['row_errors'])): ?>
                <details class="audit-details"><summary>Validation errors</summary><pre><?= htmlspecialchars(json_encode($summary['row_errors'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre></details>
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
