<div class="mi-page-header">
    <div>
        <span class="mi-breadcrumb">Admin / Catalog</span>
        <h1>Products</h1>
        <p>Canonical Product Master records used by resolution, review, and price snapshots.</p>
    </div>
    <button class="btn btn-primary js-product-new" type="button" aria-controls="product-form-panel" aria-expanded="false">
        <i class="fas fa-plus mr-2"></i>Add Product
    </button>
</div>

<div id="product-form-panel" class="collapse">
    <form id="product-form" class="card mi-admin-card mi-product-form-card mb-3" method="post" action="<?= $base ?>/admin/products/save">
        <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <input type="hidden" name="product_id" value="">
        <div class="card-header mi-product-form-header">
            <div>
                <h2 id="product-form-title">Add Product</h2>
                <p id="product-form-subtitle">Create a canonical Product Master record for resolver and price workflows.</p>
            </div>
            <button class="btn btn-sm btn-outline-secondary js-product-form-close" type="button" aria-label="Close product form">
                <i class="fas fa-times mr-1"></i>Close
            </button>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-lg-3 col-md-6">
                    <label for="product-category">Category</label>
                    <select id="product-category" class="form-control" name="category_id" required>
                        <option value="">Select</option>
                        <?php foreach($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars((string)$category['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-lg-3 col-md-6">
                    <label for="product-brand">Brand</label>
                    <select id="product-brand" class="form-control" name="brand_id" required>
                        <option value="">Select</option>
                        <?php foreach($brands as $brand): ?><option value="<?= (int)$brand['id'] ?>"><?= htmlspecialchars((string)$brand['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-lg-3 col-md-6">
                    <label for="product-model">Model</label>
                    <input id="product-model" class="form-control" name="model_name" maxlength="160" required>
                </div>
                <div class="form-group col-lg-3 col-md-6">
                    <label for="product-slug">Slug</label>
                    <input id="product-slug" class="form-control" name="slug" maxlength="190" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-lg-5 col-md-12">
                    <label for="product-full-name">Full name</label>
                    <input id="product-full-name" class="form-control" name="full_name" maxlength="255" required>
                </div>
                <div class="form-group col-lg-2 col-md-4">
                    <label for="product-generation">Generation</label>
                    <input id="product-generation" class="form-control" name="generation" maxlength="100">
                </div>
                <div class="form-group col-lg-3 col-md-5">
                    <label for="product-image-path">Image path</label>
                    <input id="product-image-path" class="form-control" name="image_path" maxlength="255" placeholder="assets/images/...">
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <span class="mi-field-label">Status</span>
                    <div class="custom-control custom-switch mi-product-active-switch">
                        <input id="product-active" class="custom-control-input" type="checkbox" name="is_active" value="1" checked>
                        <label class="custom-control-label" for="product-active">Active</label>
                    </div>
                </div>
            </div>
            <div class="form-group mb-0">
                <label for="product-spec-summary">Key specification</label>
                <textarea id="product-spec-summary" class="form-control" name="spec_summary" rows="3"></textarea>
            </div>
        </div>
        <div class="card-footer mi-product-form-actions">
            <button id="product-form-submit-label" class="btn btn-primary" type="submit">
                <i class="fas fa-save mr-2"></i>Save Product
            </button>
            <button class="btn btn-outline-secondary js-product-form-close" type="button">Cancel</button>
        </div>
    </form>
</div>

<form id="products-filters" class="card mi-admin-card mi-filter-card mb-3 js-datatable-filters" data-target="#products-table">
    <div class="card-header">
        <h2>Filters</h2>
    </div>
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-lg-3 col-md-4">
                <label for="products-filter-category">Category</label>
                <select id="products-filter-category" class="form-control" name="category_id">
                    <option value="">All</option>
                    <?php foreach($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars((string)$category['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-lg-3 col-md-4">
                <label for="products-filter-brand">Brand</label>
                <select id="products-filter-brand" class="form-control" name="brand_id">
                    <option value="">All</option>
                    <?php foreach($brands as $brand): ?><option value="<?= (int)$brand['id'] ?>"><?= htmlspecialchars((string)$brand['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-lg-3 col-md-4">
                <label for="products-filter-active">Status</label>
                <select id="products-filter-active" class="form-control" name="active">
                    <option value="">All</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="form-group col-lg-3 col-md-12">
                <button class="btn btn-primary btn-block" type="submit">
                    <i class="fas fa-filter mr-2"></i>Apply Filters
                </button>
            </div>
        </div>
    </div>
</form>

<div class="card mi-table-card">
    <div class="card-header mi-products-table-header">
        <div>
            <h2>Product Master</h2>
            <p>Search, filter, edit, and activate canonical products used by market data workflows.</p>
        </div>
    </div>
    <div class="table-responsive">
        <table id="products-table" class="table table-hover mi-admin-table mb-0 js-server-data-table" data-ajax="<?= $base ?>/admin/products/data" data-filters="#products-filters" data-page-length="25">
            <thead>
                <tr><th>Product</th><th>Brand</th><th>Category</th><th>Key Specification</th><th>Market Data</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
