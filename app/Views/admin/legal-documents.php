<?php
/**
 * Marketplace P1-A: versioned legal documents. @var list<array> $rows @var ?array $docView @var bool $ready @var list<string> $types
 * A document is saved as a draft first. Publishing is a separate step that needs the owner's confirmation; it retires the previous version of that type.
 * The text itself comes from the owner and counsel (HG-LEGAL); nothing here writes or suggests legal wording.
 */
$token = htmlspecialchars(\App\Core\Csrf::token());
$badge = ['draft' => 'badge-warning', 'published' => 'badge-success', 'retired' => 'badge-secondary'];
$statusLabel = ['draft' => 'ฉบับร่าง', 'published' => 'เผยแพร่', 'retired' => 'ยกเลิกแล้ว'];
?>
<?= mi_page_header($base, ['Members', 'Legal documents'], 'เอกสารกฎหมาย', 'เงื่อนไขและนโยบายแบบมีเวอร์ชัน ต้องเป็นฉบับร่างก่อน และเผยแพร่เมื่อเจ้าของกับทนายอนุมัติข้อความแล้วเท่านั้น', '') ?>
<?php if (!$ready): ?>
    <div class="alert alert-warning">ฐานข้อมูลนี้ยังไม่มีตารางเอกสาร ให้รัน <code>php cli/migrate.php database/migrations/031_member_identity_trust.sql</code> หลังสำรองข้อมูล</div>
<?php else: ?>
    <?php if ($docView): ?>
        <div class="card mi-admin-card mb-3"><div class="card-header"><h2><?= htmlspecialchars((string) $docView['title']) ?> · <?= htmlspecialchars((string) $docView['version']) ?></h2></div>
            <div class="card-body"><?= nl2br(htmlspecialchars((string) $docView['body_text'])) ?></div></div>
    <?php endif; ?>
    <div class="card mi-admin-card mi-table-card mb-3"><div class="card-header"><h2>เอกสารทั้งหมด (<?= count($rows) ?>)</h2></div>
        <div class="table-responsive"><table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th>ประเภท</th><th>เวอร์ชัน</th><th>ชื่อ</th><th>สถานะ</th><th>เผยแพร่เมื่อ</th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6" class="text-muted">ยังไม่มีเอกสาร</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $r['doc_type']) ?></td>
                    <td><?= htmlspecialchars((string) $r['version']) ?></td>
                    <td><?= htmlspecialchars((string) $r['title']) ?></td>
                    <td><span class="badge <?= $badge[$r['status']] ?? 'badge-light' ?>"><?= htmlspecialchars($statusLabel[$r['status']] ?? (string) $r['status']) ?></span></td>
                    <td><small><?= htmlspecialchars((string) ($r['published_at'] ? substr((string) $r['published_at'], 0, 16) : '-')) ?></small></td>
                    <td>
                        <a class="btn btn-sm btn-outline-secondary mr-1" href="<?= $base ?>/admin/legal-documents?view=<?= (int) $r['id'] ?>">อ่าน</a>
                        <?php if ($r['status'] === 'draft'): ?>
                            <form class="form-inline d-inline-flex" method="post" action="<?= $base ?>/admin/legal-documents/publish">
                                <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <label class="mr-2 mb-0"><input type="checkbox" name="confirm" value="1" required class="mr-1">เจ้าของและทนายอนุมัติแล้ว</label>
                                <button class="btn btn-sm btn-success" type="submit">เผยแพร่</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <div class="card mi-admin-card"><div class="card-header"><h2>เพิ่มฉบับร่าง</h2></div><div class="card-body">
        <form method="post" action="<?= $base ?>/admin/legal-documents/create">
            <input type="hidden" name="_token" value="<?= $token ?>">
            <div class="form-row">
                <div class="form-group col-md-4"><label for="ld-type">ประเภท</label>
                    <select class="form-control" id="ld-type" name="doc_type"><?php foreach ($types as $t): ?><option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option><?php endforeach; ?></select></div>
                <div class="form-group col-md-2"><label for="ld-version">เวอร์ชัน</label><input class="form-control" id="ld-version" name="version" maxlength="20" required></div>
                <div class="form-group col-md-6"><label for="ld-title">ชื่อเอกสาร</label><input class="form-control" id="ld-title" name="title" maxlength="160" required></div>
            </div>
            <div class="form-group"><label for="ld-body">เนื้อหา (ข้อความจากเจ้าของ/ทนาย)</label><textarea class="form-control" id="ld-body" name="body" rows="8" required></textarea></div>
            <button class="btn btn-primary" type="submit">บันทึกเป็นฉบับร่าง</button>
        </form>
    </div></div>
<?php endif; ?>
