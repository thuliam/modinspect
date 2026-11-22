<?php ob_start(); ?><?= htmlspecialchars(t('v.aliases')) ?><?php $dxT = ob_get_clean(); ob_start(); ?><?= htmlspecialchars(t('v.resolver_mapping_from_observed_marke')) ?><?php $dxD = ob_get_clean(); ob_start(); ?><button class="btn btn-primary js-alias-new" type="button"><i class="fas fa-plus mr-2"></i><?= htmlspecialchars(t('v.add_alias')) ?></button><?php $dxA = ob_get_clean(); ?>
<?php $canManage = $canManage ?? true; ?>
<?= mi_page_header($base, array (  0 => 'Catalog',), $dxT, $dxD, $canManage ? $dxA : '') ?>

<?php if ($canManage): ?>
<form id="alias-form" class="card mi-admin-card mb-3" method="post" action="<?= $base ?>/admin/product-aliases/save">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
    <input type="hidden" name="alias_id" value="">
    <div class="card-header"><h2 id="alias-form-title"><?= htmlspecialchars(t('v.add_alias')) ?></h2></div>
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-4"><label><?= htmlspecialchars(t('v.canonical_product')) ?><select class="form-control" name="product_id" required><option value=""><?= htmlspecialchars(t('v.select')) ?></option><?php foreach($products as $product): ?><option value="<?= (int)$product['id'] ?>"><?= htmlspecialchars((string)$product['full_name']) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-3"><label><?= htmlspecialchars(t('v.alias_text')) ?><input class="form-control" name="alias_text" maxlength="255" required></label></div>
            <div class="form-group col-md-3"><label><?= htmlspecialchars(t('v.source_context')) ?><input class="form-control" name="source_note" maxlength="255" placeholder="<?= htmlspecialchars(t('v.manual')) ?>"></label></div>
            <div class="form-group col-md-2"><label><?= htmlspecialchars(t('v.confidence')) ?><input class="form-control" type="number" min="0" max="100" step="1" name="confidence" value="100"></label></div>
        </div>
        <button class="btn btn-primary" type="submit"><?= htmlspecialchars(t('v.save_alias')) ?></button>
    </div>
</form>
<?php endif; ?>

<?php if ($canManage): $import = $import ?? null; $importText = $importText ?? ''; $stClass = ['new' => 'success', 'exists' => 'secondary', 'conflict' => 'danger', 'unknown_product' => 'danger', 'invalid' => 'danger', 'duplicate_in_file' => 'warning']; ?>
<form id="alias-import-form" class="card mi-admin-card mb-3" method="post" action="<?= $base ?>/admin/product-aliases/import">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
    <div class="card-header"><h2><?= htmlspecialchars(t('v.bulk_import')) ?></h2></div>
    <div class="card-body">
        <div class="form-group"><label for="alias-lines"><?= htmlspecialchars(t('v.one_alias_per_line_product_slug_alia')) ?> <?= \App\Services\Catalog\AliasImport::MAX_LINES ?> <?= htmlspecialchars(t('v.lines')) ?></label>
            <textarea id="alias-lines" class="form-control" name="alias_lines" rows="6" placeholder="<?= htmlspecialchars(t('v.amd_ryzen_5_5600_ryzen_5_5600')) ?>"><?= htmlspecialchars($importText) ?></textarea></div>
        <?php if ($import): ?>
            <p class="mb-2"><?php foreach ($import['counts'] as $k => $v): if ($v > 0): ?><span class="badge badge-<?= $stClass[$k] ?> mr-1"><?= htmlspecialchars($k) ?> <?= (int) $v ?></span><?php endif; endforeach; ?><?= $import['truncated'] ? ' <span class="text-muted">only the first ' . \App\Services\Catalog\AliasImport::MAX_LINES . ' lines were read</span>' : '' ?></p>
            <div class="table-responsive mb-3"><table class="table table-sm mi-admin-table mb-0"><thead><tr><th><?= htmlspecialchars(t('v.line')) ?></th><th><?= htmlspecialchars(t('v.product')) ?></th><th><?= htmlspecialchars(t('v.alias')) ?></th><th><?= htmlspecialchars(t('v.result')) ?></th></tr></thead><tbody>
            <?php foreach ($import['rows'] as $r): ?><tr><td><?= (int) $r['line'] ?></td><td><?= htmlspecialchars($r['slug']) ?></td><td><?= htmlspecialchars($r['alias']) ?></td><td><span class="badge badge-<?= $stClass[$r['status']] ?>"><?= htmlspecialchars($r['status']) ?></span> <small class="text-muted"><?= htmlspecialchars(\App\Services\Catalog\AliasImport::HINTS[$r['status']] ?? '') ?></small></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
        <button class="btn btn-secondary" type="submit" name="mode" value="check"><?= htmlspecialchars(t('v.check_only')) ?></button>
        <?php if ($import && $import['counts']['new'] > 0): ?><button class="btn btn-primary" type="submit" name="mode" value="confirm"><?= htmlspecialchars(t('v.import')) ?> <?= (int) $import['counts']['new'] ?> <?= htmlspecialchars(t('v.new_aliases')) ?></button><?php endif; ?>
    </div>
</form>
<?php endif; ?>

<form id="aliases-filters" class="card mi-admin-card mb-3 js-datatable-filters" data-target="#aliases-table">
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-8"><label><?= htmlspecialchars(t('v.product')) ?><select class="form-control" name="product_id"><option value=""><?= htmlspecialchars(t('v.all_products')) ?></option><?php foreach($products as $product): ?><option value="<?= (int)$product['id'] ?>"><?= htmlspecialchars((string)$product['full_name']) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-4"><button class="btn btn-primary btn-block" type="submit"><?= htmlspecialchars(t('v.apply_filters')) ?></button></div>
        </div>
    </div>
</form>

<div class="card mi-table-card">
    <div class="card-header"><h2><?= htmlspecialchars(t('v.alias_mapping')) ?></h2></div>
    <div class="table-responsive">
        <table id="aliases-table" class="table table-hover mi-admin-table mb-0 js-server-data-table" data-source-url="<?= $base ?>/admin/product-aliases/data" data-filters="#aliases-filters" data-page-length="25">
            <thead>
                <tr><th><?= htmlspecialchars(t('v.alias')) ?></th><th><?= htmlspecialchars(t('v.canonical_product')) ?></th><th><?= htmlspecialchars(t('v.normalized')) ?></th><th><?= htmlspecialchars(t('v.confidence')) ?></th><th><?= htmlspecialchars(t('v.source_context_2')) ?></th><th><?= htmlspecialchars(t('v.status')) ?></th><th data-orderable="false"><?= htmlspecialchars(t('v.action')) ?></th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
