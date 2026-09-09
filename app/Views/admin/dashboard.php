<section class="container page admin-page">
<?php require __DIR__.'/_nav.php'; ?>
<span class="eyebrow">ADMIN · OPERATIONS</span>
<h1>ศูนย์ควบคุมระบบข้อมูล</h1>

<div class="kpi-grid">
    <div class="card"><span>Products</span><b><?= (int)$products ?></b><small>CPU <?= (int)$cpu_products ?> / GPU <?= (int)$gpu_products ?></small></div>
    <div class="card"><span>Collector jobs</span><b><?= (int)$jobs_completed ?></b><small>Q <?= (int)$jobs_queued ?> · R <?= (int)$jobs_running ?> · F <?= (int)$jobs_failed ?></small></div>
    <div class="card"><span>Pipeline rows</span><b><?= (int)$candidates ?></b><small>Evidence <?= (int)$evidence ?> · Extraction <?= (int)$extractions ?></small></div>
    <div class="card"><span>Review queue</span><b><?= (int)$pending ?></b><small>Accepted <?= (int)$accepted_observations ?> · Reviews <?= (int)$reviews ?></small></div>
    <div class="card"><span>Collection runs</span><b><?= (int)$collection_runs ?></b><small>Snapshots <?= (int)$latest_snapshots ?></small></div>
    <div class="card"><span>Sources</span><b><?= (int)$sources ?></b><small>Paused/disabled <?= (int)$paused_sources ?> · Open incidents <?= (int)$unresolved_incidents ?></small></div>
</div>

<div class="admin-grid">
    <div class="card">
        <h2>Source health</h2>
        <?php foreach($source_health_summary as $status=>$count): ?>
            <div class="health-row"><span><?= htmlspecialchars($status) ?></span><b><?= (int)$count ?></b></div>
        <?php endforeach; ?>
    </div>
    <div class="card">
        <h2>Gemini POC status</h2>
        <dl class="status-list">
            <dt>Adapter</dt><dd><?= $provider_status['adapter_available'] ? 'available' : 'missing' ?></dd>
            <dt>Provider enabled</dt><dd><?= $provider_status['provider_enabled'] ? 'YES' : 'NO' ?></dd>
            <dt>API key configured</dt><dd><?= $provider_status['api_key_configured'] ? 'YES' : 'NO' ?></dd>
            <dt>Live collection</dt><dd><?= $provider_status['live_collection_enabled'] ? 'ENABLED' : 'disabled' ?></dd>
            <dt>Paid calls</dt><dd><?= $provider_status['paid_provider_calls_enabled'] ? 'ENABLED' : 'disabled' ?></dd>
            <dt>Model</dt><dd><?= htmlspecialchars($provider_status['model']) ?></dd>
            <dt>Request limit</dt><dd><?= (int)$provider_status['request_limit'] ?></dd>
        </dl>
        <?php if(!$provider_status['live_execution_allowed']): ?>
            <div class="notice">Live POC ยังไม่พร้อม: <?= htmlspecialchars(implode(', ', $provider_status['blockers'])) ?></div>
        <?php endif; ?>
    </div>
</div>
</section>
