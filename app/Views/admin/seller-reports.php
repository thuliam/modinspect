<?php
/** LS-14 Module 8: @var list<array> $rows  @var list<array> $disputes  @var bool $ready */
$token = htmlspecialchars(\App\Core\Csrf::token());
$badge = ['pending' => 'badge-warning', 'verified' => 'badge-success', 'rejected' => 'badge-secondary', 'disputed' => 'badge-danger'];
?>
<?= mi_page_header($base, ['Market data', 'Seller reports'], 'รายงานผู้ขาย', 'ตรวจรายงานเกี่ยวกับเบอร์โทรหรือบัญชี: หลักฐานตรงกับปัญหาที่แจ้ง ไม่มีข้อมูลส่วนตัวเกินจำเป็น เมื่อยืนยันแล้วจะนับในหน้าเช็กผู้ขายเมื่อฟีเจอร์เปิดอยู่ (HG-14) หมายเลขไม่ถูกเก็บ เห็นแค่ 4 ตัวท้าย') ?>
<?php if (!$ready): ?>
    <div class="alert alert-warning">ฐานข้อมูลนี้ยังไม่มีตารางรายงาน ให้รัน <code>php cli/migrate.php database/migrations/025_seller_reports.sql</code> (เจ้าของเป็นผู้รันบนฐานข้อมูลจริง)</div>
<?php else: ?>
<?php if ($disputes): ?>
<div class="card mi-admin-card mb-3"><div class="card-header"><h2>คำโต้แย้งที่เปิดอยู่ (<?= count($disputes) ?>)</h2></div>
    <div class="table-responsive"><table class="table mi-admin-table mb-0"><thead><tr><th>ท้ายเลข</th><th>เหตุผล</th><th>อีเมล</th><th>ส่งเมื่อ</th><th></th></tr></thead><tbody>
    <?php foreach ($disputes as $d): ?><tr><td><?= htmlspecialchars((string) ($d['last4'] ?? '-')) ?></td><td><?= htmlspecialchars((string) $d['explanation']) ?></td><td><?= htmlspecialchars((string) $d['contact_email']) ?></td><td><?= htmlspecialchars(substr((string) $d['created_at'], 0, 16)) ?><br><small class="<?= !empty($d['overdue']) ? 'text-danger' : 'text-muted' ?>"><?= (int) ($d['age_days'] ?? 0) ?> วัน<?= !empty($d['overdue']) ? ' · เกินกำหนดตอบ' : '' ?></small></td>
        <td><form method="post" action="<?= $base ?>/admin/seller-reports/action"><input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="dispute_id" value="<?= (int) $d['id'] ?>"><button class="btn btn-sm btn-outline-secondary" type="submit" name="decision" value="close_dispute">ปิดเรื่อง (หลังตัดสินรายงานแล้ว)</button></form></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
<?php endif; ?>
<div class="card mi-admin-card mi-table-card"><div class="card-header"><h2>รายงาน (<?= count($rows) ?>)</h2></div>
    <div class="table-responsive"><table class="table table-hover mi-admin-table mb-0">
        <thead><tr><th>ชนิด</th><th>ท้ายเลข</th><th>ปัญหา</th><th>หมายเหตุ</th><th>หลักฐาน</th><th>รายงานอื่นของเลขเดียวกัน</th><th>สถานะ</th><th style="min-width:18rem">ตรวจ</th></tr></thead><tbody>
        <?php if (!$rows): ?><tr><td colspan="8" class="text-muted">ยังไม่มีรายงาน</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?><tr>
            <td><?= htmlspecialchars(\App\Services\Listing\SellerCheck::TYPES[$r['identifier_type']] ?? (string) $r['identifier_type']) ?></td>
            <td>…<?= htmlspecialchars((string) $r['identifier_last4']) ?></td>
            <td><?= htmlspecialchars(\App\Services\Listing\SellerCheck::PROBLEMS[$r['problem_type']] ?? (string) $r['problem_type']) ?></td>
            <td><small><?= htmlspecialchars((string) ($r['note'] ?? '')) ?></small></td>
            <td><?= (int) $r['evidence_count'] ?> รูป</td>
            <td><?= (int) $r['same_identifier_reports'] ?><?= (int) $r['open_disputes'] > 0 ? ' · มีคำโต้แย้ง' : '' ?></td>
            <td><span class="badge <?= $badge[$r['status']] ?? 'badge-light' ?>"><?= htmlspecialchars((string) $r['status']) ?></span><?php if ($r['moderation_note']): ?><br><small class="text-muted"><?= htmlspecialchars((string) $r['moderation_note']) ?></small><?php endif; ?></td>
            <td><?php if (in_array($r['status'], ['pending', 'disputed'], true)): ?>
                <form class="form-inline" method="post" action="<?= $base ?>/admin/seller-reports/action"><input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <input class="form-control form-control-sm mr-1 mb-1" name="note" maxlength="255" placeholder="เหตุผล (ต้องมีเมื่อไม่ผ่าน)" aria-label="เหตุผล">
                    <button class="btn btn-sm btn-success mr-1 mb-1" type="submit" name="decision" value="verified">ยืนยัน</button>
                    <button class="btn btn-sm btn-outline-danger mb-1" type="submit" name="decision" value="rejected">ไม่ผ่าน</button></form>
            <?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table></div></div>
<?php endif; ?>
