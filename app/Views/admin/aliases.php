<?php ob_start(); ?>Aliases<?php $dxT = ob_get_clean(); ob_start(); ?>Resolver mapping from observed market naming to canonical Product Master records.<?php $dxD = ob_get_clean(); ob_start(); ?><button class="btn btn-primary js-alias-new" type="button"><i class="fas fa-plus mr-2"></i>Add Alias</button><?php $dxA = ob_get_clean(); ?>
<?php $canManage = $canManage ?? true; ?>
<?= mi_page_header($base, array (  0 => 'Catalog',), $dxT, $dxD, $canManage ? $dxA : '') ?>

<?php if ($canManage): ?>
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
<?php endif; ?>

<?php if ($canManage): $import = $import ?? null; $importText = $importText ?? ''; $stClass = ['new' => 'success', 'exists' => 'secondary', 'conflict' => 'danger', 'unknown_product' => 'danger', 'invalid' => 'danger', 'duplicate_in_file' => 'warning']; ?>
<form id="alias-import-form" class="card mi-admin-card mb-3" method="post" action="<?= $base ?>/admin/product-aliases/import">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
    <div class="card-header"><h2>Bulk import</h2></div>
    <div class="card-body">
        <div class="form-group"><label for="alias-lines">One alias per line: product-slug, alias text, optional source note (max <?= \App\Services\Catalog\AliasImport::MAX_LINES ?> lines)</label>
            <textarea id="alias-lines" class="form-control" name="alias_lines" rows="6" placeholder="amd-ryzen-5-5600, ryzen 5 5600 ลดราคา"><?= htmlspecialchars($importText) ?></textarea></div>
        <?php if ($import): ?>
            <p class="mb-2"><?php foreach ($import['counts'] as $k => $v): if ($v > 0): ?><span class="badge badge-<?= $stClass[$k] ?> mr-1"><?= htmlspecialchars($k) ?> <?= (int) $v ?></span><?php endif; endforeach; ?><?= $import['truncated'] ? ' <span class="text-muted">only the first ' . \App\Services\Catalog\AliasImport::MAX_LINES . ' lines were read</span>' : '' ?></p>
            <div class="table-responsive mb-3"><table class="table table-sm mi-admin-table mb-0"><thead><tr><th>Line</th><th>Product</th><th>Alias</th><th>Result</th></tr></thead><tbody>
            <?php foreach ($import['rows'] as $r): ?><tr><td><?= (int) $r['line'] ?></td><td><?= htmlspecialchars($r['slug']) ?></td><td><?= htmlspecialchars($r['alias']) ?></td><td><span class="badge badge-<?= $stClass[$r['status']] ?>"><?= htmlspecialchars($r['status']) ?></span> <small class="text-muted"><?= htmlspecialchars(\App\Services\Catalog\AliasImport::HINTS[$r['status']] ?? '') ?></small></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
        <button class="btn btn-secondary" type="submit" name="mode" value="check">Check only</button>
        <?php if ($import && $import['counts']['new'] > 0): ?><button class="btn btn-primary" type="submit" name="mode" value="confirm">Import <?= (int) $import['counts']['new'] ?> new aliases</button><?php endif; ?>
    </div>
</form>
<?php endif; ?>

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
        <table id="aliases-table" class="table table-hover mi-admin-table mb-0 js-server-data-table" data-source-url="<?= $base ?>/admin/product-aliases/data" data-filters="#aliases-filters" data-page-length="25">
            <thead>
                <tr><th>Alias</th><th>Canonical Product</th><th>Normalized</th><th>Confidence</th><th>Source / Context</th><th>Status</th><th data-orderable="false">Action</th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
