<section class="container page admin-page"><?php require __DIR__.'/_nav.php'; ?>
<span class="eyebrow">OBSERVATION QUEUE</span><h1>รายการข้อมูลราคา</h1>
<form class="card filter-strip" method="get" action="<?= $base ?>/admin/price-observations">
    <select name="status"><option value="">ทุกสถานะ</option><?php foreach(['pending','approved','rejected','excluded'] as $s): ?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
    <input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="ค้นหาชื่อสินค้า">
    <button><span class="material-symbols-outlined">search</span> กรอง</button>
</form>
<div class="card table-card"><table><thead><tr><th>ID</th><th>สินค้า/Raw title</th><th>ราคา</th><th>สภาพ</th><th>Confidence</th><th>Evidence</th><th>Source</th><th>สถานะ</th></tr></thead><tbody>
<?php foreach($observations as $o): ?><tr><td>#<?= (int)$o['id'] ?></td><td><b><?= htmlspecialchars($o['full_name']) ?></b><small><?= htmlspecialchars($o['raw_title']??'-') ?></small></td><td>฿<?= number_format((float)$o['asking_price']) ?></td><td><?= htmlspecialchars($o['condition_level']) ?></td><td><?= number_format((float)$o['classification_confidence']) ?>%</td><td>Level <?= (int)$o['evidence_level'] ?></td><td><?= htmlspecialchars($o['source_name'] ?? '-') ?><?= str_contains((string)($o['source_key'] ?? ''),'mock') ? '<small>MOCK TEST DATA</small>' : '' ?></td><td><span class="badge"><?= htmlspecialchars($o['verified_status']) ?></span></td></tr><?php endforeach; ?>
<?php if(!$observations): ?><tr><td colspan="8" class="empty-cell">ยังไม่มี observation ที่ตรงกับตัวกรอง</td></tr><?php endif; ?>
</tbody></table></div></section>
