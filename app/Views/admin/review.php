<?php
$filters=$filters ?? [];
$summary=$summary ?? ['by_dataset'=>[],'status_totals'=>[]];
$batchOptions=$batch_options ?? [];
$selected=function(string $name,string $value) use ($filters): string { return (string)($filters[$name] ?? '') === $value ? 'selected' : ''; };
$realPending=(int)($summary['by_dataset']['REAL']['pending'] ?? 0);
$mockPending=(int)($summary['by_dataset']['MOCK_TEST']['pending'] ?? 0);
$approved=(int)($summary['status_totals']['approved'] ?? 0);
$rejected=(int)($summary['status_totals']['rejected'] ?? 0);
$excluded=(int)($summary['status_totals']['excluded'] ?? 0);
$activeBatch=null;
foreach($batchOptions as $batchOption){ if((string)($filters['run_id'] ?? '') === (string)($batchOption['value'] ?? '')) $activeBatch=$batchOption; }
?>
<?php ob_start(); ?>Review Queue<?php $dxT = ob_get_clean(); ob_start(); ?>Human review console defaults to REAL pending evidence. MOCK_TEST records remain accessible through filters.<?php $dxD = ob_get_clean(); ob_start(); ?><a class="btn btn-outline-primary" href="<?= $base ?>/admin/review-analytics"><i class="fas fa-chart-bar mr-2"></i>Review Analytics</a><?php $dxA = ob_get_clean(); ?>
<?= mi_page_header($base, array (  0 => 'Market Data',), $dxT, $dxD, $dxA) ?>

<div class="row mi-metric-row">
    <div class="col-xl col-md-4 col-sm-6"><a class="card mi-metric-card gradient-warning" href="<?= $base ?>/admin/review-queue?dataset=REAL&status=pending"><span>REAL Pending</span><strong><?= $realPending ?></strong><small>Calibration review queue</small></a></div>
    <div class="col-xl col-md-4 col-sm-6"><a class="card mi-metric-card gradient-success" href="<?= $base ?>/admin/review-queue?dataset=MOCK_TEST&status=pending"><span>MOCK/Test Pending</span><strong><?= $mockPending ?></strong><small>Kept separate from REAL</small></a></div>
    <?php $otherPending = (int) ($summary['by_dataset']['UNKNOWN']['pending'] ?? 0); if ($otherPending > 0): // LS-21: candidates from sources that are neither REAL nor MOCK/TEST (for example a community sold-price report) are hidden by the default REAL filter ?>
    <div class="col-xl col-md-4 col-sm-6"><a class="card mi-metric-card gradient-primary" href="<?= $base ?>/admin/review-queue?dataset=UNKNOWN&status=pending"><span>Other sources pending</span><strong><?= $otherPending ?></strong><small>Community reports and other unclassified sources</small></a></div>
    <?php endif; ?>
    <div class="col-xl col-md-4 col-sm-6"><div class="card mi-metric-card gradient-info"><span>Approved</span><strong><?= $approved ?></strong><small>All datasets</small></div></div>
    <div class="col-xl col-md-4 col-sm-6"><div class="card mi-metric-card gradient-purple"><span>Rejected</span><strong><?= $rejected ?></strong><small>All datasets</small></div></div>
    <div class="col-xl col-md-4 col-sm-6"><div class="card mi-metric-card gradient-ocean"><span>Excluded</span><strong><?= $excluded ?></strong><small>All datasets</small></div></div>
</div>

<form id="review-filters" class="review-filter-panel js-datatable-filters" data-target="#review-table" method="get" action="<?= $base ?>/admin/review-queue">
    <label>Dataset
        <select name="dataset">
            <option value="REAL" <?= $selected('dataset','REAL') ?>>REAL</option>
            <option value="MOCK_TEST" <?= $selected('dataset','MOCK_TEST') ?>>MOCK_TEST</option>
            <option value="UNKNOWN" <?= $selected('dataset','UNKNOWN') ?>>UNKNOWN</option>
            <option value="ALL" <?= $selected('dataset','ALL') ?>>All datasets</option>
        </select>
    </label>
    <label>Status
        <select name="status">
            <?php foreach(['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','excluded'=>'Excluded','all'=>'All statuses'] as $value=>$label): ?><option value="<?= $value ?>" <?= $selected('status',$value) ?>><?= $label ?></option><?php endforeach; ?>
        </select>
    </label>
    <label>Validation
        <select name="lane"><option value="">All lanes</option><?php foreach(['amber'=>'Amber','green'=>'Green','red'=>'Red'] as $value=>$label): ?><option value="<?= $value ?>" <?= $selected('lane',$value) ?>><?= $label ?></option><?php endforeach; ?></select>
    </label>
    <label>Provenance
        <select name="provenance"><option value="">All provenance</option><?php foreach(['LISTING_LEVEL'=>'Listing level','SEARCH_RESULT_LEVEL'=>'Search result level','GENERIC_SOURCE'=>'Generic source'] as $value=>$label): ?><option value="<?= $value ?>" <?= $selected('provenance',$value) ?>><?= $label ?></option><?php endforeach; ?></select>
    </label>
    <label>Batch / Run
        <select name="run_id">
            <option value="">All batches/runs</option>
            <?php foreach($batchOptions as $batchOption): ?>
                <?php $batchValue=(string)($batchOption['value'] ?? ''); $batchRun=(string)($batchOption['run_id'] ?? ''); $batchText=(string)($batchOption['label'] ?? 'Review Batch').' - '.(int)($batchOption['count'] ?? 0).($batchRun!=='' ? ' / '.$batchRun : ''); ?>
                <option value="<?= htmlspecialchars($batchValue) ?>" <?= $selected('run_id',$batchValue) ?>><?= htmlspecialchars($batchText) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Product
        <select name="product_id"><option value="">All products</option><?php foreach($products as $product): ?><option value="<?= (int)$product['id'] ?>" <?= (int)($filters['product_id'] ?? 0)===(int)$product['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$product['full_name']) ?></option><?php endforeach; ?></select>
    </label>
    <label>Source
        <select name="source_id"><option value="">All sources</option><?php foreach($sources as $source): ?><option value="<?= (int)$source['id'] ?>" <?= (int)($filters['source_id'] ?? 0)===(int)$source['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$source['name']) ?></option><?php endforeach; ?></select>
    </label>
    <div class="review-filter-actions">
        <button class="btn btn-primary" type="submit">Apply</button>
        <a class="btn btn-outline-secondary" href="<?= $base ?>/admin/review-queue">Reset</a>
    </div>
</form>

<?php if($activeBatch): ?>
    <div class="review-range">
        <span><?= htmlspecialchars((string)($activeBatch['label'] ?? 'Review Batch')) ?></span>
        <span><?= (int)($activeBatch['count'] ?? 0) ?> records<?= !empty($activeBatch['run_id']) ? ' / '.htmlspecialchars((string)$activeBatch['run_id']) : '' ?></span>
    </div>
<?php endif; ?>

<div class="card mi-table-card">
    <div class="card-header"><h2>Review Records</h2></div>
    <div class="table-responsive">
        <table id="review-table" class="table mi-admin-table mb-0 js-server-data-table mi-review-dt" data-source-url="<?= $base ?>/admin/review-queue/data" data-filters="#review-filters" data-page-length="25">
            <thead><tr><th>Review Item</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>
