<?php
/** @var array $table CompareTable::build output  @var list<array{slug:string,name:string,category:string}> $options  @var list<string> $chosen */
$confTh = ['high' => 'สูง', 'medium' => 'ปานกลาง', 'low' => 'จำกัด'];
$cols = $table['columns'];
$slots = max(2, count($cols));
$byCat = [];
foreach ($options as $o) { $byCat[$o['category']][] = $o; }
$hasNote = false;
foreach ($table['rows'] as $r) { foreach ($r['cells'] as $c) { if (!empty($c['note'])) { $hasNote = true; } } }
?>
<section class="container page">
<span class="eyebrow">เปรียบเทียบสินค้า</span><h1>เปรียบเทียบสเปกและราคา</h1>
<p class="lead">เลือก 2–4 รุ่นเพื่อดูสเปกจากผู้ผลิตและราคาที่ผ่านการตรวจแล้วเทียบกัน ระบบไม่จัดอันดับและไม่บอกว่ารุ่นไหนดีกว่า</p>

<form method="get" action="<?= $base ?>/compare" class="card mb-4">
    <div class="form-row">
        <?php for ($i = 0; $i < min(4, $slots + 1); $i++): $sel = $chosen[$i] ?? ''; ?>
            <div class="form-group col-md-3">
                <label for="m<?= $i ?>">รุ่นที่ <?= $i + 1 ?></label>
                <select id="m<?= $i ?>" name="m[]" class="form-control">
                    <option value="">— ไม่เลือก —</option>
                    <?php foreach ($byCat as $cat => $list): ?>
                        <optgroup label="<?= htmlspecialchars($cat) ?>">
                            <?php foreach ($list as $o): ?>
                                <option value="<?= htmlspecialchars($o['slug']) ?>"<?= $o['slug'] === $sel ? ' selected' : '' ?>><?= htmlspecialchars($o['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endfor; ?>
    </div>
    <button type="submit" class="btn btn-primary">เปรียบเทียบ</button>
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
    <?php endif; ?>
    <div class="card mi-table-card table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead>
                <tr>
                    <th scope="col"></th>
                    <?php foreach ($cols as $c): ?>
                        <th scope="col"><a href="<?= $base ?>/price/<?= htmlspecialchars($c['slug']) ?>"><?= htmlspecialchars($c['name']) ?></a><br><small class="text-muted"><?= htmlspecialchars($c['category']) ?></small></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th scope="row">ช่วงราคาที่พบบ่อย</th>
                    <?php foreach ($cols as $c): ?>
                        <td><?php if ($c['priced']): ?>฿<?= number_format($c['range'][0]) ?>–<?= number_format($c['range'][1]) ?><?php else: ?><span class="text-muted">ยังไม่มีราคาตลาดที่ตรวจแล้ว</span><?php endif; ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <th scope="row">ค่ากลาง</th>
                    <?php foreach ($cols as $c): ?><td><?= $c['priced'] ? '฿' . number_format($c['median']) : '<span class="text-muted">—</span>' ?></td><?php endforeach; ?>
                </tr>
                <tr>
                    <th scope="row">จำนวนตัวอย่าง</th>
                    <?php foreach ($cols as $c): ?><td><?= $c['priced'] ? (int)$c['samples'] : '<span class="text-muted">—</span>' ?></td><?php endforeach; ?>
                </tr>
                <tr>
                    <th scope="row">ความมั่นใจของราคา</th>
                    <?php foreach ($cols as $c): ?><td><?= $c['priced'] ? htmlspecialchars($confTh[$c['confidence']] ?? 'ข้อมูลยังไม่พอ') : '<span class="text-muted">—</span>' ?></td><?php endforeach; ?>
                </tr>
                <?php foreach ($table['rows'] as $r): ?>
                    <tr>
                        <th scope="row"><?= htmlspecialchars($r['label']) ?><?php if ($r['differs']): ?> <span class="badge badge-warning">ต่างกัน</span><?php endif; ?></th>
                        <?php foreach ($r['cells'] as $cell): ?>
                            <td>
                                <?php if ($cell['value'] === null): ?><span class="text-muted">ไม่มีข้อมูล</span><?php else: ?>
                                    <?= htmlspecialchars($cell['value']) ?>
                                    <?php if (!empty($cell['source'])): ?><small><a href="<?= htmlspecialchars($cell['source']) ?>" target="_blank" rel="noopener noreferrer">ที่มา</a></small><?php endif; ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
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
