<?php
$conf = $report['confidence']; $t = $report['totals']; $f = $report['freshness']; $g = $report['grades']; $l = $report['ladder'];
$priced = array_sum(array_column($report['categories'], 'priced')); $products = array_sum(array_column($report['categories'], 'products'));
ob_start(); ?><a class="btn btn-outline-secondary" href="<?= $base ?>/admin/price-indices"><i class="fas fa-tags mr-2"></i><?= htmlspecialchars(t('v.price_index')) ?></a><?php $dxA = ob_get_clean(); ?>
<?= mi_page_header($base, ['Market Data', 'Data health'], htmlspecialchars(t('v.data_health')), htmlspecialchars(t('v.how_complete_fresh_and_well_evidence')), $dxA) ?>

<div class="row mi-metric-row">
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card gradient-primary"><span><?= htmlspecialchars(t('v.products_with_a_public_price')) ?></span><strong><?= (int) $priced ?> / <?= (int) $products ?></strong><small><?= $products > 0 ? number_format(100 * $priced / $products, 1) : '0' ?><?= htmlspecialchars(t('v.of_active_products')) ?></small></div></div>
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card gradient-success"><span><?= htmlspecialchars(t('v.approved_observations')) ?></span><strong><?= (int) $t['approved'] ?></strong><small><?= htmlspecialchars(t('v.newest')) ?> <?= htmlspecialchars((string) ($t['newest_approved'] ?? 'none')) ?> <?= htmlspecialchars(t('v.excluded')) ?> <?= (int) $t['excluded'] ?></small></div></div>
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card gradient-warning"><span><?= htmlspecialchars(t('v.waiting_for_review')) ?></span><strong><?= (int) $t['pending'] ?></strong><small><?= htmlspecialchars(t('v.oldest')) ?> <?= $t['oldest_pending_days'] === null ? 'none' : (int) $t['oldest_pending_days'] . ' days' ?></small></div></div>
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card gradient-info"><span><?= htmlspecialchars(t('v.confidence_public_prices')) ?></span><strong><?= (int) $conf['low'] ?> <?= htmlspecialchars(t('v.low')) ?></strong><small><?= htmlspecialchars(t('v.medium')) ?> <?= (int) $conf['medium'] ?> <?= htmlspecialchars(t('v.high')) ?> <?= (int) $conf['high'] ?></small></div></div>
</div>

<div class="card mi-admin-card mb-3">
    <div class="card-body">
        <p class="mb-2"><span class="badge <?= $g['other'] === 0 ? 'badge-warning' : 'badge-success' ?>"><?= htmlspecialchars(t('v.evidence_mix')) ?></span>
            <?= (int) $g['owner_text'] ?> <?= htmlspecialchars(t('v.approved_observations_are_owner_text')) ?> <?= (int) $g['other'] ?> <?= htmlspecialchars(t('v.come_from_other_sources')) ?>
            <?php if ($g['other'] === 0): ?><?= htmlspecialchars(t('misc.one_kind_evidence')) ?> (<?= htmlspecialchars(t('misc.see')) ?> <code>docs/SECOND_SOURCE_INTAKE.md</code>).<?php endif; ?></p>
        <p class="mb-2"><span class="badge badge-light"><?= htmlspecialchars(t('v.freshness')) ?></span>
            <?= (int) $f['fresh_majority'] ?> <?= htmlspecialchars(t('v.x8bf8854b')) ?> <?= (int) $f['snapshots'] ?> <?= htmlspecialchars(t('v.public_prices_have_mostly_fresh_samp')) ?> <?= number_format($f['avg_fresh_pct'], 1) ?><?= htmlspecialchars(t('v.of_samples_within_the_freshness_wind')) ?></p>
        <p class="mb-0"><span class="badge <?= $l['flags'] > 0 ? 'badge-danger' : 'badge-success' ?>"><?= htmlspecialchars(t('v.tier_order')) ?></span>
            <?= (int) $l['checked'] ?> <?= htmlspecialchars(t('v.prices_checked_gpu_cpu_ram')) ?> <?= (int) $l['flags'] ?> <?= htmlspecialchars(t('v.flagged_where_a_higher_tier_or_a_big')) ?>
            <?php foreach ($l['items'] as $x): ?><br><span class="text-muted"><?= htmlspecialchars($x['higher']) ?> ฿<?= number_format($x['higher_median']) ?> <?= htmlspecialchars(t('v.is_below')) ?> <?= htmlspecialchars($x['lower']) ?> ฿<?= number_format($x['lower_median']) ?></span><?php endforeach; ?></p>
    </div>
</div>

<div class="card mi-admin-card mi-table-card">
    <div class="card-header"><?= htmlspecialchars(t('v.coverage_by_category')) ?></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= htmlspecialchars(t('v.category')) ?></th><th class="text-right"><?= htmlspecialchars(t('v.products')) ?></th><th class="text-right"><?= htmlspecialchars(t('v.with_a_price')) ?></th><th class="text-right"><?= htmlspecialchars(t('v.coverage')) ?></th><th class="text-right"><?= htmlspecialchars(t('v.approved')) ?></th><th class="text-right"><?= htmlspecialchars(t('v.pending')) ?></th></tr></thead>
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
    <div class="card-header"><?= htmlspecialchars(t('v.community_reports')) ?> <a class="btn btn-sm btn-outline-primary float-right" href="<?= $base ?>/admin/community-reports"><?= htmlspecialchars(t('v.open_the_queue')) ?></a></div>
    <div class="card-body">
        <p class="mb-2"><?= (int) $community['counts']['new'] ?> <?= htmlspecialchars(t('v.new')) ?> <?= (int) $community['counts']['accepted'] ?> <?= htmlspecialchars(t('v.accepted')) ?> <?= (int) $community['counts']['rejected'] ?> <?= htmlspecialchars(t('v.rejected')) ?> <?= (int) $community['counts']['spam'] ?> <?= htmlspecialchars(t('v.spam_reports_are_signals_for_a_perso')) ?></p>
        <?php if ($community['products']): ?>
            <p class="mb-1 text-muted"><?= htmlspecialchars(t('v.models_with_the_most_open_reports')) ?></p>
            <ul class="mb-0"><?php foreach ($community['products'] as $p): ?><li><a href="<?= $base ?>/price/<?= rawurlencode((string) $p['slug']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string) $p['full_name']) ?></a> · <?= (int) $p['new_reports'] ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php if (!empty($dealOutcomes) && $dealOutcomes['total'] > 0): // LS-29 B1: what visitors said they did after a Deal Checker result; a signal, never a market price ?>
<?php $o = $dealOutcomes; $pd = $o['paid']; ?>
<div class="card mi-admin-card mt-3">
    <div class="card-header"><?= htmlspecialchars(t('v.deal_checker_outcomes')) ?></div>
    <div class="card-body">
        <p class="mb-2"><?= (int) $o['total'] ?> <?= htmlspecialchars(t('v.answers')) ?> <?= (int) $o['by_outcome']['bought'] ?> <?= htmlspecialchars(t('v.bought')) ?> <?= (int) $o['by_outcome']['negotiated'] ?> <?= htmlspecialchars(t('v.negotiated')) ?> <?= (int) $o['by_outcome']['passed'] ?> <?= htmlspecialchars(t('v.passed_these_are_what_visitors_chose')) ?></p>
        <?php if ($pd['with_price'] > 0): ?>
        <p class="mb-2"><?= (int) $pd['with_price'] ?> <?= htmlspecialchars(t('v.gave_the_price_they_paid')) ?> <?= (int) $pd['below_range'] ?> <?= htmlspecialchars(t('v.below_the_range_shown_to_them')) ?> <?= (int) $pd['within_range'] ?> <?= htmlspecialchars(t('v.inside_it')) ?> <?= (int) $pd['above_range'] ?> <?= htmlspecialchars(t('v.above_it')) ?><?= $pd['no_range'] > 0 ? ', ' . (int) $pd['no_range'] . t('v.with_no_range_to_compare') : '' ?>.
            <?php if ($pd['avg_below_asking_pct'] !== null): ?><?= htmlspecialchars(t('v.on_average_they_paid')) ?> <?= number_format(abs($pd['avg_below_asking_pct']), 1) ?>% <?= $pd['avg_below_asking_pct'] >= 0 ? 'below' : 'above' ?> <?= htmlspecialchars(t('v.the_price_they_had_checked')) ?><?php endif; ?></p>
        <?php endif; ?>
        <div class="table-responsive"><table class="table table-sm mi-admin-table mb-0">
            <thead><tr><th><?= htmlspecialchars(t('v.result_shown')) ?></th><th class="text-right"><?= htmlspecialchars(t('v.bought_2')) ?></th><th class="text-right"><?= htmlspecialchars(t('v.negotiated_2')) ?></th><th class="text-right"><?= htmlspecialchars(t('v.passed')) ?></th></tr></thead>
            <tbody><?php foreach ($o['by_label'] as $label => $c): ?><tr><td><?= htmlspecialchars((string) $label) ?></td><td class="text-right"><?= (int) ($c['bought'] ?? 0) ?></td><td class="text-right"><?= (int) ($c['negotiated'] ?? 0) ?></td><td class="text-right"><?= (int) ($c['passed'] ?? 0) ?></td></tr><?php endforeach; ?></tbody>
        </table></div>
    </div>
</div>
<?php endif; ?>
