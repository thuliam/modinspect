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
    </div>
</div>

<div class="card mi-table-card">
    <div class="card-header"><h2>Source Health</h2></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead>
                <tr>
                    <th>Source</th>
                    <th>Provider</th>
                    <th>Enabled</th>
                    <th>Policy</th>
                    <th>Health</th>
                    <th>Last Activity</th>
                    <th>Incident State</th>
                    <th>Controls</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($sources as $s): ?>
                <?php $h=$s['health'] ?? []; $enabled=(int)$s['is_active']===1 && (int)($s['is_paused'] ?? 0)===0; ?>
                <tr>
                    <td><span class="mi-row-title"><?= htmlspecialchars((string)$s['name']) ?></span><span class="mi-row-subtitle"><?= htmlspecialchars((string)($s['domain'] ?? 'User supplied data')) ?></span></td>
                    <td><?= htmlspecialchars((string)($s['source_key'] ?? '-')) ?></td>
                    <td><span class="badge <?= $enabled ? 'badge-success' : 'badge-secondary' ?>"><?= $enabled ? 'Enabled' : 'Paused/Disabled' ?></span></td>
                    <td><?= htmlspecialchars((string)($s['allowed_collection_method'] ?? $s['access_method'] ?? '-')) ?></td>
                    <td><span class="badge badge-light"><?= htmlspecialchars((string)($h['status'] ?? 'unknown')) ?></span><small>Failures <?= (int)($h['consecutive_failures'] ?? 0) ?></small></td>
                    <td><small>Success <?= htmlspecialchars((string)($h['last_success'] ?? 'Never')) ?></small><small>Failure <?= htmlspecialchars((string)($h['last_failure'] ?? 'Never')) ?></small></td>
                    <td><?= htmlspecialchars((string)($s['pause_reason'] ?? $s['disabled_reason'] ?? 'none')) ?></td>
                    <td>
                        <?php if($canControl): ?>
                            <form method="post" action="<?= $base ?>/admin/sources/action" class="source-actions">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                <input type="hidden" name="source_id" value="<?= (int)$s['id'] ?>">
                                <input name="reason" maxlength="255" placeholder="Reason / incident note" value="Admin UI action">
                                <?php if((int)$s['is_active']===0): ?>
                                    <button class="btn btn-sm btn-success" name="action" value="enable">Enable</button>
                                <?php elseif((int)($s['is_paused'] ?? 0)===1): ?>
                                    <button class="btn btn-sm btn-primary" name="action" value="resume">Resume</button>
                                    <button class="btn btn-sm btn-danger" name="action" value="disable">Disable</button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary" name="action" value="pause">Pause</button>
                                    <button class="btn btn-sm btn-danger" name="action" value="disable">Disable</button>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-secondary" name="action" value="incident">Incident</button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-secondary disabled-control" type="button" disabled>Controls unavailable</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if(!$sources): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No source policy rows found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
