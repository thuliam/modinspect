<?php $authz=new App\Services\Auth\AuthorizationService(); $canControl=$authz->can($auth_user ?? null,'sources.control'); ?>
<section class="container page admin-page">
<?php require __DIR__.'/_nav.php'; ?>
<div class="section-head">
    <div><span class="eyebrow">SOURCE HEALTH</span><h1>แหล่งข้อมูล</h1></div>
    <button class="disabled-control" type="button" disabled>ลงทะเบียน Source — ยังไม่เปิดใช้งาน</button>
</div>

<div class="card">
    <h2>Gemini readiness</h2>
    <dl class="status-list compact">
        <dt>Adapter</dt><dd><?= $provider_status['adapter_available'] ? 'available' : 'missing' ?></dd>
        <dt>Provider enabled</dt><dd><?= $provider_status['provider_enabled'] ? 'YES' : 'NO' ?></dd>
        <dt>API key configured</dt><dd><?= $provider_status['api_key_configured'] ? 'YES' : 'NO' ?></dd>
        <dt>Live collection</dt><dd><?= $provider_status['live_collection_enabled'] ? 'ENABLED' : 'disabled' ?></dd>
        <dt>Paid calls</dt><dd><?= $provider_status['paid_provider_calls_enabled'] ? 'ENABLED' : 'disabled' ?></dd>
        <dt>Blockers</dt><dd><?= htmlspecialchars(implode(', ', $provider_status['blockers']) ?: 'none') ?></dd>
    </dl>
</div>

<div class="source-grid">
<?php foreach($sources as $s): $h=$s['health'] ?? []; ?>
<article class="card">
    <div class="section-head">
        <h2><?= htmlspecialchars($s['name']) ?></h2>
        <span class="status-dot <?= ((int)$s['is_active']===1 && (int)($s['is_paused']??0)===0)?'ok':'off' ?>"></span>
    </div>
    <p><?= htmlspecialchars($s['domain'] ?? 'User supplied data') ?></p>
    <dl>
        <dt>Status</dt><dd><?= htmlspecialchars($h['status'] ?? 'unknown') ?></dd>
        <dt>Enabled</dt><dd><?= (int)$s['is_active']===1 ? 'YES' : 'NO' ?></dd>
        <dt>Paused</dt><dd><?= (int)($s['is_paused'] ?? 0)===1 ? 'YES' : 'NO' ?></dd>
        <dt>Access</dt><dd><?= htmlspecialchars($s['allowed_collection_method'] ?? $s['access_method']) ?></dd>
        <dt>Last success</dt><dd><?= htmlspecialchars($h['last_success'] ?? 'Never') ?></dd>
        <dt>Last failure</dt><dd><?= htmlspecialchars($h['last_failure'] ?? 'Never') ?></dd>
        <dt>Consecutive failures</dt><dd><?= (int)($h['consecutive_failures'] ?? 0) ?></dd>
        <dt>Candidate yield</dt><dd><?= (int)($h['candidate_yield'] ?? 0) ?></dd>
    </dl>
    <?php if(!empty($s['pause_reason']) || !empty($s['disabled_reason'])): ?>
        <div class="notice"><?= htmlspecialchars($s['pause_reason'] ?? $s['disabled_reason']) ?></div>
    <?php endif; ?>
    <?php if($canControl): ?>
    <form method="post" action="<?= $base ?>/admin/sources/action" class="source-actions">
        <input type="hidden" name="_token" value="<?= \App\Core\Csrf::token() ?>">
        <input type="hidden" name="source_id" value="<?= (int)$s['id'] ?>">
        <input name="reason" maxlength="255" placeholder="เหตุผล / incident note" value="Admin UI action">
        <?php if((int)$s['is_active']===0): ?>
            <button class="small" name="action" value="enable">Enable</button>
        <?php elseif((int)($s['is_paused'] ?? 0)===1): ?>
            <button class="small" name="action" value="resume">Resume</button>
            <button class="small danger" name="action" value="disable">Disable</button>
        <?php else: ?>
            <button class="small ghost" name="action" value="pause">Pause</button>
            <button class="small danger" name="action" value="disable">Disable</button>
        <?php endif; ?>
        <button class="small ghost" name="action" value="incident">Record incident</button>
    </form>
    <?php else: ?>
        <button class="small disabled-control" type="button" disabled>Source controls unavailable</button>
    <?php endif; ?>
</article>
<?php endforeach; ?>
<?php if(!$sources): ?><div class="empty">ยังไม่มี source policy</div><?php endif; ?>
</div>
</section>
