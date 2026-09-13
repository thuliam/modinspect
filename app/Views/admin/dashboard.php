<div class="mi-page-header">
    <div>
        <span class="mi-breadcrumb">Admin / Dashboard</span>
        <h1>Operations Dashboard</h1>
        <p>Live UAT view of catalog, review backlog, collection health, and public-price readiness.</p>
    </div>
    <a class="btn btn-primary" href="<?= $base ?>/admin/review-queue"><i class="fas fa-clipboard-check mr-2"></i>Open Review Queue</a>
</div>

<div class="row mi-metric-row">
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a class="card mi-metric-card" href="<?= $base ?>/admin/products">
            <span>Active Products</span>
            <strong><?= (int)$products ?></strong>
            <small>CPU <?= (int)$cpu_products ?> / GPU <?= (int)$gpu_products ?></small>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a class="card mi-metric-card mi-metric-warning" href="<?= $base ?>/admin/review-queue?dataset=REAL&status=pending">
            <span>REAL Pending Reviews</span>
            <strong><?= (int)$real_pending_reviews ?></strong>
            <small>Human decisions required</small>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a class="card mi-metric-card" href="<?= $base ?>/admin/review-queue?dataset=MOCK_TEST&status=pending">
            <span>MOCK/Test Pending</span>
            <strong><?= (int)$mock_test_pending_reviews ?></strong>
            <small>Separate from REAL calibration</small>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a class="card mi-metric-card" href="<?= $base ?>/admin/price-observations?dataset=REAL&status=approved">
            <span>Approved REAL</span>
            <strong><?= (int)$approved_real_observations ?></strong>
            <small>No public range until approved evidence exists</small>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a class="card mi-metric-card" href="<?= $base ?>/admin/collector-jobs">
            <span>Collection Jobs</span>
            <strong><?= (int)$jobs_completed ?></strong>
            <small>Queued <?= (int)$jobs_queued ?> / Running <?= (int)$jobs_running ?> / Failed <?= (int)$jobs_failed ?></small>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a class="card mi-metric-card" href="<?= $base ?>/admin/price-indices?provenance_status=recorded">
            <span>Public Eligible Snapshots</span>
            <strong><?= (int)$public_eligible_snapshots ?></strong>
            <small><?= (int)$latest_snapshots ?> total snapshot rows</small>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a class="card mi-metric-card" href="<?= $base ?>/admin/price-observations">
            <span>Pipeline Evidence</span>
            <strong><?= (int)$evidence ?></strong>
            <small>Candidates <?= (int)$candidates ?> / Extractions <?= (int)$extractions ?></small>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a class="card mi-metric-card" href="<?= $base ?>/admin/sources">
            <span>Sources</span>
            <strong><?= (int)$sources ?></strong>
            <small>Paused/disabled <?= (int)$paused_sources ?> / incidents <?= (int)$unresolved_incidents ?></small>
        </a>
    </div>
</div>

<div class="row">
    <div class="col-xl-5">
        <div class="card mi-admin-card">
            <div class="card-header"><h2>Review Backlog</h2></div>
            <div class="card-body">
                <div class="mi-backlog-callout">
                    <div>
                        <span class="badge badge-success">REAL</span>
                        <h3><?= (int)$real_pending_reviews ?> records waiting</h3>
                        <p>Queue defaults to REAL pending and sorts AMBER first for calibration review.</p>
                    </div>
                    <a class="btn btn-outline-primary" href="<?= $base ?>/admin/review-queue?dataset=REAL&status=pending">Review</a>
                </div>
                <div class="mi-status-list">
                    <div><span>All pending observations</span><strong><?= (int)$pending ?></strong></div>
                    <div><span>Review decisions stored</span><strong><?= (int)$reviews ?></strong></div>
                    <div><span>Accepted observations</span><strong><?= (int)$accepted_observations ?></strong></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card mi-admin-card">
            <div class="card-header"><h2>Recent Import Runs</h2></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mi-admin-table mb-0">
                        <thead><tr><th>Run</th><th>Source</th><th>Rows</th><th>Lanes</th><th>Finished</th></tr></thead>
                        <tbody>
                        <?php foreach($recent_imports as $run): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($run['provider']) ?></strong><small><?= htmlspecialchars($run['run_id']) ?></small></td>
                                <td><?= htmlspecialchars($run['source_name']) ?><small><?= htmlspecialchars($run['source_key']) ?></small></td>
                                <td><?= (int)$run['candidates_created'] ?><small>evidence <?= (int)$run['evidence_created'] ?> / reviews <?= (int)$run['reviews_created'] ?></small></td>
                                <td><span class="badge badge-success"><?= (int)$run['green_count'] ?> G</span> <span class="badge badge-warning"><?= (int)$run['amber_count'] ?> A</span> <span class="badge badge-danger"><?= (int)$run['red_count'] ?> R</span></td>
                                <td><a href="<?= $base ?>/admin/collector-jobs?run_id=<?= urlencode((string)$run['run_id']) ?>"><?= htmlspecialchars($run['finished_at']) ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(!$recent_imports): ?><tr><td colspan="5" class="text-center text-muted py-4">No import runs yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-5">
        <div class="card mi-admin-card">
            <div class="card-header"><h2>Source Health</h2></div>
            <div class="card-body">
                <div class="mi-status-list">
                    <?php foreach($source_health_summary as $status=>$count): ?>
                        <div><span><?= htmlspecialchars(ucfirst($status)) ?></span><strong><?= (int)$count ?></strong></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card mi-admin-card">
            <div class="card-header"><h2>Provider Guardrails</h2></div>
            <div class="card-body">
                <dl class="mi-definition-grid">
                    <dt>Gemini adapter</dt><dd><?= $provider_status['adapter_available'] ? 'available' : 'missing' ?></dd>
                    <dt>Provider enabled</dt><dd><?= $provider_status['provider_enabled'] ? 'YES' : 'NO' ?></dd>
                    <dt>API key configured</dt><dd><?= $provider_status['api_key_configured'] ? 'YES' : 'NO' ?></dd>
                    <dt>Live collection</dt><dd><?= $provider_status['live_collection_enabled'] ? 'ENABLED' : 'disabled' ?></dd>
                    <dt>Paid calls</dt><dd><?= $provider_status['paid_provider_calls_enabled'] ? 'ENABLED' : 'disabled' ?></dd>
                </dl>
                <?php if(!$provider_status['live_execution_allowed']): ?>
                    <div class="alert alert-warning mb-0">Live provider execution remains blocked: <?= htmlspecialchars(implode(', ', $provider_status['blockers'])) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
