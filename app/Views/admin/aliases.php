<div class="mi-page-header">
    <div>
        <span class="mi-breadcrumb">Admin / Catalog</span>
        <h1>Aliases</h1>
        <p>Resolver mapping from observed market naming to canonical Product Master records.</p>
    </div>
    <button class="btn btn-primary js-alias-new" type="button"><i class="fas fa-plus mr-2"></i>Add Alias</button>
</div>

<form id="alias-form" class="card mi-admin-card mb-3" method="post" action="<?= $base ?>/admin/product-aliases/save">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
    <input type="hidden" name="alias_id" value="">
    <div class="card-header"><h2 id="alias-form-title">Add Alias</h2></div>
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-4"><label>Canonical Product<select class="form-control" name="product_id" required><option value="">Select</option><?php foreach($products as $product): ?><option value="<?= (int)$product['id'] ?>"><?= htmlspecialchars((string)$product['full_name']) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-3"><label>Alias text<input class="form-control" name="alias_text" maxlength="255" required></label></div>
            <div class="form-group col-md-3"><label>Source / context<input class="form-control" name="source_note" maxlength="255" placeholder="Manual"></label></div>
            <div class="form-group col-md-2"><label>Confidence<input class="form-control" type="number" min="0" max="100" step="1" name="confidence" value="100"></label></div>
        </div>
        <button class="btn btn-primary" type="submit">Save Alias</button>
    </div>
</form>

<form id="aliases-filters" class="card mi-admin-card mb-3 js-datatable-filters" data-target="#aliases-table">
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-8"><label>Product<select class="form-control" name="product_id"><option value="">All products</option><?php foreach($products as $product): ?><option value="<?= (int)$product['id'] ?>"><?= htmlspecialchars((string)$product['full_name']) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-4"><button class="btn btn-primary btn-block" type="submit">Apply Filters</button></div>
        </div>
    </div>
</form>

<div class="card mi-table-card">
    <div class="card-header"><h2>Alias Mapping</h2></div>
    <div class="table-responsive">
        <table id="aliases-table" class="table table-hover mi-admin-table mb-0 js-server-data-table" data-ajax="<?= $base ?>/admin/product-aliases/data" data-filters="#aliases-filters" data-page-length="25">
            <thead>
                <tr><th>Alias</th><th>Canonical Product</th><th>Normalized</th><th>Confidence</th><th>Source / Context</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
