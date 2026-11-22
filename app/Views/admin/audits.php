<?php ob_start(); ?><?= htmlspecialchars(t('v.audit_logs')) ?><?php $dxT = ob_get_clean(); ob_start(); ?><?= htmlspecialchars(t('v.concise_audit_trail_for_administrati')) ?><?php $dxD = ob_get_clean(); ob_start(); ?><?php $dxA = ob_get_clean(); ?>
<?= mi_page_header($base, array (  0 => 'System',), $dxT, $dxD, $dxA) ?>

<form id="audits-filters" class="card mi-admin-card mb-3 js-datatable-filters" data-target="#audits-table">
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-2"><label><?= htmlspecialchars(t('v.actor_id')) ?><input class="form-control" name="actor" inputmode="numeric"></label></div>
            <div class="form-group col-md-2"><label><?= htmlspecialchars(t('v.action')) ?><input class="form-control" name="action" maxlength="120"></label></div>
            <div class="form-group col-md-2"><label><?= htmlspecialchars(t('v.entity')) ?><input class="form-control" name="entity" maxlength="100"></label></div>
            <div class="form-group col-md-2"><label><?= htmlspecialchars(t('v.from')) ?><input class="form-control" type="date" name="from"></label></div>
            <div class="form-group col-md-2"><label><?= htmlspecialchars(t('v.xe12167aa')) ?><input class="form-control" type="date" name="to"></label></div>
            <div class="form-group col-md-2"><button class="btn btn-primary btn-block" type="submit"><?= htmlspecialchars(t('v.apply')) ?></button></div>
        </div>
    </div>
</form>

<div class="card mi-table-card">
    <div class="card-header"><h2><?= htmlspecialchars(t('v.audit_events')) ?></h2></div>
    <div class="table-responsive">
        <table id="audits-table" class="table table-hover mi-admin-table mb-0 js-server-data-table" data-source-url="<?= $base ?>/admin/audit-logs/data" data-filters="#audits-filters" data-page-length="25">
            <thead><tr><th><?= htmlspecialchars(t('v.timestamp')) ?></th><th><?= htmlspecialchars(t('v.actor')) ?></th><th><?= htmlspecialchars(t('v.action')) ?></th><th><?= htmlspecialchars(t('v.entity')) ?></th><th><?= htmlspecialchars(t('v.summary')) ?></th><th data-orderable="false"><?= htmlspecialchars(t('v.details')) ?></th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>
