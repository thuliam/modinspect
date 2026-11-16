<?php $token = htmlspecialchars(\App\Core\Csrf::token()); ?>
<?= mi_page_header($base, ['Market Data', 'Owner intake'], 'Owner intake', 'Look before you write. This screen only reads: it shows the approval plan for pending owner-text rows and dry-runs a sanitised file. Importing and applying approvals stay on the command line in this version.', '') ?>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form class="card mi-admin-card mb-3" method="post" action="<?= $base ?>/admin/intake/dry-run" enctype="multipart/form-data">
    <input type="hidden" name="_token" value="<?= $token ?>">
    <div class="card-header"><h2>Dry run a sanitised file</h2></div>
    <div class="card-body">
        <p class="text-muted">Use the sanitised research CSV only (no seller names, phones or links). The file is read, the result is shown as counts, and the file is deleted again. Nothing is written to the database.</p>
        <div class="form-row align-items-end">
            <div class="form-group col-md-5"><label>CSV file (up to 5 MB)<input class="form-control-file" type="file" name="file" accept=".csv,text/csv" required></label></div>
            <div class="form-group col-md-3"><label>Category<select class="form-control" name="category"><option value="gpu">GPU</option><option value="cpu">CPU</option><option value="spec">RAM / SSD / PSU (spec classes)</option></select></label></div>
            <div class="form-group col-md-2"><button class="btn btn-primary btn-block" type="submit">Dry run</button></div>
        </div>
    </div>
</form>

<?php if ($result): $imp = $result['import']; ?>
<div class="card mi-admin-card mb-3">
    <div class="card-header"><h2>Dry run result</h2></div>
    <div class="card-body">
        <p class="mb-2"><span class="badge badge-success"><?= (int) $result['prepared'] ?> rows pass the intake rules</span>
            <span class="badge badge-secondary"><?= (int) $result['already_present'] ?> already stored</span>
            <span class="badge badge-primary"><?= (int) $result['new'] ?> new</span></p>
        <p class="mb-2">Importer dry run on the new rows: <strong><?= (int) $imp['valid_rows'] ?></strong> valid, <strong><?= (int) $imp['invalid_rows'] ?></strong> invalid, <strong><?= (int) $imp['duplicate_rows'] ?></strong> duplicate.</p>
        <?php if ($result['skipped']): ?><p class="mb-2 text-muted">Skipped before import: <?php $parts = []; foreach ($result['skipped'] as $why => $n) { $parts[] = htmlspecialchars((string) $why) . ' ' . (int) $n; } echo implode(' · ', $parts); ?></p><?php endif; ?>
        <?php if ($result['row_errors']): ?><p class="mb-2 text-muted">Importer row errors: <?php $parts = []; foreach ($result['row_errors'] as $code => $n) { $parts[] = htmlspecialchars((string) $code) . ' ' . (int) $n; } echo implode(' · ', $parts); ?></p><?php endif; ?>
        <p class="mb-0 text-muted">To import for real, run <code>php cli/import_market_evidence.php --file=… --dataset=owner_text</code> on the prepared file (see <code>docs/OPERATOR_GUIDE_TH.md</code>).</p>
    </div>
</div>
<?php endif; ?>

<div class="card mi-admin-card mi-table-card">
    <div class="card-header"><h2>Approval plan for pending owner-text rows</h2></div>
    <div class="card-body pb-0">
        <p class="text-muted">A row below 60% or above 160% of the product median (pending plus already approved rows) is excluded; a product needs 3 usable rows; a sample with two price groups is never approved automatically. Applying the plan: <code>php cli/owner_intake_approve.php --actor=&lt;your user id&gt; --apply</code>.</p>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th>Product</th><th class="text-right">Would approve</th><th class="text-right">Excluded</th><th class="text-right">Median</th><th class="text-right">Already approved</th></tr></thead>
            <tbody>
            <?php foreach ($plan as $p): ?>
                <tr><td><?= htmlspecialchars($p['name']) ?></td><td class="text-right"><?= count($p['keep']) ?></td><td class="text-right"><?= count($p['drop']) ?></td><td class="text-right">฿<?= number_format($p['median']) ?></td><td class="text-right"><?= (int) $p['already'] ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$plan): ?><tr><td colspan="5" class="text-muted">Nothing would be approved right now.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-body">
        <h3 class="h6">Staying pending (<?= count($leftPending) ?>)</h3>
        <?php if (!$leftPending): ?><p class="mb-0 text-muted">None.</p><?php else: ?>
        <ul class="mb-0">
            <?php foreach ($leftPending as [$name, $usable, $why]): ?>
                <li><?= htmlspecialchars($name) ?> <span class="text-muted">· <?= (int) $usable ?> usable<?= $why === 'bimodal' ? ' · two price groups: needs a person' : ' · fewer than 3 usable rows' ?></span></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
