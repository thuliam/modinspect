<?php $authz=new App\Services\Auth\AuthorizationService(); $canControl=$authz->can($auth_user ?? null,'sources.control'); ?>
<div class="mi-page-header">
    <div>
        <span class="mi-breadcrumb">Admin / Collection</span>
        <h1>Sources</h1>
        <p>Source policy, health, and incident controls. Blocked sources should be paused, not bypassed.</p>
    </div>
    <button class="btn btn-outline-secondary disabled-control" type="button" disabled><i class="fas fa-plus mr-2"></i>Register Source</button>
</div>

<div class="card mi-admin-card mb-3">
    <div class="card-header"><h2>Provider Guardrails</h2></div>
    <div class="card-body">
        <dl class="mi-definition-grid">
            <dt>Adapter</dt><dd><?= $provider_status['adapter_available'] ? 'available' : 'missing' ?></dd>
            <dt>Provider enabled</dt><dd><?= $provider_status['provider_enabled'] ? 'YES' : 'NO' ?></dd>
            <dt>API key configured</dt><dd><?= $provider_status['api_key_configured'] ? 'YES' : 'NO' ?></dd>
            <dt>Live collection</dt><dd><?= $provider_status['live_collection_enabled'] ? 'ENABLED' : 'disabled' ?></dd>
            <dt>Paid calls</dt><dd><?= $provider_status['paid_provider_calls_enabled'] ? 'ENABLED' : 'disabled' ?></dd>
            <dt>Blockers</dt><dd><?= htmlspecialchars(implode(', ', $provider_status['blockers']) ?: 'none') ?></dd>
        </dl>
        <?php if(!$canControl): ?><div class="alert alert-info mb-0">Your role can view source policy and health, but cannot change source state.</div><?php endif; ?>
    </div>
</div>

<form id="sources-filters" class="card mi-admin-card mb-3 js-datatable-filters" data-target="#sources-table">
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-3"><label>State<select class="form-control" name="enabled"><option value="">All</option><option value="1">Enabled</option><option value="0">Paused/Disabled</option></select></label></div>
            <div class="form-group col-md-3"><label>Provider / key<input class="form-control" name="provider" maxlength="80" placeholder="priceza, gemini, offline"></label></div>
            <div class="form-group col-md-3"><label>Health<select class="form-control" name="health"><option value="">All</option><option value="healthy">Healthy</option><option value="degraded">Degraded</option><option value="unknown">Unknown</option></select></label></div>
            <div class="form-group col-md-3"><button class="btn btn-primary btn-block" type="submit">Apply Filters</button></div>
        </div>
    </div>
</form>

<div class="card mi-table-card">
    <div class="card-header"><h2>Source Health</h2></div>
    <div class="table-responsive">
        <table id="sources-table" class="table table-hover mi-admin-table mb-0 js-server-data-table" data-ajax="<?= $base ?>/admin/sources/data" data-filters="#sources-filters" data-page-length="25">
            <thead>
                <tr><th>Source</th><th>Provider</th><th>Enabled</th><th>Policy</th><th>Health</th><th>Last Activity</th><th>Incidents</th><th>Controls</th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
