<?php
$short=function(mixed $value,int $limit=120): string {
    $text=trim((string)$value);
    return strlen($text)>$limit ? substr($text,0,$limit-3).'...' : $text;
};
?>
<div class="mi-page-header">
    <div>
        <span class="mi-breadcrumb">Admin / Catalog</span>
        <h1>Products</h1>
        <p>Canonical Product Master records used by resolution, review, and price snapshots.</p>
    </div>
    <button class="btn btn-outline-secondary disabled-control" type="button" disabled><i class="fas fa-plus mr-2"></i>Add Product</button>
</div>

<div class="card mi-table-card">
    <div class="card-header">
        <div class="mi-table-toolbar mb-0">
            <h2>Product Master</h2>
            <input class="form-control mi-table-search js-table-filter" data-target="#products-table" placeholder="Search products, brands, categories">
        </div>
    </div>
    <div class="table-responsive">
        <table id="products-table" class="table table-hover mi-admin-table mb-0">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Brand</th>
                    <th>Category</th>
                    <th>Key Specification</th>
                    <th>Market Data</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($products as $p): ?>
                <?php
                $marketStatus=(int)($p['approved_observation_count'] ?? 0)>0 ? 'Approved observations' : ((int)($p['pending_observation_count'] ?? 0)>0 ? 'Pending review' : 'No accepted market data');
                $filter=strtolower(implode(' ',[$p['full_name'] ?? '',$p['brand'] ?? '',$p['category'] ?? '',$p['slug'] ?? '',$marketStatus]));
                ?>
                <tr data-filter-row="<?= htmlspecialchars($filter) ?>">
                    <td>
                        <span class="mi-row-title"><?= htmlspecialchars((string)$p['full_name']) ?></span>
                        <span class="mi-row-subtitle"><?= htmlspecialchars((string)($p['slug'] ?? '-')) ?></span>
                    </td>
                    <td><?= htmlspecialchars((string)$p['brand']) ?></td>
                    <td><?= htmlspecialchars((string)$p['category']) ?></td>
                    <td><?= htmlspecialchars($short($p['spec_summary'] ?? $p['generation'] ?? $p['model_name'] ?? '-',90)) ?></td>
                    <td>
                        <span class="mi-row-title"><?= htmlspecialchars($marketStatus) ?></span>
                        <span class="mi-row-subtitle">Pending <?= (int)($p['pending_observation_count'] ?? 0) ?> / Approved <?= (int)($p['approved_observation_count'] ?? 0) ?></span>
                    </td>
                    <td><span class="badge <?= (int)$p['is_active']===1 ? 'badge-success' : 'badge-secondary' ?>"><?= (int)$p['is_active']===1 ? 'Active' : 'Inactive' ?></span></td>
                    <td><button class="btn btn-sm btn-outline-secondary disabled-control" type="button" disabled>View</button></td>
                </tr>
            <?php endforeach; ?>
            <?php if(!$products): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No Product Master records found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
