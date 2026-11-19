<?php
/** @var array|null $guide ConditionGuide::for output, null for the index  @var list<array{slug:string,name:string}> $categories */
?>
<section class="container page article">
<span class="eyebrow">คู่มือเช็กสภาพก่อนซื้อ</span>
<?php if ($guide === null): ?>
    <h1>เช็กสภาพคอมมือสอง ก่อนจ่ายเงิน</h1>
    <p class="lead">เลือกหมวดสินค้าเพื่อดูสิ่งที่ควรดู ควรทดสอบ ควรถาม และควรระวัง</p>
    <?php // LS-16 U-3: the same category cards as the home page (picture for the four with one, an icon for the rest), so the guide looks like the rest of the site
    require_once ROOT_PATH . '/app/Views/components/hw-art.php';
    $guideArt = ['cpu' => ['art' => 'cpu'], 'gpu' => ['art' => 'gpu'], 'ram' => ['art' => 'ram'], 'storage' => ['art' => 'ssd'], 'psu' => ['icon' => 'fa-bolt'], 'motherboard' => ['icon' => 'fa-server'], 'case' => ['icon' => 'fa-box'], 'cooling' => ['icon' => 'fa-snowflake']]; ?>
    <div class="row mi-cats">
        <?php foreach ($categories as $i => $c): $a = $guideArt[$c['slug']] ?? ['icon' => 'fa-clipboard-check']; ?>
            <div class="col-6 col-md-4 col-xl-3 mb-3">
                <a class="mi-cat reveal" style="--d:<?= (int)$i * 70 ?>ms" href="<?= $base ?>/condition-guide/<?= htmlspecialchars($c['slug']) ?>">
                    <span class="mi-cat-art"><?= isset($a['art']) ? mi_art_img($a['art'], $base) : '<i class="fas ' . htmlspecialchars($a['icon']) . '" aria-hidden="true"></i>' ?></span>
                    <strong><?= htmlspecialchars($c['name']) ?></strong>
                    <span class="mi-go"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="mi-trend-note"><?= htmlspecialchars(\App\Services\Catalog\ConditionGuide::NOTE) ?></p>
<?php else: ?>
    <h1>เช็กสภาพ <?= htmlspecialchars($guide['name']) ?> ก่อนซื้อ</h1>
    <p class="lead"><?= htmlspecialchars($guide['intro']) ?></p>
    <div class="article-grid">
        <?php // LS-19 B2: a checklist to tick while looking at the part (Bootstrap custom checkboxes in a plain form: nothing is sent or stored; "ล้าง" is a native reset) ?>
        <form class="card prose mi-guide-checklist" aria-label="รายการตรวจ ติ๊กได้ระหว่างดูของ ไม่มีการบันทึก">
            <?php foreach ($guide['sections'] as $i => $s): ?>
                <h2><?= $i + 1 ?>. <?= htmlspecialchars($s['title']) ?></h2>
                <div class="mb-3">
                    <?php foreach ($s['items'] as $j => $item): $cid = 'g' . $i . '-' . $j; ?>
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" class="custom-control-input" id="<?= $cid ?>">
                            <label class="custom-control-label" for="<?= $cid ?>"><?= htmlspecialchars($item) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <p class="mi-trend-note mb-2">ติ๊กไว้ระหว่างดูของจริงได้ ระบบไม่ได้บันทึกหรือส่งข้อมูลนี้ไปไหน</p>
            <button type="reset" class="btn btn-ghost align-self-start">ล้างที่ติ๊กไว้</button>
        </form>
        <aside class="card sticky">
            <h2>หมวดอื่น</h2>
            <ul class="list-unstyled mb-3">
                <?php foreach ($categories as $c): if ($c['slug'] === $guide['slug']) { continue; } ?>
                    <li><a href="<?= $base ?>/condition-guide/<?= htmlspecialchars($c['slug']) ?>"><?= htmlspecialchars($c['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <a class="button" href="<?= $base ?>/deal-checker">เปิด Deal Checker</a>
            <a class="button ghost mt-2" href="<?= $base ?>/price?category=<?= rawurlencode($guide['slug']) ?>">ดูราคา <?= htmlspecialchars($guide['name']) ?> มือสอง</a>
        </aside>
    </div>
    <p class="mi-trend-note"><?= htmlspecialchars($guide['note']) ?></p>
<?php endif; ?>
</section>
