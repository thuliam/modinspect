<?php
/**
 * LS-21 (R-11): the community report queue. @var list<array> $rows @var array<string,int> $counts @var string $status @var bool $ready @var bool $featureOn
 * A report is a signal. Accepting one changes no price; "send to price review" only creates a PENDING sold-price candidate that a reviewer still decides.
 */
use App\Services\Community\CommunityReports as C;
$token = htmlspecialchars(\App\Core\Csrf::token());
$badge = ['new' => 'badge-warning', 'accepted' => 'badge-success', 'rejected' => 'badge-secondary', 'spam' => 'badge-danger'];
$labels = ['new' => t('v.x1089d21f'), 'accepted' => t('v.x1cff4861'), 'rejected' => t('v.xa1f9f221'), 'spam' => t('v.xdb680854'), 'all' => t('v.xbd5d572d')];
$detail = static function (array $r): string {
    $p = $r['payload']; $out = [];
    switch ($r['report_type']) {
        case 'wrong_product': $out[] = isset($p['should_be_product_id']) ? t('v.x0838108c') . ($r['should_be_name'] ?? '#' . (int) $p['should_be_product_id']) : t('v.x8a6e59bf'); break;
        case 'price_looks_off': $out[] = ($p['direction'] ?? '') === 'higher' ? t('v.xf6f3fb7c') : t('v.x9ba7e23f'); if (isset($p['seen_price'])) { $out[] = t('v.xfe72b8dd') . number_format((int) $p['seen_price']); } break;
        case 'sold_price':
            $out[] = t('v.x820dcb86') . number_format((int) ($p['price'] ?? 0)) . ' · ' . htmlspecialchars((string) ($p['month'] ?? '')); $out[] = t('v.x0e01822f') . (C::CONDITIONS[$p['condition'] ?? ''] ?? '-');
            if (($p['warranty_months'] ?? null) !== null) { $out[] = t('v.xb2807213') . (int) $p['warranty_months'] . t('v.x9e8bc205'); }
            if (!empty($p['has_box'])) { $out[] = t('v.x3db18097'); } if (!empty($p['has_receipt'])) { $out[] = t('v.xb6a7b556'); } break;
        case 'spec_wrong': $out[] = t('v.x427fc654') . (C::SPECS[$p['spec'] ?? ''] ?? '-'); break;
        default: $out[] = t('v.xf64e7f66');
    }
    return implode(' · ', $out);
};
?>
<?= mi_page_header($base, ['Market data', 'Community reports'], htmlspecialchars(t('v.x3aed6989')), htmlspecialchars(t('v.xd8e6f9e1')), '') ?>
<?php if (!$ready): ?>
    <div class="alert alert-warning"><?= htmlspecialchars(t('misc.mig_reports_pre')) ?> <code>php cli/migrate.php database/migrations/026_community_reports.sql</code> <?= htmlspecialchars(t('misc.mig_reports_post')) ?></div>
<?php else: ?>
    <?php if (!$featureOn): ?><div class="alert alert-info">ฟอร์มรายงานสำหรับผู้เข้าชมยังปิดอยู่ (ตั้งค่า <code>COMMUNITY_REPORTS_ENABLED</code> และ <code>COMMUNITY_REPORTS_HMAC_KEY</code> หลังเจ้าของอนุมัติข้อความความเป็นส่วนตัว) คิวนี้ดูและตัดสินรายงานที่มีอยู่ได้ตามปกติ</div><?php endif; ?>
    <div class="card mi-admin-card mb-3"><div class="card-body">
        <form class="form-inline" method="get" action="<?= $base ?>/admin/community-reports">
            <label class="mr-2 mb-0" for="cr-status"><?= htmlspecialchars(t('v.x1d31fb74')) ?></label>
            <select id="cr-status" name="status" class="form-control mr-3" data-autosubmit data-width="220px">
                <?php foreach ($labels as $v => $l): ?><option value="<?= $v ?>"<?= $status === $v ? ' selected' : '' ?>><?= htmlspecialchars($l) ?><?= isset($counts[$v]) ? ' (' . (int) $counts[$v] . ')' : '' ?></option><?php endforeach; ?>
            </select>
            <noscript><button class="btn btn-primary" type="submit"><?= htmlspecialchars(t('v.x1d31fb74')) ?></button></noscript>
        </form>
    </div></div>
    <div class="card mi-admin-card mi-table-card"><div class="card-header"><h2><?= htmlspecialchars(t('v.x8790d80e')) ?><?= count($rows) ?>)</h2></div>
        <div class="table-responsive"><table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= htmlspecialchars(t('v.xb75533da')) ?></th><th><?= htmlspecialchars(t('v.xc218938a')) ?></th><th><?= htmlspecialchars(t('v.xf58fc548')) ?></th><th><?= htmlspecialchars(t('v.x412a9419')) ?></th><th><?= htmlspecialchars(t('v.x603e36c7')) ?></th><th><?= htmlspecialchars(t('v.xc9701a0d')) ?></th><th><?= htmlspecialchars(t('v.xc4ae13e2')) ?></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="7" class="text-muted"><?= htmlspecialchars(t('v.x2b27e1c1')) ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><small><?= htmlspecialchars(substr((string) $r['created_at'], 0, 16)) ?></small></td>
                    <td><a href="<?= $base ?>/price/<?= rawurlencode((string) $r['slug']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string) $r['full_name']) ?></a>
                        <?php if ((int) $r['product_new_reports'] > 1): ?><br><small class="text-muted"><?= (int) $r['product_new_reports'] ?> <?= htmlspecialchars(t('v.xb8d7863f')) ?></small><?php endif; ?></td>
                    <td><?= htmlspecialchars(C::TYPES[$r['report_type']]['label'] ?? (string) $r['report_type']) ?></td>
                    <td><?= htmlspecialchars($detail($r)) ?></td>
                    <td><small><?php if ($r['seen_median'] !== null): ?>฿<?= number_format((float) $r['seen_q1']) ?> – ฿<?= number_format((float) $r['seen_q3']) ?> <?= htmlspecialchars(t('v.x642874fc')) ?><?= number_format((float) $r['seen_median']) ?>)<?php else: ?><?= htmlspecialchars(t('v.x319bc304')) ?><?php endif; ?></small></td>
                    <td><span class="badge <?= $badge[$r['status']] ?? 'badge-light' ?>"><?= htmlspecialchars($labels[$r['status']] ?? (string) $r['status']) ?></span>
                        <?php if ($r['moderation_note']): ?><br><small class="text-muted"><?= htmlspecialchars((string) $r['moderation_note']) ?></small><?php endif; ?>
                        <?php if ($r['candidate_observation_id'] !== null): ?><br><small><a class="text-success" href="<?= $base ?>/admin/review/<?= (int) $r['candidate_observation_id'] ?>"><?= htmlspecialchars(t('v.x76859bca')) ?><?= (int) $r['candidate_observation_id'] ?> <?= htmlspecialchars(t('v.xb5b22694')) ?></a></small><?php endif; ?></td>
                    <td>
                        <?php if ($r['status'] === 'new'): ?>
                            <form class="form-inline" method="post" action="<?= $base ?>/admin/community-reports/action">
                                <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                                <input class="form-control form-control-sm mr-1 mb-1" name="note" maxlength="255" placeholder="<?= htmlspecialchars(t('v.xf04ec555')) ?>" aria-label="<?= htmlspecialchars(t('v.xc7450f3c')) ?>">
                                <button class="btn btn-sm btn-success mr-1 mb-1" type="submit" name="decision" value="accepted"><?= htmlspecialchars(t('v.xc2e84764')) ?></button>
                                <button class="btn btn-sm btn-outline-danger mr-1 mb-1" type="submit" name="decision" value="rejected"><?= htmlspecialchars(t('v.xa1f9f221')) ?></button>
                                <button class="btn btn-sm btn-outline-secondary mb-1" type="submit" name="decision" value="spam"><?= htmlspecialchars(t('v.xdb680854')) ?></button>
                            </form>
                        <?php elseif ($r['status'] === 'accepted'): ?>
                            <?php if ($r['report_type'] === 'sold_price' && $r['candidate_observation_id'] === null): ?>
                                <form method="post" action="<?= $base ?>/admin/community-reports/action">
                                    <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                                    <button class="btn btn-sm btn-primary" type="submit" name="decision" value="candidate" title="<?= htmlspecialchars(t('v.x3521d6ae')) ?>"><?= htmlspecialchars(t('v.x6abdcf97')) ?></button>
                                </form>
                            <?php elseif (in_array($r['report_type'], ['wrong_product', 'spec_wrong'], true)): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/admin/products"><?= htmlspecialchars(t('v.x5dc0ab7b')) ?></a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div></div>
<?php endif; ?>
