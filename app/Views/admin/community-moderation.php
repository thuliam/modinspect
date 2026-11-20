<?php
/**
 * LS-24 M9: forum moderation queue. @var list<array> $queue @var array<string,int> $counts @var bool $ready @var bool $featureOn
 * Three different members' flags hide an item at once; a person decides here (uphold = keep hidden, dismiss = show again). Every decision is audited.
 */
$token = htmlspecialchars(\App\Core\Csrf::token());
$typeTh = ['thread' => 'กระทู้', 'post' => 'ความเห็น', 'review' => 'รีวิว'];
$reasonTh = \App\Services\Community\Forum::REASONS;
?>
<?= mi_page_header($base, ['Market data', 'Community moderation'], 'ตรวจเนื้อหาชุมชน', 'รายการที่สมาชิกแจ้ง สามคนขึ้นไปจะซ่อนทันที แล้วคนตัดสินที่นี่ ทุกการตัดสินมีบันทึก', '') ?>
<?php if (!$ready): ?>
    <div class="alert alert-warning">ฐานข้อมูลนี้ยังไม่มีตารางชุมชน ให้รัน <code>php cli/migrate.php database/migrations/027_community_forum.sql</code> หลังสำรองข้อมูล</div>
<?php else: ?>
    <?php if (!$featureOn): ?><div class="alert alert-info">ชุมชนสำหรับผู้เข้าชมยังปิดอยู่ (ตั้งค่า <code>COMMUNITY_FORUM_ENABLED</code> หลังเจ้าของอนุมัติกฎชุมชนและข้อความความเป็นส่วนตัว) คิวนี้ยังดูและตัดสินรายการที่มีอยู่ได้</div><?php endif; ?>
    <div class="card mi-admin-card mb-3"><div class="card-body">
        กระทู้ <?= (int) ($counts['threads'] ?? 0) ?> (ซ่อน <?= (int) ($counts['threads_hidden'] ?? 0) ?>) · ความเห็น <?= (int) ($counts['posts'] ?? 0) ?> (ซ่อน <?= (int) ($counts['posts_hidden'] ?? 0) ?>) · รีวิว <?= (int) ($counts['reviews'] ?? 0) ?> (ซ่อน <?= (int) ($counts['reviews_hidden'] ?? 0) ?>) · รอตรวจ <strong><?= (int) ($counts['open_flags'] ?? 0) ?></strong> รายการ
    </div></div>
    <div class="card mi-admin-card mi-table-card"><div class="card-header"><h2>รอการตัดสิน (<?= count($queue) ?>)</h2></div>
        <div class="table-responsive"><table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th>ประเภท</th><th>ข้อความ</th><th>ผู้เขียน</th><th>ถูกแจ้ง</th><th>สถานะ</th><th>การตัดสิน</th></tr></thead>
            <tbody>
            <?php if (!$queue): ?><tr><td colspan="6" class="text-muted">ไม่มีรายการรอตรวจ</td></tr><?php endif; ?>
            <?php foreach ($queue as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($typeTh[$r['target_type']] ?? (string) $r['target_type']) ?> #<?= (int) $r['target_id'] ?></td>
                    <td style="max-width:420px"><?= htmlspecialchars((string) $r['preview']) ?></td>
                    <td><small><?= htmlspecialchars((string) $r['handle']) ?></small></td>
                    <td><?= (int) $r['flags'] ?> คน<br><small class="text-muted"><?= htmlspecialchars(implode(', ', array_map(static fn(string $k): string => $reasonTh[$k] ?? $k, explode(',', (string) $r['reasons'])))) ?></small></td>
                    <td><span class="badge <?= $r['status'] === 'hidden' ? 'badge-warning' : 'badge-light' ?>"><?= $r['status'] === 'hidden' ? 'ซ่อนอยู่' : htmlspecialchars((string) $r['status']) ?></span></td>
                    <td>
                        <form class="form-inline" method="post" action="<?= $base ?>/admin/community-moderation/action">
                            <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="type" value="<?= htmlspecialchars((string) $r['target_type']) ?>"><input type="hidden" name="id" value="<?= (int) $r['target_id'] ?>">
                            <button class="btn btn-sm btn-danger mr-1" type="submit" name="action" value="uphold">ซ่อนต่อ</button>
                            <button class="btn btn-sm btn-success mr-1" type="submit" name="action" value="dismiss">แสดงอีกครั้ง</button>
                            <?php if ($r['target_type'] === 'thread'): ?><button class="btn btn-sm btn-ghost" type="submit" name="action" value="lock">ล็อก</button><?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div></div>
<?php endif; ?>
