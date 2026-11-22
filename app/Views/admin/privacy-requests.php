<?php
/**
 * Marketplace P1-A: the data-subject request queue. @var list<array> $rows @var array<string,int> $counts @var string $status @var bool $ready
 * Handling a request here only records the decision; it deletes or exports nothing by itself. All visible text comes from app/Lang (TH default, EN).
 */
$token = htmlspecialchars(\App\Core\Csrf::token());
$states = ['received', 'in_progress', 'completed', 'rejected'];
$badge = ['received' => 'badge-warning', 'in_progress' => 'badge-info', 'completed' => 'badge-success', 'rejected' => 'badge-secondary'];
?>
<?= mi_page_header($base, [htmlspecialchars(t('pr.crumb_members')), htmlspecialchars(t('pr.crumb'))], htmlspecialchars(t('pr.title')), htmlspecialchars(t('pr.desc')), '') ?>
<?php if (!$ready): ?>
    <div class="alert alert-warning"><?= htmlspecialchars(t('common.not_ready_pre')) ?> <code>php cli/migrate.php database/migrations/031_member_identity_trust.sql</code> <?= htmlspecialchars(t('common.not_ready_post')) ?></div>
<?php else: ?>
    <div class="card mi-admin-card mb-3"><div class="card-body">
        <form class="form-inline" method="get" action="<?= $base ?>/admin/privacy-requests">
            <label class="mr-2 mb-0" for="pr-status"><?= htmlspecialchars(t('common.show')) ?></label>
            <select id="pr-status" name="status" class="form-control mr-3" data-autosubmit data-width="240px">
                <?php foreach ($states as $v): ?><option value="<?= $v ?>"<?= $status === $v ? ' selected' : '' ?>><?= htmlspecialchars(t('pr.s.' . $v)) ?> (<?= (int) ($counts[$v] ?? 0) ?>)</option><?php endforeach; ?>
            </select>
            <noscript><button class="btn btn-primary" type="submit"><?= htmlspecialchars(t('common.show')) ?></button></noscript>
        </form>
    </div></div>
    <div class="card mi-admin-card mi-table-card"><div class="card-header"><h2><?= htmlspecialchars(t('pr.requests')) ?> (<?= count($rows) ?>)</h2></div>
        <div class="table-responsive"><table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= htmlspecialchars(t('pr.col.sent')) ?></th><th><?= htmlspecialchars(t('pr.col.member')) ?></th><th><?= htmlspecialchars(t('pr.col.type')) ?></th><th><?= htmlspecialchars(t('pr.col.detail')) ?></th><th><?= htmlspecialchars(t('pr.col.status')) ?></th><th><?= htmlspecialchars(t('pr.col.action')) ?></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6" class="text-muted"><?= htmlspecialchars(t('pr.none')) ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><small><?= htmlspecialchars(substr((string) $r['received_at'], 0, 16)) ?></small><?php if ($r['due_at'] !== null): ?><br><small class="text-muted"><?= htmlspecialchars(t('pr.due')) ?> <?= htmlspecialchars(substr((string) $r['due_at'], 0, 10)) ?></small><?php endif; ?></td>
                    <td><?php if ($r['member_id'] !== null): ?>#<?= (int) $r['member_id'] ?> <?= htmlspecialchars((string) ($r['display_name'] ?? '')) ?><?php else: ?><span class="text-muted"><?= htmlspecialchars(t('pr.deleted_member')) ?></span><?php endif; ?></td>
                    <td><?= htmlspecialchars(t('pr.t.' . $r['request_type'])) ?></td>
                    <td><?= htmlspecialchars((string) ($r['detail'] ?? '')) ?></td>
                    <td><span class="badge <?= $badge[$r['status']] ?? 'badge-light' ?>"><?= htmlspecialchars(t('pr.s.' . $r['status'])) ?></span>
                        <?php if (!empty($r['response_note'])): ?><br><small class="text-muted"><?= htmlspecialchars((string) $r['response_note']) ?></small><?php endif; ?></td>
                    <td>
                        <?php if (in_array($r['status'], ['received', 'in_progress'], true)): ?>
                            <form class="form-inline" method="post" action="<?= $base ?>/admin/privacy-requests/action">
                                <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                                <input class="form-control form-control-sm mr-1 mb-1" name="note" maxlength="500" placeholder="<?= htmlspecialchars(t('pr.note_ph')) ?>" aria-label="<?= htmlspecialchars(t('pr.note_aria')) ?>">
                                <?php if ($r['status'] === 'received'): ?><button class="btn btn-sm btn-info mr-1 mb-1" type="submit" name="to" value="in_progress"><?= htmlspecialchars(t('pr.start')) ?></button><?php endif; ?>
                                <button class="btn btn-sm btn-success mr-1 mb-1" type="submit" name="to" value="completed"><?= htmlspecialchars(t('pr.done')) ?></button>
                                <button class="btn btn-sm btn-outline-secondary mb-1" type="submit" name="to" value="rejected"><?= htmlspecialchars(t('pr.reject')) ?></button>
                            </form>
                        <?php else: ?><span class="text-muted"><?= htmlspecialchars(t('pr.closed')) ?></span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
<?php endif; ?>
