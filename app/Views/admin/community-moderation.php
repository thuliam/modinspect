<?php
/**
 * LS-24 M9: forum moderation queue. @var list<array> $queue @var array<string,int> $counts @var bool $ready @var bool $featureOn
 * Three different members' flags hide an item at once; a person decides here (uphold = keep hidden, dismiss = show again). Every decision is audited.
 */
$token = htmlspecialchars(\App\Core\Csrf::token());
$typeTh = ['thread' => t('v.x5c8fd392'), 'post' => t('v.x775f736e'), 'review' => t('v.x342b5a51')];
$reasonTh = \App\Services\Community\Forum::REASONS;
?>
<?= mi_page_header($base, ['Market data', 'Community moderation'], htmlspecialchars(t('v.x8a845048')), htmlspecialchars(t('v.x3a926b56')), '') ?>
<?php if (!$ready): ?>
    <div class="alert alert-warning"><?= htmlspecialchars(t('misc.mig_community_pre')) ?> <code>php cli/migrate.php database/migrations/027_community_forum.sql</code> <?= htmlspecialchars(t('misc.mig_community_post')) ?></div>
<?php else: ?>
    <?php if (!$featureOn): ?><div class="alert alert-info">ชุมชนสำหรับผู้เข้าชมยังปิดอยู่ (ตั้งค่า <code>COMMUNITY_FORUM_ENABLED</code> หลังเจ้าของอนุมัติกฎชุมชนและข้อความความเป็นส่วนตัว) คิวนี้ยังดูและตัดสินรายการที่มีอยู่ได้</div><?php endif; ?>
    <div class="card mi-admin-card mb-3"><div class="card-body">
        <?= htmlspecialchars(t('v.x5c8fd392')) ?> <?= (int) ($counts['threads'] ?? 0) ?> <?= htmlspecialchars(t('v.x641979b4')) ?> <?= (int) ($counts['threads_hidden'] ?? 0) ?><?= htmlspecialchars(t('v.xc362a617')) ?> <?= (int) ($counts['posts'] ?? 0) ?> <?= htmlspecialchars(t('v.x641979b4')) ?> <?= (int) ($counts['posts_hidden'] ?? 0) ?><?= htmlspecialchars(t('v.xb9d57f7e')) ?> <?= (int) ($counts['reviews'] ?? 0) ?> <?= htmlspecialchars(t('v.x641979b4')) ?> <?= (int) ($counts['reviews_hidden'] ?? 0) ?><?= htmlspecialchars(t('v.x7398bb72')) ?> <strong><?= (int) ($counts['open_flags'] ?? 0) ?></strong> <?= htmlspecialchars(t('v.x4514cb25')) ?>
    </div></div>
    <div class="card mi-admin-card mi-table-card"><div class="card-header"><h2><?= htmlspecialchars(t('v.x9e79ba09')) ?><?= count($queue) ?>)</h2></div>
        <div class="table-responsive"><table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th><?= htmlspecialchars(t('v.x3f99d001')) ?></th><th><?= htmlspecialchars(t('v.xbb7af620')) ?></th><th><?= htmlspecialchars(t('v.x4da2acd6')) ?></th><th><?= htmlspecialchars(t('v.x0a6547ae')) ?></th><th><?= htmlspecialchars(t('v.xc9701a0d')) ?></th><th><?= htmlspecialchars(t('v.xc4ae13e2')) ?></th></tr></thead>
            <tbody>
            <?php if (!$queue): ?><tr><td colspan="6" class="text-muted"><?= htmlspecialchars(t('v.xa04de871')) ?></td></tr><?php endif; ?>
            <?php foreach ($queue as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($typeTh[$r['target_type']] ?? (string) $r['target_type']) ?> #<?= (int) $r['target_id'] ?></td>
                    <td class="mi-cell-max"><?= htmlspecialchars((string) $r['preview']) ?></td>
                    <td><small><?= htmlspecialchars((string) $r['handle']) ?></small></td>
                    <td><?= (int) $r['flags'] ?> <?= htmlspecialchars(t('v.xe104a02b')) ?><br><small class="text-muted"><?= htmlspecialchars(implode(', ', array_map(static fn(string $k): string => $reasonTh[$k] ?? $k, explode(',', (string) $r['reasons'])))) ?></small></td>
                    <td><span class="badge <?= $r['status'] === 'hidden' ? 'badge-warning' : 'badge-light' ?>"><?= $r['status'] === 'hidden' ? t('v.x43c58f67') : htmlspecialchars((string) $r['status']) ?></span></td>
                    <td>
                        <form class="form-inline" method="post" action="<?= $base ?>/admin/community-moderation/action">
                            <input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="type" value="<?= htmlspecialchars((string) $r['target_type']) ?>"><input type="hidden" name="id" value="<?= (int) $r['target_id'] ?>">
                            <button class="btn btn-sm btn-danger mr-1" type="submit" name="action" value="uphold"><?= htmlspecialchars(t('v.xdf79ab9f')) ?></button>
                            <button class="btn btn-sm btn-success mr-1" type="submit" name="action" value="dismiss"><?= htmlspecialchars(t('v.xf451bc8b')) ?></button>
                            <?php if ($r['target_type'] === 'thread'): ?><button class="btn btn-sm btn-ghost" type="submit" name="action" value="lock"><?= htmlspecialchars(t('v.xa4dfbefe')) ?></button><?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div></div>
<?php endif; ?>
