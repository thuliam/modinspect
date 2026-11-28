<?php
/** LS-90 D3: @var list<array> $listings  @var list<array{id:int,filters:array}> $searches  @var ?string $notice */
$e = static fn($v): string => htmlspecialchars((string) $v);
$token = $e(\App\Core\Csrf::token());
$labels = ['q' => 'ชื่อรุ่น', 'category' => 'หมวด', 'province' => 'จังหวัด', 'max_price' => 'ราคาไม่เกิน', 'verified' => 'ผู้ขายยืนยันแล้ว'];
?>
<section class="container page">
    <div class="mi-page-heading"><span class="eyebrow">ของฉัน</span><h1>ประกาศและการค้นหาที่บันทึกไว้</h1><p class="lead"><a href="<?= $base ?>/listings">กลับไปค้นประกาศ</a></p></div>
    <?php if ($notice): ?><div class="alert alert-info" role="status"><?= $e($notice) ?></div><?php endif; ?>
    <div class="card mb-3">
        <h2 class="h5">ประกาศ</h2>
        <?php if (!$listings): ?><p class="mb-0 text-muted">ยังไม่ได้บันทึกประกาศ</p><?php else: ?><ul class="list-unstyled mb-0">
            <?php foreach ($listings as $l): ?><li class="d-flex flex-wrap align-items-center mb-2">
                <?php if ((int) $l['still_public'] === 1): ?><a class="mr-2" href="<?= $base ?>/listings/<?= $e($l['uuid']) ?>"><?= $e($l['full_name']) ?></a><b class="mr-2">฿<?= number_format((float) $l['asking_price']) ?></b>
                <?php else: ?><span class="mr-2"><?= $e($l['full_name']) ?></span><span class="badge badge-secondary mr-2">ไม่แสดงแล้ว</span><?php endif; ?>
                <form class="ml-auto" method="post" action="<?= $base ?>/listings/<?= $e($l['uuid']) ?>/save"><input type="hidden" name="_token" value="<?= $token ?>"><input type="hidden" name="from" value="saved"><button class="button ghost" type="submit">เอาออก</button></form>
            </li><?php endforeach; ?></ul><?php endif; ?>
    </div>
    <div class="card">
        <h2 class="h5">การค้นหา</h2>
        <?php if (!$searches): ?><p class="mb-0 text-muted">ยังไม่ได้บันทึกการค้นหา</p><?php else: ?><ul class="list-unstyled mb-0">
            <?php foreach ($searches as $s): $parts = []; foreach ($s['filters'] as $k => $v) { if (isset($labels[$k])) { $parts[] = $k === 'verified' ? $labels[$k] : $labels[$k] . ' ' . $v; } } ?>
                <li class="d-flex flex-wrap align-items-center mb-2"><a class="mr-2" href="<?= $base ?>/listings?<?= $e(http_build_query($s['filters'])) ?>"><?= $e(implode(' · ', $parts)) ?></a>
                    <form class="ml-auto" method="post" action="<?= $base ?>/listings/searches/<?= (int) $s['id'] ?>/delete"><input type="hidden" name="_token" value="<?= $token ?>"><button class="button ghost" type="submit">ลบ</button></form></li>
            <?php endforeach; ?></ul><?php endif; ?>
    </div>
</section>
