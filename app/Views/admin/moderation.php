<?php
/** LS-90 D4: @var list<array> $queues  @var list<array> $reports  @var list<array> $waiting  @var list<array> $verified  @var bool $ready  @var bool $canListings */
$e = static fn($v): string => htmlspecialchars((string) $v);
$token = $e(\App\Core\Csrf::token());
$age = static fn(?int $h): string => $h === null ? '-' : ($h < 48 ? t('mod.hours', ['n' => $h]) : t('mod.days', ['n' => intdiv($h, 24)]));
?>
<?= mi_page_header($base, ['Market data', 'Moderation queue'], $e(t('mod.title')), $e(t('mod.help')), '') ?>
<?php if (!$ready): ?><div class="alert alert-warning"><?= $e(t('mod.not_ready')) ?></div><?php endif; ?>
<div class="card mi-admin-card mi-table-card mb-3">
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= $e(t('mod.col.queue')) ?></th><th class="text-right"><?= $e(t('mod.col.count')) ?></th><th><?= $e(t('mod.col.oldest')) ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($queues as $q): ?>
                <tr>
                    <td><?= $e(t('mod.q.' . $q['key'])) ?></td>
                    <td class="text-right"><span class="badge <?= $q['count'] > 0 ? 'badge-warning' : 'badge-light' ?>"><?= (int) $q['count'] ?></span></td>
                    <td><?= $e($age($q['age_hours'])) ?></td>
                    <td class="text-right"><?php if ($q['url'] !== ''): ?><a class="btn btn-sm btn-outline-primary" href="<?= $base . $e($q['url']) ?>"><?= $e(t('price.open')) ?></a><?php else: ?><small class="text-muted"><?= $e(t('mod.log_only')) ?></small><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if ($canListings): ?>
<div class="card mi-admin-card mi-table-card mb-3" id="listing-reports">
    <div class="card-header"><h2><?= $e(t('mod.reports')) ?> (<?= count($reports) ?>)</h2></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= $e(t('price.col.model')) ?></th><th><?= $e(t('mod.col.reason')) ?></th><th class="mi-th-wide"></th></tr></thead>
            <tbody>
            <?php if (!$reports): ?><tr><td colspan="3" class="text-muted"><?= $e(t('mod.empty')) ?></td></tr><?php endif; ?>
            <?php foreach ($reports as $r): ?>
                <tr>
                    <td><?= $e($r['full_name']) ?><br><small class="text-muted">฿<?= number_format((float) $r['asking_price']) ?> · <?= $e($r['listing_status']) ?> · <?= $e(t('mod.open_count', ['n' => (int) $r['open_for_listing']])) ?></small></td>
                    <td><span class="badge badge-warning"><?= $e(t('mod.reason.' . $r['reason'])) ?></span><small class="d-block text-muted"><?= $e($r['note'] ?? '') ?></small><small class="d-block text-muted"><?= $e(substr((string) $r['created_at'], 0, 16)) ?></small></td>
                    <td>
                        <form class="form-inline" method="post" action="<?= $base ?>/admin/moderation/listing-report">
                            <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <input class="form-control form-control-sm mr-1 mb-1" name="note" maxlength="255" placeholder="<?= $e(t('mod.note')) ?>" aria-label="<?= $e(t('mod.note')) ?>">
                            <button class="btn btn-sm btn-danger mr-1 mb-1" type="submit" name="decision" value="upheld"><?= $e(t('mod.remove')) ?></button>
                            <button class="btn btn-sm btn-outline-secondary mb-1" type="submit" name="decision" value="dismissed"><?= $e(t('mod.dismiss')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="card mi-admin-card mi-table-card" id="sellers">
    <div class="card-header"><h2><?= $e(t('mod.sellers')) ?></h2></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= $e(t('mod.col.seller')) ?></th><th><?= $e(t('price.col.state')) ?></th><th class="mi-th-wide"></th></tr></thead>
            <tbody>
            <?php if (!$waiting && !$verified): ?><tr><td colspan="3" class="text-muted"><?= $e(t('mod.empty')) ?></td></tr><?php endif; ?>
            <?php foreach (array_merge($waiting, $verified) as $s): $pending = $s['verification'] === 'pending'; ?>
                <tr>
                    <td><?= $e($s['display_name']) ?> <small class="text-muted">#<?= (int) $s['member_id'] ?></small><small class="d-block text-muted"><?= $e(trim(($s['province'] ?? '') . ' ' . ($s['about'] ?? ''))) ?></small></td>
                    <td><span class="badge <?= $pending ? 'badge-warning' : 'badge-success' ?>"><?= $e(t('mod.state.' . $s['verification'])) ?></span></td>
                    <td>
                        <form class="form-inline" method="post" action="<?= $base ?>/admin/moderation/seller">
                            <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="member_id" value="<?= (int) $s['member_id'] ?>">
                            <input class="form-control form-control-sm mr-1 mb-1" name="note" maxlength="255" placeholder="<?= $e(t('mod.note')) ?>" aria-label="<?= $e(t('mod.note')) ?>">
                            <?php if ($pending): ?>
                                <button class="btn btn-sm btn-success mr-1 mb-1" type="submit" name="decision" value="verify"><?= $e(t('mod.verify')) ?></button>
                                <button class="btn btn-sm btn-outline-danger mb-1" type="submit" name="decision" value="refuse"><?= $e(t('mod.refuse')) ?></button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-danger mb-1" type="submit" name="decision" value="revoke"><?= $e(t('mod.revoke')) ?></button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
