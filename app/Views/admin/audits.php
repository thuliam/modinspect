<section class="container page admin-page"><?php require __DIR__.'/_nav.php'; ?>
<span class="eyebrow">AUDIT LOGS</span><h1>ประวัติการเปลี่ยนแปลง</h1>
<div class="card table-card"><table><thead><tr><th>ID</th><th>Action</th><th>Entity</th><th>Before</th><th>After</th><th>Created</th></tr></thead><tbody>
<?php foreach($audits as $a): ?><tr><td>#<?= (int)$a['id'] ?></td><td><?= htmlspecialchars($a['action']) ?></td><td><?= htmlspecialchars($a['entity_type']) ?> #<?= htmlspecialchars((string)($a['entity_id'] ?? '-')) ?></td><td><code><?= htmlspecialchars(mb_strimwidth((string)($a['before_data'] ?? ''),0,120,'...')) ?></code></td><td><code><?= htmlspecialchars(mb_strimwidth((string)($a['after_data'] ?? ''),0,120,'...')) ?></code></td><td><?= htmlspecialchars($a['created_at'] ?? '-') ?></td></tr><?php endforeach; ?>
<?php if(!$audits): ?><tr><td colspan="6" class="empty-cell">ยังไม่มี audit log</td></tr><?php endif; ?>
</tbody></table></div>
</section>
