<?php
/** LS-14 Module 9: @var list<array> $rows  @var bool $ready */
$token = htmlspecialchars(\App\Core\Csrf::token());
$badge = ['submitted' => 'badge-warning', 'approved' => 'badge-success', 'rejected' => 'badge-danger', 'withdrawn' => 'badge-secondary'];
?>
<?= mi_page_header($base, ['Market data', 'Seller listings'], htmlspecialchars(t('v.x5de075c2')), htmlspecialchars(t('v.hg_17_hg_16'))) ?>
<?php if (!$ready): ?>
    <div class="alert alert-warning">ฐานข้อมูลนี้ยังไม่มีตารางประกาศ ให้รัน <code>php cli/migrate.php database/migrations/023_members.sql</code> และ <code>024_listing_attributes_and_seller_listings.sql</code> ก่อน (เจ้าของเป็นผู้รันบนฐานข้อมูลจริง)</div>
<?php else: ?>
<div class="card mi-admin-card mi-table-card">
    <div class="card-header"><h2><?= htmlspecialchars(t('v.xf1336909')) ?><?= count($rows) ?>)</h2></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= htmlspecialchars(t('v.x75cb8be2')) ?></th><th class="text-right"><?= htmlspecialchars(t('v.xe3efa74f')) ?></th><th><?= htmlspecialchars(t('v.x412a9419')) ?></th><th><?= htmlspecialchars(t('v.x2564e9fb')) ?></th><th><?= htmlspecialchars(t('v.xc9701a0d')) ?></th><th class="mi-th-wide"><?= htmlspecialchars(t('v.x8aba0b36')) ?></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6" class="text-muted"><?= htmlspecialchars(t('v.x49e63fd2')) ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $r['full_name']) ?><br><small class="text-muted"><?= htmlspecialchars(substr((string) $r['updated_at'], 0, 16)) ?></small></td>
                    <td class="text-right">฿<?= number_format((float) $r['asking_price']) ?></td>
                    <td><small><?php foreach (\App\Services\Listing\ListingAttributes::describe($r) as $d): ?><?= htmlspecialchars($d['label'] . ': ' . $d['value']) ?><br><?php endforeach; ?></small></td>
                    <td><?= (int) $r['photo_count'] ?> <?= htmlspecialchars(t('v.x2564e9fb')) ?><br><?php foreach ($photos[(int) $r['id']] ?? [] as $ph): ?><a href="<?= $base ?>/seller/photos/<?= htmlspecialchars((string) $r['uuid']) ?>/<?= htmlspecialchars((string) $ph['file_name']) ?>" target="_blank" rel="noopener"><img src="<?= $base ?>/seller/photos/<?= htmlspecialchars((string) $r['uuid']) ?>/<?= htmlspecialchars((string) $ph['file_name']) ?>" alt="<?= htmlspecialchars(t('v.x9a0e8c74')) ?>" width="48" height="48" loading="lazy" class="mr-1 mb-1"></a><?php endforeach; ?></td>
                    <td><span class="badge <?= $badge[$r['status']] ?? 'badge-light' ?>"><?= htmlspecialchars((string) $r['status']) ?></span><?php if ($r['moderation_note']): ?><br><small class="text-muted"><?= htmlspecialchars((string) $r['moderation_note']) ?></small><?php endif; ?></td>
                    <td>
                        <?php if ($r['status'] === 'submitted'): ?>
                        <form class="form-inline" method="post" action="<?= $base ?>/admin/seller-listings/action">
                            <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <input class="form-control form-control-sm mr-1 mb-1" name="note" maxlength="255" placeholder="<?= htmlspecialchars(t('v.xd26fdbcf')) ?>" aria-label="<?= htmlspecialchars(t('v.xc7450f3c')) ?>">
                            <button class="btn btn-sm btn-success mr-1 mb-1" type="submit" name="decision" value="approved"><?= htmlspecialchars(t('v.x8806565a')) ?></button>
                            <button class="btn btn-sm btn-outline-danger mb-1" type="submit" name="decision" value="rejected"><?= htmlspecialchars(t('v.xa1f9f221')) ?></button>
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
