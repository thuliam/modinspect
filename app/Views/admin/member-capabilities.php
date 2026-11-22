<?php
/**
 * Marketplace P1-A: member capabilities. @var list<array> $rows @var bool $ready @var list<string> $allowed
 * Granting a capability is necessary but not sufficient for later seller steps (verification is checked separately). Revoking keeps the history.
 * Visible text comes from app/Lang (TH default, EN).
 */
$token = htmlspecialchars(\App\Core\Csrf::token());
?>
<?= mi_page_header($base, [htmlspecialchars(t('pr.crumb_members')), htmlspecialchars(t('mc.crumb'))], htmlspecialchars(t('mc.title')), htmlspecialchars(t('mc.desc')), '') ?>
<?php if (!$ready): ?>
    <div class="alert alert-warning"><?= htmlspecialchars(t('common.not_ready_pre')) ?> <code>php cli/migrate.php database/migrations/032_member_identity_trust.sql</code> <?= htmlspecialchars(t('common.not_ready_post')) ?></div>
<?php else: ?>
    <div class="card mi-admin-card mb-3"><div class="card-header"><h2><?= htmlspecialchars(t('mc.grant_h')) ?></h2></div><div class="card-body">
        <form class="form-inline" method="post" action="<?= $base ?>/admin/member-capabilities/action">
            <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="do" value="grant">
            <label class="mr-2 mb-0" for="mc-member"><?= htmlspecialchars(t('mc.member_id')) ?></label>
            <input class="form-control mr-3" id="mc-member" name="member_id" type="number" min="1" required>
            <label class="mr-2 mb-0" for="mc-cap"><?= htmlspecialchars(t('mc.capability')) ?></label>
            <select class="form-control mr-3" id="mc-cap" name="capability"><?php foreach ($allowed as $a): ?><option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option><?php endforeach; ?></select>
            <button class="btn btn-primary" type="submit"><?= htmlspecialchars(t('mc.grant_btn')) ?></button>
        </form>
    </div></div>
    <div class="card mi-admin-card mi-table-card"><div class="card-header"><h2><?= htmlspecialchars(t('mc.holders')) ?> (<?= count($rows) ?>)</h2></div>
        <div class="table-responsive"><table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= htmlspecialchars(t('mc.col.member')) ?></th><th><?= htmlspecialchars(t('mc.col.account')) ?></th><th><?= htmlspecialchars(t('mc.col.capability')) ?></th><th><?= htmlspecialchars(t('mc.col.granted_at')) ?></th><th><?= htmlspecialchars(t('mc.col.granted_by')) ?></th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6" class="text-muted"><?= htmlspecialchars(t('mc.none')) ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>#<?= (int) $r['member_id'] ?> <?= htmlspecialchars((string) ($r['display_name'] ?? '')) ?></td>
                    <td><?= htmlspecialchars(t('member.status.' . $r['status'])) ?></td>
                    <td><?= htmlspecialchars((string) $r['capability']) ?></td>
                    <td><small><?= htmlspecialchars(substr((string) $r['granted_at'], 0, 16)) ?></small></td>
                    <td><?= $r['granted_by_user_id'] !== null ? '#' . (int) $r['granted_by_user_id'] : '-' ?></td>
                    <td>
                        <form method="post" action="<?= $base ?>/admin/member-capabilities/action" class="mb-0">
                            <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="do" value="revoke">
                            <input type="hidden" name="member_id" value="<?= (int) $r['member_id'] ?>"><input type="hidden" name="capability" value="<?= htmlspecialchars((string) $r['capability']) ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit"><?= htmlspecialchars(t('mc.revoke')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
<?php endif; ?>
