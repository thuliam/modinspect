<?php
/**
 * LS-21 (R-11): the community report queue. @var list<array> $rows @var array<string,int> $counts @var string $status @var bool $ready @var bool $featureOn
 * A report is a signal. Accepting one changes no price; "send to price review" only creates a PENDING sold-price candidate that a reviewer still decides.
 */
use App\Services\Community\CommunityReports as C;
$token = htmlspecialchars(\App\Core\Csrf::token());
$badge = ['new' => 'badge-warning', 'accepted' => 'badge-success', 'rejected' => 'badge-secondary', 'spam' => 'badge-danger'];
$labels = ['new' => 'ใหม่', 'accepted' => 'รับแล้ว', 'rejected' => 'ไม่ผ่าน', 'spam' => 'สแปม', 'all' => 'ทั้งหมด'];
$detail = static function (array $r): string {
    $p = $r['payload']; $out = [];
    switch ($r['report_type']) {
        case 'wrong_product': $out[] = isset($p['should_be_product_id']) ? 'ควรเป็นรุ่น #' . (int) $p['should_be_product_id'] : 'รุ่นที่ถูกต้องไม่มีในแคตตาล็อก'; break;
        case 'price_looks_off': $out[] = ($p['direction'] ?? '') === 'higher' ? 'ช่วงราคาดูสูงเกินไป' : 'ช่วงราคาดูต่ำเกินไป'; if (isset($p['seen_price'])) { $out[] = 'ราคาที่เห็น ฿' . number_format((int) $p['seen_price']); } break;
        case 'sold_price':
            $out[] = 'ขายได้ ฿' . number_format((int) ($p['price'] ?? 0)) . ' · ' . htmlspecialchars((string) ($p['month'] ?? '')); $out[] = 'สภาพ ' . (C::CONDITIONS[$p['condition'] ?? ''] ?? '-');
            if (($p['warranty_months'] ?? null) !== null) { $out[] = 'ประกัน ' . (int) $p['warranty_months'] . ' เดือน'; }
            if (!empty($p['has_box'])) { $out[] = 'มีกล่อง'; } if (!empty($p['has_receipt'])) { $out[] = 'มีใบเสร็จ'; } break;
        case 'spec_wrong': $out[] = 'สเปก: ' . (C::SPECS[$p['spec'] ?? ''] ?? '-'); break;
        default: $out[] = 'ข้อมูลดูเก่า';
    }
    return implode(' · ', $out);
};
?>
<?= mi_page_header($base, ['Market data', 'Community reports'], 'รายงานจากผู้ใช้', 'รายงานที่ผู้เข้าชมส่งมา เป็นสัญญาณให้คนตรวจ ไม่เปลี่ยนราคาเอง', '') ?>
<?php if (!$ready): ?>
    <div class="alert alert-warning">ฐานข้อมูลนี้ยังไม่มีตารางรายงาน ให้รัน <code>php cli/migrate.php database/migrations/026_community_reports.sql</code> หลังสำรองข้อมูล</div>
<?php else: ?>
    <?php if (!$featureOn): ?><div class="alert alert-info">ฟอร์มรายงานสำหรับผู้เข้าชมยังปิดอยู่ (ตั้งค่า <code>COMMUNITY_REPORTS_ENABLED</code> และ <code>COMMUNITY_REPORTS_HMAC_KEY</code> หลังเจ้าของอนุมัติข้อความความเป็นส่วนตัว) คิวนี้ดูและตัดสินรายงานที่มีอยู่ได้ตามปกติ</div><?php endif; ?>
    <div class="card mi-admin-card mb-3"><div class="card-body">
        <form class="form-inline" method="get" action="<?= $base ?>/admin/community-reports">
            <label class="mr-2 mb-0" for="cr-status">แสดง</label>
            <select id="cr-status" name="status" class="form-control mr-3" data-autosubmit data-width="220px">
                <?php foreach ($labels as $v => $l): ?><option value="<?= $v ?>"<?= $status === $v ? ' selected' : '' ?>><?= htmlspecialchars($l) ?><?= isset($counts[$v]) ? ' (' . (int) $counts[$v] . ')' : '' ?></option><?php endforeach; ?>
            </select>
            <noscript><button class="btn btn-primary" type="submit">แสดง</button></noscript>
        </form>
    </div></div>
    <div class="card mi-admin-card mi-table-card"><div class="card-header"><h2>รายงาน (<?= count($rows) ?>)</h2></div>
        <div class="table-responsive"><table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th>ส่งเมื่อ</th><th>รุ่น</th><th>เรื่อง</th><th>รายละเอียด</th><th>ที่ผู้ใช้เห็นตอนนั้น</th><th>สถานะ</th><th>การตัดสิน</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="7" class="text-muted">ยังไม่มีรายงาน</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><small><?= htmlspecialchars(substr((string) $r['created_at'], 0, 16)) ?></small></td>
                    <td><a href="<?= $base ?>/price/<?= rawurlencode((string) $r['slug']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string) $r['full_name']) ?></a>
                        <?php if ((int) $r['product_new_reports'] > 1): ?><br><small class="text-muted"><?= (int) $r['product_new_reports'] ?> รายงานใหม่ของรุ่นนี้</small><?php endif; ?></td>
                    <td><?= htmlspecialchars(C::TYPES[$r['report_type']]['label'] ?? (string) $r['report_type']) ?></td>
                    <td><?= htmlspecialchars($detail($r)) ?></td>
                    <td><small><?php if ($r['seen_median'] !== null): ?>฿<?= number_format((float) $r['seen_q1']) ?> – ฿<?= number_format((float) $r['seen_q3']) ?> (กลาง ฿<?= number_format((float) $r['seen_median']) ?>)<?php else: ?>ไม่มีราคาสาธารณะ<?php endif; ?></small></td>
                    <td><span class="badge <?= $badge[$r['status']] ?? 'badge-light' ?>"><?= htmlspecialchars($labels[$r['status']] ?? (string) $r['status']) ?></span>
                        <?php if ($r['moderation_note']): ?><br><small class="text-muted"><?= htmlspecialchars((string) $r['moderation_note']) ?></small><?php endif; ?>
                        <?php if ($r['candidate_observation_id'] !== null): ?><br><small><a class="text-success" href="<?= $base ?>/admin/review/<?= (int) $r['candidate_observation_id'] ?>">อยู่ในคิวตรวจราคา #<?= (int) $r['candidate_observation_id'] ?> (เปิดดู)</a></small><?php endif; ?></td>
                    <td>
                        <?php if ($r['status'] === 'new'): ?>
                            <form class="form-inline" method="post" action="<?= $base ?>/admin/community-reports/action">
                                <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                                <input class="form-control form-control-sm mr-1 mb-1" name="note" maxlength="255" placeholder="เหตุผล (ต้องมีเมื่อไม่ผ่านหรือสแปม)" aria-label="เหตุผล">
                                <button class="btn btn-sm btn-success mr-1 mb-1" type="submit" name="decision" value="accepted">รับ</button>
                                <button class="btn btn-sm btn-outline-danger mr-1 mb-1" type="submit" name="decision" value="rejected">ไม่ผ่าน</button>
                                <button class="btn btn-sm btn-outline-secondary mb-1" type="submit" name="decision" value="spam">สแปม</button>
                            </form>
                        <?php elseif ($r['status'] === 'accepted'): ?>
                            <?php if ($r['report_type'] === 'sold_price' && $r['candidate_observation_id'] === null): ?>
                                <form method="post" action="<?= $base ?>/admin/community-reports/action">
                                    <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                                    <button class="btn btn-sm btn-primary" type="submit" name="decision" value="candidate" title="สร้างรายการรอตรวจ ราคาประเภทขายได้ ยังไม่กระทบราคาสาธารณะ">ส่งเข้าคิวตรวจราคา</button>
                                </form>
                            <?php elseif (in_array($r['report_type'], ['wrong_product', 'spec_wrong'], true)): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/admin/products">แก้ในหน้าสินค้า</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div></div>
<?php endif; ?>
