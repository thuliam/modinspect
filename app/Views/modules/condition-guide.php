<?php
/** @var array|null $guide ConditionGuide::for output, null for the index  @var list<array{slug:string,name:string}> $categories */
?>
<section class="container page article">
<span class="eyebrow">คู่มือเช็กสภาพก่อนซื้อ</span>
<?php if ($guide === null): ?>
    <h1>เช็กสภาพคอมมือสอง ก่อนจ่ายเงิน</h1>
    <p class="lead">เลือกหมวดสินค้าเพื่อดูสิ่งที่ควรดู ควรทดสอบ ควรถาม และควรระวัง</p>
    <div class="row">
        <?php foreach ($categories as $c): ?>
            <div class="col-md-4 mb-4"><a class="card h-100 p-3" href="<?= $base ?>/condition-guide/<?= htmlspecialchars($c['slug']) ?>"><h2 class="h5 mb-0"><?= htmlspecialchars($c['name']) ?></h2></a></div>
        <?php endforeach; ?>
    </div>
    <p class="mi-trend-note"><?= htmlspecialchars(\App\Services\Catalog\ConditionGuide::NOTE) ?></p>
<?php else: ?>
    <h1>เช็กสภาพ <?= htmlspecialchars($guide['name']) ?> ก่อนซื้อ</h1>
    <p class="lead"><?= htmlspecialchars($guide['intro']) ?></p>
    <div class="article-grid">
        <article class="card prose">
            <?php foreach ($guide['sections'] as $i => $s): ?>
                <h2><?= $i + 1 ?>. <?= htmlspecialchars($s['title']) ?></h2>
                <ul><?php foreach ($s['items'] as $item): ?><li><?= htmlspecialchars($item) ?></li><?php endforeach; ?></ul>
            <?php endforeach; ?>
        </article>
        <aside class="card sticky">
            <h2>หมวดอื่น</h2>
            <ul class="list-unstyled mb-3">
                <?php foreach ($categories as $c): if ($c['slug'] === $guide['slug']) { continue; } ?>
                    <li><a href="<?= $base ?>/condition-guide/<?= htmlspecialchars($c['slug']) ?>"><?= htmlspecialchars($c['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <a class="button" href="<?= $base ?>/deal-checker">เปิด Deal Checker</a>
        </aside>
    </div>
    <p class="mi-trend-note"><?= htmlspecialchars($guide['note']) ?></p>
<?php endif; ?>
</section>
