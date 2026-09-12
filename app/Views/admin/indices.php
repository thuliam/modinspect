<div class="mi-page-header">
    <div>
        <span class="mi-breadcrumb">Admin / Market Data</span>
        <h1>Price Index</h1>
        <p>Operator view of snapshot readiness. Calculation hashes remain available only in advanced provenance workflows.</p>
    </div>
    <button class="btn btn-outline-secondary disabled-control" type="button" disabled><i class="fas fa-sync-alt mr-2"></i>Recalculate via CLI</button>
</div>

<div class="card mi-table-card">
    <div class="card-header">
        <div class="mi-table-toolbar mb-0">
            <h2>Current Snapshots</h2>
            <input class="form-control mi-table-search js-table-filter" data-target="#indices-table" placeholder="Search product or confidence">
        </div>
    </div>
    <div class="table-responsive">
        <table id="indices-table" class="table table-hover mi-admin-table mb-0">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Snapshot</th>
                    <th>Price Type</th>
                    <th>Market Range</th>
                    <th>Samples</th>
                    <th>Confidence</th>
                    <th>Freshness</th>
                    <th>Provenance</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($products as $p): ?>
                <?php
                $hasSnapshot=!empty($p['median']) && (int)($p['valid_sample_size'] ?? 0)>0;
                $filter=strtolower(implode(' ',[$p['full_name'] ?? '',$p['confidence_label'] ?? '',$p['provenance_status'] ?? '']));
                ?>
                <tr data-filter-row="<?= htmlspecialchars($filter) ?>">
                    <td><span class="mi-row-title"><?= htmlspecialchars((string)$p['full_name']) ?></span><span class="mi-row-subtitle"><?= htmlspecialchars((string)($p['category'] ?? '-')) ?> / <?= htmlspecialchars((string)($p['brand'] ?? '-')) ?></span></td>
                    <td><?= $hasSnapshot ? '#'.(int)$p['snapshot_id'] : 'No usable snapshot' ?><small><?= htmlspecialchars((string)($p['last_calculated_at'] ?? '-')) ?></small></td>
                    <td><?= htmlspecialchars((string)($p['price_type'] ?? '-')) ?></td>
                    <td><?= $hasSnapshot ? '฿'.number_format((float)$p['q1']).' to ฿'.number_format((float)$p['q3']) : 'Insufficient reviewed REAL data' ?></td>
                    <td><?= (int)($p['valid_sample_size'] ?? 0) ?><small>Included <?= (int)($p['provenance_included_count'] ?? 0) ?> / Excluded <?= (int)($p['provenance_excluded_count'] ?? 0) ?></small></td>
                    <td><span class="badge badge-light"><?= htmlspecialchars(strtoupper((string)($p['confidence_label'] ?? 'insufficient'))) ?></span><small>Score <?= number_format((float)($p['confidence_score'] ?? 0),2) ?></small></td>
                    <td><?= number_format((float)($p['fresh_sample_ratio'] ?? 0)*100,1) ?>%</td>
                    <td><span class="badge badge-light"><?= htmlspecialchars((string)($p['provenance_status'] ?? 'legacy_unavailable')) ?></span><small><?= htmlspecialchars((string)($p['formula_version'] ?? 'legacy-unversioned')) ?> / <?= htmlspecialchars((string)($p['cohort_version'] ?? 'legacy-unversioned')) ?></small></td>
                </tr>
            <?php endforeach; ?>
            <?php if(!$products): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No price index rows found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
