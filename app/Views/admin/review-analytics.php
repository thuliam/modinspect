<section class="container page admin-page"><?php require __DIR__.'/_nav.php'; ?>
<span class="eyebrow">CALIBRATION · REVIEW QUALITY</span>
<h1>Review Analytics</h1>
<form class="card filter-strip" method="get" action="<?= $base ?>/admin/review-analytics">
    <select name="category"><option value="">ทุกหมวด</option><?php foreach(['cpu'=>'CPU','gpu'=>'GPU','ram'=>'RAM','storage'=>'Storage'] as $v=>$l): ?><option value="<?= $v ?>" <?= ($report['filters']['category']??'')===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select>
    <select name="lane"><option value="">ทุก lane</option><?php foreach(['green','amber','red'] as $lane): ?><option value="<?= $lane ?>" <?= ($report['filters']['lane']??'')===$lane?'selected':'' ?>><?= strtoupper($lane) ?></option><?php endforeach; ?></select>
    <select name="outcome"><option value="">ทุกสถานะ</option><?php foreach(['pending','approved','rejected','excluded'] as $outcome): ?><option value="<?= $outcome ?>" <?= ($report['filters']['outcome']??'')===$outcome?'selected':'' ?>><?= htmlspecialchars($outcome) ?></option><?php endforeach; ?></select>
    <input type="date" name="from" value="<?= htmlspecialchars($report['filters']['from'] ?? '') ?>">
    <input type="date" name="to" value="<?= htmlspecialchars($report['filters']['to'] ?? '') ?>">
    <button>กรอง</button>
</form>
<div class="notice">Dataset: <?= htmlspecialchars($report['dataset_label']) ?> · Calibration: <?= htmlspecialchars($report['calibration_status']) ?> · MOCK/TEST metrics are not live-market proof.</div>
<div class="kpi-grid">
    <div class="card"><span>Sample</span><b><?= (int)$report['sample_size'] ?></b><small>Mock/Test <?= (int)$report['mock_test_records'] ?> · Real <?= (int)$report['real_records'] ?></small></div>
    <div class="card"><span>Backlog</span><b><?= (int)$report['lifecycle']['review_backlog'] ?></b><small>Oldest <?= $report['lifecycle']['oldest_pending_age_hours']===null?'-':number_format((float)$report['lifecycle']['oldest_pending_age_hours'],1).'h' ?></small></div>
    <div class="card"><span>Reviewed</span><b><?= (int)$report['lifecycle']['reviewed_count'] ?></b><small>Decision <?= number_format((float)$report['lifecycle']['decision_rate'],1) ?>%</small></div>
    <div class="card"><span>Correction rate</span><b><?= number_format((float)$report['lifecycle']['correction_rate'],1) ?>%</b><small><?= (int)$report['lifecycle']['corrected_reviews'] ?> corrected</small></div>
    <div class="card"><span>Clean Green precision</span><b><?= $report['green_precision']['clean_green_precision']===null?'-':number_format((float)$report['green_precision']['clean_green_precision'],1).'%' ?></b><small>Corrected Green approved <?= (int)$report['green_precision']['approved_after_correction'] ?></small></div>
    <div class="card"><span>Live valid yield</span><b><?= $report['valid_observation_yield']['yield_percent']===null?'-':number_format((float)$report['valid_observation_yield']['yield_percent'],1).'%' ?></b><small>Mock/test excluded</small></div>
</div>
<div class="admin-grid">
    <div class="card"><h2>Lane outcomes</h2><?php foreach($report['lanes'] as $lane=>$row): ?><div class="health-row"><span><?= strtoupper(htmlspecialchars($lane)) ?></span><b><?= (int)$row['total'] ?></b><small>A <?= (int)$row['approved'] ?> · R <?= (int)$row['rejected'] ?> · X <?= (int)$row['excluded'] ?> · C <?= (int)$row['corrected'] ?></small></div><?php endforeach; ?></div>
    <div class="card"><h2>Top quality flags</h2><?php foreach($report['quality_flags'] as $flag=>$row): ?><div class="health-row"><span><?= htmlspecialchars($flag) ?></span><b><?= (int)$row['count'] ?></b><small>A <?= (int)$row['approved'] ?> · R <?= (int)$row['rejected'] ?> · C <?= (int)$row['corrected'] ?></small></div><?php endforeach; ?><?php if(!$report['quality_flags']): ?><div class="empty">ยังไม่มี quality flags</div><?php endif; ?></div>
    <div class="card"><h2>Corrected fields</h2><?php foreach($report['corrected_fields'] as $field=>$count): ?><div class="health-row"><span><?= htmlspecialchars($field) ?></span><b><?= (int)$count ?></b></div><?php endforeach; ?><?php if(!$report['corrected_fields']): ?><div class="empty">ยังไม่มี corrections</div><?php endif; ?></div>
    <div class="card"><h2>Source quality</h2><?php foreach(array_slice($report['source_quality'],0,8) as $source): ?><div class="health-row"><span><?= htmlspecialchars($source['source_name']) ?></span><b><?= $source['valid_observation_yield']===null?'-':number_format((float)$source['valid_observation_yield'],1).'%' ?></b><small><?= htmlspecialchars(strtoupper($source['dataset_type'])) ?> · reviewed <?= (int)$source['reviewed'] ?></small></div><?php endforeach; ?><?php if(!$report['source_quality']): ?><div class="empty">ยังไม่มี source quality data</div><?php endif; ?></div>
</div>
</section>
