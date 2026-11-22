<?php
$tabs = ['open' => t('v.open'), 'stale' => t('v.already_matched'), 'decided' => t('v.decided'), 'all' => t('v.all')];
$pages = (int) max(1, ceil($total / $per));
?>
<?= mi_page_header($base, ['Catalog', 'Unresolved listings'], htmlspecialchars(t('v.unresolved_listings')), htmlspecialchars(t('v.listings_the_resolver_could_not_matc')), '') ?>

<?php if (!$actionsAvailable): ?>
    <div class="alert alert-warning">The decision table (migration 019) is not installed on this database, so this page is read-only. Run <code>php cli/migrate.php</code> to enable the actions.</div>
<?php elseif (!$canManage): ?>
    <div class="alert alert-info"><?= htmlspecialchars(t('v.you_can_view_this_queue_adding_alias')) ?></div>
<?php endif; ?>

<ul class="nav nav-pills mb-3">
    <?php foreach ($tabs as $key => $label): ?>
        <li class="nav-item"><a class="nav-link <?= $status === $key ? 'active' : '' ?>" href="<?= $base ?>/admin/unresolved-listings?status=<?= $key ?>"><?= $label ?></a></li>
    <?php endforeach; ?>
</ul>

<div class="card mi-admin-card mi-table-card">
    <div class="card-header"><?= (int) $total ?> <?= htmlspecialchars(t('v.listing_s_page')) ?> <?= (int) $page ?> <?= htmlspecialchars(t('v.x8bf8854b')) ?> <?= $pages ?></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= htmlspecialchars(t('v.listing_private')) ?></th><th class="text-right"><?= htmlspecialchars(t('v.price_text')) ?></th><th><?= htmlspecialchars(t('v.evidence_3')) ?></th><th style="min-width:22rem"><?= htmlspecialchars(t('v.decision')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars(mb_strimwidth((string) $r['title'], 0, 140, '…', 'UTF-8')) ?><br><small class="text-muted">#<?= (int) $r['raw_id'] ?> · <?= htmlspecialchars(substr((string) $r['captured_at'], 0, 10)) ?></small></td>
                    <td class="text-right"><?= htmlspecialchars((string) $r['price_text']) ?></td>
                    <td><?= $r['grade'] === 'owner_text' ? t('v.owner_text_2') : t('v.other_source') ?></td>
                    <td>
                        <?php if ($r['decided'] !== null): ?>
                            <span class="badge <?= $r['decided'] === 'alias_added' ? 'badge-success' : 'badge-secondary' ?>"><?= $r['decided'] === 'alias_added' ? t('v.alias_added') : t('v.dismissed') ?></span>
                        <?php elseif ($actionsAvailable && $canManage): ?>
                            <form method="post" action="<?= $base ?>/admin/unresolved-listings/action">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                <input type="hidden" name="raw_id" value="<?= (int) $r['raw_id'] ?>">
                                <div class="form-row">
                                    <div class="col-12 mb-1">
                                        <select class="form-control form-control-sm" name="product_id" aria-label="<?= htmlspecialchars(t('v.product')) ?>">
                                            <option value=""><?= htmlspecialchars(t('v.product_2')) ?></option>
                                            <?php foreach ($r['suggestions'] as $s): ?><option value="<?= (int) $s['product_id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 mb-1"><input class="form-control form-control-sm" name="alias_text" maxlength="255" value="<?= htmlspecialchars($r['proposed_alias']) ?>" placeholder="<?= htmlspecialchars(t('v.alias_text')) ?>" aria-label="<?= htmlspecialchars(t('v.alias_text')) ?>"></div>
                                    <div class="col-12">
                                        <button class="btn btn-sm btn-primary" type="submit" name="action" value="alias"><?= htmlspecialchars(t('v.add_alias_2')) ?></button>
                                        <button class="btn btn-sm btn-outline-secondary" type="submit" name="action" value="dismiss"><?= htmlspecialchars(t('v.dismiss')) ?></button>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <?php foreach ($r['suggestions'] as $s): ?><div class="text-muted"><?= htmlspecialchars($s['name']) ?></div><?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="4" class="text-muted"><?= htmlspecialchars(t('v.nothing_here')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
        <div class="card-body">
            <?php if ($page > 1): ?><a class="btn btn-sm btn-outline-secondary" href="<?= $base ?>/admin/unresolved-listings?status=<?= htmlspecialchars($status) ?>&page=<?= $page - 1 ?>"><?= htmlspecialchars(t('v.previous_2')) ?></a><?php endif; ?>
            <?php if ($page < $pages): ?><a class="btn btn-sm btn-outline-secondary" href="<?= $base ?>/admin/unresolved-listings?status=<?= htmlspecialchars($status) ?>&page=<?= $page + 1 ?>"><?= htmlspecialchars(t('v.next')) ?></a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>
