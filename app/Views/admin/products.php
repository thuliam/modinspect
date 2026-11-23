<?php ob_start(); ?><?= htmlspecialchars(t('v.products')) ?><?php $dxT = ob_get_clean(); ob_start(); ?><?= htmlspecialchars(t('v.canonical_product_master_records_use')) ?><?php $dxD = ob_get_clean(); ob_start(); ?><button class="btn btn-primary js-product-new" type="button" aria-controls="product-form-panel" aria-expanded="false">
        <i class="fas fa-plus mr-2"></i><?= htmlspecialchars(t('v.add_product')) ?>
    </button><?php $dxA = ob_get_clean(); ?>
<?php $canManage = $canManage ?? true; ?>
<?= mi_page_header($base, array (  0 => 'Catalog',), $dxT, $dxD, $canManage ? $dxA : '') ?>

<?php if ($canManage): ?>
<div id="product-form-panel" class="collapse">
    <form id="product-form" class="card mi-admin-card mi-product-form-card mb-3" method="post" action="<?= $base ?>/admin/products/save">
        <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <input type="hidden" name="product_id" value="">
        <div class="card-header mi-product-form-header">
            <div>
                <h2 id="product-form-title"><?= htmlspecialchars(t('v.add_product')) ?></h2>
                <p id="product-form-subtitle"><?= htmlspecialchars(t('v.create_a_canonical_product_master_re')) ?></p>
            </div>
            <button class="btn btn-sm btn-outline-secondary js-product-form-close" type="button" aria-label="<?= htmlspecialchars(t('v.close_product_form')) ?>">
                <i class="fas fa-times mr-1"></i><?= htmlspecialchars(t('v.close')) ?>
            </button>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-lg-3 col-md-6">
                    <label for="product-category"><?= htmlspecialchars(t('v.category')) ?></label>
                    <select id="product-category" class="form-control" name="category_id" required>
                        <option value=""><?= htmlspecialchars(t('v.select')) ?></option>
                        <?php foreach($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars((string)$category['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-lg-3 col-md-6">
                    <label for="product-brand"><?= htmlspecialchars(t('v.brand')) ?></label>
                    <select id="product-brand" class="form-control" name="brand_id" required>
                        <option value=""><?= htmlspecialchars(t('v.select')) ?></option>
                        <?php foreach($brands as $brand): ?><option value="<?= (int)$brand['id'] ?>"><?= htmlspecialchars((string)$brand['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-lg-3 col-md-6">
                    <label for="product-model"><?= htmlspecialchars(t('v.model')) ?></label>
                    <input id="product-model" class="form-control" name="model_name" maxlength="160" required>
                </div>
                <div class="form-group col-lg-3 col-md-6">
                    <label for="product-slug"><?= htmlspecialchars(t('v.slug')) ?></label>
                    <input id="product-slug" class="form-control" name="slug" maxlength="190" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-lg-5 col-md-12">
                    <label for="product-full-name"><?= htmlspecialchars(t('v.full_name')) ?></label>
                    <input id="product-full-name" class="form-control" name="full_name" maxlength="255" required>
                </div>
                <div class="form-group col-lg-2 col-md-4">
                    <label for="product-generation"><?= htmlspecialchars(t('v.generation')) ?></label>
                    <input id="product-generation" class="form-control" name="generation" maxlength="100">
                </div>
                <div class="form-group col-lg-3 col-md-5">
                    <label for="product-image-path"><?= htmlspecialchars(t('v.image_path')) ?></label>
                    <input id="product-image-path" class="form-control" name="image_path" maxlength="255" placeholder="<?= htmlspecialchars(t('v.assets_images')) ?>">
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <span class="mi-field-label"><?= htmlspecialchars(t('v.status')) ?></span>
                    <div class="custom-control custom-checkbox mi-product-active-switch">
                        <input id="product-active" class="custom-control-input" type="checkbox" name="is_active" value="1" checked>
                        <label class="custom-control-label" for="product-active"><?= htmlspecialchars(t('v.active')) ?></label>
                    </div>
                </div>
            </div>
            <div class="form-group mb-0">
                <label for="product-spec-summary"><?= htmlspecialchars(t('v.key_specification')) ?></label>
                <textarea id="product-spec-summary" class="form-control" name="spec_summary" rows="3"></textarea>
            </div>
        </div>
        <div class="card-footer mi-product-form-actions">
            <button id="product-form-submit-label" class="btn btn-primary" type="submit">
                <i class="fas fa-save mr-2"></i><?= htmlspecialchars(t('v.save_product')) ?>
            </button>
            <button class="btn btn-outline-secondary js-product-form-close" type="button"><?= htmlspecialchars(t('v.cancel')) ?></button>
        </div>
    </form>
</div>
<?php endif; ?>

<form id="products-filters" class="card mi-admin-card mi-filter-card mb-3 js-datatable-filters" data-target="#products-table">
    <div class="card-header">
        <h2><?= htmlspecialchars(t('v.filters')) ?></h2>
    </div>
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-lg-3 col-md-4">
                <label for="products-filter-category"><?= htmlspecialchars(t('v.category')) ?></label>
                <select id="products-filter-category" class="form-control" name="category_id">
                    <option value=""><?= htmlspecialchars(t('v.all')) ?></option>
                    <?php foreach($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars((string)$category['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-lg-3 col-md-4">
                <label for="products-filter-brand"><?= htmlspecialchars(t('v.brand')) ?></label>
                <select id="products-filter-brand" class="form-control" name="brand_id">
                    <option value=""><?= htmlspecialchars(t('v.all')) ?></option>
                    <?php foreach($brands as $brand): ?><option value="<?= (int)$brand['id'] ?>"><?= htmlspecialchars((string)$brand['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-lg-3 col-md-4">
                <label for="products-filter-active"><?= htmlspecialchars(t('v.status')) ?></label>
                <select id="products-filter-active" class="form-control" name="active">
                    <option value=""><?= htmlspecialchars(t('v.all')) ?></option>
                    <option value="1"><?= htmlspecialchars(t('v.active')) ?></option>
                    <option value="0"><?= htmlspecialchars(t('v.inactive')) ?></option>
                </select>
            </div>
            <div class="form-group col-lg-3 col-md-12">
                <button class="btn btn-primary btn-block" type="submit">
                    <i class="fas fa-filter mr-2"></i><?= htmlspecialchars(t('v.apply_filters')) ?>
                </button>
            </div>
        </div>
    </div>
</form>

<div class="card mi-table-card">
    <div class="card-header mi-products-table-header">
        <div>
            <h2><?= htmlspecialchars(t('v.product_master')) ?></h2>
            <p><?= htmlspecialchars(t('v.search_filter_edit_and_activate_cano')) ?></p>
        </div>
    </div>
    <div class="table-responsive">
        <table id="products-table" class="table table-hover mi-admin-table mb-0 js-server-data-table" data-source-url="<?= $base ?>/admin/products/data" data-filters="#products-filters" data-page-length="25">
            <thead>
                <tr><th><?= htmlspecialchars(t('v.product')) ?></th><th><?= htmlspecialchars(t('v.brand')) ?></th><th><?= htmlspecialchars(t('v.category')) ?></th><th><?= htmlspecialchars(t('v.key_specification_2')) ?></th><th><?= htmlspecialchars(t('v.market_data')) ?></th><th><?= htmlspecialchars(t('v.status')) ?></th><th data-orderable="false"><?= htmlspecialchars(t('v.action')) ?></th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
