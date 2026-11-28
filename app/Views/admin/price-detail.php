<?php
/** LS-89 BE-2: @var array $detail  @var bool $canRecalculate */
$token = htmlspecialchars(\App\Core\Csrf::token());
$product = $detail['product'];
$money = static fn(mixed $v): string => $v === null ? '-' : '฿' . number_format((float)$v);
$tone = ['hidden' => 'badge-danger', 'outdated' => 'badge-warning', 'ready' => 'badge-info', 'ok' => 'badge-success', 'none' => 'badge-secondary'];
$columns = ['public' => $detail['public'], 'preview' => $detail['preview']];
ob_start(); ?>
<a class="btn btn-outline-secondary" href="<?= $base ?>/admin/price-status"><i class="fas fa-arrow-left mr-2"></i><?= htmlspecialchars(t('price.back')) ?></a>
<a class="btn btn-outline-primary" href="<?= $base ?>/price/<?= htmlspecialchars((string)$product['slug']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars(t('price.public_page')) ?></a>
<?php $actions = ob_get_clean(); ?>
<?= mi_page_header($base, ['Market data', 'Price status'], htmlspecialchars((string)$product['full_name']), htmlspecialchars(t('price.state.' . $detail['state'])), $actions) ?>

<div class="card mi-admin-card mb-3">
    <div class="card-header"><h2><span class="badge <?= $tone[$detail['state']] ?>"><?= htmlspecialchars(t('price.state.' . $detail['state'])) ?></span></h2></div>
    <div class="table-responsive">
        <table class="table mi-admin-table mb-0">
            <thead><tr><th></th><th class="text-right"><?= htmlspecialchars(t('price.col.public')) ?></th><th class="text-right"><?= htmlspecialchars(t('price.col.preview')) ?></th></tr></thead>
            <tbody>
            <?php foreach (['median' => 'price.col.median', 'q1' => 'price.col.q1', 'q3' => 'price.col.q3'] as $field => $label): ?>
                <tr><th><?= htmlspecialchars(t($label)) ?></th><?php foreach ($columns as $snapshot): ?><td class="text-right"><?= $money($snapshot[$field] ?? null) ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
                <tr><th><?= htmlspecialchars(t('price.col.sample')) ?></th><?php foreach ($columns as $snapshot): ?><td class="text-right"><?= isset($snapshot['valid_sample_size']) ? (int)$snapshot['valid_sample_size'] : '-' ?></td><?php endforeach; ?></tr>
                <tr><th><?= htmlspecialchars(t('price.col.confidence')) ?></th><?php foreach ($columns as $snapshot): ?><td class="text-right"><?= isset($snapshot['confidence_label']) ? htmlspecialchars(\App\Core\Lang::status((string)$snapshot['confidence_label'])) : '-' ?></td><?php endforeach; ?></tr>
                <tr><th><?= htmlspecialchars(t('price.col.calculated')) ?></th><td class="text-right"><?= htmlspecialchars((string)($detail['public']['last_calculated_at'] ?? '-')) ?></td><td class="text-right">-</td></tr>
            </tbody>
        </table>
    </div>
    <div class="card-body">
        <?php if ($detail['preview'] === null): ?><div class="alert alert-warning mb-2"><?= htmlspecialchars(t('price.below_minimum')) ?></div><?php endif; ?>
        <?php if ($canRecalculate && $detail['can_recalculate']): ?>
            <form method="post" action="<?= $base ?>/admin/price-status/recalculate">
                <input type="hidden" name="_token" value="<?= $token ?>">
                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                <button class="btn btn-primary" type="submit"><i class="fas fa-sync-alt mr-2"></i><?= htmlspecialchars(t('price.recalculate')) ?></button>
            </form>
        <?php else: ?>
            <span class="text-muted"><?= htmlspecialchars(t($detail['can_recalculate'] ? 'price.no_permission' : 'price.nothing_to_do')) ?></span>
        <?php endif; ?>
    </div>
</div>

<div class="card mi-admin-card mi-table-card mb-3">
    <div class="card-header"><h2><?= htmlspecialchars(t('price.included', ['n' => count($detail['included'])])) ?></h2></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th>#</th><th><?= htmlspecialchars(t('price.col.listing')) ?></th><th class="text-right"><?= htmlspecialchars(t('price.col.price')) ?></th><th><?= htmlspecialchars(t('price.col.observed')) ?></th><th><?= htmlspecialchars(t('price.col.source')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($detail['included'] as $row): ?>
                <tr>
                    <td><a href="<?= $base ?>/admin/review/<?= (int)$row['observation_id'] ?>"><?= (int)$row['observation_id'] ?></a></td>
                    <td><?= htmlspecialchars(mb_strimwidth((string)($row['raw_title'] ?? ''), 0, 90, '…', 'UTF-8')) ?></td>
                    <td class="text-right"><?= $money($row['calculation_price']) ?></td>
                    <td><?= htmlspecialchars(substr((string)$row['observed_at'], 0, 10)) ?></td>
                    <td><?= htmlspecialchars((string)($row['source_name'] ?? '-')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($detail['excluded_reasons']): ?>
<div class="card mi-admin-card">
    <div class="card-header"><h2><?= htmlspecialchars(t('price.excluded')) ?></h2></div>
    <div class="card-body">
        <?php foreach ($detail['excluded_reasons'] as $reason => $count): ?><span class="badge badge-light mr-2"><?= htmlspecialchars(\App\Core\Lang::status((string)$reason)) ?>: <?= (int)$count ?></span><?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
