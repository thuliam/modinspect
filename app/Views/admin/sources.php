<?php $authz=new App\Services\Auth\AuthorizationService(); $canControl=$authz->can($auth_user ?? null,'sources.control'); ?>
<?php ob_start(); ?><?= htmlspecialchars(t('v.sources')) ?><?php $dxT = ob_get_clean(); ob_start(); ?><?= htmlspecialchars(t('v.source_policy_health_and_incident_co')) ?><?php $dxD = ob_get_clean(); ob_start(); ?><button class="btn btn-outline-secondary disabled-control" type="button" disabled><i class="fas fa-plus mr-2"></i><?= htmlspecialchars(t('v.register_source')) ?></button><?php $dxA = ob_get_clean(); ?>
<?= mi_page_header($base, array (  0 => 'Collection',), $dxT, $dxD, $dxA) ?>

<div class="card mi-admin-card mb-3">
    <div class="card-header"><h2><?= htmlspecialchars(t('v.provider_guardrails')) ?></h2></div>
    <div class="card-body">
        <dl class="mi-definition-grid">
            <dt><?= htmlspecialchars(t('v.adapter')) ?></dt><dd><?= $provider_status['adapter_available'] ? t('st.available') : t('st.missing') ?></dd>
            <dt><?= htmlspecialchars(t('v.provider_enabled')) ?></dt><dd><?= $provider_status['provider_enabled'] ? t('st.yes') : t('st.no') ?></dd>
            <dt><?= htmlspecialchars(t('v.api_key_configured')) ?></dt><dd><?= $provider_status['api_key_configured'] ? t('st.yes') : t('st.no') ?></dd>
            <dt><?= htmlspecialchars(t('v.live_collection')) ?></dt><dd><?= $provider_status['live_collection_enabled'] ? t('st.enabled') : t('st.disabled') ?></dd>
            <dt><?= htmlspecialchars(t('v.paid_calls')) ?></dt><dd><?= $provider_status['paid_provider_calls_enabled'] ? t('st.enabled') : t('st.disabled') ?></dd>
            <dt><?= htmlspecialchars(t('v.blockers')) ?></dt><dd><?= htmlspecialchars(implode(', ', array_map(static fn($b) => \App\Core\Lang::status((string) $b), $provider_status['blockers'])) ?: t('st.none')) ?></dd>
        </dl>
        <?php if(!$canControl): ?><div class="alert alert-info mb-0"><?= htmlspecialchars(t('v.your_role_can_view_source_policy_and')) ?></div><?php endif; ?>
    </div>
</div>

<form id="sources-filters" class="card mi-admin-card mb-3 js-datatable-filters" data-target="#sources-table">
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-3"><label><?= htmlspecialchars(t('v.state')) ?><select class="form-control" name="enabled"><option value=""><?= htmlspecialchars(t('v.all')) ?></option><option value="1"><?= htmlspecialchars(t('v.enabled')) ?></option><option value="0"><?= htmlspecialchars(t('v.paused_disabled_2')) ?></option></select></label></div>
            <div class="form-group col-md-3"><label><?= htmlspecialchars(t('v.provider_key')) ?><input class="form-control" name="provider" maxlength="80" placeholder="<?= htmlspecialchars(t('v.priceza_gemini_offline')) ?>"></label></div>
            <div class="form-group col-md-3"><label><?= htmlspecialchars(t('v.health')) ?><select class="form-control" name="health"><option value=""><?= htmlspecialchars(t('v.all')) ?></option><option value="healthy"><?= htmlspecialchars(t('v.healthy')) ?></option><option value="degraded"><?= htmlspecialchars(t('v.degraded')) ?></option><option value="unknown"><?= htmlspecialchars(t('v.unknown_2')) ?></option></select></label></div>
            <div class="form-group col-md-3"><button class="btn btn-primary btn-block" type="submit"><?= htmlspecialchars(t('v.apply_filters')) ?></button></div>
        </div>
    </div>
</form>

<div class="card mi-table-card">
    <div class="card-header"><h2><?= htmlspecialchars(t('v.source_health')) ?></h2></div>
    <div class="table-responsive">
        <table id="sources-table" class="table table-hover mi-admin-table mb-0 js-server-data-table" data-source-url="<?= $base ?>/admin/sources/data" data-filters="#sources-filters" data-page-length="25">
            <thead>
                <tr><th><?= htmlspecialchars(t('v.source')) ?></th><th><?= htmlspecialchars(t('v.provider')) ?></th><th><?= htmlspecialchars(t('v.enabled')) ?></th><th><?= htmlspecialchars(t('v.policy')) ?></th><th><?= htmlspecialchars(t('v.health')) ?></th><th><?= htmlspecialchars(t('v.last_activity')) ?></th><th><?= htmlspecialchars(t('v.incidents_2')) ?></th><th data-orderable="false"><?= htmlspecialchars(t('v.controls')) ?></th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
