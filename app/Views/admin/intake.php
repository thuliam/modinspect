<?php $token = htmlspecialchars(\App\Core\Csrf::token()); ?>
<?= mi_page_header($base, ['Market Data', 'Owner intake'], htmlspecialchars(t('v.owner_intake')), htmlspecialchars(t('v.look_before_you_write_this_screen_on')), '') ?>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form class="card mi-admin-card mb-3" method="post" action="<?= $base ?>/admin/intake/dry-run" enctype="multipart/form-data">
    <input type="hidden" name="_token" value="<?= $token ?>">
    <div class="card-header"><h2><?= htmlspecialchars(t('v.dry_run_a_sanitised_file')) ?></h2></div>
    <div class="card-body">
        <p class="text-muted"><?= htmlspecialchars(t('v.use_the_sanitised_research_csv_only')) ?></p>
        <div class="form-row align-items-end">
            <div class="form-group col-md-5"><label><?= htmlspecialchars(t('v.csv_file_up_to_5_mb')) ?><input class="form-control-file" type="file" name="file" accept=".csv,text/csv" required></label></div>
            <div class="form-group col-md-3"><label><?= htmlspecialchars(t('v.category')) ?><select class="form-control" name="category"><option value="gpu"><?= htmlspecialchars(t('v.gpu_2')) ?></option><option value="cpu"><?= htmlspecialchars(t('v.cpu')) ?></option><option value="spec"><?= htmlspecialchars(t('v.ram_ssd_psu_spec_classes')) ?></option></select></label></div>
            <div class="form-group col-md-2"><button class="btn btn-primary btn-block" type="submit"><?= htmlspecialchars(t('v.dry_run')) ?></button></div>
        </div>
    </div>
</form>

<?php if ($result): $imp = $result['import']; ?>
<div class="card mi-admin-card mb-3">
    <div class="card-header"><h2><?= htmlspecialchars(t('v.dry_run_result')) ?></h2></div>
    <div class="card-body">
        <p class="mb-2"><span class="badge badge-success"><?= (int) $result['prepared'] ?> <?= htmlspecialchars(t('v.rows_pass_the_intake_rules')) ?></span>
            <span class="badge badge-secondary"><?= (int) $result['already_present'] ?> <?= htmlspecialchars(t('v.already_stored')) ?></span>
            <span class="badge badge-primary"><?= (int) $result['new'] ?> <?= htmlspecialchars(t('v.new_2')) ?></span></p>
        <p class="mb-2"><?= htmlspecialchars(t('v.importer_dry_run_on_the_new_rows')) ?> <strong><?= (int) $imp['valid_rows'] ?></strong> <?= htmlspecialchars(t('v.valid')) ?> <strong><?= (int) $imp['invalid_rows'] ?></strong> <?= htmlspecialchars(t('v.invalid')) ?> <strong><?= (int) $imp['duplicate_rows'] ?></strong> <?= htmlspecialchars(t('v.duplicate')) ?></p>
        <?php if ($result['skipped']): ?><p class="mb-2 text-muted"><?= htmlspecialchars(t('v.skipped_before_import')) ?> <?php $parts = []; foreach ($result['skipped'] as $why => $n) { $parts[] = htmlspecialchars((string) $why) . ' ' . (int) $n; } echo implode(' · ', $parts); ?></p><?php endif; ?>
        <?php if ($result['row_errors']): ?><p class="mb-2 text-muted"><?= htmlspecialchars(t('v.importer_row_errors')) ?> <?php $parts = []; foreach ($result['row_errors'] as $code => $n) { $parts[] = htmlspecialchars((string) $code) . ' ' . (int) $n; } echo implode(' · ', $parts); ?></p><?php endif; ?>
        <p class="mb-0 text-muted"><?= htmlspecialchars(t('misc.intake_import_pre')) ?> <code>php cli/import_market_evidence.php --file=… --dataset=owner_text</code> <?= htmlspecialchars(t('misc.intake_import_mid')) ?> <code>docs/OPERATOR_GUIDE_TH.md</code>).</p>
    </div>
</div>
<?php endif; ?>

<div class="card mi-admin-card mi-table-card">
    <div class="card-header"><h2><?= htmlspecialchars(t('v.approval_plan_for_pending_owner_text')) ?></h2></div>
    <div class="card-body pb-0">
        <p class="text-muted"><?= htmlspecialchars(t('misc.intake_rule')) ?> <code>php cli/owner_intake_approve.php --actor=&lt;your user id&gt; --apply</code>.</p>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= htmlspecialchars(t('v.product')) ?></th><th class="text-right"><?= htmlspecialchars(t('v.would_approve')) ?></th><th class="text-right"><?= htmlspecialchars(t('v.excluded_2')) ?></th><th class="text-right"><?= htmlspecialchars(t('v.median')) ?></th><th class="text-right"><?= htmlspecialchars(t('v.already_approved')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($plan as $p): ?>
                <tr><td><?= htmlspecialchars($p['name']) ?></td><td class="text-right"><?= count($p['keep']) ?></td><td class="text-right"><?= count($p['drop']) ?></td><td class="text-right">฿<?= number_format($p['median']) ?></td><td class="text-right"><?= (int) $p['already'] ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$plan): ?><tr><td colspan="5" class="text-muted"><?= htmlspecialchars(t('v.nothing_would_be_approved_right_now')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-body">
        <h3 class="h6"><?= htmlspecialchars(t('v.staying_pending')) ?><?= count($leftPending) ?>)</h3>
        <?php if (!$leftPending): ?><p class="mb-0 text-muted"><?= htmlspecialchars(t('v.none')) ?></p><?php else: ?>
        <ul class="mb-0">
            <?php foreach ($leftPending as [$name, $usable, $why]): ?>
                <li><?= htmlspecialchars($name) ?> <span class="text-muted">· <?= (int) $usable ?> <?= htmlspecialchars(t('v.usable')) ?><?= $why === 'bimodal' ? t('v.two_price_groups_needs_a_person') : t('v.fewer_than_3_usable_rows') ?></span></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
