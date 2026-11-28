<?php
/** LS-89 BE-5: @var array $product  @var array{slots:list<array>,primary:?string,shared:array<string,int>} $pictures  @var bool $ready  @var int $maxSlots  @var bool $canManage */
$token = htmlspecialchars(\App\Core\Csrf::token());
$id = (int)$product['id'];
$hidden = static fn(string $action, int $slot = 0): string => '<input type="hidden" name="_token" value="' . $token . '"><input type="hidden" name="action" value="' . $action . '"><input type="hidden" name="product_id" value="' . $id . '">' . ($slot > 0 ? '<input type="hidden" name="slot" value="' . $slot . '">' : '');
$src = static fn(string $path): string => preg_match('#^https?://#i', $path) ? $path : $base . '/' . ltrim($path, '/');
ob_start(); ?><a class="btn btn-outline-secondary" href="<?= $base ?>/admin/products"><i class="fas fa-arrow-left mr-2"></i><?= htmlspecialchars(t('md.back_to_products')) ?></a>
<a class="btn btn-outline-primary" href="<?= $base ?>/admin/products/<?= $id ?>/specs"><?= htmlspecialchars(t('md.specs_button')) ?></a><?php $actions = ob_get_clean(); ?>
<?= mi_page_header($base, ['Catalog', 'Product pictures'], htmlspecialchars((string)$product['full_name']), htmlspecialchars(t('md.pictures_intro')), $actions) ?>

<?php if (!$ready): ?><div class="alert alert-warning"><?= htmlspecialchars(t('md.err.TABLE_MISSING')) ?></div><?php endif; ?>

<?php if (!$pictures['slots'] && $pictures['primary'] !== null): ?>
<div class="card mi-admin-card mb-3">
    <div class="card-header"><h2><?= htmlspecialchars(t('md.single_picture')) ?></h2></div>
    <div class="card-body">
        <img class="img-thumbnail mb-2" width="240" src="<?= htmlspecialchars($src($pictures['primary'])) ?>" alt="<?= htmlspecialchars((string)$product['full_name']) ?>">
        <p><code><?= htmlspecialchars($pictures['primary']) ?></code></p>
        <?php if (($pictures['shared'][$pictures['primary']] ?? 0) > 0): ?><p><span class="badge badge-warning"><?= htmlspecialchars(t('md.shared', ['n' => $pictures['shared'][$pictures['primary']]])) ?></span></p><?php endif; ?>
        <?php if ($canManage): ?>
        <form method="post" action="<?= $base ?>/admin/products/pictures/action" class="form-inline">
            <?= $hidden('clear_primary') ?>
            <label class="mr-2 mb-0"><input type="checkbox" name="confirm" value="1" required class="mr-1"><?= htmlspecialchars(t('md.confirm')) ?></label>
            <button class="btn btn-sm btn-outline-danger" type="submit"><?= htmlspecialchars(t('md.remove')) ?></button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <?php foreach ($pictures['slots'] as $picture): $slot = (int)$picture['slot']; ?>
    <div class="col-lg-4 col-md-6">
        <div class="card mi-admin-card mb-3">
            <div class="card-header"><h2><?= htmlspecialchars(t('md.slot', ['n' => $slot])) ?></h2></div>
            <div class="card-body">
                <img class="img-fluid img-thumbnail mb-2" src="<?= htmlspecialchars($src((string)$picture['path'])) ?>" alt="<?= htmlspecialchars((string)($picture['alt_text'] ?: $product['full_name'])) ?>">
                <p class="mb-1"><small><?= htmlspecialchars($picture['source_name'] . ' · ' . $picture['license_note']) ?></small></p>
                <?php if (($pictures['shared'][$picture['path']] ?? 0) > 0): ?><p><span class="badge badge-warning"><?= htmlspecialchars(t('md.shared', ['n' => $pictures['shared'][$picture['path']]])) ?></span></p><?php endif; ?>
                <?php if ($canManage): ?>
                <form method="post" action="<?= $base ?>/admin/products/pictures/action" class="form-inline mb-2">
                    <?= $hidden('move', $slot) ?>
                    <label class="mr-2 mb-0"><?= htmlspecialchars(t('md.move_to')) ?></label>
                    <select class="form-control form-control-sm mr-2" name="to"><?php for ($i = 1; $i <= $maxSlots; $i++): if ($i === $slot) continue; ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select>
                    <button class="btn btn-sm btn-outline-primary" type="submit"><?= htmlspecialchars(t('md.move')) ?></button>
                </form>
                <form method="post" action="<?= $base ?>/admin/products/pictures/action" class="form-inline">
                    <?= $hidden('remove', $slot) ?>
                    <label class="mr-2 mb-0"><input type="checkbox" name="confirm" value="1" required class="mr-1"><?= htmlspecialchars(t('md.confirm')) ?></label>
                    <button class="btn btn-sm btn-outline-danger" type="submit"><?= htmlspecialchars(t('md.remove')) ?></button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($ready && $canManage): ?>
<form class="card mi-admin-card" method="post" action="<?= $base ?>/admin/products/pictures/action" enctype="multipart/form-data" autocomplete="off">
    <?= $hidden('upload') ?>
    <div class="card-header"><h2><?= htmlspecialchars(t('md.upload_title')) ?></h2></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group col-md-2"><label><?= htmlspecialchars(t('md.slot_label')) ?><select class="form-control" name="slot"><?php for ($i = 1; $i <= $maxSlots; $i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></label></div>
            <div class="form-group col-md-4"><label><?= htmlspecialchars(t('md.file')) ?><input class="form-control-file" type="file" name="picture" accept="image/jpeg,image/png,image/webp" required></label></div>
            <div class="form-group col-md-3"><label><?= htmlspecialchars(t('md.source_name')) ?><input class="form-control" name="source_name" maxlength="120" required></label></div>
            <div class="form-group col-md-3"><label><?= htmlspecialchars(t('md.license')) ?><input class="form-control" name="license_note" maxlength="120" required></label></div>
        </div>
        <div class="form-row align-items-end">
            <div class="form-group col-md-5"><label><?= htmlspecialchars(t('md.source_url')) ?><input class="form-control" type="url" name="source_url" maxlength="500" placeholder="https://"></label></div>
            <div class="form-group col-md-5"><label><?= htmlspecialchars(t('md.alt')) ?><input class="form-control" name="alt" maxlength="160"></label></div>
            <div class="form-group col-md-2"><button class="btn btn-primary btn-block" type="submit"><i class="fas fa-upload mr-2"></i><?= htmlspecialchars(t('md.upload')) ?></button></div>
        </div>
    </div>
</form>
<?php endif; ?>
