<?php
/** LS-14 Module 6.5: @var list<array> $rows  @var bool $ready  @var list<array> $products */
$token = htmlspecialchars(\App\Core\Csrf::token());
$byCat = []; foreach ($products as $p) { $byCat[(string) $p['category_name']][] = $p; }
?>
<?= mi_page_header($base, ['Market data', 'New-price refs'], htmlspecialchars(t('v.x1efe243b')), htmlspecialchars(t('v.snapshot_audit'))) ?>

<?php if (!$ready): ?>
    <div class="alert alert-warning"><?= htmlspecialchars(t('misc.np_table_pre')) ?> <code>new_price_references</code> <?= htmlspecialchars(t('misc.np_table_mid')) ?> <code>php cli/migrate.php database/migrations/022_new_price_references.sql</code> <?= htmlspecialchars(t('misc.np_table_post')) ?></div>
<?php else: ?>
<form class="card mi-admin-card mb-3" method="post" action="<?= $base ?>/admin/new-prices/action" autocomplete="off">
    <input type="hidden" name="_token" value="<?= $token ?>">
    <input type="hidden" name="action" value="add">
    <div class="card-header"><h2><?= htmlspecialchars(t('v.x39d147c1')) ?></h2></div>
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-4"><label><?= htmlspecialchars(t('v.x75cb8be2')) ?><select class="form-control" name="product_id" required><option value=""><?= htmlspecialchars(t('v.xf5297fed')) ?></option><?php foreach ($byCat as $cat => $list): ?><optgroup label="<?= htmlspecialchars($cat) ?>"><?php foreach ($list as $p): ?><option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars((string) $p['full_name']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-2"><label><?= htmlspecialchars(t('v.xc9fb13c1')) ?><input class="form-control" type="number" name="price" min="1" max="10000000" step="1" required></label></div>
            <div class="form-group col-md-3"><label><?= htmlspecialchars(t('v.x0a118015')) ?><input class="form-control" type="url" name="source_url" maxlength="500" placeholder="https://" required></label></div>
            <div class="form-group col-md-2"><label><?= htmlspecialchars(t('v.x3e61f4b8')) ?><input class="form-control" type="date" name="checked_on" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required></label></div>
            <div class="form-group col-md-1"><button class="btn btn-primary btn-block" type="submit"><?= htmlspecialchars(t('v.xd317fd76')) ?></button></div>
        </div>
        <div class="form-row"><div class="form-group col-md-6 mb-0"><label><?= htmlspecialchars(t('v.xcdcd4758')) ?><input class="form-control" name="note" maxlength="255"></label></div></div>
    </div>
</form>

<div class="card mi-admin-card mi-table-card">
    <div class="card-header"><h2><?= htmlspecialchars(t('v.xf1336909')) ?><?= count($rows) ?>)</h2></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= htmlspecialchars(t('v.x75cb8be2')) ?></th><th class="text-right"><?= htmlspecialchars(t('v.xe3efa74f')) ?></th><th><?= htmlspecialchars(t('v.xf071d3a6')) ?></th><th><?= htmlspecialchars(t('v.x155329f3')) ?></th><th><?= htmlspecialchars(t('v.xcf62a0f7')) ?></th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6" class="text-muted"><?= htmlspecialchars(t('v.xf8997b6c')) ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $r['full_name']) ?><br><small class="text-muted"><?= htmlspecialchars((string) $r['category_name']) ?><?= $r['note'] ? ' · ' . htmlspecialchars((string) $r['note']) : '' ?></small></td>
                    <td class="text-right">฿<?= number_format((float) $r['price']) ?></td>
                    <td><a href="<?= htmlspecialchars((string) $r['source_url']) ?>" rel="noopener noreferrer nofollow" target="_blank"><?= htmlspecialchars((string) (parse_url((string) $r['source_url'], PHP_URL_HOST) ?: 'link')) ?></a></td>
                    <td><?= htmlspecialchars((string) $r['checked_on']) ?></td>
                    <td><?= htmlspecialchars((string) ($r['created_by_name'] ?? '')) ?></td>
                    <td><form method="post" action="<?= $base ?>/admin/new-prices/action"><input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit"><?= htmlspecialchars(t('v.x221622d8')) ?></button></form></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
