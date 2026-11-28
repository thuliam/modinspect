<?php
/** LS-89 BE-5: @var list<array> $brands  @var list<array> $categories  @var bool $canManage */
$token = htmlspecialchars(\App\Core\Csrf::token());
$builtIn = \App\Services\Catalog\MasterData::BUILT_IN_CATEGORIES;
$hidden = static fn(string $action, int $id = 0): string => '<input type="hidden" name="_token" value="' . $token . '"><input type="hidden" name="action" value="' . $action . '">' . ($id > 0 ? '<input type="hidden" name="id" value="' . $id . '">' : '');
?>
<?= mi_page_header($base, ['Catalog', 'Brands and categories'], htmlspecialchars(t('md.lists_title')), htmlspecialchars(t('md.lists_intro'))) ?>

<div class="card mi-admin-card mi-table-card mb-3">
    <div class="card-header"><h2><?= htmlspecialchars(t('md.categories', ['n' => count($categories)])) ?></h2></div>
    <?php if ($canManage): ?>
    <div class="card-body">
        <form method="post" action="<?= $base ?>/admin/catalog-lists/action" class="form-row align-items-end" autocomplete="off">
            <?= $hidden('category_save') ?>
            <div class="form-group col-md-4"><label><?= htmlspecialchars(t('md.name')) ?><input class="form-control" name="name" maxlength="100" required></label></div>
            <div class="form-group col-md-3"><label><?= htmlspecialchars(t('md.slug')) ?><input class="form-control" name="slug" maxlength="60" pattern="[a-z0-9][a-z0-9-]{1,59}" required></label></div>
            <div class="form-group col-md-2"><label><?= htmlspecialchars(t('md.sort')) ?><input class="form-control" type="number" name="sort_order" min="0" max="9999" value="100" required></label></div>
            <div class="form-group col-md-3"><button class="btn btn-primary btn-block" type="submit"><i class="fas fa-plus mr-2"></i><?= htmlspecialchars(t('md.add_category')) ?></button></div>
        </form>
    </div>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= htmlspecialchars(t('md.name')) ?></th><th><?= htmlspecialchars(t('md.slug')) ?></th><th class="text-right"><?= htmlspecialchars(t('md.products')) ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($categories as $c): ?>
                <tr>
                    <td>
                        <?php if ($canManage): ?>
                        <form method="post" action="<?= $base ?>/admin/catalog-lists/action" class="form-inline">
                            <?= $hidden('category_save', (int)$c['id']) ?>
                            <input class="form-control form-control-sm mr-2" name="name" value="<?= htmlspecialchars((string)$c['name']) ?>" maxlength="100" required aria-label="<?= htmlspecialchars(t('md.name')) ?>">
                            <input class="form-control form-control-sm mr-2" type="number" name="sort_order" value="<?= (int)$c['sort_order'] ?>" min="0" max="9999" required aria-label="<?= htmlspecialchars(t('md.sort')) ?>">
                            <button class="btn btn-sm btn-outline-primary" type="submit"><?= htmlspecialchars(t('md.save')) ?></button>
                        </form>
                        <?php else: ?><?= htmlspecialchars((string)$c['name']) ?><?php endif; ?>
                    </td>
                    <td><code><?= htmlspecialchars((string)$c['slug']) ?></code></td>
                    <td class="text-right"><?= (int)$c['products'] ?></td>
                    <td class="text-right">
                        <?php if ($canManage && (int)$c['products'] === 0 && !in_array((string)$c['slug'], $builtIn, true)): ?>
                        <form method="post" action="<?= $base ?>/admin/catalog-lists/action" class="form-inline justify-content-end">
                            <?= $hidden('category_delete', (int)$c['id']) ?>
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

<div class="card mi-admin-card mi-table-card">
    <div class="card-header"><h2><?= htmlspecialchars(t('md.brands', ['n' => count($brands)])) ?></h2></div>
    <?php if ($canManage): ?>
    <div class="card-body">
        <form method="post" action="<?= $base ?>/admin/catalog-lists/action" class="form-row align-items-end" autocomplete="off">
            <?= $hidden('brand_save') ?>
            <div class="form-group col-md-6"><label><?= htmlspecialchars(t('md.name')) ?><input class="form-control" name="name" maxlength="100" required></label></div>
            <div class="form-group col-md-3"><button class="btn btn-primary btn-block" type="submit"><i class="fas fa-plus mr-2"></i><?= htmlspecialchars(t('md.add_brand')) ?></button></div>
        </form>
    </div>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= htmlspecialchars(t('md.name')) ?></th><th><?= htmlspecialchars(t('md.slug')) ?></th><th class="text-right"><?= htmlspecialchars(t('md.products')) ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($brands as $b): ?>
                <tr>
                    <td>
                        <?php if ($canManage): ?>
                        <form method="post" action="<?= $base ?>/admin/catalog-lists/action" class="form-inline">
                            <?= $hidden('brand_save', (int)$b['id']) ?>
                            <input class="form-control form-control-sm mr-2" name="name" value="<?= htmlspecialchars((string)$b['name']) ?>" maxlength="100" required aria-label="<?= htmlspecialchars(t('md.name')) ?>">
                            <button class="btn btn-sm btn-outline-primary" type="submit"><?= htmlspecialchars(t('md.save')) ?></button>
                        </form>
                        <?php else: ?><?= htmlspecialchars((string)$b['name']) ?><?php endif; ?>
                    </td>
                    <td><code><?= htmlspecialchars((string)$b['slug']) ?></code></td>
                    <td class="text-right"><?= (int)$b['products'] ?></td>
                    <td class="text-right">
                        <?php if ($canManage && (int)$b['products'] === 0): ?>
                        <form method="post" action="<?= $base ?>/admin/catalog-lists/action" class="form-inline justify-content-end">
                            <?= $hidden('brand_delete', (int)$b['id']) ?>
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
