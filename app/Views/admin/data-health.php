<?php
$conf = $report['confidence']; $t = $report['totals']; $f = $report['freshness']; $g = $report['grades']; $l = $report['ladder'];
$priced = array_sum(array_column($report['categories'], 'priced')); $products = array_sum(array_column($report['categories'], 'products'));
ob_start(); ?><a class="btn btn-outline-secondary" href="<?= $base ?>/admin/price-indices"><i class="fas fa-tags mr-2"></i>Price Index</a><?php $dxA = ob_get_clean(); ?>
<?= mi_page_header($base, ['Market Data', 'Data health'], 'Data health', 'How complete, fresh and well-evidenced the public prices are. Counts only: no source names, URLs or listing text.', $dxA) ?>

<div class="row mi-metric-row">
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card gradient-primary"><span>Products with a public price</span><strong><?= (int) $priced ?> / <?= (int) $products ?></strong><small><?= $products > 0 ? number_format(100 * $priced / $products, 1) : '0' ?>% of active products</small></div></div>
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card gradient-success"><span>Approved observations</span><strong><?= (int) $t['approved'] ?></strong><small>Newest <?= htmlspecialchars((string) ($t['newest_approved'] ?? 'none')) ?> · excluded <?= (int) $t['excluded'] ?></small></div></div>
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card gradient-warning"><span>Waiting for review</span><strong><?= (int) $t['pending'] ?></strong><small>Oldest <?= $t['oldest_pending_days'] === null ? 'none' : (int) $t['oldest_pending_days'] . ' days' ?></small></div></div>
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card gradient-info"><span>Confidence (public prices)</span><strong><?= (int) $conf['low'] ?> low</strong><small>medium <?= (int) $conf['medium'] ?> · high <?= (int) $conf['high'] ?></small></div></div>
</div>

<div class="card mi-admin-card mb-3">
    <div class="card-body">
        <p class="mb-2"><span class="badge <?= $g['other'] === 0 ? 'badge-warning' : 'badge-success' ?>">Evidence mix</span>
            <?= (int) $g['owner_text'] ?> approved observations are owner text without a listing URL, <?= (int) $g['other'] ?> come from other sources.
            <?php if ($g['other'] === 0): ?>With one kind of evidence every public price stays at low confidence (see <code>docs/SECOND_SOURCE_INTAKE.md</code>).<?php endif; ?></p>
        <p class="mb-2"><span class="badge badge-light">Freshness</span>
            <?= (int) $f['fresh_majority'] ?> of <?= (int) $f['snapshots'] ?> public prices have mostly fresh samples (average <?= number_format($f['avg_fresh_pct'], 1) ?>% of samples within the freshness window).</p>
        <p class="mb-0"><span class="badge <?= $l['flags'] > 0 ? 'badge-danger' : 'badge-success' ?>">Tier order</span>
            <?= (int) $l['checked'] ?> prices checked (GPU, CPU, RAM), <?= (int) $l['flags'] ?> flagged where a higher tier (or a bigger RAM kit) is priced clearly below a lower one.
            <?php foreach ($l['items'] as $x): ?><br><span class="text-muted"><?= htmlspecialchars($x['higher']) ?> ฿<?= number_format($x['higher_median']) ?> is below <?= htmlspecialchars($x['lower']) ?> ฿<?= number_format($x['lower_median']) ?></span><?php endforeach; ?></p>
    </div>
</div>

<div class="card mi-admin-card mi-table-card">
    <div class="card-header">Coverage by category</div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th>Category</th><th class="text-right">Products</th><th class="text-right">With a price</th><th class="text-right">Coverage</th><th class="text-right">Approved</th><th class="text-right">Pending</th></tr></thead>
            <tbody>
            <?php foreach ($report['categories'] as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['category']) ?></td>
                    <td class="text-right"><?= (int) $c['products'] ?></td>
                    <td class="text-right"><?= (int) $c['priced'] ?></td>
                    <td class="text-right"><?= number_format($c['priced_pct'], 1) ?>%</td>
                    <td class="text-right"><?= (int) $c['approved'] ?></td>
                    <td class="text-right"><?= (int) $c['pending'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if (!empty($community) && array_sum($community['counts']) > 0): // LS-21 (R-11): an attention signal for a moderator, never a vote and never a price input ?>
<div class="card mi-admin-card mt-3">
    <div class="card-header">Community reports <a class="btn btn-sm btn-outline-primary float-right" href="<?= $base ?>/admin/community-reports">Open the queue</a></div>
    <div class="card-body">
        <p class="mb-2"><?= (int) $community['counts']['new'] ?> new · <?= (int) $community['counts']['accepted'] ?> accepted · <?= (int) $community['counts']['rejected'] ?> rejected · <?= (int) $community['counts']['spam'] ?> spam. Reports are signals for a person to check: they never change a price, and many reports are not a vote.</p>
        <?php if ($community['products']): ?>
            <p class="mb-1 text-muted">Models with the most open reports</p>
            <ul class="mb-0"><?php foreach ($community['products'] as $p): ?><li><a href="<?= $base ?>/price/<?= rawurlencode((string) $p['slug']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string) $p['full_name']) ?></a> · <?= (int) $p['new_reports'] ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
