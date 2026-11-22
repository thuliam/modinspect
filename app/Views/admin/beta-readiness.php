<?php
$sections = ['A' => t('v.data_and_trust'), 'B' => t('v.product_and_experience'), 'C' => t('v.security'), 'D' => t('v.operations'), 'E' => t('v.legal_privacy_and_rights'), 'F' => t('v.engineering_gates')];
$badge = ['met' => ['badge-success', t('v.met')], 'not_met' => ['badge-danger', t('v.not_met_2')], 'owner' => ['badge-warning', t('v.owner')], 'unknown' => ['badge-secondary', '?']];
$v = $panel['verdict'];
?>
<?= mi_page_header($base, ['Market Data', 'Beta readiness'], htmlspecialchars(t('v.beta_readiness')), htmlspecialchars(t('v.read_only_view_of_docs_beta_readines')), '') ?>

<?php if (!$panel['available']): ?>
    <div class="alert alert-warning"><?= htmlspecialchars(t('v.the_checklist_file_is_not_available')) ?></div>
<?php else: ?>
<div class="row mi-metric-row">
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card <?= $v['verdict'] === 'GO' ? 'gradient-success' : 'gradient-warning' ?>"><span><?= htmlspecialchars(t('v.verdict_by_the_checklist_s_rule')) ?></span><strong><?= htmlspecialchars($v['verdict']) ?></strong><small><?= htmlspecialchars(t('v.go_needs_every_must_row_met')) ?></small></div></div>
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card gradient-primary"><span><?= htmlspecialchars(t('v.must_rows_met')) ?></span><strong><?= (int) $v['must_met'] ?> / <?= (int) $v['must'] ?></strong><small><?= count($v['unmet_must']) ?> <?= htmlspecialchars(t('v.not_met')) ?></small></div></div>
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card gradient-info"><span><?= htmlspecialchars(t('v.should_rows_met')) ?></span><strong><?= (int) $v['should_met'] ?> / <?= (int) $v['should'] ?></strong><small><?= htmlspecialchars(t('v.listed_with_an_owner_and_a_date_when')) ?></small></div></div>
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card gradient-purple"><span><?= htmlspecialchars(t('v.measured_now')) ?></span><strong><?= count(array_filter($panel['rows'], static fn($r) => $r['live'])) ?></strong><small><?= htmlspecialchars(t('v.rows_with_a_live_value')) ?></small></div></div>
</div>

<?php if ($v['unmet_must']): ?>
<div class="card mi-admin-card mb-3"><div class="card-body"><strong><?= htmlspecialchars(t('v.must_rows_not_met')) ?></strong> <?= htmlspecialchars(implode(', ', $v['unmet_must'])) ?></div></div>
<?php endif; ?>

<?php foreach ($sections as $letter => $title): $rows = array_values(array_filter($panel['rows'], static fn($r) => $r['section'] === $letter)); if (!$rows) { continue; } ?>
<div class="card mi-admin-card mi-table-card mb-3">
    <div class="card-header"><?= $letter ?>. <?= htmlspecialchars($title) ?></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= htmlspecialchars(t('v.xb718adec')) ?></th><th><?= htmlspecialchars(t('v.level')) ?></th><th><?= htmlspecialchars(t('v.requirement')) ?></th><th><?= htmlspecialchars(t('v.status')) ?></th><th><?= htmlspecialchars(t('v.evidence_gap')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): [$cls, $label] = $badge[$r['status']] ?? $badge['unknown']; ?>
                <tr>
                    <td><?= htmlspecialchars($r['id']) ?></td>
                    <td><?= htmlspecialchars($r['level']) ?></td>
                    <td><?= htmlspecialchars($r['requirement']) ?></td>
                    <td><span class="badge <?= $cls ?>"><?= $label ?></span><?= $r['owner'] ? ' <span class="badge badge-light">owner</span>' : '' ?><?= $r['live'] ? ' <span class="badge badge-info">live</span>' : '' ?></td>
                    <td class="text-muted"><?= htmlspecialchars($r['evidence']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
