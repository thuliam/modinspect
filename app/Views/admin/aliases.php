<div class="mi-page-header">
    <div>
        <span class="mi-breadcrumb">Admin / Catalog</span>
        <h1>Aliases</h1>
        <p>Readable mapping from observed market naming to canonical Product Master records.</p>
    </div>
    <button class="btn btn-outline-secondary disabled-control" type="button" disabled><i class="fas fa-plus mr-2"></i>Add Alias</button>
</div>

<div class="card mi-table-card">
    <div class="card-header">
        <div class="mi-table-toolbar mb-0">
            <h2>Alias Mapping</h2>
            <input class="form-control mi-table-search js-table-filter" data-target="#aliases-table" placeholder="Search alias or product">
        </div>
    </div>
    <div class="table-responsive">
        <table id="aliases-table" class="table table-hover mi-admin-table mb-0">
            <thead>
                <tr>
                    <th>Alias</th>
                    <th>Canonical Product</th>
                    <th>Normalized</th>
                    <th>Confidence</th>
                    <th>Source / Context</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($aliases as $a): ?>
                <?php $filter=strtolower(implode(' ',[$a['alias_text'] ?? '',$a['full_name'] ?? '',$a['normalized_alias'] ?? '',$a['source_note'] ?? ''])); ?>
                <tr data-filter-row="<?= htmlspecialchars($filter) ?>">
                    <td><span class="mi-row-title"><?= htmlspecialchars((string)$a['alias_text']) ?></span></td>
                    <td><?= htmlspecialchars((string)$a['full_name']) ?></td>
                    <td><code><?= htmlspecialchars((string)$a['normalized_alias']) ?></code></td>
                    <td><?= number_format((float)$a['confidence']) ?>%</td>
                    <td><?= htmlspecialchars((string)($a['source_note'] ?? 'Manual')) ?></td>
                    <td><span class="badge badge-success">Active</span></td>
                </tr>
            <?php endforeach; ?>
            <?php if(!$aliases): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No aliases found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
