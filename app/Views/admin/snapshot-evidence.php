<?php
$grade = ['owner_text' => t('v.owner_text_no_listing_url'), 'other' => t('v.other_source')];
$replayTone = $report['replay'] === 'MATCH' ? 'badge-success' : 'badge-danger';
ob_start(); ?><a class="btn btn-outline-secondary" href="<?= $base ?>/admin/price-indices"><i class="fas fa-arrow-left mr-2"></i><?= htmlspecialchars(t('v.price_index')) ?></a><?php $dxA = ob_get_clean(); ?>
<?= mi_page_header($base, ['Market Data', 'Snapshot #' . (int) $report['snapshot_id']], htmlspecialchars($report['product']), htmlspecialchars(t('misc.snapshot_desc')), $dxA) ?>

<div class="row mi-metric-row">
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card gradient-primary"><span><?= htmlspecialchars(t('v.median')) ?></span><strong>฿<?= number_format($report['quartiles']['median']) ?></strong><small>Q1 ฿<?= number_format($report['quartiles']['q1']) ?> / Q3 ฿<?= number_format($report['quartiles']['q3']) ?></small></div></div>
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card gradient-success"><span><?= htmlspecialchars(t('v.included_samples')) ?></span><strong><?= (int) $report['counts']['included'] ?></strong><small><?= htmlspecialchars(t('v.excluded_2')) ?> <?= (int) array_sum($report['counts']['excluded_by_reason']) ?></small></div></div>
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card gradient-info"><span><?= htmlspecialchars(t('v.confidence')) ?></span><strong><?= htmlspecialchars(mb_strtoupper(\App\Core\Lang::status((string) $report['confidence_label']))) ?></strong><small><?= htmlspecialchars(t('v.score')) ?> <?= number_format($report['confidence_score'], 1) ?> · <?= htmlspecialchars($report['confidence_method_version']) ?></small></div></div>
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card <?= $report['sources']['dominant_share'] >= 0.8 ? 'gradient-warning' : 'gradient-purple' ?>"><span><?= htmlspecialchars(t('v.sources')) ?></span><strong><?= (int) $report['sources']['distinct'] ?></strong><small><?= htmlspecialchars(t('v.dominant_source')) ?> <?= number_format($report['sources']['dominant_share'] * 100) ?><?= htmlspecialchars(t('v.of_samples')) ?></small></div></div>
</div>

<div class="card mi-admin-card mb-3">
    <div class="card-body">
        <p class="mb-2"><span class="badge <?= $replayTone ?>"><?= htmlspecialchars(t('v.replay')) ?> <?= htmlspecialchars(\App\Core\Lang::status((string) $report['replay'])) ?></span>
            <span class="badge badge-light"><?= htmlspecialchars(t('v.provenance')) ?> <?= htmlspecialchars(\App\Core\Lang::status((string) $report['provenance_status'])) ?></span>
            <span class="badge badge-light"><?= htmlspecialchars(t('v.formula')) ?> <?= htmlspecialchars($report['formula_version']) ?></span>
            <span class="badge badge-light"><?= htmlspecialchars(t('v.calculated')) ?> <?= htmlspecialchars($report['calculated_at']) ?></span></p>
        <p class="mb-0 text-muted"><?= htmlspecialchars(t('v.evidence_grade')) ?> <?= (int) $report['grades']['owner_text'] ?> <?= htmlspecialchars(t('v.owner_text')) ?> <?= (int) $report['grades']['other'] ?> <?= htmlspecialchars(t('v.other')) ?>
            <?php if ($report['policy_caps']): ?><?= htmlspecialchars(t('v.policy_caps_applied')) ?> <?= htmlspecialchars(implode(', ', array_map(static fn($c) => \App\Core\Lang::status((string) $c), $report['policy_caps']))) ?>.<?php endif; ?></p>
        <?php if ($report['counts']['excluded_by_reason']): ?>
            <p class="mb-0 mt-2 text-muted"><?= htmlspecialchars(t('v.excluded_3')) ?> <?php $parts = []; foreach ($report['counts']['excluded_by_reason'] as $reason => $n) { $parts[] = htmlspecialchars(\App\Core\Lang::status((string) $reason)) . ' ' . (int) $n; } echo implode(' · ', $parts); ?></p>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($history)): $hp = $history['previous']; $hd = $history['diff']; $hm = $history['median']; ?>
<div class="card mi-admin-card mb-3">
    <div class="card-header"><?= htmlspecialchars(t('v.compared_with_the_previous_snapshot')) ?></div>
    <div class="card-body">
        <?php if ($hp === null): ?>
            <p class="mb-0 text-muted"><?= htmlspecialchars(t('v.this_is_the_first_public_snapshot_of')) ?></p>
        <?php else: ?>
            <p class="mb-2"><?= htmlspecialchars(t('v.previous')) ?> <a href="<?= $base ?>/admin/snapshots/<?= (int) $hp['id'] ?>">#<?= (int) $hp['id'] ?></a><?= htmlspecialchars(t('v.calculated_2')) ?> <?= htmlspecialchars(substr($hp['calculated_at'], 0, 16)) ?><?= htmlspecialchars(t('v.median_2')) ?><?= number_format($hp['median']) ?>.</p>
            <p class="mb-0"><?= htmlspecialchars(t('v.median')) ?> <?= $hm['delta'] >= 0 ? '+' : '−' ?>฿<?= number_format(abs($hm['delta'])) ?><?= $hm['change_pct'] === null ? '' : ' (' . ($hm['change_pct'] >= 0 ? '+' : '') . number_format($hm['change_pct'], 1) . '%)' ?>
                · <?= (int) $hd['added'] ?> <?= htmlspecialchars(t('v.observations_added')) ?> <?= (int) $hd['removed'] ?> <?= htmlspecialchars(t('v.removed')) ?> <?= (int) $hd['kept'] ?> <?= htmlspecialchars(t('v.kept')) ?></p>
        <?php endif; ?>
    </div>
    <?php if (count($history['history']) > 1): $chartId = 'snaphist' . bin2hex(random_bytes(3)); $series = array_reverse($history['history']); ?>
    <div class="card-body pt-0">
        <div class="mi-chart-box"><canvas id="<?= $chartId ?>" data-chart="history" data-color="#6366f1" data-source="<?= $chartId ?>-data" role="img" aria-label="<?= htmlspecialchars(t('v.median_at_each_calculation_of_this_p')) ?>"></canvas></div>
        <p class="text-muted mt-2 mb-0"><?= htmlspecialchars(t('v.median_as_published_at_each_calculat')) ?></p>
        <?php // LS-20 B1: data block (not run), drawn by mi-charts.js (no inline script, so a Content-Security-Policy can be enforced) ?>
        <script src="<?= $base ?>/assets/vendor/chartjs/chart.umd.min.js"></script>
        <script src="<?= $base ?>/assets/mi-charts.js?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/mi-charts.js') ?>" defer></script>
        <script type="application/json" id="<?= $chartId ?>-data"><?= json_encode(array_map(static fn($r) => ['t' => substr((string) $r['calculated_at'], 5, 11), 'm' => (float) $r['median'], 'n' => (int) $r['included']], $series), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= htmlspecialchars(t('v.snapshot')) ?></th><th><?= htmlspecialchars(t('v.calculated')) ?></th><th class="text-right"><?= htmlspecialchars(t('v.median')) ?></th><th class="text-right"><?= htmlspecialchars(t('v.included')) ?></th><th><?= htmlspecialchars(t('v.confidence')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($history['history'] as $hr): ?>
                <tr>
                    <td><a href="<?= $base ?>/admin/snapshots/<?= (int) $hr['id'] ?>">#<?= (int) $hr['id'] ?></a></td>
                    <td><?= htmlspecialchars(substr($hr['calculated_at'], 0, 16)) ?></td>
                    <td class="text-right">฿<?= number_format($hr['median']) ?></td>
                    <td class="text-right"><?= (int) $hr['included'] ?></td>
                    <td><?= htmlspecialchars($hr['label']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!$report['gap']['reaches_medium']): ?>
<div class="card mi-admin-card mb-3">
    <div class="card-header"><?= htmlspecialchars(t('v.what_is_missing_for_medium_confidenc')) ?></div>
    <div class="card-body">
        <ul class="mb-0"><?php foreach ($report['gap']['blockers'] as $b): ?><li><?= htmlspecialchars($b) ?></li><?php endforeach; ?></ul>
        <?php if ($report['gap']['notes']): ?><p class="mb-0 mt-2 text-muted"><?= htmlspecialchars(implode(' ', $report['gap']['notes'])) ?></p><?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card mi-admin-card mi-table-card">
    <div class="card-header"><?= htmlspecialchars(t('v.members_of_this_snapshot')) ?></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= htmlspecialchars(t('v.observed')) ?></th><th class="text-right"><?= htmlspecialchars(t('v.price')) ?></th><th><?= htmlspecialchars(t('v.evidence_grade_2')) ?></th><th><?= htmlspecialchars(t('v.used')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($report['members'] as $m): ?>
                <tr>
                    <td><?= htmlspecialchars(substr($m['observed_at'], 0, 10)) ?></td>
                    <td class="text-right">฿<?= number_format($m['price']) ?></td>
                    <td><?= htmlspecialchars($grade[$m['grade']] ?? $m['grade']) ?></td>
                    <td><?= $m['included'] ? '<span class="badge badge-success">included</span>' : '<span class="badge badge-secondary">' . htmlspecialchars((string) $m['reason']) . '</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
