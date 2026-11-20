<?php
/** LS-24 A1/A2: paste a listing, get it read. @var ?array $result @var string $text @var ?string $error */
$labels = ['below_range' => 'ต่ำกว่าช่วงราคาที่พบ', 'in_range' => 'อยู่ในช่วงตลาด', 'above_range' => 'สูงกว่าช่วงราคาที่พบ', 'insufficient' => 'ข้อมูลยังไม่พอเทียบ'];
$tones = ['below_range' => 'warn', 'in_range' => 'ok', 'above_range' => 'warn', 'insufficient' => 'neutral'];
$r = $result; $v = $r['verdict'] ?? null; $p = $r['product'] ?? null;
$yn = static fn(?bool $b): string => $b === true ? 'มีระบุ' : 'ไม่ได้ระบุ';
?>
<section class="container page mi-deal-page">
    <div class="mi-page-heading narrow-copy">
        <span class="eyebrow">วางประกาศ</span>
        <h1>วางประกาศที่เจอ แล้วให้ระบบอ่านให้</h1>
        <p class="lead">คัดลอกข้อความประกาศจาก Facebook, Pantip หรือ Kaidee มาวาง ระบบจะบอกว่าเป็นรุ่นอะไร ราคาอยู่ตรงไหนของตลาด มีวลีที่ควรถามก่อนซื้อไหม ไม่เก็บข้อความที่วาง และซ่อนเบอร์โทรกับไลน์ให้</p>
    </div>

    <div class="mi-deal-layout">
        <form method="post" action="<?= $base ?>/read-listing" class="card form mi-deal-form">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
            <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <div class="mi-field">
                <label for="listing-text">ข้อความประกาศ</label>
                <textarea id="listing-text" name="listing" rows="9" maxlength="3500" class="form-control" placeholder="วางข้อความทั้งโพสต์ที่นี่ เช่น: ขาย RTX 3070 Gigabyte Gaming OC ราคา 8,500 บาท ประกันเหลือ 6 เดือน มีกล่อง"><?= htmlspecialchars($r ? $text : '') ?></textarea>
            </div>
            <?php if ($r && ($r['price_confidence'] ?? '') !== 'high'): ?>
                <div class="mi-field">
                    <label for="listing-price">ราคาในประกาศ (บาท)</label>
                    <input id="listing-price" type="number" name="price" min="1" max="10000000" step="1" class="form-control" value="<?= $r['price'] !== null ? (int) $r['price'] : '' ?>" placeholder="ระบบอ่านราคาไม่ชัด กรอกเองได้">
                </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">อ่านประกาศ</button>
        </form>
    </div>

    <?php if ($r && !$r['empty']): ?>
    <div class="mi-deal-layout mt-4">
        <article class="card result mi-result-panel">
            <?php if ($p): ?>
                <div class="mi-result-head">
                    <div>
                        <span class="mi-result-kicker">อ่านได้ว่าเป็นรุ่น</span>
                        <h2 class="h4 mb-1"><a href="<?= $base ?>/price/<?= rawurlencode($p['slug']) ?>"><?= htmlspecialchars($p['name']) ?></a></h2>
                        <?php if ($r['price'] !== null): ?><div class="big-price">฿<?= number_format((float) $r['price']) ?></div>
                            <small class="text-muted"><?= ['high' => 'ราคาที่ระบุในประกาศ', 'typed' => 'ราคาที่คุณกรอก', 'low' => 'อ่านได้ตัวเลขเดียวที่ไม่ใช่สเปก (ตรวจให้ตรงกับประกาศ)'][$r['price_confidence']] ?? '' ?></small>
                        <?php endif; ?>
                    </div>
                    <?php if ($v): ?><span class="mi-verdict mi-verdict-<?= $tones[$v['label']] ?? 'neutral' ?>"><?= htmlspecialchars($labels[$v['label']] ?? $v['label']) ?></span><?php endif; ?>
                </div>
                <?php if ($v): ?>
                    <p class="mi-result-text"><?= htmlspecialchars((string) $v['recommendation']) ?></p>
                    <?php if (($v['label'] ?? '') !== 'insufficient' && $p['q1'] && $p['q3']): ?>
                        <div class="mi-compare">
                            <div><span>ช่วงราคาที่พบ</span><b>฿<?= number_format($p['q1']) ?> – ฿<?= number_format($p['q3']) ?></b></div>
                            <?php if ($p['median']): ?><div><span>ราคากลาง</span><b>฿<?= number_format($p['median']) ?></b></div><?php endif; ?>
                            <div><span>ตัวอย่าง</span><b><?= (int) $p['samples'] ?> ประกาศ</b></div>
                        </div>
                    <?php endif; ?>
                    <p class="mi-trend-note"><?= htmlspecialchars((string) ($v['warning'] ?? '')) ?></p>
                    <a class="btn btn-ghost btn-sm" href="<?= $base ?>/deal-checker?product=<?= (int) $p['id'] ?>&amp;user_price=<?= (int) $r['price'] ?>">บันทึกเป็นผลเช็กดีล และสร้างการ์ดแชร์</a>
                <?php elseif ($r['price'] === null): ?>
                    <p class="mi-result-text">ยังอ่านราคาจากข้อความไม่ได้ชัดเจน<?= !empty($r['price_candidates']) ? ' (พบตัวเลข ' . htmlspecialchars(implode(', ', array_map(static fn($n) => number_format((float) $n), $r['price_candidates']))) . ')' : '' ?> กรอกราคาในช่องด้านบนแล้วกดอ่านอีกครั้ง</p>
                <?php endif; ?>
            <?php else: ?>
                <div class="mi-result-empty"><i class="fas fa-search" aria-hidden="true"></i>
                    <div><strong>ยังจับคู่รุ่นจากข้อความไม่ได้</strong><span>ลองใส่ชื่อรุ่นให้ชัดขึ้น หรือเลือกรุ่นเองที่ <a href="<?= $base ?>/deal-checker">Deal Checker</a></span></div>
                </div>
            <?php endif; ?>
            <?php foreach ($r['notes'] as $n): ?><div class="alert mt-3"><?= htmlspecialchars($n) ?></div><?php endforeach; ?>
        </article>

        <aside class="card mi-result-panel">
            <h2 class="h5">สิ่งที่อ่านได้จากข้อความ</h2>
            <ul class="mb-0">
                <li>ประกัน: <?= $r['facts']['warranty_months'] !== null ? 'เหลือ ' . (int) $r['facts']['warranty_months'] . ' เดือน' : (in_array('no_warranty', array_column($r['flags'], 'code'), true) ? 'ประกาศระบุว่าไม่มี' : 'ไม่ได้ระบุ') ?></li>
                <li>กล่อง: <?= $yn($r['facts']['has_box']) ?> · ใบเสร็จ: <?= $yn($r['facts']['has_receipt']) ?></li>
            </ul>
        </aside>
    </div>

    <div class="mi-deal-layout mt-4">
        <section class="card mi-result-panel">
            <h2 class="h5">วลีในประกาศที่ควรถามเพิ่ม</h2>
            <?php if ($r['flags']): ?>
                <ul class="list-unstyled mb-0">
                <?php foreach ($r['flags'] as $f): ?>
                    <li class="mb-3"><span class="badge"><?= $f['level'] === 'caution' ? 'ควรถาม' : 'ข้อสังเกต' ?></span> <strong><?= htmlspecialchars($f['label']) ?></strong> <small class="text-muted">พบคำว่า “<?= htmlspecialchars($f['phrase']) ?>”</small><br>
                        <span><?= htmlspecialchars($f['why']) ?></span><br><small class="text-muted"><?= htmlspecialchars($f['ask']) ?></small></li>
                <?php endforeach; ?>
                </ul>
            <?php else: ?><p class="mb-0">ไม่พบวลีที่อยู่ในรายการเฝ้าระวัง นี่ไม่ได้แปลว่าปลอดภัย ลองถามคำถามด้านล่างก่อนจ่ายเสมอ</p><?php endif; ?>
            <p class="mi-trend-note mt-3 mb-0">รายการนี้อธิบายข้อความในประกาศ ไม่ได้ตัดสินตัวผู้ขาย · <a href="<?= $base ?>/glossary">ศัพท์ตลาดมือสอง</a></p>
        </section>
        <aside class="card mi-result-panel">
            <h2 class="h5">คำถามที่ควรถามก่อนจ่าย</h2>
            <ol class="mb-0"><?php foreach ($r['questions'] as $q): ?><li><?= htmlspecialchars($q) ?></li><?php endforeach; ?></ol>
        </aside>
    </div>
    <?php // LS-24 A3 ?>
    <?php $neg = ($p && $v && ($v['label'] ?? '') !== 'insufficient' && $p['q1'] && $p['q3'] && $r['price'] !== null) ? \App\Services\Deal\Negotiation::plan($p['name'], (float) $r['price'], $p['q1'], $p['q3'], $p['median'], $p['samples']) : null; require ROOT_PATH . '/app/Views/components/negotiation.php'; ?>
    <?php endif; ?>
</section>
