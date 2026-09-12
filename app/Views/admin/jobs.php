<?php $authz=new App\Services\Auth\AuthorizationService(); $canRequeue=$authz->can($auth_user ?? null,'collection.jobs.requeue'); ?>
<div class="mi-page-header">
    <div>
        <span class="mi-breadcrumb">Admin / Collection</span>
        <h1>Imports</h1>
        <p>Collector and offline import jobs with yield, status, and retry controls.</p>
    </div>
    <button class="btn btn-outline-secondary disabled-control" type="button" disabled><i class="fas fa-plus mr-2"></i>Create Job</button>
</div>

<div class="card mi-table-card">
    <div class="card-header">
        <div class="mi-table-toolbar mb-0">
            <h2>Latest Jobs</h2>
            <input class="form-control mi-table-search js-table-filter" data-target="#jobs-table" placeholder="Search job, source, product">
        </div>
    </div>
    <div class="table-responsive">
        <table id="jobs-table" class="table table-hover mi-admin-table mb-0">
            <thead>
                <tr>
                    <th>Import / Job</th>
                    <th>Product</th>
                    <th>Source</th>
                    <th>Rows</th>
                    <th>Status</th>
                    <th>Attempts</th>
                    <th>Created</th>
                    <th>Finished</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($jobs as $j): ?>
                <?php $filter=strtolower(implode(' ',[$j['job_type'] ?? '',$j['source_name'] ?? '',$j['full_name'] ?? '',$j['status'] ?? '',$j['run_id'] ?? ''])); ?>
                <tr data-filter-row="<?= htmlspecialchars($filter) ?>">
                    <td>#<?= (int)$j['id'] ?><small><?= htmlspecialchars((string)$j['job_type']) ?></small></td>
                    <td><?= htmlspecialchars((string)($j['full_name'] ?? $j['query_text'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string)$j['source_name']) ?><small><?= htmlspecialchars((string)($j['source_key'] ?? '-')) ?></small></td>
                    <td><?= (int)($j['raw_count'] ?? 0) ?><small>Valid <?= (int)($j['valid_count'] ?? 0) ?></small></td>
                    <td><span class="badge badge-light"><?= htmlspecialchars((string)$j['status']) ?></span></td>
                    <td><?= (int)($j['attempt_count'] ?? 0) ?></td>
                    <td><?= htmlspecialchars((string)($j['created_at'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string)($j['completed_at'] ?? '-')) ?></td>
                    <td>
                        <?php if($j['status']==='failed' && $canRequeue): ?>
                            <form method="post" action="<?= $base ?>/admin/collector-jobs/requeue">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                <input type="hidden" name="job_id" value="<?= (int)$j['id'] ?>">
                                <button class="btn btn-sm btn-primary">Requeue</button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-secondary disabled-control" type="button" disabled>Unavailable</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if(!$jobs): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No collector or import jobs found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
