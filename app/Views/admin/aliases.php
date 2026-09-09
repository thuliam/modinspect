<section class="container page admin-page"><?php require __DIR__.'/_nav.php'; ?>
<div class="section-head"><div><span class="eyebrow">NORMALIZATION</span><h1>Product Aliases</h1></div><button class="disabled-control" type="button" disabled>เพิ่ม Alias — ยังไม่เปิดใช้งาน</button></div>
<div class="card table-card"><table><thead><tr><th>Alias</th><th>Normalized</th><th>Matched Product</th><th>Confidence</th><th>Source</th></tr></thead><tbody>
<?php foreach($aliases as $a): ?><tr><td><b><?= htmlspecialchars($a['alias_text']) ?></b></td><td><code><?= htmlspecialchars($a['normalized_alias']) ?></code></td><td><?= htmlspecialchars($a['full_name']) ?></td><td><?= number_format((float)$a['confidence']) ?>%</td><td><?= htmlspecialchars($a['source_note']??'Manual') ?></td></tr><?php endforeach; ?>
<?php if(!$aliases): ?><tr><td colspan="5" class="empty-cell">ยังไม่มี alias</td></tr><?php endif; ?>
</tbody></table></div></section>
