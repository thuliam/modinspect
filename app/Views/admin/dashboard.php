<?php ob_start(); ?><?= htmlspecialchars(t('v.operations_dashboard')) ?><?php $dxT = ob_get_clean(); ob_start(); ?><?= htmlspecialchars(t('v.live_uat_view_of_catalog_review_back')) ?><?php $dxD = ob_get_clean(); ob_start(); ?><a class="btn btn-primary" href="<?= $base ?>/admin/review-queue"><i class="fas fa-clipboard-check mr-2"></i><?= htmlspecialchars(t('v.open_review_queue')) ?></a><?php $dxA = ob_get_clean(); ?>
<?= mi_page_header($base, array (  0 => 'Dashboard',), $dxT, $dxD, $dxA) ?>

<div class="row mi-metric-row">
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a class="card mi-metric-card gradient-primary" href="<?= $base ?>/admin/products">
            <span><?= htmlspecialchars(t('v.active_products')) ?></span>
            <strong><?= (int)$products ?></strong>
            <small><?= htmlspecialchars(t('v.cpu')) ?> <?= (int)$cpu_products ?> <?= htmlspecialchars(t('v.gpu')) ?> <?= (int)$gpu_products ?></small>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a class="card mi-metric-card gradient-warning" href="<?= $base ?>/admin/review-queue?dataset=REAL&status=pending">
            <span><?= htmlspecialchars(t('v.real_pending_reviews')) ?></span>
            <strong><?= (int)$real_pending_reviews ?></strong>
            <small><?= htmlspecialchars(t('v.human_decisions_required')) ?></small>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a class="card mi-metric-card gradient-info" href="<?= $base ?>/admin/review-queue?dataset=MOCK_TEST&status=pending">
            <span><?= htmlspecialchars(t('v.mock_test_pending')) ?></span>
            <strong><?= (int)$mock_test_pending_reviews ?></strong>
            <small><?= htmlspecialchars(t('v.separate_from_real_calibration')) ?></small>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a class="card mi-metric-card gradient-purple" href="<?= $base ?>/admin/price-observations?dataset=REAL&status=approved">
            <span><?= htmlspecialchars(t('v.approved_real')) ?></span>
            <strong><?= (int)$approved_real_observations ?></strong>
            <small><?= htmlspecialchars(t('v.no_public_range_until_approved_evide')) ?></small>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a class="card mi-metric-card gradient-ocean" href="<?= $base ?>/admin/collector-jobs">
            <span><?= htmlspecialchars(t('v.collection_jobs')) ?></span>
            <strong><?= (int)$jobs_completed ?></strong>
            <small><?= htmlspecialchars(t('v.queued')) ?> <?= (int)$jobs_queued ?> <?= htmlspecialchars(t('v.running')) ?> <?= (int)$jobs_running ?> <?= htmlspecialchars(t('v.failed')) ?> <?= (int)$jobs_failed ?></small>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a class="card mi-metric-card gradient-pink" href="<?= $base ?>/admin/price-indices?provenance_status=recorded">
            <span><?= htmlspecialchars(t('v.public_eligible_snapshots')) ?></span>
            <strong><?= (int)$public_eligible_snapshots ?></strong>
            <small><?= (int)$latest_snapshots ?> <?= htmlspecialchars(t('v.total_snapshot_rows')) ?></small>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a class="card mi-metric-card gradient-primary" href="<?= $base ?>/admin/price-observations">
            <span><?= htmlspecialchars(t('v.pipeline_evidence')) ?></span>
            <strong><?= (int)$evidence ?></strong>
            <small><?= htmlspecialchars(t('v.candidates')) ?> <?= (int)$candidates ?> <?= htmlspecialchars(t('v.extractions')) ?> <?= (int)$extractions ?></small>
        </a>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <a class="card mi-metric-card gradient-success" href="<?= $base ?>/admin/sources">
            <span><?= htmlspecialchars(t('v.sources')) ?></span>
            <strong><?= (int)$sources ?></strong>
            <small><?= htmlspecialchars(t('v.paused_disabled')) ?> <?= (int)$paused_sources ?> <?= htmlspecialchars(t('v.incidents')) ?> <?= (int)$unresolved_incidents ?></small>
        </a>
    </div>
</div>

<div class="row">
    <div class="col-xl-5">
        <div class="card mi-admin-card">
            <div class="card-header"><h2><?= htmlspecialchars(t('v.review_backlog')) ?></h2></div>
            <div class="card-body">
                <div class="mi-backlog-callout">
                    <div>
                        <span class="badge badge-success"><?= htmlspecialchars(t('v.real')) ?></span>
                        <h3><?= (int)$real_pending_reviews ?> <?= htmlspecialchars(t('v.records_waiting')) ?></h3>
                        <p><?= htmlspecialchars(t('v.queue_defaults_to_real_pending_and_s')) ?></p>
                    </div>
                    <a class="btn btn-outline-primary" href="<?= $base ?>/admin/review-queue?dataset=REAL&status=pending"><?= htmlspecialchars(t('v.review')) ?></a>
                </div>
                <div class="mi-status-list">
                    <div><span><?= htmlspecialchars(t('v.all_pending_observations')) ?></span><strong><?= (int)$pending ?></strong></div>
                    <div><span><?= htmlspecialchars(t('v.review_decisions_stored')) ?></span><strong><?= (int)$reviews ?></strong></div>
                    <div><span><?= htmlspecialchars(t('v.accepted_observations')) ?></span><strong><?= (int)$accepted_observations ?></strong></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card mi-admin-card">
            <div class="card-header"><h2><?= htmlspecialchars(t('v.recent_import_runs')) ?></h2></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mi-admin-table mb-0">
                        <thead><tr><th><?= htmlspecialchars(t('v.run')) ?></th><th><?= htmlspecialchars(t('v.source')) ?></th><th><?= htmlspecialchars(t('v.rows')) ?></th><th><?= htmlspecialchars(t('v.lanes')) ?></th><th><?= htmlspecialchars(t('v.finished')) ?></th></tr></thead>
                        <tbody>
                        <?php foreach($recent_imports as $run): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($run['provider']) ?></strong><small><?= htmlspecialchars($run['run_id']) ?></small></td>
                                <td><?= htmlspecialchars($run['source_name']) ?><small><?= htmlspecialchars($run['source_key']) ?></small></td>
                                <td><?= (int)$run['candidates_created'] ?><small><?= htmlspecialchars(t('v.evidence')) ?> <?= (int)$run['evidence_created'] ?> <?= htmlspecialchars(t('v.reviews')) ?> <?= (int)$run['reviews_created'] ?></small></td>
                                <td><span class="badge badge-success"><?= (int)$run['green_count'] ?> G</span> <span class="badge badge-warning"><?= (int)$run['amber_count'] ?> A</span> <span class="badge badge-danger"><?= (int)$run['red_count'] ?> R</span></td>
                                <td><a href="<?= $base ?>/admin/collector-jobs?run_id=<?= urlencode((string)$run['run_id']) ?>"><?= htmlspecialchars($run['finished_at']) ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(!$recent_imports): ?><tr><td colspan="5" class="text-center text-muted py-4"><?= htmlspecialchars(t('v.no_import_runs_yet')) ?></td></tr><?php endif; ?>
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
            <div class="card-header"><h2><?= htmlspecialchars(t('v.source_health')) ?></h2></div>
            <div class="card-body">
                <div class="mi-status-list">
                    <?php foreach($source_health_summary as $status=>$count): ?>
                        <div><span><?= htmlspecialchars(\App\Core\Lang::status((string) $status)) ?></span><strong><?= (int)$count ?></strong></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card mi-admin-card">
            <div class="card-header"><h2><?= htmlspecialchars(t('v.provider_guardrails')) ?></h2></div>
            <div class="card-body">
                <dl class="mi-definition-grid">
                    <dt><?= htmlspecialchars(t('v.gemini_adapter')) ?></dt><dd><?= $provider_status['adapter_available'] ? t('st.available') : t('st.missing') ?></dd>
                    <dt><?= htmlspecialchars(t('v.provider_enabled')) ?></dt><dd><?= $provider_status['provider_enabled'] ? t('st.yes') : t('st.no') ?></dd>
                    <dt><?= htmlspecialchars(t('v.api_key_configured')) ?></dt><dd><?= $provider_status['api_key_configured'] ? t('st.yes') : t('st.no') ?></dd>
                    <dt><?= htmlspecialchars(t('v.live_collection')) ?></dt><dd><?= $provider_status['live_collection_enabled'] ? t('st.enabled') : t('st.disabled') ?></dd>
                    <dt><?= htmlspecialchars(t('v.paid_calls')) ?></dt><dd><?= $provider_status['paid_provider_calls_enabled'] ? t('st.enabled') : t('st.disabled') ?></dd>
                </dl>
                <?php if(!$provider_status['live_execution_allowed']): ?>
                    <div class="alert alert-warning mb-0"><?= htmlspecialchars(t('v.live_provider_execution_remains_bloc')) ?> <?= htmlspecialchars(implode(', ', array_map(static fn($b) => \App\Core\Lang::status((string) $b), $provider_status['blockers']))) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
