<?php
/** LS-14 Module 8: @var list<array> $rows  @var list<array> $disputes  @var bool $ready */
$token = htmlspecialchars(\App\Core\Csrf::token());
$badge = ['pending' => 'badge-warning', 'verified' => 'badge-success', 'rejected' => 'badge-secondary', 'disputed' => 'badge-danger'];
?>
<?= mi_page_header($base, ['Market data', 'Seller reports'], htmlspecialchars(t('v.x9862e9d0')), htmlspecialchars(t('v.hg_14_4'))) ?>
<?php if (!$ready): ?>
    <div class="alert alert-warning">ฐานข้อมูลนี้ยังไม่มีตารางรายงาน ให้รัน <code>php cli/migrate.php database/migrations/025_seller_reports.sql</code> (เจ้าของเป็นผู้รันบนฐานข้อมูลจริง)</div>
<?php else: ?>
<?php if ($disputes): ?>
<div class="card mi-admin-card mb-3"><div class="card-header"><h2><?= htmlspecialchars(t('v.x4f5b48fd')) ?><?= count($disputes) ?>)</h2></div>
    <div class="table-responsive"><table class="table mi-admin-table mb-0"><thead><tr><th><?= htmlspecialchars(t('v.xa43ca823')) ?></th><th><?= htmlspecialchars(t('v.xc7450f3c')) ?></th><th><?= htmlspecialchars(t('v.x6df8fbf0')) ?></th><th><?= htmlspecialchars(t('v.xb75533da')) ?></th><th></th></tr></thead><tbody>
    <?php foreach ($disputes as $d): ?><tr><td><?= htmlspecialchars((string) ($d['last4'] ?? '-')) ?></td><td><?= htmlspecialchars((string) $d['explanation']) ?></td><td><?= htmlspecialchars((string) $d['contact_email']) ?></td><td><?= htmlspecialchars(substr((string) $d['created_at'], 0, 16)) ?><br><small class="<?= !empty($d['overdue']) ? 'text-danger' : 'text-muted' ?>"><?= (int) ($d['age_days'] ?? 0) ?> <?= htmlspecialchars(t('v.x08f6a033')) ?><?= !empty($d['overdue']) ? t('v.xa740d285') : '' ?></small></td>
        <td><form method="post" action="<?= $base ?>/admin/seller-reports/action"><input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="dispute_id" value="<?= (int) $d['id'] ?>"><button class="btn btn-sm btn-outline-secondary" type="submit" name="decision" value="close_dispute"><?= htmlspecialchars(t('v.xa98012e1')) ?></button></form></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
<?php endif; ?>
<div class="card mi-admin-card mi-table-card"><div class="card-header"><h2><?= htmlspecialchars(t('v.x8790d80e')) ?><?= count($rows) ?>)</h2></div>
    <div class="table-responsive"><table class="table table-hover mi-admin-table mb-0">
        <thead><tr><th><?= htmlspecialchars(t('v.xe0e40ba8')) ?></th><th><?= htmlspecialchars(t('v.xa43ca823')) ?></th><th><?= htmlspecialchars(t('v.x57042bac')) ?></th><th><?= htmlspecialchars(t('v.x89ef56de')) ?></th><th><?= htmlspecialchars(t('v.x4d8016a7')) ?></th><th><?= htmlspecialchars(t('v.x92d64201')) ?></th><th><?= htmlspecialchars(t('v.xc9701a0d')) ?></th><th style="min-width:18rem"><?= htmlspecialchars(t('v.x8aba0b36')) ?></th></tr></thead><tbody>
        <?php if (!$rows): ?><tr><td colspan="8" class="text-muted"><?= htmlspecialchars(t('v.x2b27e1c1')) ?></td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?><tr>
            <td><?= htmlspecialchars(\App\Services\Listing\SellerCheck::TYPES[$r['identifier_type']] ?? (string) $r['identifier_type']) ?></td>
            <td>…<?= htmlspecialchars((string) $r['identifier_last4']) ?></td>
            <td><?= htmlspecialchars(\App\Services\Listing\SellerCheck::PROBLEMS[$r['problem_type']] ?? (string) $r['problem_type']) ?></td>
            <td><small><?= htmlspecialchars((string) ($r['note'] ?? '')) ?></small></td>
            <td><?= (int) $r['evidence_count'] ?> <?= htmlspecialchars(t('v.x2564e9fb')) ?></td>
            <td><?= (int) $r['same_identifier_reports'] ?><?= (int) $r['open_disputes'] > 0 ? t('v.xeecdb962') : '' ?></td>
            <td><span class="badge <?= $badge[$r['status']] ?? 'badge-light' ?>"><?= htmlspecialchars((string) $r['status']) ?></span><?php if ($r['moderation_note']): ?><br><small class="text-muted"><?= htmlspecialchars((string) $r['moderation_note']) ?></small><?php endif; ?></td>
            <td><?php if (in_array($r['status'], ['pending', 'disputed'], true)): ?>
                <form class="form-inline" method="post" action="<?= $base ?>/admin/seller-reports/action"><input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <input class="form-control form-control-sm mr-1 mb-1" name="note" maxlength="255" placeholder="<?= htmlspecialchars(t('v.xd26fdbcf')) ?>" aria-label="<?= htmlspecialchars(t('v.xc7450f3c')) ?>">
                    <button class="btn btn-sm btn-success mr-1 mb-1" type="submit" name="decision" value="verified"><?= htmlspecialchars(t('v.xbfed3e5a')) ?></button>
                    <button class="btn btn-sm btn-outline-danger mb-1" type="submit" name="decision" value="rejected"><?= htmlspecialchars(t('v.xa1f9f221')) ?></button></form>
            <?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table></div></div>
<?php endif; ?>
