<?php
/** LS-89 BE-2: @var array{counts:array<string,int>,rows:list<array>} $overview  @var bool $canRecalculate */
$token = htmlspecialchars(\App\Core\Csrf::token());
$money = static fn(?float $v): string => $v === null ? '-' : '฿' . number_format($v);
$tone = ['hidden' => 'badge-danger', 'outdated' => 'badge-warning', 'ready' => 'badge-info', 'ok' => 'badge-success', 'none' => 'badge-secondary'];
$actionable = count(array_filter($overview['rows'], static fn(array $r): bool => $r['can_recalculate']));
ob_start(); if ($canRecalculate && $actionable > 0): ?>
<form method="post" action="<?= $base ?>/admin/price-status/recalculate">
    <input type="hidden" name="_token" value="<?= $token ?>">
    <input type="hidden" name="all" value="1">
    <button class="btn btn-primary" type="submit"><i class="fas fa-sync-alt mr-2"></i><?= htmlspecialchars(t('price.recalculate_all', ['n' => $actionable])) ?></button>
</form>
<?php endif; $actions = ob_get_clean(); ?>
<?= mi_page_header($base, ['Market data', 'Price status'], htmlspecialchars(t('price.status_title')), htmlspecialchars(t('price.status_intro')), $actions) ?>

<div class="card mi-admin-card mb-3">
    <div class="card-body">
        <?php foreach ($overview['counts'] as $state => $count): ?>
            <span class="badge <?= $tone[$state] ?> mr-2"><?= htmlspecialchars(t('price.state.' . $state)) ?>: <?= (int)$count ?></span>
        <?php endforeach; ?>
    </div>
</div>

<div class="card mi-admin-card mi-table-card">
    <div class="card-header"><h2><?= htmlspecialchars(t('price.attention', ['n' => count($overview['rows'])])) ?></h2></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= htmlspecialchars(t('price.col.model')) ?></th><th><?= htmlspecialchars(t('price.col.state')) ?></th><th class="text-right"><?= htmlspecialchars(t('price.col.public')) ?></th><th><?= htmlspecialchars(t('price.col.change')) ?></th><th></th></tr></thead>
            <tbody>
            <?php if (!$overview['rows']): ?><tr><td colspan="5" class="text-muted"><?= htmlspecialchars(t('price.all_current')) ?></td></tr><?php endif; ?>
            <?php foreach ($overview['rows'] as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string)$row['name']) ?></td>
                    <td><span class="badge <?= $tone[$row['state']] ?>"><?= htmlspecialchars(t('price.state.' . $row['state'])) ?></span></td>
                    <td class="text-right"><?= $money($row['public_median']) ?><?php if ($row['public_sample'] !== null): ?><br><small class="text-muted"><?= htmlspecialchars(t('price.samples', ['n' => $row['public_sample']])) ?></small><?php endif; ?></td>
                    <td>
                        <?php if (isset($row['changes']['median'])): ?><?= htmlspecialchars(t('price.col.median')) ?> <?= $money($row['changes']['median']['from'] === null ? null : (float)$row['changes']['median']['from']) ?> → <?= $money((float)$row['changes']['median']['to']) ?>
                        <?php elseif ($row['status'] === 'not_eligible'): ?><?= htmlspecialchars(t('price.below_minimum')) ?>
                        <?php else: ?><?= htmlspecialchars(t('price.details_changed')) ?><?php endif; ?>
                    </td>
                    <td class="text-right"><a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/admin/price-status/<?= (int)$row['product_id'] ?>"><?= htmlspecialchars(t('price.open')) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
