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
<?php ob_start(); ?><?= htmlspecialchars(t('v.review_queue')) ?><?php $dxT = ob_get_clean(); ob_start(); ?><?= htmlspecialchars(t('v.human_review_console_defaults_to_rea')) ?><?php $dxD = ob_get_clean(); ob_start(); ?><a class="btn btn-outline-primary" href="<?= $base ?>/admin/review-analytics"><i class="fas fa-chart-bar mr-2"></i><?= htmlspecialchars(t('v.review_analytics')) ?></a><a class="btn btn-outline-secondary ml-2" href="<?= $base ?>/admin/review-queue/export"><i class="fas fa-file-csv mr-2"></i><?= htmlspecialchars(t('review.export')) ?></a><?php $dxA = ob_get_clean(); ?>
<?= mi_page_header($base, array (  0 => 'Market Data',), $dxT, $dxD, $dxA) ?>

<div class="row mi-metric-row">
    <div class="col-xl col-md-4 col-sm-6"><a class="card mi-metric-card gradient-warning" href="<?= $base ?>/admin/review-queue?dataset=REAL&status=pending"><span><?= htmlspecialchars(t('v.real_pending')) ?></span><strong><?= $realPending ?></strong><small><?= htmlspecialchars(t('v.calibration_review_queue')) ?></small></a></div>
    <div class="col-xl col-md-4 col-sm-6"><a class="card mi-metric-card gradient-success" href="<?= $base ?>/admin/review-queue?dataset=MOCK_TEST&status=pending"><span><?= htmlspecialchars(t('v.mock_test_pending')) ?></span><strong><?= $mockPending ?></strong><small><?= htmlspecialchars(t('v.kept_separate_from_real')) ?></small></a></div>
    <?php $otherPending = (int) ($summary['by_dataset']['UNKNOWN']['pending'] ?? 0); if ($otherPending > 0): // LS-21: candidates from sources that are neither REAL nor MOCK/TEST (for example a community sold-price report) are hidden by the default REAL filter ?>
    <div class="col-xl col-md-4 col-sm-6"><a class="card mi-metric-card gradient-primary" href="<?= $base ?>/admin/review-queue?dataset=UNKNOWN&status=pending"><span><?= htmlspecialchars(t('v.other_sources_pending')) ?></span><strong><?= $otherPending ?></strong><small><?= htmlspecialchars(t('v.community_reports_and_other_unclassi')) ?></small></a></div>
    <?php endif; ?>
    <div class="col-xl col-md-4 col-sm-6"><div class="card mi-metric-card gradient-info"><span><?= htmlspecialchars(t('v.approved')) ?></span><strong><?= $approved ?></strong><small><?= htmlspecialchars(t('v.all_datasets')) ?></small></div></div>
    <div class="col-xl col-md-4 col-sm-6"><div class="card mi-metric-card gradient-purple"><span><?= htmlspecialchars(t('v.rejected_2')) ?></span><strong><?= $rejected ?></strong><small><?= htmlspecialchars(t('v.all_datasets')) ?></small></div></div>
    <div class="col-xl col-md-4 col-sm-6"><div class="card mi-metric-card gradient-ocean"><span><?= htmlspecialchars(t('v.excluded_2')) ?></span><strong><?= $excluded ?></strong><small><?= htmlspecialchars(t('v.all_datasets')) ?></small></div></div>
</div>

<form id="review-filters" class="review-filter-panel js-datatable-filters" data-target="#review-table" method="get" action="<?= $base ?>/admin/review-queue">
    <label><?= htmlspecialchars(t('review.impact')) ?>
        <select name="impact">
            <option value="" <?= $selected('impact','') ?>><?= htmlspecialchars(t('review.impact.all')) ?></option>
            <option value="price" <?= $selected('impact','price') ?>><?= htmlspecialchars(t('review.impact.price')) ?></option>
            <option value="first_price" <?= $selected('impact','first_price') ?>><?= htmlspecialchars(t('review.impact.first_price')) ?></option>
        </select>
    </label>
    <label><?= htmlspecialchars(t('v.dataset')) ?>
        <select name="dataset">
            <option value="REAL" <?= $selected('dataset','REAL') ?>><?= htmlspecialchars(t('v.real')) ?></option>
            <option value="MOCK_TEST" <?= $selected('dataset','MOCK_TEST') ?>><?= htmlspecialchars(t('v.mock_test')) ?></option>
            <option value="UNKNOWN" <?= $selected('dataset','UNKNOWN') ?>><?= htmlspecialchars(t('v.unknown')) ?></option>
            <option value="ALL" <?= $selected('dataset','ALL') ?>><?= htmlspecialchars(t('v.all_datasets')) ?></option>
        </select>
    </label>
    <label><?= htmlspecialchars(t('v.status')) ?>
        <select name="status">
            <?php foreach(['pending'=>t('v.pending'),'approved'=>t('v.approved'),'rejected'=>t('v.rejected_2'),'excluded'=>t('v.excluded_2'),'all'=>t('v.all_statuses')] as $value=>$label): ?><option value="<?= $value ?>" <?= $selected('status',$value) ?>><?= $label ?></option><?php endforeach; ?>
        </select>
    </label>
    <label><?= htmlspecialchars(t('v.validation')) ?>
        <select name="lane"><option value=""><?= htmlspecialchars(t('v.all_lanes')) ?></option><?php foreach(['amber'=>t('v.amber_2'),'green'=>t('v.green'),'red'=>t('v.red_2')] as $value=>$label): ?><option value="<?= $value ?>" <?= $selected('lane',$value) ?>><?= $label ?></option><?php endforeach; ?></select>
    </label>
    <label><?= htmlspecialchars(t('v.provenance')) ?>
        <select name="provenance"><option value=""><?= htmlspecialchars(t('v.all_provenance')) ?></option><?php foreach(['LISTING_LEVEL'=>t('v.listing_level'),'SEARCH_RESULT_LEVEL'=>t('v.search_result_level'),'GENERIC_SOURCE'=>t('v.generic_source')] as $value=>$label): ?><option value="<?= $value ?>" <?= $selected('provenance',$value) ?>><?= $label ?></option><?php endforeach; ?></select>
    </label>
    <label><?= htmlspecialchars(t('v.batch_run')) ?>
        <select name="run_id">
            <option value=""><?= htmlspecialchars(t('v.all_batches_runs')) ?></option>
            <?php foreach($batchOptions as $batchOption): ?>
                <?php $batchValue=(string)($batchOption['value'] ?? ''); $batchRun=(string)($batchOption['run_id'] ?? ''); $batchText=(string)($batchOption['label'] ?? t('v.review_batch')).' - '.(int)($batchOption['count'] ?? 0).($batchRun!=='' ? ' / '.$batchRun : ''); ?>
                <option value="<?= htmlspecialchars($batchValue) ?>" <?= $selected('run_id',$batchValue) ?>><?= htmlspecialchars($batchText) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label><?= htmlspecialchars(t('v.product')) ?>
        <select name="product_id"><option value=""><?= htmlspecialchars(t('v.all_products')) ?></option><?php foreach($products as $product): ?><option value="<?= (int)$product['id'] ?>" <?= (int)($filters['product_id'] ?? 0)===(int)$product['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$product['full_name']) ?></option><?php endforeach; ?></select>
    </label>
    <label><?= htmlspecialchars(t('v.source')) ?>
        <select name="source_id"><option value=""><?= htmlspecialchars(t('v.all_sources')) ?></option><?php foreach($sources as $source): ?><option value="<?= (int)$source['id'] ?>" <?= (int)($filters['source_id'] ?? 0)===(int)$source['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$source['name']) ?></option><?php endforeach; ?></select>
    </label>
    <div class="review-filter-actions">
        <button class="btn btn-primary" type="submit"><?= htmlspecialchars(t('v.apply')) ?></button>
        <a class="btn btn-outline-secondary" href="<?= $base ?>/admin/review-queue"><?= htmlspecialchars(t('v.reset')) ?></a>
    </div>
</form>

<?php if($activeBatch): ?>
    <div class="review-range">
        <span><?= htmlspecialchars((string)($activeBatch['label'] ?? t('v.review_batch'))) ?></span>
        <span><?= (int)($activeBatch['count'] ?? 0) ?> <?= htmlspecialchars(t('v.records')) ?><?= !empty($activeBatch['run_id']) ? ' / '.htmlspecialchars((string)$activeBatch['run_id']) : '' ?></span>
    </div>
<?php endif; ?>

<div class="card mi-table-card">
    <div class="card-header"><h2><?= htmlspecialchars(t('v.review_records')) ?></h2></div>
    <div class="table-responsive">
        <table id="review-table" class="table mi-admin-table mb-0 js-server-data-table mi-review-dt" data-source-url="<?= $base ?>/admin/review-queue/data" data-filters="#review-filters" data-page-length="25">
            <thead><tr><th><?= htmlspecialchars(t('v.review_item')) ?></th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>
