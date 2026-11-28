<?php
$authz = new App\Services\Auth\AuthorizationService();
$canCorrect = $authz->can($auth_user ?? null, 'review.correct');
$canDecide = $authz->can($auth_user ?? null, 'review.decide');
$o = $observation;
$final = $o['final_values'] ?? [];
$productNames = array_column($products, 'full_name', 'id');
$finalProductName = $productNames[(int)($final['product_id'] ?? $o['product_id'])] ?? $o['full_name'];
$lane = strtolower((string)($o['lane'] ?? 'unknown'));
$isPending = (string)$o['verified_status'] === 'pending';
$sourceUrl = (string)($o['source_url_encrypted'] ?? '');
$hasSourceLink = preg_match('#^https?://#i', $sourceUrl) === 1;
$priceLabel = (string)($o['price_type'] ?? 'asking') === 'sold' ? t('v.sold_final_price') : t('v.listing_asking_price');
$backPath = $back_path ?? '/admin/review-queue';
$currentPath = '/admin/review/' . (int)$o['id'] . '?return_to=' . rawurlencode($backPath);
$renderValue = function (mixed $value): string {
    if ($value === null || $value === '') return 'unknown';
    return (string)$value;
};
$evidenceMetadata = function (string $excerpt): array {
    $fields = [];
    foreach (preg_split('/\R/', $excerpt) ?: [] as $line) {
        if (!str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key !== '') $fields[strtoupper($key)] = $value;
    }
    return $fields;
};
$categoryIcon = function (string $category): string {
    return match (strtolower($category)) {
        'cpu' => 'fa-microchip',
        'gpu' => 'fa-tv',
        'motherboard' => 'fa-server',
        'ram' => 'fa-memory',
        'storage' => 'fa-hdd',
        'psu' => 'fa-plug',
        'cooling' => 'fa-snowflake',
        default => 'fa-cube',
    };
};
$productImagePath = trim((string)($o['product_image_path'] ?? ''));
$productImageUrl = $productImagePath !== '' && is_file(ROOT_PATH . '/public/' . ltrim($productImagePath, '/')) ? $base . '/' . ltrim($productImagePath, '/') : null;
$productIcon = $categoryIcon((string)($o['category_slug'] ?? ''));
$resolutionDiagnostic = $o['resolution_diagnostic'] ?? null;
$resolutionConfidenceLabel = is_array($resolutionDiagnostic) && isset($resolutionDiagnostic['confidence'])
    ? number_format((float)$resolutionDiagnostic['confidence'] * 100, 1) . '%'
    : 'unknown';
$displayTitle = (string)($o['display_title'] ?? $o['raw_title'] ?? '-');
$extractedDisplayTitle = (string)($o['extracted_display_title'] ?? $o['extracted_data_map']['product_name'] ?? $o['extracted_data_map']['product'] ?? $displayTitle);
$provenanceQuality = is_array($o['provenance_quality'] ?? null) ? $o['provenance_quality'] : [
    'label' => 'GENERIC SOURCE',
    'quality' => 'GENERIC_SOURCE',
    'reason' => 'Source is known, but no exact listing/result item reference is available.',
    'fields' => [],
];
$provenanceFields = is_array($provenanceQuality['fields'] ?? null) ? $provenanceQuality['fields'] : [];
$evidenceFields = $evidenceMetadata((string)($o['excerpt'] ?? ''));
?>
<?php ob_start(); ?><a class="btn btn-outline-secondary" href="<?= $base . htmlspecialchars($backPath) ?>"><i class="fas fa-arrow-left mr-2"></i><?= htmlspecialchars(t('v.back_to_queue')) ?></a><?php $dxA = ob_get_clean(); ?>
<?= mi_page_header($base, ['Human Review Detail'], htmlspecialchars((string)$finalProductName), '', $dxA) ?>

<section class="review-detail-summary">
    <div class="review-summary-image-block">
        <div class="review-thumb review-thumb-lg" aria-hidden="true">
            <?php if ($productImageUrl): ?>
                <img src="<?= htmlspecialchars($productImageUrl) ?>" alt="">
            <?php else: ?>
                <i class="fas <?= htmlspecialchars($productIcon) ?>"></i>
            <?php endif; ?>
        </div>
        <small><?= htmlspecialchars(t('v.product_master_image')) ?></small>
    </div>
    <div>
        <span class="dataset-badge dataset-<?= strtolower((string)$o['dataset_label']) ?>"><?= htmlspecialchars((string)$o['dataset_label']) ?></span>
        <span class="lane-badge lane-<?= htmlspecialchars($lane) ?> mt-2"><?= htmlspecialchars(strtoupper($lane)) ?></span>
        <span class="mi-status-badge mi-status-<?= htmlspecialchars((string)$o['verified_status']) ?> mt-2"><?= htmlspecialchars((string)$o['verified_status']) ?></span>
        <?php if (!empty($o['review_purpose'])): ?><small><?= htmlspecialchars((string)$o['review_purpose']) ?></small><?php endif; ?>
        <?php if (!empty($o['review_run_id'])): ?><small><?= htmlspecialchars((string)$o['review_run_id']) ?></small><?php endif; ?>
    </div>
    <div>
        <h2><?= htmlspecialchars((string)$finalProductName) ?></h2>
        <p><?= htmlspecialchars(t('v.matched_to_product_master')) ?><?= (int)($final['product_id'] ?? $o['product_id']) ?></p>
    </div>
    <div class="review-detail-price">
        <b>&#3647;<?= number_format((float)($final['price'] ?? $o['display_price'])) ?></b>
        <span><?= htmlspecialchars($priceLabel) ?></span>
    </div>
    <dl>
        <div><dt><?= htmlspecialchars(t('v.source')) ?></dt><dd><?= htmlspecialchars((string)($o['source_name'] ?? t('v.unknown_source'))) ?></dd></div>
        <div><dt><?= htmlspecialchars(t('v.observed')) ?></dt><dd><?= htmlspecialchars((string)($final['observed_at'] ?? $o['observed_at'] ?? '-')) ?></dd></div>
        <div><dt><?= htmlspecialchars(t('v.observation')) ?></dt><dd>#<?= (int)$o['id'] ?></dd></div>
    </dl>
</section>

<div class="review-detail-workspace">
    <div class="review-detail-main">
        <section class="review-panel">
            <span class="eyebrow"><?= htmlspecialchars(t('v.b_original_market_evidence')) ?></span>
            <p class="review-evidence-title"><?= htmlspecialchars($displayTitle) ?></p>
            <?php if (!empty($o['title_was_normalized'])): ?>
                <div class="review-green-note"><?= htmlspecialchars(t('v.display_title_excludes_known_modinsp')) ?></div>
            <?php endif; ?>
            <div class="review-evidence-meta">
                <div>
                    <span><?= htmlspecialchars(t('v.source')) ?></span>
                    <b><?= htmlspecialchars((string)($o['source_name'] ?? t('v.unknown_source'))) ?></b>
                </div>
                <div>
                    <span><?= htmlspecialchars(t('v.observed')) ?></span>
                    <b><?= htmlspecialchars((string)($final['observed_at'] ?? $o['observed_at'] ?? '-')) ?></b>
                </div>
                <div>
                    <span><?= htmlspecialchars(t('v.captured_price')) ?></span>
                    <b>&#3647;<?= number_format((float)$o['display_price']) ?></b>
                    <small><?= htmlspecialchars($priceLabel) ?></small>
                </div>
                <div>
                    <span><?= htmlspecialchars(t('v.price_text')) ?></span>
                    <b><?= htmlspecialchars($renderValue($o['raw_price_text'] ?? null)) ?></b>
                </div>
                <div>
                    <span><?= htmlspecialchars(t('v.condition')) ?></span>
                    <b><?= htmlspecialchars($renderValue($o['raw_condition_text'] ?? $o['condition_level'] ?? null)) ?></b>
                </div>
                <div>
                    <span><?= htmlspecialchars(t('v.warranty')) ?></span>
                    <b><?= htmlspecialchars($renderValue($o['raw_warranty_text'] ?? null)) ?></b>
                </div>
            </div>
            <div class="<?= ($provenanceQuality['quality'] ?? '') === 'LISTING_LEVEL' ? 'review-green-note' : 'review-decision-alert' ?>">
                <strong><?= htmlspecialchars(t('v.provenance_2')) ?> <?= htmlspecialchars((string)($provenanceQuality['label'] ?? 'GENERIC SOURCE')) ?></strong>
                <?php if (($provenanceQuality['quality'] ?? '') === 'SEARCH_RESULT_LEVEL'): ?>
                    <p><?= htmlspecialchars(t('v.x72e0f748')) ?></p>
                <?php else: ?>
                    <p><?= htmlspecialchars((string)($provenanceQuality['reason'] ?? '')) ?></p>
                <?php endif; ?>
                <?php if (!empty($provenanceFields['source_item_id']) || !empty($provenanceFields['merchant'])): ?>
                    <small><?= htmlspecialchars(trim(implode(' / ', array_filter([
                        !empty($provenanceFields['source_item_id']) ? 'item ' . $provenanceFields['source_item_id'] : null,
                        !empty($provenanceFields['merchant']) ? (string)$provenanceFields['merchant'] : null,
                    ])))) ?></small>
                <?php endif; ?>
                <?php if (!empty($o['review_purpose']) || !empty($o['review_run_id'])): ?>
                    <small><?= htmlspecialchars(trim(implode(' / ', array_filter([
                        !empty($o['review_purpose']) ? (string)$o['review_purpose'] : null,
                        !empty($o['review_run_id']) ? 'run ' . (string)$o['review_run_id'] : null,
                    ])))) ?></small>
                <?php endif; ?>
            </div>
            <div class="review-source-row">
                <span><?= htmlspecialchars(t('misc.source_reference')) ?> <code><?= htmlspecialchars(substr((string)($o['external_reference_hash'] ?? ''), 0, 16) ?: '-') ?></code></span>
                <?php if ($hasSourceLink): ?>
                    <a class="btn btn-primary" href="<?= htmlspecialchars($sourceUrl) ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-external-link-alt mr-2"></i><?= htmlspecialchars(t('v.open_source')) ?></a>
                <?php else: ?>
                    <button class="btn btn-outline-secondary disabled-control" type="button" disabled><?= htmlspecialchars(t('v.source_link_unavailable')) ?></button>
                <?php endif; ?>
            </div>
        </section>

        <section class="review-panel">
            <span class="eyebrow"><?= htmlspecialchars(t('v.c_extraction_and_resolution')) ?></span>
            <dl class="review-definition-list">
                <div><dt><?= htmlspecialchars(t('v.extracted_product_title')) ?></dt><dd><?= htmlspecialchars($extractedDisplayTitle) ?></dd></div>
                <div><dt><?= htmlspecialchars(t('v.resolved_product_master')) ?></dt><dd><?= htmlspecialchars((string)$finalProductName) ?></dd></div>
                <div><dt><?= htmlspecialchars(t('v.extraction_confidence')) ?></dt><dd><?= number_format((float)($o['extraction_confidence'] ?? 0) * 100, 1) ?>%</dd></div>
                <div><dt><?= htmlspecialchars(t('v.stored_observation_confidence')) ?></dt><dd><?= number_format((float)($o['classification_confidence'] ?? 0), 1) ?>%</dd></div>
                <div><dt><?= htmlspecialchars(t('v.product_resolution_diagnostic')) ?></dt><dd><?= htmlspecialchars($resolutionConfidenceLabel) ?><?= is_array($resolutionDiagnostic) && !empty($resolutionDiagnostic['method']) ? ' / ' . htmlspecialchars((string)$resolutionDiagnostic['method']) : '' ?></dd></div>
                <div><dt><?= htmlspecialchars(t('v.validation_lane')) ?></dt><dd><span class="lane-badge lane-<?= htmlspecialchars($lane) ?>"><?= htmlspecialchars(strtoupper($lane)) ?></span></dd></div>
            </dl>
            <?php if (!empty($o['warning_items'])): ?>
                <div class="review-warning-list">
                    <?php foreach ($o['warning_items'] as $warning): ?>
                        <div>
                            <b><?= htmlspecialchars((string)$warning['label']) ?></b>
                            <small><?= htmlspecialchars((string)$warning['code']) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="review-green-note"><?= htmlspecialchars(t('v.no_critical_validation_warnings_huma')) ?></div>
            <?php endif; ?>
            <details class="review-tech-details">
                <summary><?= htmlspecialchars(t('v.advanced_technical_details')) ?></summary>
                <pre><?= htmlspecialchars(json_encode([
                    'reason_codes' => $o['reason_codes_list'] ?? [],
                    'rule_result' => $o['rule_result_data'] ?? [],
                    'resolution_diagnostic' => $o['resolution_diagnostic'] ?? null,
                    'provenance' => [
                        'quality' => $provenanceQuality['quality'] ?? null,
                        'source_search_url' => $evidenceFields['SOURCE_SEARCH_URL'] ?? null,
                        'source_listing_url' => $evidenceFields['SOURCE_LISTING_URL'] ?? null,
                        'source_item_id' => $evidenceFields['SOURCE_ITEM_ID'] ?? null,
                        'merchant' => $evidenceFields['MERCHANT'] ?? null,
                        'listing_title' => $evidenceFields['LISTING_TITLE'] ?? null,
                        'asking_price' => $evidenceFields['ASKING_PRICE'] ?? null,
                        'observed_at' => $evidenceFields['OBSERVED_AT'] ?? null,
                        'evidence_hash' => $evidenceFields['EVIDENCE_HASH'] ?? null,
                        'evidence_snapshot_json' => $evidenceFields['EVIDENCE_SNAPSHOT_JSON'] ?? null,
                    ],
                    'raw_title' => $o['raw_title'] ?? null,
                    'extracted_data' => $o['extracted_data_map'] ?? [],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            </details>
        </section>
    </div>

    <aside class="review-decision-sidebar">
        <section class="review-panel review-decision-panel review-decision-card">
            <span class="eyebrow"><?= htmlspecialchars(t('v.e_review_decision')) ?></span>
            <?php if (!empty($o['warning_items'])): ?>
                <div class="review-decision-alert">
                    <strong><?= htmlspecialchars(strtoupper($lane)) ?> <?= htmlspecialchars(t('v.attention')) ?></strong>
                    <?php foreach ($o['warning_items'] as $warning): ?>
                        <p><?= htmlspecialchars((string)$warning['label']) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!$isPending): ?>
                <div class="notice"><?= htmlspecialchars(t('v.decision_already_recorded')) ?> <?= htmlspecialchars((string)$o['verified_status']) ?></div>
                <?php if ($canDecide && (string)$o['verified_status'] === 'approved'): ?>
                <form method="post" action="<?= $base ?>/admin/review-queue/withdraw" class="review-detail-form">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                    <input type="hidden" name="observation_id" value="<?= (int)$o['id'] ?>">
                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentPath) ?>">
                    <h3 class="h5"><?= htmlspecialchars(t('review.withdraw.title')) ?></h3>
                    <label><?= htmlspecialchars(t('review.withdraw.to')) ?>
                        <select class="form-control" name="to">
                            <?php foreach (\App\Services\Review\ReviewDecisionService::WITHDRAW_TO as $to): ?><option value="<?= $to ?>"><?= htmlspecialchars(t('review.withdraw.to.' . $to)) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label><?= htmlspecialchars(t('review.withdraw.reason')) ?>
                        <textarea class="form-control" name="reason" required maxlength="500"></textarea>
                    </label>
                    <button class="btn btn-danger" type="submit"><i class="fas fa-undo mr-2"></i><?= htmlspecialchars(t('review.withdraw.button')) ?></button>
                </form>
                <?php endif; ?>
            <?php elseif ($canDecide): ?>
                <form method="post" action="<?= $base ?>/admin/review-queue/decision" class="review-detail-form">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                    <input type="hidden" name="observation_id" value="<?= (int)$o['id'] ?>">
                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentPath) ?>">
                    <label><?= htmlspecialchars(t('v.audit_note')) ?>
                        <textarea name="notes" placeholder="<?= htmlspecialchars(t('v.optional_for_approval_use_for_reject')) ?>"></textarea>
                    </label>
                    <div class="review-decision-actions">
                        <button class="btn btn-success" type="submit" name="decision" value="approved"><i class="fas fa-check mr-2"></i><?= htmlspecialchars(t('v.approve')) ?></button>
                        <button class="btn btn-danger" type="submit" name="decision" value="rejected"><i class="fas fa-times mr-2"></i><?= htmlspecialchars(t('v.reject')) ?></button>
                        <button class="btn btn-outline-secondary" type="submit" name="decision" value="excluded"><i class="fas fa-ban mr-2"></i><?= htmlspecialchars(t('v.exclude')) ?></button>
                    </div>
                </form>
            <?php else: ?>
                <button class="btn btn-outline-secondary disabled-control" type="button" disabled><?= htmlspecialchars(t('v.decision_unavailable')) ?></button>
            <?php endif; ?>
        </section>

        <section class="review-correction-compact">
            <span class="eyebrow"><?= htmlspecialchars(t('v.d_correction')) ?></span>
            <?php if (!$isPending): ?>
                <div class="notice"><?= htmlspecialchars(t('v.this_observation_is_no_longer_pendin')) ?></div>
            <?php elseif ($canCorrect): ?>
                <details class="review-correction-panel">
                    <summary><span><?= htmlspecialchars(t('v.edit_correct_data')) ?></span><small><?= htmlspecialchars(t('v.original_evidence_stays_immutable')) ?></small></summary>
                    <form method="post" action="<?= $base ?>/admin/review-queue/correction" class="review-detail-form">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                        <input type="hidden" name="observation_id" value="<?= (int)$o['id'] ?>">
                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentPath) ?>">
                        <label><?= htmlspecialchars(t('v.field')) ?>
                            <select name="field_name">
                                <option value="price"><?= htmlspecialchars(t('v.price')) ?></option>
                                <option value="price_type"><?= htmlspecialchars(t('v.price_type_2')) ?></option>
                                <option value="product_id"><?= htmlspecialchars(t('v.product')) ?></option>
                                <option value="listing_type"><?= htmlspecialchars(t('v.listing_type')) ?></option>
                                <option value="condition_level"><?= htmlspecialchars(t('v.condition')) ?></option>
                                <option value="warranty_months"><?= htmlspecialchars(t('v.warranty_months')) ?></option>
                                <option value="observed_at"><?= htmlspecialchars(t('v.observed_at')) ?></option>
                            </select>
                        </label>
                        <label><?= htmlspecialchars(t('v.corrected_value')) ?>
                            <select name="corrected_value" required data-combo data-placeholder="<?= htmlspecialchars(t('v.pick_a_value_or_type_one')) ?>">
                                <option value=""></option>
                                <?php foreach ($products as $product): ?><option value="<?= (int)$product['id'] ?>"><?= htmlspecialchars((string)$product['full_name']) ?> (#<?= (int)$product['id'] ?>)</option><?php endforeach; ?>
                                <?php foreach (['asking', 'sold', 'trade_in', 'new', 'single_item', 'bundle', 'whole_pc', 'wanted', 'unknown', 'like_new', 'good', 'fair', 'poor'] as $v): ?><option value="<?= $v ?>"><?= $v ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label><?= htmlspecialchars(t('v.reason')) ?>
                            <textarea name="reason" required placeholder="<?= htmlspecialchars(t('v.reason_for_audit_trail')) ?>"></textarea>
                        </label>
                        <button class="btn btn-primary" type="submit"><?= htmlspecialchars(t('v.save_correction')) ?></button>
                    </form>
                </details>
            <?php else: ?>
                <button class="btn btn-outline-secondary disabled-control" type="button" disabled><?= htmlspecialchars(t('v.correction_unavailable')) ?></button>
            <?php endif; ?>
            <?php if (!empty($o['corrections'])): ?>
                <div class="review-correction-history mt-3">
                    <h3><?= htmlspecialchars(t('v.saved_corrections')) ?></h3>
                    <?php foreach ($o['corrections'] as $correction): ?>
                        <p><b><?= htmlspecialchars((string)$correction['field_name']) ?></b>: <?= htmlspecialchars((string)$correction['original_value']) ?> <?= htmlspecialchars(t('v.x01b6e203')) ?> <?= htmlspecialchars((string)$correction['corrected_value']) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </aside>
</div>
