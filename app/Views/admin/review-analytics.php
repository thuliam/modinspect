<?php $filters=$report['filters'] ?? []; ?>
<?php ob_start(); ?><?= htmlspecialchars(t('v.review_analytics')) ?><?php $dxT = ob_get_clean(); ob_start(); ?><?= htmlspecialchars(t('v.review_quality_and_calibration_metri')) ?><?php $dxD = ob_get_clean(); ob_start(); ?><a class="btn btn-primary" href="<?= $base ?>/admin/review-queue"><i class="fas fa-clipboard-check mr-2"></i><?= htmlspecialchars(t('v.review_queue')) ?></a><?php $dxA = ob_get_clean(); ?>
<?= mi_page_header($base, array (  0 => 'Market Data',), $dxT, $dxD, $dxA) ?>

<form class="card mi-admin-card mb-3" method="get" action="<?= $base ?>/admin/review-analytics">
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-2"><label><?= htmlspecialchars(t('v.category')) ?><select class="form-control" name="category"><option value=""><?= htmlspecialchars(t('v.all')) ?></option><?php foreach(['cpu'=>'CPU','gpu'=>'GPU','ram'=>'RAM','storage'=>'Storage'] as $v=>$l): ?><option value="<?= $v ?>" <?= ($filters['category'] ?? '')===$v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-2"><label><?= htmlspecialchars(t('v.lane')) ?><select class="form-control" name="lane"><option value=""><?= htmlspecialchars(t('v.all')) ?></option><?php foreach(['green','amber','red'] as $lane): ?><option value="<?= $lane ?>" <?= ($filters['lane'] ?? '')===$lane ? 'selected' : '' ?>><?= strtoupper($lane) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-2"><label><?= htmlspecialchars(t('v.outcome')) ?><select class="form-control" name="outcome"><option value=""><?= htmlspecialchars(t('v.all')) ?></option><?php foreach(['pending','approved','rejected','excluded'] as $outcome): ?><option value="<?= $outcome ?>" <?= ($filters['outcome'] ?? '')===$outcome ? 'selected' : '' ?>><?= htmlspecialchars($outcome) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-group col-md-2"><label><?= htmlspecialchars(t('v.from')) ?><input class="form-control" type="date" name="from" value="<?= htmlspecialchars((string)($filters['from'] ?? '')) ?>"></label></div>
            <div class="form-group col-md-2"><label><?= htmlspecialchars(t('v.xe12167aa')) ?><input class="form-control" type="date" name="to" value="<?= htmlspecialchars((string)($filters['to'] ?? '')) ?>"></label></div>
            <div class="form-group col-md-2"><button class="btn btn-primary btn-block" type="submit"><?= htmlspecialchars(t('v.filter')) ?></button></div>
        </div>
    </div>
</form>

<div class="notice mb-3"><?= htmlspecialchars(t('v.dataset_2')) ?> <?= htmlspecialchars(\App\Core\Lang::status((string)$report['dataset_label'])) ?> <?= htmlspecialchars(t('v.calibration')) ?> <?= htmlspecialchars(\App\Core\Lang::status((string)$report['calibration_status'])) ?> <?= htmlspecialchars(t('v.mock_test_metrics_are_not_live_marke')) ?></div>

<div class="row mi-metric-row">
    <div class="col-xl-2 col-md-4"><div class="card mi-metric-card gradient-primary"><span><?= htmlspecialchars(t('v.sample')) ?></span><strong><?= (int)$report['sample_size'] ?></strong><small><?= htmlspecialchars(t('v.real')) ?> <?= (int)$report['real_records'] ?> <?= htmlspecialchars(t('v.mock')) ?> <?= (int)$report['mock_test_records'] ?></small></div></div>
    <div class="col-xl-2 col-md-4"><div class="card mi-metric-card gradient-warning"><span><?= htmlspecialchars(t('v.backlog')) ?></span><strong><?= (int)$report['lifecycle']['review_backlog'] ?></strong><small><?= htmlspecialchars(t('v.pending_decisions')) ?></small></div></div>
    <div class="col-xl-2 col-md-4"><div class="card mi-metric-card gradient-info"><span><?= htmlspecialchars(t('v.reviewed')) ?></span><strong><?= (int)$report['lifecycle']['reviewed_count'] ?></strong><small><?= number_format((float)$report['lifecycle']['decision_rate'],1) ?><?= htmlspecialchars(t('v.decision_rate')) ?></small></div></div>
    <div class="col-xl-2 col-md-4"><div class="card mi-metric-card gradient-purple"><span><?= htmlspecialchars(t('v.corrected')) ?></span><strong><?= (int)$report['lifecycle']['corrected_reviews'] ?></strong><small><?= number_format((float)$report['lifecycle']['correction_rate'],1) ?><?= htmlspecialchars(t('v.correction_rate')) ?></small></div></div>
    <div class="col-xl-2 col-md-4"><div class="card mi-metric-card gradient-ocean"><span><?= htmlspecialchars(t('v.green_precision')) ?></span><strong><?= $report['green_precision']['clean_green_precision']===null ? '-' : number_format((float)$report['green_precision']['clean_green_precision'],1).'%' ?></strong><small><?= htmlspecialchars(t('v.after_human_review')) ?></small></div></div>
    <div class="col-xl-2 col-md-4"><div class="card mi-metric-card gradient-pink"><span><?= htmlspecialchars(t('v.live_yield')) ?></span><strong><?= $report['valid_observation_yield']['yield_percent']===null ? '-' : number_format((float)$report['valid_observation_yield']['yield_percent'],1).'%' ?></strong><small><?= htmlspecialchars(t('v.mock_excluded')) ?></small></div></div>
</div>

<div class="row">
    <div class="col-xl-6">
        <div class="card mi-admin-card mb-3"><div class="card-header"><h2><?= htmlspecialchars(t('v.lane_outcomes')) ?></h2></div><div class="card-body mi-status-list">
            <?php foreach($report['lanes'] as $lane=>$row): ?><div><span><span class="lane-badge lane-<?= htmlspecialchars($lane) ?>"><?= strtoupper(htmlspecialchars($lane)) ?></span></span><strong><?= (int)$row['total'] ?></strong><small>A <?= (int)$row['approved'] ?> / R <?= (int)$row['rejected'] ?> / X <?= (int)$row['excluded'] ?> / C <?= (int)$row['corrected'] ?></small></div><?php endforeach; ?>
        </div></div>
    </div>
    <div class="col-xl-6">
        <div class="card mi-admin-card mb-3"><div class="card-header"><h2><?= htmlspecialchars(t('v.source_quality')) ?></h2></div><div class="card-body mi-status-list">
            <?php foreach(array_slice($report['source_quality'],0,8) as $source): ?><div><span><?= htmlspecialchars((string)$source['source_name']) ?><small><?= htmlspecialchars(strtoupper((string)$source['dataset_type'])) ?></small></span><strong><?= $source['valid_observation_yield']===null ? '-' : number_format((float)$source['valid_observation_yield'],1).'%' ?></strong><small><?= htmlspecialchars(t('v.reviewed')) ?> <?= (int)$source['reviewed'] ?></small></div><?php endforeach; ?>
            <?php if(!$report['source_quality']): ?><div class="empty"><?= htmlspecialchars(t('v.no_source_quality_data_yet')) ?></div><?php endif; ?>
        </div></div>
    </div>
</div>
<div class="row">
    <div class="col-xl-6">
        <?php $captureLabels=['owner_transcription'=>t('v.owner_transcription'),'machine_capture'=>t('v.machine_capture'),'owner_text'=>t('v.owner_text_no_link'),'other_real'=>t('v.other_real'),'fixture'=>t('v.fixture')]; ?>
        <div class="card mi-admin-card mb-3"><div class="card-header"><h2><?= htmlspecialchars(t('v.capture_method')) ?></h2></div><div class="card-body mi-status-list">
            <?php foreach(($report['capture_methods'] ?? []) as $method=>$row): ?><div><span><?= htmlspecialchars($captureLabels[$method] ?? $method) ?><?php if($method==='fixture'): ?><small><?= htmlspecialchars(t('v.not_live_evidence')) ?></small><?php endif; ?></span><strong><?= (int)$row['candidates'] ?></strong><small><?= htmlspecialchars(t('v.reviewed')) ?> <?= (int)$row['reviewed'] ?> / A <?= (int)$row['approved'] ?> / R <?= (int)$row['rejected'] ?> / X <?= (int)$row['excluded'] ?> / C <?= (int)$row['corrected'] ?></small></div><?php endforeach; ?>
        </div></div>
    </div>
</div>
