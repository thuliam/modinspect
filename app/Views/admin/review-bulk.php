<?php /** LS-90 T8: confirm one decision for several pending listings. Inputs: $rows (BulkReview::preview), $decision, $base. */ ?>
<?php $label = t('review.bulk.' . $decision); ?>
<?= mi_page_header($base, ['Market data', 'Review queue'], htmlspecialchars($label), htmlspecialchars(t('review.bulk.help')), '<a class="btn btn-outline-secondary" href="' . $base . '/admin/review-queue">' . htmlspecialchars(t('review.bulk.back')) . '</a>') ?>
<form method="post" action="<?= $base ?>/admin/review-queue/bulk-apply">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
    <input type="hidden" name="decision" value="<?= htmlspecialchars($decision) ?>">
    <div class="card mi-admin-card mi-table-card">
        <div class="table-responsive">
            <table class="table table-hover mi-admin-table mb-0">
                <thead><tr><th></th><th>#</th><th><?= htmlspecialchars(t('price.col.model')) ?></th><th class="text-right"><?= htmlspecialchars(t('review.bulk.price')) ?></th><th><?= htmlspecialchars(t('review.bulk.text')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): $flagged = $decision === 'approved' && $row['warnings'] !== []; ?>
                    <tr<?= $row['warnings'] !== [] ? ' class="table-warning"' : '' ?>>
                        <td><input type="checkbox" name="ids[]" value="<?= (int)$row['id'] ?>" aria-label="#<?= (int)$row['id'] ?>"<?= $flagged ? '' : ' checked' ?>></td>
                        <td><a href="<?= $base ?>/admin/review/<?= (int)$row['id'] ?>" target="_blank" rel="noopener">#<?= (int)$row['id'] ?></a></td>
                        <td><?= htmlspecialchars($row['model']) ?></td>
                        <td class="text-right">&#3647;<?= number_format($row['price']) ?></td>
                        <td><?php foreach ($row['warnings'] as $code): ?><span class="badge badge-warning mr-1"><?= htmlspecialchars(t('review.warn.' . $code)) ?></span><?php endforeach; ?><small class="d-block text-muted"><?= htmlspecialchars(mb_substr($row['title'], 0, 220)) ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-body">
            <div class="form-group"><label for="bulk-note"><?= htmlspecialchars(t('review.bulk.note')) ?></label><input id="bulk-note" class="form-control" name="note" maxlength="200"></div>
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="bulk-confirm" name="confirm" value="1" required><label class="form-check-label" for="bulk-confirm"><?= htmlspecialchars(t('review.bulk.confirm')) ?></label></div>
            <button class="btn <?= $decision === 'approved' ? 'btn-success' : 'btn-danger' ?>" type="submit"><?= htmlspecialchars($label) ?></button>
        </div>
    </div>
</form>
