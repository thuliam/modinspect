<?php
/** LS-89 BE-5: @var array $product  @var list<array> $specs  @var bool $ready  @var bool $canManage */
$token = htmlspecialchars(\App\Core\Csrf::token());
ob_start(); ?><a class="btn btn-outline-secondary" href="<?= $base ?>/admin/products"><i class="fas fa-arrow-left mr-2"></i><?= htmlspecialchars(t('md.back_to_products')) ?></a>
<a class="btn btn-outline-primary" href="<?= $base ?>/admin/products/<?= (int)$product['id'] ?>/pictures"><?= htmlspecialchars(t('md.pictures')) ?></a><?php $actions = ob_get_clean(); ?>
<?= mi_page_header($base, ['Catalog', 'Product specs'], htmlspecialchars((string)$product['full_name']), htmlspecialchars(t('md.specs_intro')), $actions) ?>

<?php if (!$ready): ?>
    <div class="alert alert-warning"><?= htmlspecialchars(t('md.err.TABLE_MISSING')) ?></div>
<?php else: ?>
<?php if ($canManage): ?>
<form class="card mi-admin-card mb-3" method="post" action="<?= $base ?>/admin/products/specs/action" autocomplete="off">
    <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
    <div class="card-header"><h2><?= htmlspecialchars(t('md.spec_form')) ?></h2></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group col-md-3"><label><?= htmlspecialchars(t('md.spec_key')) ?><input class="form-control" name="spec_key" list="spec-keys" pattern="[a-z][a-z0-9_]{1,59}" required></label>
                <datalist id="spec-keys"><?php foreach ($specs as $s): ?><option value="<?= htmlspecialchars((string)$s['spec_key']) ?>"></option><?php endforeach; ?></datalist></div>
            <div class="form-group col-md-3"><label><?= htmlspecialchars(t('md.spec_value')) ?><input class="form-control" name="spec_value" maxlength="120" required></label></div>
            <div class="form-group col-md-2"><label><?= htmlspecialchars(t('md.unit')) ?><input class="form-control" name="unit" maxlength="12"></label></div>
            <div class="form-group col-md-4"><label><?= htmlspecialchars(t('md.source_url')) ?><input class="form-control" type="url" name="source_url" maxlength="500" placeholder="https://" required></label></div>
        </div>
        <div class="form-row align-items-end">
            <div class="form-group col-md-2"><label><?= htmlspecialchars(t('md.date_checked')) ?><input class="form-control" type="date" name="date_checked" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required></label></div>
            <div class="form-group col-md-3"><label><?= htmlspecialchars(t('md.review_status')) ?><select class="form-control" name="review_status"><?php foreach (\App\Services\Catalog\SpecImport::STATUSES as $status): ?><option value="<?= $status ?>"<?= $status === 'needs_variant_review' ? ' selected' : '' ?>><?= htmlspecialchars(t('md.status.' . $status)) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-5"><label><?= htmlspecialchars(t('md.note')) ?><input class="form-control" name="review_note" maxlength="500"></label></div>
            <div class="form-group col-md-2"><button class="btn btn-primary btn-block" type="submit"><?= htmlspecialchars(t('md.save')) ?></button></div>
        </div>
    </div>
</form>
<?php endif; ?>

<div class="card mi-admin-card mi-table-card">
    <div class="card-header"><h2><?= htmlspecialchars(t('md.specs', ['n' => count($specs)])) ?></h2></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= htmlspecialchars(t('md.spec_key')) ?></th><th><?= htmlspecialchars(t('md.spec_value')) ?></th><th><?= htmlspecialchars(t('md.source_url')) ?></th><th><?= htmlspecialchars(t('md.date_checked')) ?></th><th><?= htmlspecialchars(t('md.review_status')) ?></th><th></th></tr></thead>
            <tbody>
            <?php if (!$specs): ?><tr><td colspan="6" class="text-muted"><?= htmlspecialchars(t('md.no_specs')) ?></td></tr><?php endif; ?>
            <?php foreach ($specs as $s): ?>
                <tr>
                    <td><code><?= htmlspecialchars((string)$s['spec_key']) ?></code></td>
                    <td><?= htmlspecialchars(trim($s['spec_value'] . ' ' . ($s['unit'] ?? ''))) ?><?php if (!empty($s['review_note'])): ?><br><small class="text-muted"><?= htmlspecialchars((string)$s['review_note']) ?></small><?php endif; ?></td>
                    <td><?php if (preg_match('#^https?://#i', (string)$s['source_url'])): ?><a href="<?= htmlspecialchars((string)$s['source_url']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= htmlspecialchars((string)(parse_url((string)$s['source_url'], PHP_URL_HOST) ?: 'link')) ?></a><?php else: ?><?= htmlspecialchars((string)$s['source_url']) ?><?php endif; ?></td>
                    <td><?= htmlspecialchars((string)$s['date_checked']) ?></td>
                    <td><?= htmlspecialchars(t('md.status.' . $s['review_status'])) ?></td>
                    <td class="text-right">
                        <?php if ($canManage): ?>
                        <form method="post" action="<?= $base ?>/admin/products/specs/action" class="form-inline justify-content-end">
                            <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>"><input type="hidden" name="spec_key" value="<?= htmlspecialchars((string)$s['spec_key']) ?>">
                            <label class="mr-2 mb-0"><input type="checkbox" name="confirm" value="1" required class="mr-1"><?= htmlspecialchars(t('md.confirm')) ?></label>
                            <button class="btn btn-sm btn-outline-danger" type="submit"><?= htmlspecialchars(t('md.delete')) ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
