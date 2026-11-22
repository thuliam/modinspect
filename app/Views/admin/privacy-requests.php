<?php
/**
 * Marketplace P1-A: the data-subject request queue. @var list<array> $rows @var array<string,int> $counts @var string $status @var bool $ready
 * Handling a request here only records the decision; it deletes or exports nothing by itself.
 */
$token = htmlspecialchars(\App\Core\Csrf::token());
$labels = ['received' => 'ใหม่', 'in_progress' => 'กำลังดำเนินการ', 'completed' => 'เสร็จแล้ว', 'rejected' => 'ปฏิเสธ'];
$types = ['access' => 'ขอเข้าถึงข้อมูล', 'export' => 'ขอสำเนาข้อมูล', 'correction' => 'ขอแก้ไขข้อมูล', 'deletion' => 'ขอให้ลบข้อมูล', 'objection' => 'คัดค้านการใช้ข้อมูล'];
$badge = ['received' => 'badge-warning', 'in_progress' => 'badge-info', 'completed' => 'badge-success', 'rejected' => 'badge-secondary'];
?>
<?= mi_page_header($base, ['Members', 'Privacy requests'], 'คำขอข้อมูลส่วนบุคคล', 'คำขอที่สมาชิกส่งมา การตัดสินที่นี่เป็นบันทึกการดำเนินการ ไม่ได้ลบหรือส่งออกข้อมูลเอง', '') ?>
<?php if (!$ready): ?>
    <div class="alert alert-warning">ฐานข้อมูลนี้ยังไม่มีตารางคำขอ ให้รัน <code>php cli/migrate.php database/migrations/031_member_identity_trust.sql</code> หลังสำรองข้อมูล</div>
<?php else: ?>
    <div class="card mi-admin-card mb-3"><div class="card-body">
        <form class="form-inline" method="get" action="<?= $base ?>/admin/privacy-requests">
            <label class="mr-2 mb-0" for="pr-status">แสดง</label>
            <select id="pr-status" name="status" class="form-control mr-3" data-autosubmit data-width="240px">
                <?php foreach ($labels as $v => $l): ?><option value="<?= $v ?>"<?= $status === $v ? ' selected' : '' ?>><?= htmlspecialchars($l) ?> (<?= (int) ($counts[$v] ?? 0) ?>)</option><?php endforeach; ?>
            </select>
            <noscript><button class="btn btn-primary" type="submit">แสดง</button></noscript>
        </form>
    </div></div>
    <div class="card mi-admin-card mi-table-card"><div class="card-header"><h2>คำขอ (<?= count($rows) ?>)</h2></div>
        <div class="table-responsive"><table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th>ส่งเมื่อ</th><th>สมาชิก</th><th>ประเภท</th><th>รายละเอียด</th><th>สถานะ</th><th>การดำเนินการ</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6" class="text-muted">ไม่มีคำขอในสถานะนี้</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><small><?= htmlspecialchars(substr((string) $r['received_at'], 0, 16)) ?></small><?php if ($r['due_at'] !== null): ?><br><small class="text-muted">กำหนด <?= htmlspecialchars(substr((string) $r['due_at'], 0, 10)) ?></small><?php endif; ?></td>
                    <td><?php if ($r['member_id'] !== null): ?>#<?= (int) $r['member_id'] ?> <?= htmlspecialchars((string) ($r['display_name'] ?? '')) ?><?php else: ?><span class="text-muted">บัญชีถูกลบแล้ว</span><?php endif; ?></td>
                    <td><?= htmlspecialchars($types[$r['request_type']] ?? (string) $r['request_type']) ?></td>
                    <td><?= htmlspecialchars((string) ($r['detail'] ?? '')) ?></td>
                    <td><span class="badge <?= $badge[$r['status']] ?? 'badge-light' ?>"><?= htmlspecialchars($labels[$r['status']] ?? (string) $r['status']) ?></span>
                        <?php if (!empty($r['response_note'])): ?><br><small class="text-muted"><?= htmlspecialchars((string) $r['response_note']) ?></small><?php endif; ?></td>
                    <td>
                        <?php if (in_array($r['status'], ['received', 'in_progress'], true)): ?>
                            <form class="form-inline" method="post" action="<?= $base ?>/admin/privacy-requests/action">
                                <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                                <input class="form-control form-control-sm mr-1 mb-1" name="note" maxlength="500" placeholder="บันทึกการตอบกลับ (ต้องมีเมื่อปิดคำขอ)" aria-label="บันทึกการตอบกลับ">
                                <?php if ($r['status'] === 'received'): ?><button class="btn btn-sm btn-info mr-1 mb-1" type="submit" name="to" value="in_progress">เริ่มดำเนินการ</button><?php endif; ?>
                                <button class="btn btn-sm btn-success mr-1 mb-1" type="submit" name="to" value="completed">เสร็จแล้ว</button>
                                <button class="btn btn-sm btn-outline-secondary mb-1" type="submit" name="to" value="rejected">ปฏิเสธ</button>
                            </form>
                        <?php else: ?><span class="text-muted">ปิดแล้ว</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
<?php endif; ?>
