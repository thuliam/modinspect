<?php
/**
 * LS-15: the right-hand summary of the Build page: price list with TOTAL, the six spec-based stats (radar) and the compatibility checks.
 * One template for the live page (replaced by JavaScript after every pick) and the saved page.
 * @var array<string,mixed> $data  BuildPreview::run output   @var string $base   @var string $mode  'live' | 'saved'
 */
use App\Services\Build\BuildStats;

$t = $data['totals'];
$statusText = ['ok' => 'ผ่าน', 'fail' => 'ไม่ผ่าน', 'unknown' => 'ตรวจไม่ได้'];
$statusIcon = ['ok' => 'fa-check-circle text-success', 'fail' => 'fa-exclamation-triangle text-danger', 'unknown' => 'fa-question-circle text-warning'];
$basisBadge = ['used' => 'badge-success', 'new_ref' => 'badge-warning', 'none' => 'badge-secondary'];
$axes = array_map(static fn(array $a): array => ['l' => $a['label'], 'v' => $a['value']], $data['stats']);
?>
<div class="mi-summary" data-blocked="<?= $data['blocked'] ? '1' : '0' ?>" data-chosen="<?= (int) $data['chosen_count'] ?>" data-total="<?= (float) $t['expected'] ?>">
    <section class="card mb-3">
        <div class="card-body">
            <h2 class="h5 mb-3">รายการสเปกและราคา</h2>
            <ul class="list-unstyled mb-0 mi-price-list">
                <?php foreach ($data['items'] as $it): ?>
                    <li class="py-2 border-bottom border-secondary">
                        <small class="text-muted d-block"><?= htmlspecialchars($it['role_label']) ?></small>
                        <?php if ($it['chosen']): ?>
                            <div class="d-flex">
                                <span class="flex-grow-1 pr-2"><a href="<?= $base ?>/price/<?= rawurlencode($it['slug']) ?>"><?= htmlspecialchars($it['name']) ?></a><br>
                                    <span class="badge <?= $basisBadge[$it['basis']] ?? 'badge-secondary' ?>"><?= htmlspecialchars($it['basis_label']) ?></span>
                                    <?php if ($it['basis'] === 'new_ref' && $it['checked_on']): ?><small class="text-muted">ตรวจ <?= htmlspecialchars((string) $it['checked_on']) ?></small><?php endif; ?></span>
                                <span class="text-right text-nowrap"><?= $it['basis'] === 'none' ? '<span class="text-muted">—</span>' : '฿' . number_format((float) $it['expected']) ?></span>
                            </div>
                        <?php else: ?>
                            <span class="text-muted">ยังไม่ได้เลือก</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="d-flex justify-content-between align-items-baseline border-top pt-3 mt-3">
                <span class="h5 mb-0">รวม</span>
                <span class="h4 mb-0 js-total" data-value="<?= (float) $t['expected'] ?>">฿<?= number_format((float) $t['expected']) ?></span>
            </div>
            <?php if ($t['used'] > 0 && ($t['low'] != $t['expected'] || $t['high'] != $t['expected'])): ?>
                <p class="small text-muted mb-1">ช่วงที่พบ ฿<?= number_format((float) $t['low']) ?>–฿<?= number_format((float) $t['high']) ?></p>
            <?php endif; ?>
            <?php if ($data['remaining'] !== null): ?>
                <p class="small mb-1 <?= $data['remaining'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= $data['remaining'] >= 0 ? 'อยู่ในงบ เหลือ ฿' . number_format($data['remaining']) : 'เกินงบ ฿' . number_format(-$data['remaining']) ?></p>
            <?php endif; ?>
            <?php if ($data['totals_note'] !== ''): ?><p class="small text-muted mb-0"><?= htmlspecialchars($data['totals_note']) ?></p><?php endif; ?>
        </div>
    </section>

    <?php if ($data['stats']): ?>
    <section class="card mb-3">
        <div class="card-body">
            <h2 class="h5 mb-2">ค่าพลังของสเปกนี้</h2>
            <div class="mi-radar-box"><canvas class="js-radar" data-axes="<?= htmlspecialchars(json_encode($axes, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>" role="img" aria-label="ค่าพลัง <?= htmlspecialchars(implode(', ', array_map(static fn($a) => $a['l'] . ' ' . ($a['v'] === null ? 'ไม่มีข้อมูล' : (string) round($a['v'])), $axes))) ?>"></canvas></div>
            <ul class="list-unstyled small mb-2 mt-2">
                <?php foreach ($data['stats'] as $a): ?>
                    <li class="d-flex justify-content-between"><span><?= htmlspecialchars($a['label']) ?></span><b><?= $a['value'] === null ? 'ไม่มีข้อมูล' : number_format($a['value']) ?></b></li>
                <?php endforeach; ?>
            </ul>
            <p class="small text-muted mb-0"><?= htmlspecialchars(BuildStats::NOTE) ?></p>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($data['checks']): ?>
    <section class="card mb-3">
        <div class="card-body">
            <h2 class="h5 mb-2">ชิ้นส่วนเข้ากันได้ไหม</h2>
            <ul class="list-unstyled small mb-0">
                <?php foreach ($data['checks'] as $c): ?>
                    <li class="mb-2"><i class="fas <?= $statusIcon[$c['status']] ?? 'fa-question-circle' ?>" aria-hidden="true"></i> <b><?= htmlspecialchars($c['label']) ?>: <?= $statusText[$c['status']] ?? '' ?></b><br><span class="text-muted"><?= htmlspecialchars($c['text']) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php endif; ?>
</div>
