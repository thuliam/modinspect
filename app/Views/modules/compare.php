<?php
/** @var array $table CompareTable::build output  @var list<array{slug:string,name:string,category:string}> $options  @var list<string> $chosen */
$confTh = ['high' => 'สูง', 'medium' => 'ปานกลาง', 'low' => 'จำกัด'];
$cols = $table['columns'];
$slots = max(2, count($cols));
$byCat = [];
foreach ($options as $o) { $byCat[$o['category']][] = $o; }
$hasNote = false;
foreach ($table['rows'] as $r) { foreach ($r['cells'] as $c) { if (!empty($c['note'])) { $hasNote = true; } } }
$diff = []; $same = [];
foreach ($table['rows'] as $r) { if ($r['differs']) { $diff[] = $r; } else { $same[] = $r; } }
$palette = \App\Services\Benchmark\CompareRadar::COLORS;
$pricedCols = array_values(array_filter($cols, static fn(array $c): bool => $c['priced']));
$lowMedian = $pricedCols ? min(array_column($pricedCols, 'median')) : null;
// ▲ highest / ▼ lowest value of a row (only when two or more cells are plain numbers with at most a unit, and they differ). It says which value is larger, not which is better.
$extremes = static function (array $values): array {
    $nums = [];
    foreach ($values as $i => $v) { if ($v !== null && preg_match('/^\s*(\d[\d,]*(?:\.\d+)?)\s?[A-Za-z%]*\s*$/', (string)$v, $m)) { $nums[$i] = (float)str_replace(',', '', $m[1]); } }
    if (count($nums) < 2 || max($nums) === min($nums)) { return []; }
    $hi = max($nums); $lo = min($nums);
    $out = []; foreach ($nums as $i => $n) { if ($n === $hi) { $out[$i] = 'max' . ($lo > 0 ? ':' . round(($hi - $lo) / $lo * 100, 1) : ''); } elseif ($n === $lo) { $out[$i] = 'min' . ($hi > 0 ? ':' . round(($lo - $hi) / $hi * 100, 1) : ''); } }
    return $out;
};
$mark = static function (?string $m): string {
    // LS-45: pill after the 21st.dev "badge delta": a small outlined box, a filled triangle and the percentage against the other end of the row. Direction only, never "better".
    if ($m === null || $m === '') { return ''; }
    $parts = explode(':', $m, 2); $up = $parts[0] === 'max'; $pct = isset($parts[1]) ? (float) $parts[1] : null;
    $txt = $up ? 'สูงสุดในแถวนี้' : 'ต่ำสุดในแถวนี้';
    $val = $pct !== null ? ($up ? '+' : '−') . number_format(abs($pct), 1) . '%' : '';
    return ' <span class="mi-delta ' . ($up ? 'mi-delta-up' : 'mi-delta-down') . '" title="' . $txt . ($val !== '' ? ' (' . $val . ' เทียบกับอีกปลายของแถว)' : '') . '"><i class="fas ' . ($up ? 'fa-caret-up' : 'fa-caret-down') . '" aria-hidden="true"></i>' . $val . '<span class="sr-only"> ' . $txt . '</span></span>';
};
$cell = static function (array $cell): string {
    if ($cell['value'] === null) { return '<span class="text-muted">ไม่มีข้อมูล</span>'; }
    $o = htmlspecialchars($cell['value']);
    if (!empty($cell['source'])) { $o .= ' <small><a href="' . htmlspecialchars($cell['source']) . '" target="_blank" rel="noopener noreferrer">ที่มา</a></small>'; }
    return $o;
};
?>
<section class="container page">
<span class="eyebrow">เปรียบเทียบสินค้า</span><h1>เปรียบเทียบสเปกและราคา</h1>
<p class="lead">เลือก 2–4 รุ่นเพื่อดูสเปกจากผู้ผลิตและราคาที่ผ่านการตรวจแล้วเทียบกัน ระบบไม่จัดอันดับและไม่บอกว่ารุ่นไหนดีกว่า</p>

<form method="get" action="<?= $base ?>/compare" class="card mb-4">
    <div class="form-row">
        <?php // LS-18 (owner): searchable Select2 boxes grouped by category; once one model is chosen the other boxes only offer its category
        // (site.js locks them, data-cat on every option). Without scripts it is a plain grouped <select>; typed names in old links still resolve (CompareTable::resolve). ?>
        <?php for ($i = 0; $i < 4; $i++): $sel = $chosen[$i] ?? ''; ?>
            <div class="form-group col-md-3">
                <label for="m<?= $i ?>">รุ่นที่ <?= $i + 1 ?></label>
                <select id="m<?= $i ?>" name="m[]" class="form-control h-auto js-select2-manual js-compare" data-placeholder="พิมพ์หรือเลือกรุ่น">
                    <option value=""></option>
                    <?php foreach ($byCat as $cat => $list): ?>
                        <optgroup label="<?= htmlspecialchars($cat) ?>">
                            <?php foreach ($list as $o): ?>
                                <option value="<?= htmlspecialchars($o['slug']) ?>" data-cat="<?= htmlspecialchars($cat) ?>"<?= $o['slug'] === $sel ? ' selected' : '' ?>><?= htmlspecialchars($o['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endfor; ?>
    </div>
    <p class="mi-trend-note mb-3" id="compare-lock-note" hidden>แสดงเฉพาะรุ่นในหมวด <strong></strong> ให้ตรงกับรุ่นแรกที่เลือก เปลี่ยนหมวดได้โดยเปลี่ยนรุ่นแรก</p>
    <?php if (!empty($unmatched)): ?>
        <p class="mi-note" role="alert"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> ไม่พบรุ่นที่ตรงกับ “<?= htmlspecialchars(implode('”, “', array_map(static fn(string $u): string => mb_substr($u, 0, 60), $unmatched))) ?>” หรือมีหลายรุ่นที่ตรงกัน ลองเลือกจากรายการที่ขึ้นมาตอนพิมพ์</p>
    <?php endif; ?>
    <div class="d-flex align-items-center">
        <button type="submit" class="btn btn-primary flex-grow-1">เปรียบเทียบ</button>
        <?php if ($chosen): ?><a class="btn btn-ghost ml-3" href="<?= $base ?>/compare">ล้างทั้งหมด</a><?php endif; ?>
    </div>
</form>

<?php if (count($cols) < 2): ?>
    <p class="mi-note"><i class="fas fa-info-circle" aria-hidden="true"></i> เลือกอย่างน้อย 2 รุ่นเพื่อเริ่มเปรียบเทียบ</p>
    <?php if (!empty($pairs)): ?>
        <div class="mi-empty-state">
            <h2 class="h5">ลองเทียบคู่นี้</h2>
            <p class="mi-trend-note">คู่รุ่นหมวดเดียวกันที่มีช่วงราคาแล้ว กดแล้วเห็นตารางทันที</p>
            <div class="mi-category-chips" aria-label="คู่รุ่นแนะนำให้เปรียบเทียบ">
                <?php foreach ($pairs as $pair): ?>
                    <a href="<?= $base ?>/compare?<?= htmlspecialchars(http_build_query(['m' => [$pair['a']['slug'], $pair['b']['slug']]])) ?>"><i class="fas fa-exchange-alt mr-2" aria-hidden="true"></i><?= htmlspecialchars($pair['a']['name']) ?> กับ <?= htmlspecialchars($pair['b']['name']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
<?php else: ?>
    <?php if ($table['mixed_category']): ?>
        <p class="mi-note"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> รุ่นที่เลือกอยู่คนละหมวด สเปกบางแถวจึงเทียบกันไม่ได้ ช่องที่ไม่มีข้อมูลจะเว้นว่างไว้</p>
        <?php // LS-19 B4: one tap back to a same-category table (only categories with two or more of the chosen models)
        $sameCat = []; foreach ($cols as $c) { $sameCat[$c['category']][] = $c['slug']; } $sameCat = array_filter($sameCat, static fn(array $s): bool => count($s) >= 2); ?>
        <?php if ($sameCat): ?>
            <div class="mi-category-chips mb-3" aria-label="เทียบเฉพาะหมวดเดียว">
                <?php foreach ($sameCat as $cat => $slugs): ?>
                    <a href="<?= $base ?>/compare?<?= htmlspecialchars(http_build_query(['m' => $slugs])) ?>"><i class="fas fa-filter mr-2" aria-hidden="true"></i>เทียบเฉพาะหมวด <?= htmlspecialchars((string)$cat) ?> (<?= count($slugs) ?> รุ่น)</a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <div class="row mb-4">
        <?php foreach ($cols as $ci => $c): ?>
            <div class="col-md-<?= (int) (12 / max(2, count($cols))) ?> col-6 mb-3">
                <div class="card h-100 mi-cmp-card" style="--i:<?= $ci ?>"><div class="card-body">
                    <span class="mi-trend-note"><span class="mi-cmp-dot" style="background:<?= $palette[$ci % 4] ?>" aria-hidden="true"></span><?= htmlspecialchars($c['category']) ?></span>
                    <h2 class="h6 mb-2"><a href="<?= $base ?>/price/<?= htmlspecialchars($c['slug']) ?>"><?= htmlspecialchars($c['name']) ?></a></h2>
                    <?php if ($c['priced']): ?>
                        <p class="h4 mb-1">฿<?= number_format($c['median']) ?></p>
                        <p class="mi-trend-note mb-1">ช่วง ฿<?= number_format($c['range'][0]) ?>–<?= number_format($c['range'][1]) ?> · <?= (int) $c['samples'] ?> ตัวอย่าง</p>
                        <p class="mi-trend-note mb-0"><?php if ($lowMedian !== null && $c['median'] > $lowMedian): ?>สูงกว่ารุ่นที่ราคากลางต่ำสุด <?= number_format(($c['median'] / $lowMedian - 1) * 100) ?>%<?php elseif (count($pricedCols) > 1): ?>ราคากลางต่ำสุดในกลุ่มที่เลือก<?php else: ?>ความมั่นใจราคา <?= htmlspecialchars($confTh[$c['confidence']] ?? 'ข้อมูลยังไม่พอ') ?><?php endif; ?></p>
                    <?php else: ?>
                        <p class="text-muted mb-0">ยังไม่มีราคาตลาดที่ตรวจแล้ว</p>
                    <?php endif; ?>
                </div></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if (!empty($radar)): $rid = 'cmpradar' . bin2hex(random_bytes(4)); ?>
        <section class="card mi-radar-card mb-4" aria-labelledby="<?= $rid ?>-t">
            <div class="mi-section-head compact"><div><span class="eyebrow">ข้อมูลอ้างอิง</span><h2 id="<?= $rid ?>-t">คะแนนประสิทธิภาพซ้อนกัน</h2></div></div>
            <div class="mi-radar-wrap"><canvas id="<?= $rid ?>" data-chart="radar-multi" data-source="<?= $rid ?>-data" role="img" aria-label="เรดาร์คะแนนเปรียบเทียบ <?= htmlspecialchars(implode(', ', array_column($radar['series'], 'name'))) ?>"></canvas></div>
            <?php if (empty($GLOBALS['mi_chartjs_loaded'])): $GLOBALS['mi_chartjs_loaded'] = true; ?>
                <script src="<?= $base ?>/assets/vendor/chartjs/chart.umd.min.js"></script>
                <script src="<?= $base ?>/assets/mi-charts.js?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/mi-charts.js') ?>" defer></script>
            <?php endif; ?>
            <script type="application/json" id="<?= $rid ?>-data"><?= json_encode(['labels' => $radar['labels'], 'series' => $radar['series']], 271) ?></script>
            <ul class="mi-radar-models list-unstyled" aria-label="รุ่นที่เลือก">
                <?php foreach ($radar['models'] as $rm): ?>
                    <li class="<?= $rm['scored'] ? '' : 'is-missing' ?>"><span class="mi-cmp-dot" style="--c:<?= $rm['color'] ?>" aria-hidden="true"></span><?= htmlspecialchars($rm['name']) ?><?php if (!$rm['scored']): ?> <em>ไม่มีคะแนนที่ตรงรุ่น</em><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
            <p class="mi-trend-note mb-0">คะแนนบรรณาธิการ 0-100 จาก InnoReviews (CC BY 4.0) ไม่ใช่ผลวัดดิบ และไม่ใช่การจัดอันดับของ ModInspect แต่ละสีคือหนึ่งรุ่น สีตรงกับจุดบนการ์ดด้านบน<?php if (!empty($radar['missing'])): ?> · รุ่นที่ไม่มีคะแนนที่ตรงกัน (เช่น แหล่งข้อมูลแยกรุ่น 8GB และ 16GB แต่ในระบบยังไม่ระบุขนาด) จะไม่ถูกเดาและไม่อยู่ในกราฟ<?php endif; ?></p>
        </section>
    <?php endif; ?>
    <?php // LS-22: a plain statement of what differs (labels only, no verdict), plus copy-link and print; both are hidden on paper ?>
    <?php $diffLabels = array_slice(array_map(static fn(array $r): string => (string) $r['label'], $diff), 0, 6); ?>
    <?php if ($diffLabels): ?><p class="mb-2"><strong>ที่ต่างกันระหว่างรุ่นที่เลือก:</strong> <?= htmlspecialchars(implode(', ', $diffLabels)) ?><?= count($diff) > 6 ? ' และอีก ' . (count($diff) - 6) . ' รายการ' : '' ?></p><?php endif; ?>
    <p class="d-print-none mb-2"><button type="button" class="btn btn-ghost btn-sm" data-copy-url><i class="fas fa-link mr-1" aria-hidden="true"></i><span>คัดลอกลิงก์เปรียบเทียบ</span></button> <button type="button" class="btn btn-ghost btn-sm" data-print><i class="fas fa-print mr-1" aria-hidden="true"></i>พิมพ์</button></p>
    <p class="mi-trend-note">ต่างกัน <?= count($diff) ?> รายการ · เหมือนกัน <?= count($same) ?> รายการ · กดหัวข้อเพื่อพับ/ขยายกลุ่ม · <span class="mi-delta mi-delta-up"><i class="fas fa-caret-up" aria-hidden="true"></i>+%</span> ค่าสูงสุดในแถว <span class="mi-delta mi-delta-down"><i class="fas fa-caret-down" aria-hidden="true"></i>−%</span> ค่าต่ำสุดในแถว (ตัวเลขคือส่วนต่างจากปลายอีกด้านของแถว) · สีบอกทิศทางของตัวเลขเท่านั้น ไม่ได้บอกว่ารุ่นไหนดีกว่า (เช่น TDP สูงไม่ได้แปลว่าดี)</p>
    <div class="card mi-table-card table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead>
                <tr>
                    <th scope="col"></th>
                    <?php foreach ($cols as $c): ?>
                        <th scope="col"><a href="<?= $base ?>/price/<?= htmlspecialchars($c['slug']) ?>"><?= htmlspecialchars($c['name']) ?></a><span class="d-block font-weight-normal"><?= htmlspecialchars($c['category']) ?></span></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <?php
            $group = static function (string $id, string $title, int $count, bool $open, int $span): string {
                return '<tr class="mi-cmp-group"><th colspan="' . $span . '" scope="colgroup"><a href="#' . $id . '" role="button" class="mi-cmp-toggle' . ($open ? '' : ' collapsed') . '" data-toggle="collapse" aria-expanded="' . ($open ? 'true' : 'false') . '" aria-controls="' . $id . '"><i class="fas fa-chevron-up mi-chev-up" aria-hidden="true"></i><i class="fas fa-chevron-down mi-chev-down" aria-hidden="true"></i><span>' . htmlspecialchars($title) . '</span><small>' . $count . '</small></a></th></tr>';
            };
            $span = count($cols) + 1;
            $specRows = static function (array $rows, string $badge) use ($cell, $extremes, $mark): void {
                foreach ($rows as $r): ?>
                    <?php $ex = $extremes(array_column($r['cells'], 'value')); ?><tr><th scope="row"><?= htmlspecialchars($r['label']) ?><?= $badge ?></th><?php foreach ($r['cells'] as $i => $c): ?><td><?= $cell($c) ?><?= $mark($ex[$i] ?? null) ?></td><?php endforeach; ?></tr>
                <?php endforeach;
            };
            ?>
            <tbody>
                <?= $group('cmp-price', 'ราคาตลาด', 4, true, $span) ?>
            </tbody>
            <tbody id="cmp-price" class="collapse show">
                <tr>
                    <th scope="row">ช่วงราคาที่พบบ่อย</th>
                    <?php foreach ($cols as $c): ?>
                        <td><?php if ($c['priced']): ?>฿<?= number_format($c['range'][0]) ?>–<?= number_format($c['range'][1]) ?><?php else: ?><span class="text-muted">ยังไม่มีราคาตลาดที่ตรวจแล้ว</span><?php endif; ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <th scope="row">ค่ากลาง</th>
                    <?php $ex = $extremes(array_map(static fn(array $c) => $c['priced'] ? (string)(int)$c['median'] : null, $cols)); foreach ($cols as $i => $c): ?><td><?= $c['priced'] ? '฿' . number_format($c['median']) . $mark($ex[$i] ?? null) : '<span class="text-muted">—</span>' ?></td><?php endforeach; ?>
                </tr>
                <tr>
                    <th scope="row">จำนวนตัวอย่าง</th>
                    <?php $ex = $extremes(array_map(static fn(array $c) => $c['priced'] ? (string)(int)$c['samples'] : null, $cols)); foreach ($cols as $i => $c): ?><td><?= $c['priced'] ? (int) $c['samples'] . $mark($ex[$i] ?? null) : '<span class="text-muted">—</span>' ?></td><?php endforeach; ?>
                </tr>
                <tr>
                    <th scope="row">ความมั่นใจของราคา</th>
                    <?php foreach ($cols as $c): ?><td><?= $c['priced'] ? htmlspecialchars($confTh[$c['confidence']] ?? 'ข้อมูลยังไม่พอ') : '<span class="text-muted">—</span>' ?></td><?php endforeach; ?>
                </tr>
            </tbody>
            <?php if ($diff): ?>
            <tbody><?= $group('cmp-diff', 'สเปกที่ต่างกัน', count($diff), true, $span) ?></tbody>
            <tbody id="cmp-diff" class="collapse show"><?php $specRows($diff, ' <span class="badge badge-warning">ต่างกัน</span>'); ?></tbody>
            <?php endif; ?>
            <?php if ($same): ?>
            <tbody><?= $group('cmp-same', 'สเปกที่เหมือนกัน', count($same), !$diff, $span) ?></tbody>
            <tbody id="cmp-same" class="collapse<?= $diff ? '' : ' show' ?>"><?php $specRows($same, ''); ?></tbody>
            <?php endif; ?>
        </table>
    </div>
    <?php if (!$table['any_priced']): ?>
        <p class="mi-note"><i class="fas fa-info-circle" aria-hidden="true"></i> ยังไม่มีรุ่นที่เลือกที่มีราคาตลาดผ่านการตรวจสอบ จึงเปรียบเทียบราคาไม่ได้ สเปกด้านบนยังใช้ได้</p>
    <?php endif; ?>
    <?php if ($hasNote): ?>
        <p class="mi-trend-note">บางค่าแหล่งที่มาระบุหลายรุ่นย่อย ตรวจรุ่นย่อยของชิ้นที่จะซื้อให้ตรงก่อน (ดูคำเตือนรายค่าได้ที่หน้ารุ่นนั้น)</p>
    <?php endif; ?>
    <p class="mi-trend-note">ราคาเป็นราคาประกาศขายจากข้อมูลที่ตรวจแล้ว ไม่ใช่ราคาที่ขายได้จริง ตารางนี้ไม่ได้จัดอันดับ ไม่มีคะแนนเบนช์มาร์ก เพราะยังไม่มีแหล่งข้อมูลที่ได้รับอนุญาตให้นำมาแสดง</p>
<?php endif; ?>
</section>
