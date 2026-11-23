<?php
/** @var string $type products|specs|images|game_fps */
$tt = static fn (string $k): string => htmlspecialchars(t($k));
$tabs = ['products' => 'misc.imp_type_products', 'specs' => 'misc.imp_type_specs', 'images' => 'misc.imp_type_images', 'game_fps' => 'misc.imp_type_fps'];
ob_start(); ?><?= $tt('crumb.import_centre') ?><?php $dxT = ob_get_clean(); ob_start(); ?><?= $tt('misc.imp_desc') ?><?php $dxD = ob_get_clean();
?>
<?= mi_page_header($base, ['Collection'], $dxT, $dxD, '') ?>

<ul class="nav nav-pills mb-3">
    <?php foreach ($tabs as $k => $label): ?>
        <li class="nav-item"><a class="nav-link<?= $type === $k ? ' active' : '' ?>" href="<?= $base ?>/admin/import?type=<?= $k ?>"><?= $tt($label) ?></a></li>
    <?php endforeach; ?>
</ul>

<?php if ($type === 'game_fps' && empty($fps_table)): ?>
    <div class="alert alert-warning"><?= $tt('misc.imp_fps_missing') ?></div>
<?php endif; ?>
<?php if ($type === 'images' && empty($images_table)): ?>
    <div class="alert alert-warning"><?= $tt('misc.imp_images_missing') ?></div>
<?php endif; ?>

<form class="card mi-admin-card mb-3" method="post" action="<?= $base ?>/admin/import/dry-run" enctype="multipart/form-data">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
    <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2><?= $tt($tabs[$type]) ?></h2>
        <?php if ($type !== 'images'): ?><a class="btn btn-outline-secondary" href="<?= $base ?>/admin/import/template?type=<?= htmlspecialchars($type) ?>"><i class="fas fa-download mr-2" aria-hidden="true"></i><?= $tt('misc.imp_download') ?></a><?php endif; ?>
    </div>
    <div class="card-body">
        <p class="text-muted"><?= $tt('misc.imp_flow') ?></p>
        <p class="text-muted"><?php if ($type === 'game_fps'): ?><?= $tt('misc.imp_rules_fps') ?><?php elseif ($type === 'specs'): ?><?= $tt('misc.imp_rules_specs') ?><?php elseif ($type === 'images'): ?><?= $tt('misc.imp_rules_images') ?><?php else: ?><?= $tt('misc.imp_rules_products') ?><?php endif; ?></p>
        <?php if ($can_manage): ?>
            <?php if ($type === 'images'): ?>
                <div class="form-group">
                    <label for="import-files"><?= $tt('misc.imp_pick_images') ?></label>
                    <input class="form-control" id="import-files" type="file" name="import_files[]" accept="image/jpeg,image/png,image/webp" multiple required>
                    <small class="form-text text-muted"><?= htmlspecialchars(t('misc.imp_img_limit', ['n' => (int) $max_images])) ?></small>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4"><label for="img-src"><?= $tt('misc.imp_src_name') ?></label><input class="form-control" id="img-src" name="source_name" maxlength="120" required></div>
                    <div class="form-group col-md-4"><label for="img-lic"><?= $tt('misc.imp_license') ?></label><input class="form-control" id="img-lic" name="license_note" maxlength="120" required></div>
                    <div class="form-group col-md-4"><label for="img-url"><?= $tt('misc.imp_src_url') ?></label><input class="form-control" id="img-url" type="url" name="source_url" maxlength="500"></div>
                </div>
                <div class="form-group"><label for="img-alt"><?= $tt('misc.imp_alt') ?></label><input class="form-control" id="img-alt" name="alt" maxlength="160"></div>
            <?php else: ?>
            <div class="form-group">
                <label for="import-file"><?= $tt('misc.imp_file') ?></label>
                <input class="form-control" id="import-file" type="file" name="import_file" accept=".csv,.json" required>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-muted"><?= $tt('misc.imp_readonly') ?></p>
        <?php endif; ?>
    </div>
    <?php if ($can_manage): ?>
        <div class="card-footer"><button class="btn btn-primary" type="submit"><i class="fas fa-vial mr-2" aria-hidden="true"></i><?= $tt('misc.imp_dry') ?></button></div>
    <?php endif; ?>
</form>

<div class="card mi-table-card mb-3">
    <div class="card-header"><h2><?= $tt('misc.imp_result') ?></h2></div>
    <?php if (empty($result)): ?>
        <div class="card-body"><p class="text-muted mb-0"><?= $tt('misc.imp_none') ?></p></div>
    <?php else: ?>
        <div class="card-body">
            <p class="mb-3"><strong><?= htmlspecialchars((string) $result['name']) ?></strong>
                · <?= $tt('misc.imp_rows') ?> <?= (int) $result['rows'] ?>
                · <?= $tt('misc.imp_create') ?> <?= (int) ($result['count']['create'] ?? 0) ?>
                · <?= $tt('misc.imp_update') ?> <?= (int) ($result['count']['update'] ?? 0) ?>
                · <?= $tt('misc.imp_refused') ?> <?= (int) ($result['count']['refused'] ?? 0) ?></p>
            <?php if ($can_manage && ((int) ($result['count']['create'] ?? 0) + (int) ($result['count']['update'] ?? 0)) > 0): ?>
                <form method="post" action="<?= $base ?>/admin/import/apply" class="mb-0">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                    <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-check mr-2" aria-hidden="true"></i><?= $tt('misc.imp_confirm') ?></button>
                    <small class="form-text text-muted"><?= $tt('misc.imp_confirm_note') ?></small>
                </form>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mi-admin-table mb-0">
                <thead><tr><th><?= $tt('misc.imp_col_line') ?></th><th><?= $tt('misc.imp_col_action') ?></th><th><?= $tt('misc.imp_col_item') ?></th><th><?= $tt('misc.imp_col_note') ?></th></tr></thead>
                <tbody>
                <?php foreach ($result['lines'] as $l): ?>
                    <tr>
                        <td><?= (int) $l['line'] ?></td>
                        <td><?php if ($l['action'] === 'refused'): ?><span class="badge badge-danger"><?= $tt('misc.imp_refused') ?></span><?php elseif ($l['action'] === 'update'): ?><span class="badge badge-info"><?= $tt('misc.imp_update') ?></span><?php else: ?><span class="badge badge-success"><?= $tt('misc.imp_create') ?></span><?php endif; ?></td>
                        <td><?= htmlspecialchars((string) ($l['name'] !== '' ? $l['name'] : $l['slug'])) ?></td>
                        <td><?= htmlspecialchars((string) ($l['error'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ((int) $result['rows'] > count($result['lines'])): ?><div class="card-body"><small class="text-muted"><?= $tt('misc.imp_truncated') ?></small></div><?php endif; ?>
    <?php endif; ?>
</div>

<div class="row">
    <div class="col-lg-6 mb-3">
        <div class="card mi-admin-card h-100">
            <div class="card-header"><h2><?= $tt('misc.imp_other_title') ?></h2></div>
            <div class="card-body">
                <ul class="list-unstyled mb-3">
                    <li class="mb-2"><a href="<?= $base ?>/admin/collector-jobs"><?= $tt('misc.imp_other_market') ?></a></li>
                    <li class="mb-2"><a href="<?= $base ?>/admin/product-aliases"><?= $tt('misc.imp_other_alias') ?></a></li>
                    <li><a href="<?= $base ?>/admin/new-prices"><?= $tt('misc.imp_other_new') ?></a></li>
                </ul>
                <p class="text-muted mb-0"><?= $tt('misc.imp_other_cli') ?></p>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="card mi-table-card h-100">
            <div class="card-header"><h2><?= $tt('misc.imp_recent') ?></h2></div>
            <?php if (empty($recent)): ?>
                <div class="card-body"><p class="text-muted mb-0"><?= $tt('misc.imp_recent_none') ?></p></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table mi-admin-table mb-0">
                        <thead><tr><th><?= $tt('misc.imp_col_when') ?></th><th><?= $tt('misc.imp_col_file') ?></th><th><?= $tt('misc.imp_col_result') ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($recent as $r): $a = json_decode((string) ($r['after_data'] ?? ''), true) ?: []; ?>
                            <tr>
                                <td><?= htmlspecialchars((string) $r['created_at']) ?></td>
                                <td><?= htmlspecialchars((string) ($a['file'] ?? '')) ?></td>
                                <td><?= (int) ($a['created'] ?? 0) ?> / <?= (int) ($a['updated'] ?? 0) ?> / <?= (int) ($a['refused'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
