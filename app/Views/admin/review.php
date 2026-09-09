<?php $authz=new App\Services\Auth\AuthorizationService(); $canCorrect=$authz->can($auth_user ?? null,'review.correct'); $canDecide=$authz->can($auth_user ?? null,'review.decide'); ?>
<section class="container page admin-page">
<?php require __DIR__.'/_nav.php'; ?>
<span class="eyebrow">HUMAN REVIEW</span>
<h1>ตรวจ Observation</h1>
<p class="muted">ข้อมูลจาก collector ต้องผ่านการตัดสินใจของคนก่อนเข้าสู่ accepted observations และ price snapshots</p>

<div class="metric-grid">
    <div class="metric"><span>Pending</span><b><?= (int)$summary['total_pending'] ?></b></div>
    <div class="metric"><span>Corrected</span><b><?= (int)$summary['corrected'] ?></b></div>
    <div class="metric"><span>Approved</span><b><?= (int)$summary['approved'] ?></b></div>
    <div class="metric"><span>Rejected</span><b><?= (int)$summary['rejected'] ?></b></div>
    <div class="metric"><span>Excluded</span><b><?= (int)$summary['excluded'] ?></b></div>
</div>

<div class="card table-card">
<table>
<thead><tr><th>Observation</th><th>Candidate/evidence</th><th>Extracted</th><th>Reviewed values</th><th>Validation lane</th><th>Correct</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($observations as $o): ?>
<tr>
    <td>#<?= (int)$o['id'] ?><small><?= htmlspecialchars($o['source_name'] ?? 'unknown source') ?><?= str_contains((string)($o['source_key'] ?? ''),'mock') ? ' · MOCK TEST DATA' : '' ?></small></td>
    <td><b><?= htmlspecialchars($o['raw_title'] ?? '-') ?></b><small><?= htmlspecialchars($o['source_url_encrypted'] ?? '-') ?></small><small>Evidence <?= htmlspecialchars($o['evidence_quality'] ?? '-') ?> / <?= htmlspecialchars($o['evidence_type'] ?? '-') ?></small></td>
    <td>฿<?= number_format((float)$o['display_price']) ?><small><?= htmlspecialchars($o['price_type'] ?? '-') ?> · <?= htmlspecialchars($o['listing_type'] ?? '-') ?> · <?= htmlspecialchars($o['condition_level'] ?? '-') ?></small><small>Warranty <?= $o['warranty_months'] === null ? 'unknown' : (int)$o['warranty_months'].' months' ?></small><small><?= htmlspecialchars($o['observed_at'] ?? '-') ?></small></td>
    <td><?= htmlspecialchars($o['full_name']) ?><small>Final product #<?= (int)$o['final_values']['product_id'] ?> · <?= htmlspecialchars((string)$o['final_values']['price_type']) ?> · ฿<?= number_format((float)$o['final_values']['price']) ?></small><small><?= htmlspecialchars((string)$o['final_values']['listing_type']) ?> · <?= htmlspecialchars((string)$o['final_values']['condition_level']) ?> · warranty <?= $o['final_values']['warranty_months'] === null ? 'unknown' : (int)$o['final_values']['warranty_months'].' months' ?></small><?php foreach($o['corrections'] as $c): ?><small>Corrected <?= htmlspecialchars($c['field_name']) ?>: <?= htmlspecialchars((string)$c['original_value']) ?> → <?= htmlspecialchars((string)$c['corrected_value']) ?></small><?php endforeach; ?></td>
    <td><span class="badge"><?= htmlspecialchars($o['lane'] ?? '-') ?></span><small><?= htmlspecialchars($o['reason_codes'] ?? '[]') ?></small><?php if(!empty($o['quality_flags'])): ?><small><?= htmlspecialchars(implode(', ', $o['quality_flags'])) ?></small><?php endif; ?></td>
    <td>
        <?php if($canCorrect): ?>
        <form method="post" action="<?= $base ?>/admin/review-queue/correction" class="review-actions">
            <input type="hidden" name="_token" value="<?= \App\Core\Csrf::token() ?>">
            <input type="hidden" name="observation_id" value="<?= (int)$o['id'] ?>">
            <select name="field_name">
                <option value="price">Price</option>
                <option value="price_type">Price type</option>
                <option value="product_id">Product</option>
                <option value="listing_type">Listing type</option>
                <option value="condition_level">Condition</option>
                <option value="warranty_months">Warranty months</option>
                <option value="observed_at">Observed at</option>
            </select>
            <input name="corrected_value" list="review-products-<?= (int)$o['id'] ?>" placeholder="Corrected value">
            <datalist id="review-products-<?= (int)$o['id'] ?>">
                <?php foreach($products as $p): ?><option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['full_name']) ?></option><?php endforeach; ?>
                <option value="asking"><option value="sold"><option value="trade_in"><option value="new">
                <option value="single_item"><option value="bundle"><option value="whole_pc"><option value="wanted"><option value="unknown">
                <option value="new"><option value="like_new"><option value="good"><option value="fair"><option value="poor"><option value="unknown">
            </datalist>
            <textarea name="reason" placeholder="เหตุผลการแก้ไข" required></textarea>
            <button class="small" type="submit">Save correction</button>
        </form>
        <?php else: ?>
            <button class="small disabled-control" type="button" disabled>แก้ไขรายฟิลด์ — ไม่มีสิทธิ์</button>
        <?php endif; ?>
    </td>
    <td>
        <?php if($canDecide): ?>
        <form method="post" action="<?= $base ?>/admin/review-queue/decision" class="review-actions">
            <input type="hidden" name="_token" value="<?= \App\Core\Csrf::token() ?>">
            <input type="hidden" name="observation_id" value="<?= (int)$o['id'] ?>">
            <textarea name="notes" placeholder="หมายเหตุสำหรับ audit"></textarea>
            <button class="small" name="decision" value="approved">Approve</button>
            <button class="small danger" name="decision" value="rejected">Reject</button>
            <button class="small ghost" name="decision" value="excluded">Exclude</button>
        </form>
        <?php else: ?>
            <button class="small disabled-control" type="button" disabled>Review decision unavailable</button>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
<?php if(!$observations): ?><tr><td colspan="7" class="empty-cell">ไม่มี observation ที่รอตรวจ</td></tr><?php endif; ?>
</tbody>
</table>
</div>
</section>
