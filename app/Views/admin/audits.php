<?php
$short=function(mixed $value,int $limit=130): string {
    $text=trim((string)$value);
    return strlen($text)>$limit ? substr($text,0,$limit-3).'...' : $text;
};
?>
<div class="mi-page-header">
    <div>
        <span class="mi-breadcrumb">Admin / System</span>
        <h1>Audit Logs</h1>
        <p>Concise audit trail for administrative mutations. Full payloads are collapsed by default.</p>
    </div>
</div>

<div class="card mi-table-card">
    <div class="card-header"><h2>Latest Audit Events</h2></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th>Actor</th><th>Action</th><th>Entity</th><th>Summary</th><th>Timestamp</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach($audits as $a): ?>
                <tr>
                    <td>User #<?= htmlspecialchars((string)($a['user_id'] ?? '-')) ?></td>
                    <td><span class="badge badge-light"><?= htmlspecialchars((string)$a['action']) ?></span></td>
                    <td><?= htmlspecialchars((string)$a['entity_type']) ?> #<?= htmlspecialchars((string)($a['entity_id'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars($short($a['after_data'] ?? $a['before_data'] ?? 'No payload')) ?></td>
                    <td><?= htmlspecialchars((string)($a['created_at'] ?? '-')) ?></td>
                    <td>
                        <details class="audit-details">
                            <summary>Open</summary>
                            <pre><?= htmlspecialchars(json_encode(['before'=>$a['before_data'] ?? null,'after'=>$a['after_data'] ?? null],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if(!$audits): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No audit logs found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
