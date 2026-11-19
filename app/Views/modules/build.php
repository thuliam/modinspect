<?php
/**
 * LS-15: the Build page, laid out like a PC builder: parts list (left), picker (centre), summary (right). Saved builds show the same summary.
 * @var list<array> $products  @var ?array $result saved build  @var array $errors  @var array $old  @var array $newRefs  @var ?array $preview BuildPreview::run for a saved build
 */
use App\Services\Build\BuildPreview;
use App\Services\Build\BuildPricing;

require_once ROOT_PATH . '/app/Views/components/hw-art.php';
$newRefs = $newRefs ?? [];
$roleIcon = ['cpu' => 'memory', 'motherboard' => 'developer_board', 'gpu' => 'videogame_asset', 'ram' => 'view_module', 'storage' => 'database', 'psu' => 'bolt', 'case' => 'inventory_2', 'cooling' => 'toys'];
$byRole = [];
foreach ($products as $p) { $byRole[strtolower((string) $p['category_name'])][] = $p; }
$initial = [];
foreach (BuildPreview::ROLES as $role => $_l) { $id = (int) ($old['parts'][$role] ?? 0); if ($id > 0) { $initial[$role] = $id; } }
$thumb = static function (array $p, string $role) use ($base, $roleIcon): string {
    $photo = mi_product_photo($p, $base, 'mi-photo');
    return $photo ?: '<i class="fas ' . mi_fa($roleIcon[$role] ?? 'memory') . '" aria-hidden="true"></i>';
};
?>
<section class="container-fluid px-lg-4 page spec-workspace mi-builder pb-5">
    <div class="mi-page-heading">
        <span class="eyebrow">จัดสเปกคอมมือสอง</span>
        <h1>จัดสเปกคอมมือสอง</h1>
        <p class="lead"><?= $result ? 'สเปกที่บันทึกไว้ ราคาและค่าพลังคำนวณใหม่จากข้อมูลล่าสุดทุกครั้งที่เปิดหน้านี้' : 'เลือกทีละชิ้น ระบบกรองให้เหลือเฉพาะรุ่นที่ใช้ด้วยกันได้ และสรุปราคากับค่าพลังของสเปกให้ทันที ราคามือสองมาจากข้อมูลที่ผ่านการตรวจแล้วเท่านั้น' ?></p>
    </div>
    <?php foreach ($errors as $error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endforeach; ?>
    <?php if (!empty($recommendation)): ?><div class="notice mb-3"><?= htmlspecialchars($recommendation['notes']) ?></div><?php endif; ?>

<?php if ($result): ?>
    <div class="row">
        <div class="col-lg-8 mb-3">
            <div class="card saved-build">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div><span class="meta">สเปก <?= htmlspecialchars(substr((string) $result['uuid'], 0, 8)) ?></span><h2 class="h4 mb-0">สเปกที่บันทึกแล้ว</h2></div>
                        <a class="button ghost" href="<?= $base ?>/build">จัดสเปกใหม่</a>
                    </div>
                    <div class="form-group">
                        <label for="build-share">ลิงก์สเปกนี้ (คัดลอกไปส่งต่อได้)</label>
                        <input id="build-share" class="form-control h-auto" type="url" readonly data-select-on-focus value="<?= htmlspecialchars(rtrim((string) $base, '/') . '/build/' . rawurlencode((string) $result['uuid'])) ?>">
                    </div>
                    <div class="row mi-saved-grid">
                        <?php foreach ($preview['items'] as $it): if (!$it['chosen']) { continue; } ?>
                            <div class="col-6 col-md-4 mb-3">
                                <a class="card h-100 text-center mi-saved-tile" href="<?= $base ?>/price/<?= rawurlencode($it['slug']) ?>">
                                    <div class="mi-thumb mi-thumb-lg"><?= $thumb($it['product'], $it['role']) ?></div>
                                    <div class="card-body p-2"><small class="text-muted d-block"><?= htmlspecialchars($it['role_label']) ?></small><b class="small"><?= htmlspecialchars($it['name']) ?></b></div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php require ROOT_PATH . '/app/Views/components/build-radar.php'; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <?php $data = $preview; $mode = 'saved'; require ROOT_PATH . '/app/Views/components/build-summary.php'; ?>
        </div>
    </div>
    <script src="<?= $base ?>/assets/vendor/chartjs/chart.umd.min.js"></script>
    <script type="application/json" id="mi-build-config">{"mode":"saved"}</script>   <?php /* LS-19 D1: a data block (not run) read by the Build page script, which the footer loads once, deferred after jQuery */ ?>
<?php else: ?>
    <?php $data = (new BuildPreview())->run($products, [], $newRefs, [], (float) ($old['budget'] ?? 25000), false);   // the empty state; the script asks for the real summary on load ?>
    <form method="post" action="<?= $base ?>/build" id="spec-builder-form" class="mb-4">
        <input type="hidden" name="_token" value="<?= \App\Core\Csrf::token() ?>">
        <input type="hidden" name="mix_preference" value="used">
        <div id="selected-inputs"></div>

        <div class="card mb-3">
            <div class="card-body">
                <button type="button" class="btn btn-ghost btn-block d-lg-none" data-toggle="collapse" data-target="#controls-body" aria-expanded="false" aria-controls="controls-body"><i class="fas fa-sliders-h mr-1" aria-hidden="true"></i> ตั้งงบและการใช้งาน</button>
                <div class="collapse d-lg-block mt-3 mt-lg-0" id="controls-body">
                <div class="form-row align-items-end">
                    <div class="form-group col-6 col-md-2 mb-2">
                        <label for="spec-budget">งบประมาณ (บาท)</label>
                        <input id="spec-budget" class="form-control h-auto" type="number" name="budget" min="5000" max="500000" step="500" value="<?= htmlspecialchars((string) ($old['budget'] ?? 25000)) ?>" required>
                    </div>
                    <div class="form-group col-6 col-md-2 mb-2">
                        <label for="spec-use">ใช้สำหรับ</label>
                        <select id="spec-use" class="form-control h-auto" name="intended_use"><?php foreach (['gaming' => 'Gaming', 'work' => 'Work', 'streaming' => 'Streaming', 'office' => 'Office', 'ai' => 'AI'] as $v => $l): ?><option value="<?= $v ?>"<?= (($old['intended_use'] ?? 'gaming') === $v) ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="form-group col-6 col-md-2 mb-2">
                        <label for="spec-res">ความละเอียด</label>
                        <select id="spec-res" class="form-control h-auto" name="resolution"><?php foreach (['1080p' => '1080p', '1440p' => '1440p', '4k' => '4K', 'none' => 'ไม่เกี่ยวข้อง'] as $v => $l): ?><option value="<?= $v ?>"<?= (($old['resolution'] ?? '1080p') === $v) ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="form-group col-6 col-md-2 mb-2">
                        <label for="spec-preset">รูปแบบการแนะนำ</label>
                        <select id="spec-preset" class="form-control h-auto" name="preset"><?php foreach (['saving' => 'ประหยัด', 'balanced' => 'สมดุล', 'full' => 'ใช้งบเต็มที่'] as $v => $l): ?><option value="<?= $v ?>"<?= (($old['preset'] ?? 'balanced') === $v) ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="form-group col-12 col-md-4 mb-2">
                        <button type="submit" name="action" value="recommend" class="btn btn-block btn-ghost"><i class="fas fa-magic mr-1" aria-hidden="true"></i> แนะนำสเปกให้</button>
                    </div>
                </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- left: the parts list -->
            <aside class="col-lg-3 order-2 order-lg-1 mb-3" id="parts-card">
                <div class="card sticky-top mi-parts-card">
                    <div class="card-body pb-2">
                        <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 mb-0">ชิ้นส่วนของคุณ</h2><span class="d-flex align-items-center"><a href="#picker" class="d-lg-none small mr-2">เลือกต่อ</a><span class="badge badge-pill badge-secondary js-count">0/8</span></span></div>
                    </div>
                    <ul class="list-group list-group-flush" id="parts-list">
                        <?php foreach (BuildPreview::ROLES as $role => $label): ?>
                            <li class="list-group-item mi-part-row bg-transparent border-secondary" data-role="<?= $role ?>">
                                <div class="d-flex align-items-center">
                                    <span class="mi-thumb mr-2"><i class="fas <?= mi_fa($roleIcon[$role]) ?>" aria-hidden="true"></i></span>
                                    <div class="flex-grow-1 min-w-0">
                                        <small class="text-muted d-block"><?= htmlspecialchars($label) ?></small>
                                        <span class="d-block mi-part-name js-name text-muted">ยังไม่ได้เลือก</span>
                                        <small class="js-price d-block"></small>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-ghost js-pick" data-role="<?= $role ?>" aria-label="เลือก<?= htmlspecialchars($label) ?>"><i class="fas fa-plus" aria-hidden="true"></i></button>
                                    <button type="button" class="btn btn-sm btn-ghost text-danger js-remove" data-role="<?= $role ?>" aria-label="เอา<?= htmlspecialchars($label) ?>ออก" hidden><i class="fas fa-times" aria-hidden="true"></i></button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-baseline mb-2"><button type="button" class="btn btn-sm btn-ghost js-reset"><i class="fas fa-undo mr-1" aria-hidden="true"></i> เริ่มใหม่</button><span class="h4 mb-0 js-side-total">฿0</span></div>
                        <button type="submit" class="btn btn-lime btn-block js-save" disabled>บันทึกและแชร์สเปก</button>
                        <small class="text-muted d-block mt-2 js-save-hint">เลือกอย่างน้อย 1 ชิ้นเพื่อบันทึก</small>
                    </div>
                </div>
            </aside>

            <!-- centre: the picker -->
            <section class="col-lg-6 order-1 order-lg-2 mb-3" id="picker" aria-label="เลือกชิ้นส่วน">
                <ul class="list-unstyled d-flex flex-wrap mb-2 mi-role-pills" aria-label="หมวดชิ้นส่วน">
                    <?php foreach (BuildPreview::ROLES as $role => $label): ?>
                        <li class="mr-1 mb-1"><button type="button" class="btn btn-sm btn-ghost js-role" data-role="<?= $role ?>"><?= htmlspecialchars($label) ?> <i class="fas fa-check-circle text-success ml-1 js-done" aria-hidden="true" hidden></i></button></li>
                    <?php endforeach; ?>
                </ul>
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-baseline mb-2"><h2 class="h4 mb-0" id="picker-title">เลือก CPU</h2><span class="small text-muted js-found" role="status" aria-live="polite"></span></div>
                        <div class="form-row mb-3">
                            <div class="col-12 col-md-4 mb-2"><label class="sr-only" for="f-brand">ยี่ห้อ</label><select id="f-brand" class="form-control form-control-sm h-auto"><option value="">ทุกยี่ห้อ</option></select></div>
                            <div class="col-12 col-md-4 mb-2"><label class="sr-only" for="f-q">ค้นหา</label><input id="f-q" class="form-control form-control-sm h-auto" type="search" placeholder="ค้นหารุ่น" autocomplete="off"></div>
                            <div class="col-12 col-md-4 mb-2"><label class="sr-only" for="f-sort">เรียงตาม</label><select id="f-sort" class="form-control form-control-sm h-auto"><option value="rel">แนะนำ (มีราคามือสองก่อน)</option><option value="asc">ราคาต่ำไปสูง</option><option value="desc">ราคาสูงไปต่ำ</option><option value="name">ชื่อ A-Z</option></select></div>
                        </div>
                        <div class="custom-control custom-checkbox mb-3"><input type="checkbox" class="custom-control-input" id="f-compat" checked><label class="custom-control-label" for="f-compat">แสดงเฉพาะรุ่นที่ใช้ด้วยกันได้</label></div>
                        <div class="row" id="part-grid">
                            <?php foreach (BuildPreview::ROLES as $role => $label): foreach ($byRole[$role] ?? [] as $p):
                                $pr = BuildPricing::for($p, $newRefs[(int) $p['id']] ?? null);
                                $badge = ['used' => 'badge-success', 'new_ref' => 'badge-warning', 'none' => 'badge-secondary'][$pr['basis']];
                                ?>
                                <div class="col-6 col-xl-4 mb-3 mi-part-col" hidden data-role="<?= $role ?>" data-id="<?= (int) $p['id'] ?>" data-brand="<?= htmlspecialchars((string) $p['brand_name']) ?>" data-name="<?= htmlspecialchars(mb_strtolower((string) $p['full_name'])) ?>" data-price="<?= $pr['expected'] ?>" data-basis="<?= $pr['basis'] ?>" data-conf="<?= (int) ($pr['basis'] === 'used' ? (float) $p['confidence_score'] : 0) ?>">
                                    <div class="card h-100 mi-part-card">
                                        <div class="mi-thumb mi-thumb-lg js-thumb"><?= $thumb($p, $role) ?></div>
                                        <div class="card-body p-2 d-flex flex-column">
                                            <small class="text-muted"><?= htmlspecialchars((string) $p['brand_name']) ?></small>
                                            <h3 class="h6 mi-part-title js-title"><?= htmlspecialchars((string) $p['full_name']) ?></h3>
                                            <div class="mb-1"><b class="js-priceval"><?= $pr['basis'] === 'none' ? '—' : '฿' . number_format($pr['expected']) ?></b> <span class="badge <?= $badge ?> js-basis"><?= htmlspecialchars($pr['label']) ?></span></div>
                                            <small class="text-muted mb-2"><?= $pr['basis'] === 'used' ? 'ความมั่นใจ ' . (int) $p['confidence_score'] . '% · ' . (int) $p['valid_sample_size'] . ' รายการ' : ($pr['basis'] === 'new_ref' ? 'ตรวจ ' . htmlspecialchars((string) $pr['checked_on']) : 'ไม่รวมในยอดรวม') ?></small>
                                            <small class="js-flag mb-2" aria-live="polite"></small>
                                            <button type="button" class="btn btn-sm btn-block btn-lime mt-auto js-add" data-id="<?= (int) $p['id'] ?>" data-role="<?= $role ?>">เพิ่มลงสเปก</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; endforeach; ?>
                        </div>
                        <p class="text-center text-muted my-4" id="part-empty" hidden>ไม่มีรุ่นที่ตรงกับตัวกรอง ลองปิด "แสดงเฉพาะรุ่นที่ใช้ด้วยกันได้" หรือล้างคำค้นหา</p>
                    </div>
                </div>
            </section>

            <!-- right: the summary (replaced by JavaScript after every pick) -->
            <aside class="col-lg-3 order-3 mb-3" id="summary-pane" aria-live="polite">
                <?php $mode = 'live'; require ROOT_PATH . '/app/Views/components/build-summary.php'; ?>
            </aside>
        </div>
        <noscript><div class="alert">หน้านี้ต้องเปิด JavaScript เพื่อเลือกชิ้นส่วน</div></noscript>
    </form>
    <!-- phones: the parts list sits far below the picker, so a bar that stays on screen shows the count and the total and jumps to the list -->
    <div class="fixed-bottom d-lg-none bg-dark border-top border-secondary"><a href="#parts-card" class="d-flex justify-content-between align-items-center px-3 py-2 text-white"><span><i class="fas fa-list mr-2" aria-hidden="true"></i>ชิ้นส่วน <b class="js-count">0/8</b></span><b class="js-side-total">฿0</b><span>ดูรายการ <i class="fas fa-chevron-down" aria-hidden="true"></i></span></a></div>
    <script src="<?= $base ?>/assets/vendor/chartjs/chart.umd.min.js"></script>
    <script type="application/json" id="mi-build-config"><?= json_encode(['mode' => 'live', 'previewUrl' => $base . '/build/preview', 'initial' => (object) $initial], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?></script>
<?php endif; ?>
</section>
