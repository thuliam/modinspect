<?php ob_start(); ?>Price Index<?php $dxT = ob_get_clean(); ob_start(); ?>Operator view of snapshot readiness and public price-data availability.<?php $dxD = ob_get_clean(); ob_start(); ?><button class="btn btn-outline-secondary disabled-control" type="button" disabled><i class="fas fa-sync-alt mr-2"></i>Recalculate via CLI</button><?php $dxA = ob_get_clean(); ?>
<?= mi_page_header($base, array (  0 => 'Market Data',), $dxT, $dxD, $dxA) ?>

<form id="indices-filters" class="card mi-admin-card mb-3 js-datatable-filters" data-target="#indices-table">
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-3"><label>Category<select class="form-control" name="category"><option value="">All</option><?php foreach($categories as $category): ?><option value="<?= htmlspecialchars((string)$category['slug']) ?>"><?= htmlspecialchars((string)$category['name']) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-3"><label>Confidence<select class="form-control" name="confidence"><option value="">All</option><?php foreach(['high','medium','medium_low','low','insufficient'] as $confidence): ?><option value="<?= $confidence ?>"><?= htmlspecialchars($confidence) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-3"><label>Provenance<select class="form-control" name="provenance_status"><option value="">All</option><option value="recorded">Recorded</option><option value="legacy_unavailable">Legacy unavailable</option></select></label></div>
            <div class="form-group col-md-3"><button class="btn btn-primary btn-block" type="submit">Apply Filters</button></div>
        </div>
    </div>
</form>

<div class="card mi-table-card">
    <div class="card-header"><h2>Current Snapshots</h2></div>
    <div class="table-responsive">
        <table id="indices-table" class="table table-hover mi-admin-table mb-0 js-server-data-table" data-source-url="<?= $base ?>/admin/price-indices/data" data-filters="#indices-filters" data-page-length="25">
            <thead>
                <tr><th>Product</th><th>Price Type</th><th>Market Range</th><th>Median</th><th>Samples</th><th>Confidence</th><th>Freshness</th><th>Provenance</th><th>Snapshot Date</th><th data-orderable="false">Action</th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
