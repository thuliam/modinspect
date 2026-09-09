<?php $authz=new App\Services\Auth\AuthorizationService(); $canRequeue=$authz->can($auth_user ?? null,'collection.jobs.requeue'); ?>
<section class="container page admin-page">
<?php require __DIR__.'/_nav.php'; ?>
<div class="section-head">
    <div><span class="eyebrow">COLLECTOR JOBS</span><h1>งานเก็บข้อมูล</h1></div>
    <button class="disabled-control" type="button" disabled>สร้างงานเอง — ยังไม่เปิดใช้งาน</button>
</div>
<div class="card table-card">
<table>
<thead><tr><th>Job</th><th>Product</th><th>Source/provider</th><th>Status</th><th>Attempts</th><th>Counts</th><th>Time</th><th>Error/run</th><th>Action</th></tr></thead>
<tbody>
<?php foreach($jobs as $j): ?>
<tr>
    <td>#<?= (int)$j['id'] ?><small><?= htmlspecialchars($j['job_type']) ?></small></td>
    <td><?= htmlspecialchars($j['full_name'] ?? $j['query_text'] ?? '-') ?></td>
    <td><?= htmlspecialchars($j['source_name']) ?><small><?= htmlspecialchars($j['source_key'] ?? '-') ?></small></td>
    <td><span class="badge"><?= htmlspecialchars($j['status']) ?></span></td>
    <td><?= (int)($j['attempt_count'] ?? 0) ?></td>
    <td>Raw <?= (int)$j['raw_count'] ?><small>Valid <?= (int)$j['valid_count'] ?></small></td>
    <td><small>Created <?= htmlspecialchars($j['created_at'] ?? '-') ?></small><small>Started <?= htmlspecialchars($j['started_at'] ?? '-') ?></small><small>Finished <?= htmlspecialchars($j['completed_at'] ?? '-') ?></small></td>
    <td><small><?= htmlspecialchars($j['run_id'] ?? '-') ?></small><small><?= htmlspecialchars($j['error_message'] ?? '') ?></small></td>
    <td>
        <?php if($j['status']==='failed' && $canRequeue): ?>
            <form method="post" action="<?= $base ?>/admin/collector-jobs/requeue" class="inline-form">
                <input type="hidden" name="_token" value="<?= \App\Core\Csrf::token() ?>">
                <input type="hidden" name="job_id" value="<?= (int)$j['id'] ?>">
                <button class="small">Requeue</button>
            </form>
        <?php elseif($j['status']==='failed'): ?>
            <button class="small disabled-control" type="button" disabled>ไม่มีสิทธิ์ Requeue</button>
        <?php else: ?>
            <button class="small disabled-control" type="button" disabled>Requeue unavailable</button>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
<?php if(!$jobs): ?><tr><td colspan="9" class="empty-cell">ยังไม่มี collector job</td></tr><?php endif; ?>
</tbody>
</table>
</div>
</section>
