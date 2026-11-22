<?php
/**
 * Marketplace P1-A: versioned legal documents. @var list<array> $rows @var ?array $docView @var bool $ready @var list<string> $types
 * A document is saved as a draft first. Publishing is a separate step that needs the owner's confirmation; it retires the previous version of that type.
 * The text itself comes from the owner and counsel (HG-LEGAL); nothing here writes or suggests legal wording. Visible text comes from app/Lang.
 */
$token = htmlspecialchars(\App\Core\Csrf::token());
$badge = ['draft' => 'badge-warning', 'published' => 'badge-success', 'retired' => 'badge-secondary'];
?>
<?= mi_page_header($base, [htmlspecialchars(t('pr.crumb_members')), htmlspecialchars(t('ld.crumb'))], htmlspecialchars(t('ld.title')), htmlspecialchars(t('ld.desc')), '') ?>
<?php if (!$ready): ?>
    <div class="alert alert-warning"><?= htmlspecialchars(t('common.not_ready_pre')) ?> <code>php cli/migrate.php database/migrations/031_member_identity_trust.sql</code> <?= htmlspecialchars(t('common.not_ready_post')) ?></div>
<?php else: ?>
    <?php if ($docView): ?>
        <div class="card mi-admin-card mb-3"><div class="card-header"><h2><?= htmlspecialchars((string) $docView['title']) ?> · <?= htmlspecialchars((string) $docView['version']) ?></h2></div>
            <div class="card-body"><?= nl2br(htmlspecialchars((string) $docView['body_text'])) ?></div></div>
    <?php endif; ?>
    <div class="card mi-admin-card mi-table-card mb-3"><div class="card-header"><h2><?= htmlspecialchars(t('ld.all')) ?> (<?= count($rows) ?>)</h2></div>
        <div class="table-responsive"><table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= htmlspecialchars(t('ld.col.type')) ?></th><th><?= htmlspecialchars(t('ld.col.version')) ?></th><th><?= htmlspecialchars(t('ld.col.title')) ?></th><th><?= htmlspecialchars(t('ld.col.status')) ?></th><th><?= htmlspecialchars(t('ld.col.published')) ?></th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6" class="text-muted"><?= htmlspecialchars(t('ld.none')) ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $r['doc_type']) ?></td>
                    <td><?= htmlspecialchars((string) $r['version']) ?></td>
                    <td><?= htmlspecialchars((string) $r['title']) ?></td>
                    <td><span class="badge <?= $badge[$r['status']] ?? 'badge-light' ?>"><?= htmlspecialchars(t('ld.s.' . $r['status'])) ?></span></td>
                    <td><small><?= htmlspecialchars((string) ($r['published_at'] ? substr((string) $r['published_at'], 0, 16) : '-')) ?></small></td>
                    <td>
                        <a class="btn btn-sm btn-outline-secondary mr-1" href="<?= $base ?>/admin/legal-documents?view=<?= (int) $r['id'] ?>"><?= htmlspecialchars(t('ld.read')) ?></a>
                        <?php if ($r['status'] === 'draft'): ?>
                            <form class="form-inline d-inline-flex" method="post" action="<?= $base ?>/admin/legal-documents/publish">
                                <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <label class="mr-2 mb-0"><input type="checkbox" name="confirm" value="1" required class="mr-1"><?= htmlspecialchars(t('ld.confirm')) ?></label>
                                <button class="btn btn-sm btn-success" type="submit"><?= htmlspecialchars(t('ld.publish')) ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <div class="card mi-admin-card"><div class="card-header"><h2><?= htmlspecialchars(t('ld.add')) ?></h2></div><div class="card-body">
        <form method="post" action="<?= $base ?>/admin/legal-documents/create">
            <input type="hidden" name="_token" value="<?= $token ?>">
            <div class="form-row">
                <div class="form-group col-md-4"><label for="ld-type"><?= htmlspecialchars(t('ld.f.type')) ?></label>
                    <select class="form-control" id="ld-type" name="doc_type"><?php foreach ($types as $tp): ?><option value="<?= htmlspecialchars($tp) ?>"><?= htmlspecialchars($tp) ?></option><?php endforeach; ?></select></div>
                <div class="form-group col-md-2"><label for="ld-version"><?= htmlspecialchars(t('ld.f.version')) ?></label><input class="form-control" id="ld-version" name="version" maxlength="20" required></div>
                <div class="form-group col-md-6"><label for="ld-title"><?= htmlspecialchars(t('ld.f.title')) ?></label><input class="form-control" id="ld-title" name="title" maxlength="160" required></div>
            </div>
            <div class="form-group"><label for="ld-body"><?= htmlspecialchars(t('ld.f.body')) ?></label><textarea class="form-control" id="ld-body" name="body" rows="8" required></textarea></div>
            <button class="btn btn-primary" type="submit"><?= htmlspecialchars(t('ld.save')) ?></button>
        </form>
    </div></div>
<?php endif; ?>
