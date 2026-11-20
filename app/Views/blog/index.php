<?php /** LS-24 M8: article list. @var string $q @var array $list */
$rows = $list['rows'];
$months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
$thDate = static function (string $d) use ($months): string { $t = strtotime($d); return $t ? (int) date('j', $t) . ' ' . $months[(int) date('n', $t) - 1] . ' ' . ((int) date('Y', $t) + 543) : ''; };
$link = static fn(array $extra): string => '?' . http_build_query(array_filter($extra));
?>
<link rel="alternate" type="application/rss+xml" title="ModInspect บทความ" href="<?= $base ?>/blog/feed.xml">
<section class="container page">
    <div class="mi-page-heading">
        <span class="eyebrow">บทความ</span>
        <h1>เรื่องเล่าและคู่มือจากตลาดคอมมือสอง</h1>
        <p class="lead">วิธีดูของ วิธีอ่านราคา และสิ่งที่ควรถามก่อนจ่ายเงิน เขียนด้วยตัวเลขจริงจากข้อมูลที่ตรวจแล้ว</p>
    </div>
    <form class="mi-filter-card" method="get" action="<?= $base ?>/blog">
        <label for="blog-q">ค้นหาบทความ</label>
        <div class="mi-filter-row">
            <input id="blog-q" name="q" type="search" class="form-control" value="<?= htmlspecialchars($q) ?>" maxlength="80" placeholder="เช่น การ์ดจอขุด, ต่อรองราคา">
            <button type="submit">ค้นหา</button>
        </div>
    </form>
    <?php if (!$rows): ?>
        <div class="mi-empty-state"><h2><?= $q !== '' ? 'ไม่พบบทความที่ค้นหา' : 'ยังไม่มีบทความ' ?></h2><p>ลองคำอื่น หรือกลับมาดูใหม่เร็ว ๆ นี้</p><div class="mi-actions"><a class="button" href="<?= $base ?>/blog">ดูทั้งหมด</a></div></div>
    <?php else: ?>
        <div class="row">
        <?php foreach ($rows as $a): $sum = trim((string) ($a['excerpt'] ?? '')) !== '' ? (string) $a['excerpt'] : \App\Services\Blog\BlogRenderer::summary((string) $a['body_head'], 150); ?>
            <div class="col-md-4 mb-4"><article class="card h-100"><div class="card-body">
                <small class="text-muted"><?= $thDate((string) $a['published_at']) ?> · อ่าน ~<?= max(1, (int) ceil((int) $a['body_len'] / 900)) ?> นาที</small>
                <h2 class="h5 mt-1"><a href="<?= $base ?>/blog/<?= rawurlencode((string) $a['slug']) ?>"><?= htmlspecialchars((string) $a['title']) ?></a></h2>
                <p class="mb-0"><?= htmlspecialchars($sum) ?></p>
            </div></article></div>
        <?php endforeach; ?>
        </div>
        <?php if ($list['pages'] > 1): ?>
        <nav class="d-flex justify-content-between" aria-label="หน้าบทความ">
            <?php if ($list['page'] > 1): ?><a class="btn btn-ghost" href="<?= $link(['q' => $q, 'page' => $list['page'] - 1 > 1 ? $list['page'] - 1 : null]) ?>">ก่อนหน้า</a><?php else: ?><span></span><?php endif; ?>
            <span class="mi-trend-note">หน้า <?= (int) $list['page'] ?> จาก <?= (int) $list['pages'] ?></span>
            <?php if ($list['page'] < $list['pages']): ?><a class="btn btn-ghost" href="<?= $link(['q' => $q, 'page' => $list['page'] + 1]) ?>">ถัดไป</a><?php else: ?><span></span><?php endif; ?>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
