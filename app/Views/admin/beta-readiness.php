<?php
$sections = ['A' => 'Data and trust', 'B' => 'Product and experience', 'C' => 'Security', 'D' => 'Operations', 'E' => 'Legal, privacy and rights', 'F' => 'Engineering gates'];
$badge = ['met' => ['badge-success', 'Met'], 'not_met' => ['badge-danger', 'Not met'], 'owner' => ['badge-warning', 'Owner'], 'unknown' => ['badge-secondary', '?']];
$v = $panel['verdict'];
?>
<?= mi_page_header($base, ['Market Data', 'Beta readiness'], 'Beta readiness', 'Read-only view of docs/BETA_READINESS_CHECKLIST.md. Rows marked live are measured now on this database and environment; the others show the status written in the checklist. It decides nothing: the Go/No-Go decision is the owner\'s.', '') ?>

<?php if (!$panel['available']): ?>
    <div class="alert alert-warning">The checklist file is not available on this installation (docs/BETA_READINESS_CHECKLIST.md).</div>
<?php else: ?>
<div class="row mi-metric-row">
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card <?= $v['verdict'] === 'GO' ? 'gradient-success' : 'gradient-warning' ?>"><span>Verdict by the checklist's rule</span><strong><?= htmlspecialchars($v['verdict']) ?></strong><small>GO needs every MUST row met</small></div></div>
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card gradient-primary"><span>MUST rows met</span><strong><?= (int) $v['must_met'] ?> / <?= (int) $v['must'] ?></strong><small><?= count($v['unmet_must']) ?> not met</small></div></div>
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card gradient-info"><span>SHOULD rows met</span><strong><?= (int) $v['should_met'] ?> / <?= (int) $v['should'] ?></strong><small>listed with an owner and a date when a decision is taken</small></div></div>
    <div class="col-xl-3 col-md-6"><div class="card mi-metric-card gradient-purple"><span>Measured now</span><strong><?= count(array_filter($panel['rows'], static fn($r) => $r['live'])) ?></strong><small>rows with a live value</small></div></div>
</div>

<?php if ($v['unmet_must']): ?>
<div class="card mi-admin-card mb-3"><div class="card-body"><strong>MUST rows not met:</strong> <?= htmlspecialchars(implode(', ', $v['unmet_must'])) ?></div></div>
<?php endif; ?>

<?php foreach ($sections as $letter => $title): $rows = array_values(array_filter($panel['rows'], static fn($r) => $r['section'] === $letter)); if (!$rows) { continue; } ?>
<div class="card mi-admin-card mi-table-card mb-3">
    <div class="card-header"><?= $letter ?>. <?= htmlspecialchars($title) ?></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th>ID</th><th>Level</th><th>Requirement</th><th>Status</th><th>Evidence / gap</th></tr></thead>
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
