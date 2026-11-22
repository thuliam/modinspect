<?php ob_start(); ?><?= htmlspecialchars(t('v.observations')) ?><?php $dxT = ob_get_clean(); ob_start(); ?><?= htmlspecialchars(t('v.operational_observation_view_full_ev')) ?><?php $dxD = ob_get_clean(); ob_start(); ?><a class="btn btn-primary" href="<?= $base ?>/admin/review-queue"><i class="fas fa-clipboard-check mr-2"></i><?= htmlspecialchars(t('v.review_queue')) ?></a><?php $dxA = ob_get_clean(); ?>
<?= mi_page_header($base, array (  0 => 'Market Data',), $dxT, $dxD, $dxA) ?>

<form id="observations-filters" class="card mi-admin-card mb-3 js-datatable-filters" data-target="#observations-table">
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-2"><label><?= htmlspecialchars(t('v.dataset')) ?><select class="form-control" name="dataset"><option value=""><?= htmlspecialchars(t('v.all')) ?></option><option value="REAL"><?= htmlspecialchars(t('v.real')) ?></option><option value="MOCK_TEST"><?= htmlspecialchars(t('v.mock_test')) ?></option><option value="UNKNOWN"><?= htmlspecialchars(t('v.unknown')) ?></option></select></label></div>
            <div class="form-group col-md-2"><label><?= htmlspecialchars(t('v.status')) ?><select class="form-control" name="status"><option value=""><?= htmlspecialchars(t('v.all')) ?></option><?php foreach(['pending','approved','rejected','excluded'] as $status): ?><option value="<?= $status ?>"><?= htmlspecialchars(\App\Core\Lang::status((string) $status)) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-2"><label><?= htmlspecialchars(t('v.price_type')) ?><select class="form-control" name="price_type"><option value=""><?= htmlspecialchars(t('v.all')) ?></option><?php foreach(['asking','sold','trade_in','new'] as $type): ?><option value="<?= $type ?>"><?= htmlspecialchars(\App\Core\Lang::status($type)) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-2"><label><?= htmlspecialchars(t('v.provenance')) ?><select class="form-control" name="provenance"><option value=""><?= htmlspecialchars(t('v.all')) ?></option><option value="LISTING_LEVEL"><?= htmlspecialchars(t('v.listing_level')) ?></option><option value="SEARCH_RESULT_LEVEL"><?= htmlspecialchars(t('v.search_result')) ?></option><option value="GENERIC_SOURCE"><?= htmlspecialchars(t('v.generic')) ?></option></select></label></div>
            <div class="form-group col-md-4"><label><?= htmlspecialchars(t('v.batch_run')) ?><select class="form-control" name="run_id"><option value=""><?= htmlspecialchars(t('v.all')) ?></option><?php foreach($batch_options as $batch): ?><option value="<?= htmlspecialchars((string)$batch['value']) ?>"><?= htmlspecialchars((string)$batch['label']) ?> - <?= (int)$batch['count'] ?></option><?php endforeach; ?></select></label></div>
        </div>
        <div class="form-row align-items-end">
            <div class="form-group col-md-5"><label><?= htmlspecialchars(t('v.product')) ?><select class="form-control" name="product_id"><option value=""><?= htmlspecialchars(t('v.all_products')) ?></option><?php foreach($products as $product): ?><option value="<?= (int)$product['id'] ?>"><?= htmlspecialchars((string)$product['full_name']) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-5"><label><?= htmlspecialchars(t('v.source')) ?><select class="form-control" name="source_id"><option value=""><?= htmlspecialchars(t('v.all_sources')) ?></option><?php foreach($sources as $source): ?><option value="<?= (int)$source['id'] ?>"><?= htmlspecialchars((string)$source['name']) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-2"><button class="btn btn-primary btn-block" type="submit"><?= htmlspecialchars(t('v.apply_filters')) ?></button></div>
        </div>
    </div>
</form>

<div class="card mi-table-card">
    <div class="card-header"><h2><?= htmlspecialchars(t('v.market_observations')) ?></h2></div>
    <div class="table-responsive">
        <table id="observations-table" class="table table-hover mi-admin-table mb-0 js-server-data-table" data-source-url="<?= $base ?>/admin/price-observations/data" data-filters="#observations-filters" data-page-length="25">
            <thead>
                <tr><th><?= htmlspecialchars(t('v.observation')) ?></th><th><?= htmlspecialchars(t('v.product')) ?></th><th><?= htmlspecialchars(t('v.dataset')) ?></th><th><?= htmlspecialchars(t('v.provenance')) ?></th><th><?= htmlspecialchars(t('v.price_type')) ?></th><th><?= htmlspecialchars(t('v.price')) ?></th><th><?= htmlspecialchars(t('v.source')) ?></th><th><?= htmlspecialchars(t('v.review_status')) ?></th><th><?= htmlspecialchars(t('v.date')) ?></th><th data-orderable="false"><?= htmlspecialchars(t('v.action')) ?></th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
