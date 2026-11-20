<?php /** LS-25 C5: models the visitor watches. @var list<array{product:array,then:?float,now:?float,change:?array}> $rows */
$baht = static fn(float $v): string => '฿' . number_format($v); ?>
<section class="container page">
    <div class="mi-page-heading narrow-copy">
        <span class="eyebrow">รุ่นที่ติดตาม</span>
        <h1>รายการที่คุณติดตามอยู่</h1>
        <p class="lead">เก็บไว้ในเบราว์เซอร์ของคุณเท่านั้น ไม่ต้องสมัครสมาชิก และเราไม่เก็บรายการนี้ไว้ที่เซิร์ฟเวอร์ ราคาที่แสดงคือราคากลางมือสองที่ตรวจแล้ว เทียบกับตอนที่คุณกดติดตาม</p>
    </div>
    <?php if (!$rows): ?>
        <div class="mi-empty-state"><h2>ยังไม่ได้ติดตามรุ่นไหน</h2><p>เปิดหน้ารุ่นที่สนใจแล้วกด “ติดตามรุ่นนี้”</p><div class="mi-actions"><a class="button" href="<?= $base ?>/price">ค้นหารุ่น</a></div></div>
    <?php else: ?>
        <div class="row">
        <?php foreach ($rows as $r): $p = $r['product']; $c = $r['change']; ?>
            <div class="col-md-6 mb-3"><article class="card h-100"><div class="card-body">
                <h2 class="h5 mb-1"><a href="<?= $base ?>/price/<?= rawurlencode((string) $p['slug']) ?>"><?= htmlspecialchars((string) $p['full_name']) ?></a></h2>
                <?php if ($r['now'] === null): ?>
                    <p class="text-muted mb-0">ตอนนี้ยังไม่มีราคาที่ตรวจแล้ว</p>
                <?php else: ?>
                    <p class="mb-1"><strong><?= $baht($r['now']) ?></strong> <small class="text-muted">ราคากลางมือสองตอนนี้</small></p>
                    <?php if ($c === null): ?>
                        <p class="mb-0"><small class="text-muted">ตอนกดติดตามรุ่นนี้ยังไม่มีราคา จึงยังไม่มีส่วนต่างให้เทียบ</small></p>
                    <?php elseif ($c['direction'] === 'same'): ?>
                        <p class="mb-0"><small class="text-muted">เท่ากับตอนที่กดติดตาม (<?= $baht((float) $r['then']) ?>)</small></p>
                    <?php else: ?>
                        <p class="mb-0"><?php if ($c['direction'] === 'up'): ?><i class="fas fa-arrow-up text-success" aria-hidden="true"></i><?php else: ?><i class="fas fa-arrow-down text-danger" aria-hidden="true"></i><?php endif; ?>
                            <small><?= $c['direction'] === 'up' ? 'สูงขึ้น' : 'ต่ำลง' ?> <?= $baht($c['diff']) ?> (<?= (int) $c['percent'] ?>%) จากตอนที่กดติดตาม <?= $baht((float) $r['then']) ?></small></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div></article></div>
        <?php endforeach; ?>
        </div>
        <p class="mi-trend-note">ลูกศรบอกทิศทางของราคากลางเท่านั้น ไม่ได้บอกว่าควรซื้อหรือรอ</p>
    <?php endif; ?>
</section>
