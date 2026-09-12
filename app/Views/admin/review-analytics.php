<?php $filters=$report['filters'] ?? []; ?>
<div class="mi-page-header">
    <div>
        <span class="mi-breadcrumb">Admin / Market Data</span>
        <h1>Review Analytics</h1>
        <p>Review quality and calibration metrics. Fixture metrics are labeled separately from REAL market evidence.</p>
    </div>
    <a class="btn btn-primary" href="<?= $base ?>/admin/review-queue"><i class="fas fa-clipboard-check mr-2"></i>Review Queue</a>
</div>

<form class="card mi-admin-card mb-3" method="get" action="<?= $base ?>/admin/review-analytics">
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-2"><label>Category<select class="form-control" name="category"><option value="">All</option><?php foreach(['cpu'=>'CPU','gpu'=>'GPU','ram'=>'RAM','storage'=>'Storage'] as $v=>$l): ?><option value="<?= $v ?>" <?= ($filters['category'] ?? '')===$v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-2"><label>Lane<select class="form-control" name="lane"><option value="">All</option><?php foreach(['green','amber','red'] as $lane): ?><option value="<?= $lane ?>" <?= ($filters['lane'] ?? '')===$lane ? 'selected' : '' ?>><?= strtoupper($lane) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-2"><label>Outcome<select class="form-control" name="outcome"><option value="">All</option><?php foreach(['pending','approved','rejected','excluded'] as $outcome): ?><option value="<?= $outcome ?>" <?= ($filters['outcome'] ?? '')===$outcome ? 'selected' : '' ?>><?= htmlspecialchars($outcome) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-2"><label>From<input class="form-control" type="date" name="from" value="<?= htmlspecialchars((string)($filters['from'] ?? '')) ?>"></label></div>
            <div class="form-group col-md-2"><label>To<input class="form-control" type="date" name="to" value="<?= htmlspecialchars((string)($filters['to'] ?? '')) ?>"></label></div>
            <div class="form-group col-md-2"><button class="btn btn-primary btn-block" type="submit">Filter</button></div>
        </div>
    </div>
</form>

<div class="notice mb-3">Dataset: <?= htmlspecialchars((string)$report['dataset_label']) ?> / Calibration: <?= htmlspecialchars((string)$report['calibration_status']) ?> / MOCK_TEST metrics are not live-market proof.</div>

<div class="row mi-metric-row">
    <div class="col-xl-2 col-md-4"><div class="card mi-metric-card"><span>Sample</span><strong><?= (int)$report['sample_size'] ?></strong><small>REAL <?= (int)$report['real_records'] ?> / MOCK <?= (int)$report['mock_test_records'] ?></small></div></div>
    <div class="col-xl-2 col-md-4"><div class="card mi-metric-card mi-metric-warning"><span>Backlog</span><strong><?= (int)$report['lifecycle']['review_backlog'] ?></strong><small>Pending decisions</small></div></div>
    <div class="col-xl-2 col-md-4"><div class="card mi-metric-card"><span>Reviewed</span><strong><?= (int)$report['lifecycle']['reviewed_count'] ?></strong><small><?= number_format((float)$report['lifecycle']['decision_rate'],1) ?>% decision rate</small></div></div>
    <div class="col-xl-2 col-md-4"><div class="card mi-metric-card"><span>Corrected</span><strong><?= (int)$report['lifecycle']['corrected_reviews'] ?></strong><small><?= number_format((float)$report['lifecycle']['correction_rate'],1) ?>% correction rate</small></div></div>
    <div class="col-xl-2 col-md-4"><div class="card mi-metric-card"><span>Green Precision</span><strong><?= $report['green_precision']['clean_green_precision']===null ? '-' : number_format((float)$report['green_precision']['clean_green_precision'],1).'%' ?></strong><small>After human review</small></div></div>
    <div class="col-xl-2 col-md-4"><div class="card mi-metric-card"><span>Live Yield</span><strong><?= $report['valid_observation_yield']['yield_percent']===null ? '-' : number_format((float)$report['valid_observation_yield']['yield_percent'],1).'%' ?></strong><small>MOCK excluded</small></div></div>
</div>

<div class="row">
    <div class="col-xl-6">
        <div class="card mi-admin-card mb-3"><div class="card-header"><h2>Lane Outcomes</h2></div><div class="card-body mi-status-list">
            <?php foreach($report['lanes'] as $lane=>$row): ?><div><span><span class="lane-badge lane-<?= htmlspecialchars($lane) ?>"><?= strtoupper(htmlspecialchars($lane)) ?></span></span><strong><?= (int)$row['total'] ?></strong><small>A <?= (int)$row['approved'] ?> / R <?= (int)$row['rejected'] ?> / X <?= (int)$row['excluded'] ?> / C <?= (int)$row['corrected'] ?></small></div><?php endforeach; ?>
        </div></div>
    </div>
    <div class="col-xl-6">
        <div class="card mi-admin-card mb-3"><div class="card-header"><h2>Source Quality</h2></div><div class="card-body mi-status-list">
            <?php foreach(array_slice($report['source_quality'],0,8) as $source): ?><div><span><?= htmlspecialchars((string)$source['source_name']) ?><small><?= htmlspecialchars(strtoupper((string)$source['dataset_type'])) ?></small></span><strong><?= $source['valid_observation_yield']===null ? '-' : number_format((float)$source['valid_observation_yield'],1).'%' ?></strong><small>Reviewed <?= (int)$source['reviewed'] ?></small></div><?php endforeach; ?>
            <?php if(!$report['source_quality']): ?><div class="empty">No source quality data yet.</div><?php endif; ?>
        </div></div>
    </div>
</div>
