<?php
$tabs = ['open' => 'Open', 'decided' => 'Decided', 'all' => 'All'];
$pages = (int) max(1, ceil($total / $per));
?>
<?= mi_page_header($base, ['Catalog', 'Unresolved listings'], 'Unresolved listings', 'Listings the resolver could not match to a product. A person confirms every alias: nothing is added automatically, and an alias only affects future imports (no price is created or changed). Raw listing text is private evidence: admin only.', '') ?>

<?php if (!$actionsAvailable): ?>
    <div class="alert alert-warning">The decision table (migration 019) is not installed on this database, so this page is read-only. Run <code>php cli/migrate.php</code> to enable the actions.</div>
<?php elseif (!$canManage): ?>
    <div class="alert alert-info">You can view this queue. Adding aliases needs the product-management permission (admin).</div>
<?php endif; ?>

<ul class="nav nav-pills mb-3">
    <?php foreach ($tabs as $key => $label): ?>
        <li class="nav-item"><a class="nav-link <?= $status === $key ? 'active' : '' ?>" href="<?= $base ?>/admin/unresolved-listings?status=<?= $key ?>"><?= $label ?></a></li>
    <?php endforeach; ?>
</ul>

<div class="card mi-admin-card mi-table-card">
    <div class="card-header"><?= (int) $total ?> listing(s) · page <?= (int) $page ?> of <?= $pages ?></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th>Listing (private)</th><th class="text-right">Price text</th><th>Evidence</th><th style="min-width:22rem">Decision</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars(mb_strimwidth((string) $r['title'], 0, 140, '…', 'UTF-8')) ?><br><small class="text-muted">#<?= (int) $r['raw_id'] ?> · <?= htmlspecialchars(substr((string) $r['captured_at'], 0, 10)) ?></small></td>
                    <td class="text-right"><?= htmlspecialchars((string) $r['price_text']) ?></td>
                    <td><?= $r['grade'] === 'owner_text' ? 'Owner text' : 'Other source' ?></td>
                    <td>
                        <?php if ($r['decided'] !== null): ?>
                            <span class="badge <?= $r['decided'] === 'alias_added' ? 'badge-success' : 'badge-secondary' ?>"><?= $r['decided'] === 'alias_added' ? 'Alias added' : 'Dismissed' ?></span>
                        <?php elseif ($actionsAvailable && $canManage): ?>
                            <form method="post" action="<?= $base ?>/admin/unresolved-listings/action">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                <input type="hidden" name="raw_id" value="<?= (int) $r['raw_id'] ?>">
                                <div class="form-row">
                                    <div class="col-12 mb-1">
                                        <select class="form-control form-control-sm" name="product_id" aria-label="Product">
                                            <option value="">Product…</option>
                                            <?php foreach ($r['suggestions'] as $s): ?><option value="<?= (int) $s['product_id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 mb-1"><input class="form-control form-control-sm" name="alias_text" maxlength="255" value="<?= htmlspecialchars($r['proposed_alias']) ?>" placeholder="Alias text" aria-label="Alias text"></div>
                                    <div class="col-12">
                                        <button class="btn btn-sm btn-primary" type="submit" name="action" value="alias">Add alias</button>
                                        <button class="btn btn-sm btn-outline-secondary" type="submit" name="action" value="dismiss">Dismiss</button>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <?php foreach ($r['suggestions'] as $s): ?><div class="text-muted"><?= htmlspecialchars($s['name']) ?></div><?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="4" class="text-muted">Nothing here.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
        <div class="card-body">
            <?php if ($page > 1): ?><a class="btn btn-sm btn-outline-secondary" href="<?= $base ?>/admin/unresolved-listings?status=<?= htmlspecialchars($status) ?>&page=<?= $page - 1 ?>">Previous</a><?php endif; ?>
            <?php if ($page < $pages): ?><a class="btn btn-sm btn-outline-secondary" href="<?= $base ?>/admin/unresolved-listings?status=<?= htmlspecialchars($status) ?>&page=<?= $page + 1 ?>">Next</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>
