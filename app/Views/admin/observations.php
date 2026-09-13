<div class="mi-page-header">
    <div>
        <span class="mi-breadcrumb">Admin / Market Data</span>
        <h1>Observations</h1>
        <p>Operational observation view. Full evidence and review decisions stay behind detail workflows.</p>
    </div>
    <a class="btn btn-primary" href="<?= $base ?>/admin/review-queue"><i class="fas fa-clipboard-check mr-2"></i>Review Queue</a>
</div>

<form id="observations-filters" class="card mi-admin-card mb-3 js-datatable-filters" data-target="#observations-table">
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-2"><label>Dataset<select class="form-control" name="dataset"><option value="">All</option><option value="REAL">REAL</option><option value="MOCK_TEST">MOCK_TEST</option><option value="UNKNOWN">UNKNOWN</option></select></label></div>
            <div class="form-group col-md-2"><label>Status<select class="form-control" name="status"><option value="">All</option><?php foreach(['pending','approved','rejected','excluded'] as $status): ?><option value="<?= $status ?>"><?= htmlspecialchars(ucfirst($status)) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-2"><label>Price Type<select class="form-control" name="price_type"><option value="">All</option><?php foreach(['asking','sold','trade_in','new'] as $type): ?><option value="<?= $type ?>"><?= htmlspecialchars($type) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-2"><label>Provenance<select class="form-control" name="provenance"><option value="">All</option><option value="LISTING_LEVEL">Listing level</option><option value="SEARCH_RESULT_LEVEL">Search result</option><option value="GENERIC_SOURCE">Generic</option></select></label></div>
            <div class="form-group col-md-4"><label>Batch / Run<select class="form-control" name="run_id"><option value="">All</option><?php foreach($batch_options as $batch): ?><option value="<?= htmlspecialchars((string)$batch['value']) ?>"><?= htmlspecialchars((string)$batch['label']) ?> - <?= (int)$batch['count'] ?></option><?php endforeach; ?></select></label></div>
        </div>
        <div class="form-row align-items-end">
            <div class="form-group col-md-5"><label>Product<select class="form-control" name="product_id"><option value="">All products</option><?php foreach($products as $product): ?><option value="<?= (int)$product['id'] ?>"><?= htmlspecialchars((string)$product['full_name']) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-5"><label>Source<select class="form-control" name="source_id"><option value="">All sources</option><?php foreach($sources as $source): ?><option value="<?= (int)$source['id'] ?>"><?= htmlspecialchars((string)$source['name']) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-2"><button class="btn btn-primary btn-block" type="submit">Apply Filters</button></div>
        </div>
    </div>
</form>

<div class="card mi-table-card">
    <div class="card-header"><h2>Market Observations</h2></div>
    <div class="table-responsive">
        <table id="observations-table" class="table table-hover mi-admin-table mb-0 js-server-data-table" data-ajax="<?= $base ?>/admin/price-observations/data" data-filters="#observations-filters" data-page-length="25">
            <thead>
                <tr><th>Observation</th><th>Product</th><th>Dataset</th><th>Provenance</th><th>Price Type</th><th>Price</th><th>Source</th><th>Review Status</th><th>Date</th><th>Action</th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
