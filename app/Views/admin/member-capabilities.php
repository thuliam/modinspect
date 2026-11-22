<?php
/**
 * Marketplace P1-A: member capabilities. @var list<array> $rows @var bool $ready @var list<string> $allowed
 * Granting a capability is necessary but not sufficient for later seller steps (verification is checked separately). Revoking keeps the history.
 */
$token = htmlspecialchars(\App\Core\Csrf::token());
?>
<?= mi_page_header($base, ['Members', 'Member capabilities'], 'สิทธิ์ของสมาชิก', 'ให้หรือถอนสิทธิ์ผู้ขายของสมาชิก การให้สิทธิ์ไม่ได้ข้ามการตรวจสอบผู้ขายในขั้นตอนถัดไป', '') ?>
<?php if (!$ready): ?>
    <div class="alert alert-warning">ฐานข้อมูลนี้ยังไม่มีตารางสิทธิ์ ให้รัน <code>php cli/migrate.php database/migrations/031_member_identity_trust.sql</code> หลังสำรองข้อมูล</div>
<?php else: ?>
    <div class="card mi-admin-card mb-3"><div class="card-header"><h2>ให้สิทธิ์</h2></div><div class="card-body">
        <form class="form-inline" method="post" action="<?= $base ?>/admin/member-capabilities/action">
            <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="do" value="grant">
            <label class="mr-2 mb-0" for="mc-member">รหัสสมาชิก</label>
            <input class="form-control mr-3" id="mc-member" name="member_id" type="number" min="1" required>
            <label class="mr-2 mb-0" for="mc-cap">สิทธิ์</label>
            <select class="form-control mr-3" id="mc-cap" name="capability"><?php foreach ($allowed as $a): ?><option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option><?php endforeach; ?></select>
            <button class="btn btn-primary" type="submit">ให้สิทธิ์</button>
        </form>
    </div></div>
    <div class="card mi-admin-card mi-table-card"><div class="card-header"><h2>ผู้ถือสิทธิ์อยู่ตอนนี้ (<?= count($rows) ?>)</h2></div>
        <div class="table-responsive"><table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th>สมาชิก</th><th>สถานะบัญชี</th><th>สิทธิ์</th><th>ให้เมื่อ</th><th>ให้โดย (admin)</th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6" class="text-muted">ยังไม่มีใครถือสิทธิ์</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>#<?= (int) $r['member_id'] ?> <?= htmlspecialchars((string) ($r['display_name'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) $r['status']) ?></td>
                    <td><?= htmlspecialchars((string) $r['capability']) ?></td>
                    <td><small><?= htmlspecialchars(substr((string) $r['granted_at'], 0, 16)) ?></small></td>
                    <td><?= $r['granted_by_user_id'] !== null ? '#' . (int) $r['granted_by_user_id'] : '-' ?></td>
                    <td>
                        <form method="post" action="<?= $base ?>/admin/member-capabilities/action" class="mb-0">
                            <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="do" value="revoke">
                            <input type="hidden" name="member_id" value="<?= (int) $r['member_id'] ?>"><input type="hidden" name="capability" value="<?= htmlspecialchars((string) $r['capability']) ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">ถอนสิทธิ์</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
<?php endif; ?>
