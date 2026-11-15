<?php $t = $report['totals']; $regionNames = ['central' => 'Central', 'east' => 'East', 'north' => 'North', 'northeast' => 'Northeast', 'south' => 'South']; ?>
<?php ob_start(); ?>Market Trends &amp; Locations<?php $dxT = ob_get_clean(); ob_start(); ?>Monthly price movement from approved observations, and the coarse place (province only) listings were offered from. Private: not shown on public pages.<?php $dxD = ob_get_clean(); ob_start(); ?><?php $dxA = ob_get_clean(); ?>
<?= mi_page_header($base, array (  0 => 'Market Data',), $dxT, $dxD, $dxA) ?>

<div class="row mi-metric-row">
    <div class="col-xl-3 col-md-6 mb-3"><div class="card mi-metric-card gradient-primary"><span>Trend months</span><strong><?= (int) $t['trend_points'] ?></strong><small>Months with at least 3 approved samples</small></div></div>
    <div class="col-xl-3 col-md-6 mb-3"><div class="card mi-metric-card gradient-success"><span>Products with a trend</span><strong><?= (int) $t['trend_products'] ?></strong><small><?= count(array_filter($report['trends'], static fn($x) => (int) $x['months'] > 1)) ?> have two or more months</small></div></div>
    <div class="col-xl-3 col-md-6 mb-3"><div class="card mi-metric-card gradient-info"><span>Listings with a province</span><strong><?= (int) $t['location_rows'] ?></strong><small>From owner-collected text, province granularity only</small></div></div>
    <div class="col-xl-3 col-md-6 mb-3"><div class="card mi-metric-card <?= $t['top_share'] > 50 ? 'gradient-warning' : 'gradient-purple' ?>"><span>Largest province share</span><strong><?= number_format((float) $t['top_share'], 0) ?>%</strong><small><?= htmlspecialchars((string) ($t['top_province'] ?? '-')) ?><?= $t['top_share'] > 50 ? ' - one province dominates; may be repeated posts from one seller' : '' ?></small></div></div>
</div>

<div class="row">
    <div class="col-xl-7 mb-3">
        <div class="card mi-admin-card mi-table-card">
            <div class="card-header">Price trend by product</div>
            <div class="table-responsive">
                <table class="table table-hover mi-admin-table mb-0">
                    <thead><tr><th>Product</th><th>Category</th><th class="text-right">Months</th><th class="text-right">Samples</th><th class="text-right">Latest median</th><th class="text-right">Change</th></tr></thead>
                    <tbody>
                    <?php foreach ($report['trends'] as $x): ?>
                        <tr>
                            <td><a href="<?= $base ?>/price/<?= rawurlencode($x['slug']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($x['full_name']) ?></a></td>
                            <td><?= htmlspecialchars($x['category_name']) ?></td>
                            <td class="text-right"><?= (int) $x['months'] ?></td>
                            <td class="text-right"><?= (int) $x['samples'] ?></td>
                            <td class="text-right">฿<?= number_format((float) $x['last_median']) ?></td>
                            <td class="text-right"><?= $x['change_pct'] === null ? '<span class="badge badge-secondary">one month</span>' : '<span class="badge ' . ($x['change_pct'] >= 0 ? 'badge-warning' : 'badge-success') . '">' . ($x['change_pct'] >= 0 ? '+' : '') . number_format((float) $x['change_pct'], 1) . '%</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$report['trends']): ?><tr><td colspan="6" class="text-muted">No trend yet. Run <code>php cli/price_trend_build.php</code> after approving observations.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-5 mb-3">
        <div class="card mi-admin-card mi-table-card">
            <div class="card-header">Listings by province and category</div>
            <div class="px-3 pb-2">
                <?php foreach ($report['regions'] as $r => $n): ?><span class="badge badge-secondary mr-1"><?= $regionNames[$r] ?? htmlspecialchars((string) $r) ?> <?= (int) $n ?></span><?php endforeach; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mi-admin-table mb-0">
                    <thead><tr><th>Province</th><th>Category</th><th class="text-right">Listings</th><th class="text-right">Median ask</th><th class="text-right">Repost rate</th></tr></thead>
                    <tbody>
                    <?php $repost = []; foreach (($dup['provinces'] ?? []) as $dp) { $repost[$dp['province']] = $dp['repost_rate']; } foreach (array_slice($report['locations'], 0, 40) as $l): ?>
                        <tr><td><?= htmlspecialchars($l['province']) ?></td><td><?= htmlspecialchars((string) $l['category']) ?></td><td class="text-right"><?= (int) $l['n'] ?></td><td class="text-right"><?= $l['median'] === null ? '<span class="text-muted">needs 3+</span>' : '฿' . number_format((float) $l['median']) ?></td><td class="text-right"><?php $rr = $repost[$l['province']] ?? null; echo $rr === null ? '-' : '<span class="badge ' . ($rr >= 0.5 ? 'badge-warning' : 'badge-secondary') . '">' . number_format($rr * 100) . '%</span>'; ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$report['locations']): ?><tr><td colspan="5" class="text-muted">No location log yet. Import with <code>php cli/location_log_import.php --file=...</code>.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php if (!empty($dup['overall']['listings'])): ?><div class="notice mb-3">Repeated posts: <b><?= number_format($dup['overall']['repost_rate'] * 100) ?>%</b> of the <?= (int) $dup['overall']['listings'] ?> listings with a province are reposts of an earlier listing. A province with a high repost rate shows one poster, not a market pattern.</div><?php endif; ?>
<div class="notice">Location is a context signal only. It never changes a price snapshot or confidence. Only the province is stored: no seller, shop, phone, district or address.</div>
