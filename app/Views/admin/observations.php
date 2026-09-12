<?php
$statusOptions=[''=>'All statuses','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','excluded'=>'Excluded'];
$datasetClass=function(string $dataset): string { return 'mi-dataset-'.strtolower($dataset); };
$statusClass=function(string $status): string { return 'mi-status-badge mi-status-'.strtolower($status); };
?>
<div class="mi-page-header">
    <div>
        <span class="mi-breadcrumb">Admin / Market Data</span>
        <h1>Observations</h1>
        <p>Operational observation view. Raw provenance and review decisions stay behind detail workflows.</p>
    </div>
    <a class="btn btn-primary" href="<?= $base ?>/admin/review-queue"><i class="fas fa-clipboard-check mr-2"></i>Review Queue</a>
</div>

<form class="card mi-admin-card mb-3" method="get" action="<?= $base ?>/admin/price-observations">
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-3 mb-md-0">
                <label for="observation-status">Status</label>
                <select class="form-control" id="observation-status" name="status">
                    <?php foreach($statusOptions as $value=>$label): ?>
                        <option value="<?= htmlspecialchars($value) ?>" <?= (string)$status===$value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-6 mb-md-0">
                <label for="observation-search">Search</label>
                <input class="form-control" id="observation-search" name="q" value="<?= htmlspecialchars((string)$q) ?>" placeholder="Product or listing title">
            </div>
            <div class="form-group col-md-3 mb-0">
                <button class="btn btn-primary btn-block" type="submit"><i class="fas fa-search mr-2"></i>Filter</button>
            </div>
        </div>
    </div>
</form>

<div class="card mi-table-card">
    <div class="card-header"><h2>Latest Observations</h2></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead>
                <tr>
                    <th>Observation</th>
                    <th>Product</th>
                    <th>Dataset</th>
                    <th>Price Type</th>
                    <th>Price</th>
                    <th>Source</th>
                    <th>Review Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($observations as $o): ?>
                <tr>
                    <td>#<?= (int)$o['id'] ?><small>Raw #<?= (int)($o['raw_observation_id'] ?? 0) ?></small></td>
                    <td>
                        <span class="mi-row-title"><?= htmlspecialchars((string)$o['full_name']) ?></span>
                        <span class="mi-row-subtitle"><?= htmlspecialchars((string)($o['raw_title'] ?? '-')) ?></span>
                    </td>
                    <td><span class="<?= htmlspecialchars($datasetClass((string)$o['dataset_label'])) ?>"><?= htmlspecialchars((string)$o['dataset_label']) ?></span></td>
                    <td><?= htmlspecialchars((string)($o['price_type'] ?? 'asking')) ?></td>
                    <td><strong>฿<?= number_format((float)$o['display_price']) ?></strong></td>
                    <td><?= htmlspecialchars((string)($o['source_name'] ?? '-')) ?><small><?= htmlspecialchars((string)($o['source_key'] ?? '')) ?></small></td>
                    <td><span class="<?= htmlspecialchars($statusClass((string)$o['verified_status'])) ?>"><?= htmlspecialchars((string)$o['verified_status']) ?></span></td>
                    <td><?= htmlspecialchars((string)($o['observed_at'] ?? $o['created_at'] ?? '-')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if(!$observations): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No observations match the current filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
