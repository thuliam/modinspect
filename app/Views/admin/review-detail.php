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
$priceLabel = (string)($o['price_type'] ?? 'asking') === 'sold' ? 'Sold / Final Price' : 'Listing / Asking Price';
$backPath = $back_path ?? '/admin/review-queue';
$currentPath = '/admin/review/' . (int)$o['id'] . '?return_to=' . rawurlencode($backPath);
$renderValue = function (mixed $value): string {
    if ($value === null || $value === '') return 'unknown';
    return (string)$value;
};
$categoryIcon = function (string $category): string {
    return match (strtolower($category)) {
        'cpu' => 'fa-microchip',
        'gpu' => 'fa-tv',
        'motherboard' => 'fa-server',
        'ram' => 'fa-memory',
        'storage' => 'fa-hdd',
        'psu' => 'fa-plug',
        'cooling' => 'fa-fan',
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
?>
<div class="mi-page-header">
    <div>
        <a class="text-primary font-weight-bold" href="<?= $base . htmlspecialchars($backPath) ?>"><i class="fas fa-arrow-left mr-2"></i>Back to queue</a>
        <span class="mi-breadcrumb mt-2">Admin / Human Review Detail</span>
        <h1><?= htmlspecialchars((string)$finalProductName) ?></h1>
    </div>
</div>

<section class="review-detail-summary">
    <div class="review-summary-image-block">
        <div class="review-thumb review-thumb-lg" aria-hidden="true">
            <?php if ($productImageUrl): ?>
                <img src="<?= htmlspecialchars($productImageUrl) ?>" alt="">
            <?php else: ?>
                <i class="fas <?= htmlspecialchars($productIcon) ?>"></i>
            <?php endif; ?>
        </div>
        <small>Product Master image</small>
    </div>
    <div>
        <span class="dataset-badge dataset-<?= strtolower((string)$o['dataset_label']) ?>"><?= htmlspecialchars((string)$o['dataset_label']) ?></span>
        <span class="lane-badge lane-<?= htmlspecialchars($lane) ?> mt-2"><?= htmlspecialchars(strtoupper($lane)) ?></span>
        <span class="mi-status-badge mi-status-<?= htmlspecialchars((string)$o['verified_status']) ?> mt-2"><?= htmlspecialchars((string)$o['verified_status']) ?></span>
    </div>
    <div>
        <h2><?= htmlspecialchars((string)$finalProductName) ?></h2>
        <p>Matched to Product Master #<?= (int)($final['product_id'] ?? $o['product_id']) ?></p>
    </div>
    <div class="review-detail-price">
        <b>&#3647;<?= number_format((float)($final['price'] ?? $o['display_price'])) ?></b>
        <span><?= htmlspecialchars($priceLabel) ?></span>
    </div>
    <dl>
        <div><dt>Source</dt><dd><?= htmlspecialchars((string)($o['source_name'] ?? 'unknown source')) ?></dd></div>
        <div><dt>Observed</dt><dd><?= htmlspecialchars((string)($final['observed_at'] ?? $o['observed_at'] ?? '-')) ?></dd></div>
        <div><dt>Observation</dt><dd>#<?= (int)$o['id'] ?></dd></div>
    </dl>
</section>

<div class="review-detail-workspace">
    <div class="review-detail-main">
        <section class="review-panel">
            <span class="eyebrow">B / Original Market Evidence</span>
            <p class="review-evidence-title"><?= htmlspecialchars($displayTitle) ?></p>
            <?php if (!empty($o['title_was_normalized'])): ?>
                <div class="review-green-note">Display title excludes known ModInspect importer/privacy metadata. Raw imported title remains available in Advanced / Technical Details.</div>
            <?php endif; ?>
            <div class="review-evidence-meta">
                <div>
                    <span>Source</span>
                    <b><?= htmlspecialchars((string)($o['source_name'] ?? 'unknown source')) ?></b>
                </div>
                <div>
                    <span>Observed</span>
                    <b><?= htmlspecialchars((string)($final['observed_at'] ?? $o['observed_at'] ?? '-')) ?></b>
                </div>
                <div>
                    <span>Captured price</span>
                    <b>&#3647;<?= number_format((float)$o['display_price']) ?></b>
                    <small><?= htmlspecialchars($priceLabel) ?></small>
                </div>
                <div>
                    <span>Price text</span>
                    <b><?= htmlspecialchars($renderValue($o['raw_price_text'] ?? null)) ?></b>
                </div>
                <div>
                    <span>Condition</span>
                    <b><?= htmlspecialchars($renderValue($o['raw_condition_text'] ?? $o['condition_level'] ?? null)) ?></b>
                </div>
                <div>
                    <span>Warranty</span>
                    <b><?= htmlspecialchars($renderValue($o['raw_warranty_text'] ?? null)) ?></b>
                </div>
            </div>
            <div class="review-source-row">
                <span>Source reference <code><?= htmlspecialchars(substr((string)($o['external_reference_hash'] ?? ''), 0, 16) ?: '-') ?></code></span>
                <?php if ($hasSourceLink): ?>
                    <a class="btn btn-primary" href="<?= htmlspecialchars($sourceUrl) ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-external-link-alt mr-2"></i>Open Source</a>
                <?php else: ?>
                    <button class="btn btn-outline-secondary disabled-control" type="button" disabled>Source link unavailable</button>
                <?php endif; ?>
            </div>
        </section>

        <section class="review-panel">
            <span class="eyebrow">C / Extraction and Resolution</span>
            <dl class="review-definition-list">
                <div><dt>Extracted product/title</dt><dd><?= htmlspecialchars($extractedDisplayTitle) ?></dd></div>
                <div><dt>Resolved Product Master</dt><dd><?= htmlspecialchars((string)$finalProductName) ?></dd></div>
                <div><dt>Extraction confidence</dt><dd><?= number_format((float)($o['extraction_confidence'] ?? 0) * 100, 1) ?>%</dd></div>
                <div><dt>Stored observation confidence</dt><dd><?= number_format((float)($o['classification_confidence'] ?? 0), 1) ?>%</dd></div>
                <div><dt>Product-resolution diagnostic</dt><dd><?= htmlspecialchars($resolutionConfidenceLabel) ?><?= is_array($resolutionDiagnostic) && !empty($resolutionDiagnostic['method']) ? ' / ' . htmlspecialchars((string)$resolutionDiagnostic['method']) : '' ?></dd></div>
                <div><dt>Validation lane</dt><dd><span class="lane-badge lane-<?= htmlspecialchars($lane) ?>"><?= htmlspecialchars(strtoupper($lane)) ?></span></dd></div>
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
                <div class="review-green-note">No critical validation warnings. Human approval is still required.</div>
            <?php endif; ?>
            <details class="review-tech-details">
                <summary>Advanced / Technical Details</summary>
                <pre><?= htmlspecialchars(json_encode([
                    'reason_codes' => $o['reason_codes_list'] ?? [],
                    'rule_result' => $o['rule_result_data'] ?? [],
                    'resolution_diagnostic' => $o['resolution_diagnostic'] ?? null,
                    'raw_title' => $o['raw_title'] ?? null,
                    'extracted_data' => $o['extracted_data_map'] ?? [],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            </details>
        </section>
    </div>

    <aside class="review-decision-sidebar">
        <section class="review-panel review-decision-panel review-decision-card">
            <span class="eyebrow">E / Review Decision</span>
            <?php if (!empty($o['warning_items'])): ?>
                <div class="review-decision-alert">
                    <strong><?= htmlspecialchars(strtoupper($lane)) ?> attention</strong>
                    <?php foreach ($o['warning_items'] as $warning): ?>
                        <p><?= htmlspecialchars((string)$warning['label']) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!$isPending): ?>
                <div class="notice">Decision already recorded: <?= htmlspecialchars((string)$o['verified_status']) ?></div>
            <?php elseif ($canDecide): ?>
                <form method="post" action="<?= $base ?>/admin/review-queue/decision" class="review-detail-form">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                    <input type="hidden" name="observation_id" value="<?= (int)$o['id'] ?>">
                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentPath) ?>">
                    <label>Audit note
                        <textarea name="notes" placeholder="Optional for approval; use for reject or exclude rationale"></textarea>
                    </label>
                    <div class="review-decision-actions">
                        <button class="btn btn-success" type="submit" name="decision" value="approved"><i class="fas fa-check mr-2"></i>Approve</button>
                        <button class="btn btn-danger" type="submit" name="decision" value="rejected"><i class="fas fa-times mr-2"></i>Reject</button>
                        <button class="btn btn-outline-secondary" type="submit" name="decision" value="excluded"><i class="fas fa-ban mr-2"></i>Exclude</button>
                    </div>
                </form>
            <?php else: ?>
                <button class="btn btn-outline-secondary disabled-control" type="button" disabled>Decision unavailable</button>
            <?php endif; ?>
        </section>

        <section class="review-correction-compact">
            <span class="eyebrow">D / Correction</span>
            <?php if (!$isPending): ?>
                <div class="notice">This observation is no longer pending. Corrections are closed.</div>
            <?php elseif ($canCorrect): ?>
                <details class="review-correction-panel">
                    <summary><span>Edit / Correct Data</span><small>Original evidence stays immutable.</small></summary>
                    <form method="post" action="<?= $base ?>/admin/review-queue/correction" class="review-detail-form">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                        <input type="hidden" name="observation_id" value="<?= (int)$o['id'] ?>">
                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentPath) ?>">
                        <label>Field
                            <select name="field_name">
                                <option value="price">Price</option>
                                <option value="price_type">Price type</option>
                                <option value="product_id">Product</option>
                                <option value="listing_type">Listing type</option>
                                <option value="condition_level">Condition</option>
                                <option value="warranty_months">Warranty months</option>
                                <option value="observed_at">Observed at</option>
                            </select>
                        </label>
                        <label>Corrected value
                            <input name="corrected_value" list="review-correction-values" required>
                        </label>
                        <datalist id="review-correction-values">
                            <?php foreach ($products as $product): ?><option value="<?= (int)$product['id'] ?>"><?= htmlspecialchars((string)$product['full_name']) ?></option><?php endforeach; ?>
                            <option value="asking"></option>
                            <option value="sold"></option>
                            <option value="trade_in"></option>
                            <option value="new"></option>
                            <option value="single_item"></option>
                            <option value="bundle"></option>
                            <option value="whole_pc"></option>
                            <option value="wanted"></option>
                            <option value="unknown"></option>
                            <option value="like_new"></option>
                            <option value="good"></option>
                            <option value="fair"></option>
                            <option value="poor"></option>
                        </datalist>
                        <label>Reason
                            <textarea name="reason" required placeholder="Reason for audit trail"></textarea>
                        </label>
                        <button class="btn btn-primary" type="submit">Save Correction</button>
                    </form>
                </details>
            <?php else: ?>
                <button class="btn btn-outline-secondary disabled-control" type="button" disabled>Correction unavailable</button>
            <?php endif; ?>
            <?php if (!empty($o['corrections'])): ?>
                <div class="review-correction-history mt-3">
                    <h3>Saved corrections</h3>
                    <?php foreach ($o['corrections'] as $correction): ?>
                        <p><b><?= htmlspecialchars((string)$correction['field_name']) ?></b>: <?= htmlspecialchars((string)$correction['original_value']) ?> to <?= htmlspecialchars((string)$correction['corrected_value']) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </aside>
</div>
