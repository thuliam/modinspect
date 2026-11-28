<?php
/** LS-89 BE-3: @var list<array{code:string,status:string,detail:string}> $checks  @var list<array> $runs  @var bool $jobsTable */
$tone = ['ok' => 'badge-success', 'warn' => 'badge-warning', 'fail' => 'badge-danger', 'running' => 'badge-info', 'failed' => 'badge-danger'];
?>
<?= mi_page_header($base, ['System health'], htmlspecialchars(t('health.title')), htmlspecialchars(t('health.intro'))) ?>

<div class="card mi-admin-card mi-table-card mb-3">
    <div class="card-header"><h2><?= htmlspecialchars(t('health.checks')) ?></h2></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= htmlspecialchars(t('health.col.check')) ?></th><th><?= htmlspecialchars(t('health.col.state')) ?></th><th><?= htmlspecialchars(t('health.col.detail')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($checks as $check): ?>
                <tr><td><code><?= htmlspecialchars($check['code']) ?></code></td><td><span class="badge <?= $tone[$check['status']] ?? 'badge-secondary' ?>"><?= htmlspecialchars(strtoupper($check['status'])) ?></span></td><td><?= htmlspecialchars($check['detail']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mi-admin-card mi-table-card">
    <div class="card-header"><h2><?= htmlspecialchars(t('health.runs')) ?></h2></div>
    <?php if (!$jobsTable): ?>
        <div class="card-body"><div class="alert alert-warning mb-0"><?= htmlspecialchars(t('health.no_job_table')) ?> <code>php cli/migrate_pending.php</code></div></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= htmlspecialchars(t('health.col.job')) ?></th><th><?= htmlspecialchars(t('health.col.state')) ?></th><th><?= htmlspecialchars(t('health.col.started')) ?></th><th><?= htmlspecialchars(t('health.col.finished')) ?></th><th><?= htmlspecialchars(t('health.col.detail')) ?></th></tr></thead>
            <tbody>
            <?php if (!$runs): ?><tr><td colspan="5" class="text-muted"><?= htmlspecialchars(t('health.no_runs')) ?> <code>php cli/daily_jobs.php</code></td></tr><?php endif; ?>
            <?php foreach ($runs as $run): ?>
                <tr>
                    <td><?= htmlspecialchars((string)$run['job_name']) ?></td>
                    <td><span class="badge <?= $tone[$run['status']] ?? 'badge-secondary' ?>"><?= htmlspecialchars(strtoupper((string)$run['status'])) ?></span></td>
                    <td><?= htmlspecialchars((string)$run['started_at']) ?></td>
                    <td><?= htmlspecialchars((string)($run['finished_at'] ?? '-')) ?></td>
                    <td class="text-break"><small><?= htmlspecialchars(trim((string)($run['error_code'] ?? '') . ' ' . (string)($run['summary'] ?? ''))) ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
