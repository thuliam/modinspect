<?php
/** LS-14 Module 9: @var list<array> $rows  @var bool $ready */
$token = htmlspecialchars(\App\Core\Csrf::token());
$badge = ['submitted' => 'badge-warning', 'approved' => 'badge-success', 'rejected' => 'badge-danger', 'withdrawn' => 'badge-secondary'];
?>
<?= mi_page_header($base, ['Market data', 'Seller listings'], 'ประกาศจากผู้ขาย', 'ตรวจประกาศที่สมาชิกส่งมา: รูปเป็นสินค้าจริงตรงรุ่น ไม่มีข้อมูลติดต่อหรือข้อมูลส่วนตัว ราคาไม่ผิดปกติ การอนุมัติไม่สร้างราคาตลาด (เจ้าของยังไม่ได้ตัดสินใจ HG-17) และไม่ทำให้ประกาศเป็นสาธารณะจนกว่าจะเปิด HG-16') ?>
<?php if (!$ready): ?>
    <div class="alert alert-warning">ฐานข้อมูลนี้ยังไม่มีตารางประกาศ ให้รัน <code>php cli/migrate.php database/migrations/023_members.sql</code> และ <code>024_listing_attributes_and_seller_listings.sql</code> ก่อน (เจ้าของเป็นผู้รันบนฐานข้อมูลจริง)</div>
<?php else: ?>
<div class="card mi-admin-card mi-table-card">
    <div class="card-header"><h2>รายการ (<?= count($rows) ?>)</h2></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th>สินค้า</th><th class="text-right">ราคา</th><th>รายละเอียด</th><th>รูป</th><th>สถานะ</th><th style="min-width:18rem">ตรวจ</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6" class="text-muted">ยังไม่มีประกาศที่ส่งมา</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $r['full_name']) ?><br><small class="text-muted"><?= htmlspecialchars(substr((string) $r['updated_at'], 0, 16)) ?></small></td>
                    <td class="text-right">฿<?= number_format((float) $r['asking_price']) ?></td>
                    <td><small><?php foreach (\App\Services\Listing\ListingAttributes::describe($r) as $d): ?><?= htmlspecialchars($d['label'] . ': ' . $d['value']) ?><br><?php endforeach; ?></small></td>
                    <td><?= (int) $r['photo_count'] ?> รูป<br><?php foreach ($photos[(int) $r['id']] ?? [] as $ph): ?><a href="<?= $base ?>/seller/photos/<?= htmlspecialchars((string) $r['uuid']) ?>/<?= htmlspecialchars((string) $ph['file_name']) ?>" target="_blank" rel="noopener"><img src="<?= $base ?>/seller/photos/<?= htmlspecialchars((string) $r['uuid']) ?>/<?= htmlspecialchars((string) $ph['file_name']) ?>" alt="รูปประกาศ" width="48" height="48" loading="lazy" class="mr-1 mb-1"></a><?php endforeach; ?></td>
                    <td><span class="badge <?= $badge[$r['status']] ?? 'badge-light' ?>"><?= htmlspecialchars((string) $r['status']) ?></span><?php if ($r['moderation_note']): ?><br><small class="text-muted"><?= htmlspecialchars((string) $r['moderation_note']) ?></small><?php endif; ?></td>
                    <td>
                        <?php if ($r['status'] === 'submitted'): ?>
                        <form class="form-inline" method="post" action="<?= $base ?>/admin/seller-listings/action">
                            <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <input class="form-control form-control-sm mr-1 mb-1" name="note" maxlength="255" placeholder="เหตุผล (ต้องมีเมื่อไม่ผ่าน)" aria-label="เหตุผล">
                            <button class="btn btn-sm btn-success mr-1 mb-1" type="submit" name="decision" value="approved">ผ่าน</button>
                            <button class="btn btn-sm btn-outline-danger mb-1" type="submit" name="decision" value="rejected">ไม่ผ่าน</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
