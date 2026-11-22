<?php ob_start(); ?><?= htmlspecialchars(t('v.price_index')) ?><?php $dxT = ob_get_clean(); ob_start(); ?><?= htmlspecialchars(t('v.operator_view_of_snapshot_readiness')) ?><?php $dxD = ob_get_clean(); ob_start(); ?><button class="btn btn-outline-secondary disabled-control" type="button" disabled><i class="fas fa-sync-alt mr-2"></i><?= htmlspecialchars(t('v.recalculate_via_cli')) ?></button><?php $dxA = ob_get_clean(); ?>
<?= mi_page_header($base, array (  0 => 'Market Data',), $dxT, $dxD, $dxA) ?>

<form id="indices-filters" class="card mi-admin-card mb-3 js-datatable-filters" data-target="#indices-table">
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-3"><label><?= htmlspecialchars(t('v.category')) ?><select class="form-control" name="category"><option value=""><?= htmlspecialchars(t('v.all')) ?></option><?php foreach($categories as $category): ?><option value="<?= htmlspecialchars((string)$category['slug']) ?>"><?= htmlspecialchars((string)$category['name']) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-3"><label><?= htmlspecialchars(t('v.confidence')) ?><select class="form-control" name="confidence"><option value=""><?= htmlspecialchars(t('v.all')) ?></option><?php foreach(['high','medium','medium_low','low','insufficient'] as $confidence): ?><option value="<?= $confidence ?>"><?= htmlspecialchars($confidence) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-3"><label><?= htmlspecialchars(t('v.provenance')) ?><select class="form-control" name="provenance_status"><option value=""><?= htmlspecialchars(t('v.all')) ?></option><option value="recorded"><?= htmlspecialchars(t('v.recorded')) ?></option><option value="legacy_unavailable"><?= htmlspecialchars(t('v.legacy_unavailable')) ?></option></select></label></div>
            <div class="form-group col-md-3"><button class="btn btn-primary btn-block" type="submit"><?= htmlspecialchars(t('v.apply_filters')) ?></button></div>
        </div>
    </div>
</form>

<div class="card mi-table-card">
    <div class="card-header"><h2><?= htmlspecialchars(t('v.current_snapshots')) ?></h2></div>
    <div class="table-responsive">
        <table id="indices-table" class="table table-hover mi-admin-table mb-0 js-server-data-table" data-source-url="<?= $base ?>/admin/price-indices/data" data-filters="#indices-filters" data-page-length="25">
            <thead>
                <tr><th><?= htmlspecialchars(t('v.product')) ?></th><th><?= htmlspecialchars(t('v.price_type')) ?></th><th><?= htmlspecialchars(t('v.market_range')) ?></th><th><?= htmlspecialchars(t('v.median')) ?></th><th><?= htmlspecialchars(t('v.samples')) ?></th><th><?= htmlspecialchars(t('v.confidence')) ?></th><th><?= htmlspecialchars(t('v.freshness')) ?></th><th><?= htmlspecialchars(t('v.provenance')) ?></th><th><?= htmlspecialchars(t('v.snapshot_date')) ?></th><th data-orderable="false"><?= htmlspecialchars(t('v.action')) ?></th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
