<?php
$grade = ['owner_text' => 'Owner text (no listing URL)', 'other' => 'Other source'];
$replayTone = $report['replay'] === 'MATCH' ? 'badge-success' : 'badge-danger';
ob_start(); ?><a class="btn btn-outline-secondary" href="<?= $base ?>/admin/price-indices"><i class="fas fa-arrow-left mr-2"></i>Price Index</a><?php $dxA = ob_get_clean(); ?>
<?= mi_page_header($base, ['Market Data', 'Snapshot #' . (int) $report['snapshot_id']], htmlspecialchars($report['product']), 'Evidence behind one price snapshot. Dates, prices and evidence grade only: no source names, URLs or listing text.', $dxA) ?>

<div class="row mi-metric-row">
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card gradient-primary"><span>Median</span><strong>฿<?= number_format($report['quartiles']['median']) ?></strong><small>Q1 ฿<?= number_format($report['quartiles']['q1']) ?> / Q3 ฿<?= number_format($report['quartiles']['q3']) ?></small></div></div>
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card gradient-success"><span>Included samples</span><strong><?= (int) $report['counts']['included'] ?></strong><small>Excluded <?= (int) array_sum($report['counts']['excluded_by_reason']) ?></small></div></div>
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card gradient-info"><span>Confidence</span><strong><?= htmlspecialchars(strtoupper($report['confidence_label'])) ?></strong><small>Score <?= number_format($report['confidence_score'], 1) ?> · <?= htmlspecialchars($report['confidence_method_version']) ?></small></div></div>
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card <?= $report['sources']['dominant_share'] >= 0.8 ? 'gradient-warning' : 'gradient-purple' ?>"><span>Sources</span><strong><?= (int) $report['sources']['distinct'] ?></strong><small>Dominant source <?= number_format($report['sources']['dominant_share'] * 100) ?>% of samples</small></div></div>
</div>

<div class="card mi-admin-card mb-3">
    <div class="card-body">
        <p class="mb-2"><span class="badge <?= $replayTone ?>">Replay <?= htmlspecialchars($report['replay']) ?></span>
            <span class="badge badge-light">Provenance <?= htmlspecialchars($report['provenance_status']) ?></span>
            <span class="badge badge-light">Formula <?= htmlspecialchars($report['formula_version']) ?></span>
            <span class="badge badge-light">Calculated <?= htmlspecialchars($report['calculated_at']) ?></span></p>
        <p class="mb-0 text-muted">Evidence grade: <?= (int) $report['grades']['owner_text'] ?> owner text, <?= (int) $report['grades']['other'] ?> other.
            <?php if ($report['policy_caps']): ?>Policy caps applied: <?= htmlspecialchars(implode(', ', $report['policy_caps'])) ?>.<?php endif; ?></p>
        <?php if ($report['counts']['excluded_by_reason']): ?>
            <p class="mb-0 mt-2 text-muted">Excluded: <?php $parts = []; foreach ($report['counts']['excluded_by_reason'] as $reason => $n) { $parts[] = htmlspecialchars((string) $reason) . ' ' . (int) $n; } echo implode(' · ', $parts); ?></p>
        <?php endif; ?>
    </div>
</div>

<?php if (!$report['gap']['reaches_medium']): ?>
<div class="card mi-admin-card mb-3">
    <div class="card-header">What is missing for medium confidence</div>
    <div class="card-body">
        <ul class="mb-0"><?php foreach ($report['gap']['blockers'] as $b): ?><li><?= htmlspecialchars($b) ?></li><?php endforeach; ?></ul>
        <?php if ($report['gap']['notes']): ?><p class="mb-0 mt-2 text-muted"><?= htmlspecialchars(implode(' ', $report['gap']['notes'])) ?></p><?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card mi-admin-card mi-table-card">
    <div class="card-header">Members of this snapshot</div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th>Observed</th><th class="text-right">Price</th><th>Evidence grade</th><th>Used</th></tr></thead>
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
