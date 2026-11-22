<?php /** LS-24 M8: article list. @var string $q @var array $list */
$rows = $list['rows'];
$months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
$thDate = static function (string $d) use ($months): string { $t = strtotime($d); return $t ? (int) date('j', $t) . ' ' . $months[(int) date('n', $t) - 1] . ' ' . ((int) date('Y', $t) + 543) : ''; };
$link = static fn(array $extra): string => '?' . http_build_query(array_filter($extra));
// LS-55: category sidebar (only when the database has article categories); the search keeps the chosen category
$cat = $cat ?? null; $catCounts = $catCounts ?? []; $sideCats = $catCounts ? array_merge(\App\Services\Blog\ArticleCategory::LABELS, [\App\Services\Blog\ArticleCategory::GENERAL => 'ทั่วไป']) : [];
?>
<link rel="alternate" type="application/rss+xml" title="ModInspect บทความ" href="<?= $base ?>/blog/feed.xml">
<section class="container page">
    <div class="mi-page-heading">
        <span class="eyebrow">บทความ</span>
        <h1>เรื่องเล่าและคู่มือจากตลาดคอมมือสอง</h1>
        <p class="lead">วิธีดูของ วิธีอ่านราคา และสิ่งที่ควรถามก่อนจ่ายเงิน เขียนด้วยตัวเลขจริงจากข้อมูลที่ตรวจแล้ว</p>
    </div>
    <form class="mi-ask mb-4" method="get" action="<?= $base ?>/blog" role="search">
        <label class="sr-only" for="blog-q">ค้นหาบทความ</label>
        <?php if ($cat): ?><input type="hidden" name="cat" value="<?= htmlspecialchars($cat) ?>"><?php endif; ?>
        <div class="mi-ask-bar">
            <i class="fas fa-search mi-ask-ico" aria-hidden="true"></i>
            <input id="blog-q" class="mi-ask-input" name="q" type="search" autocomplete="off" value="<?= htmlspecialchars($q) ?>" maxlength="80" placeholder="ค้นหาบทความ เช่น การ์ดจอขุด, ต่อรองราคา">
            <button class="mi-ask-go" type="submit" aria-label="ค้นหา" title="ค้นหา"><i class="fas fa-arrow-right" aria-hidden="true"></i></button>
        </div>
    </form>
    <div class="row mi-price-layout">
    <?php if ($sideCats): ?>
    <aside class="col-lg-3 mi-side mi-side-plain" aria-label="หมวดบทความ">
        <nav class="mi-filter-card mi-filter-side mi-blog-cats" aria-label="หมวดบทความ">
            <p class="mi-side-title">หมวดบทความ</p>
            <a class="mi-blog-cat<?= $cat === null ? ' active' : '' ?>"<?= $cat === null ? ' aria-current="true"' : '' ?> href="<?= $base ?>/blog<?= $q !== '' ? $link(['q' => $q]) : '' ?>"><i class="fas fa-th-large" aria-hidden="true"></i><span>ทั้งหมด</span><em><?= array_sum($catCounts) ?></em></a>
            <?php foreach ($sideCats as $cv => $cl): if (empty($catCounts[$cv]) && $cat !== $cv) { continue; } ?>
                <a class="mi-blog-cat<?= $cat === $cv ? ' active' : '' ?>"<?= $cat === $cv ? ' aria-current="true"' : '' ?> href="<?= $base ?>/blog<?= $link(['q' => $q, 'cat' => $cv]) ?>"><i class="fas <?= \App\Services\Blog\ArticleCategory::ICONS[$cv] ?? 'fa-book-open' ?>" aria-hidden="true"></i><span><?= htmlspecialchars($cl) ?></span><em><?= (int) ($catCounts[$cv] ?? 0) ?></em></a>
            <?php endforeach; ?>
        </nav>
    </aside>
    <?php endif; ?>
    <div class="<?= $sideCats ? 'col-lg-9' : 'col-12' ?> mi-main">
    <?php if (!$rows): ?>
        <div class="mi-empty-state"><h2><?= $q !== '' ? 'ไม่พบบทความที่ค้นหา' : 'ยังไม่มีบทความ' ?></h2><p>ลองคำอื่น หรือกลับมาดูใหม่เร็ว ๆ นี้</p><div class="mi-actions"><a class="button" href="<?= $base ?>/blog">ดูทั้งหมด</a></div></div>
    <?php else: ?>
        <div class="row">
        <?php foreach ($rows as $a): $sum = trim((string) ($a['excerpt'] ?? '')) !== '' ? (string) $a['excerpt'] : \App\Services\Blog\BlogRenderer::summary((string) $a['body_head'], 150); ?>
            <div class="<?= $sideCats ? 'col-md-6 col-xl-4' : 'col-md-4' ?> mb-4"><article class="card h-100"><div class="card-body">
                <?php if ($sideCats && !empty($a['category'])): ?><span class="mi-blog-tag"><?= htmlspecialchars(\App\Services\Blog\ArticleCategory::label($a['category'])) ?></span><?php endif; ?>
                <small class="text-muted"><?= $thDate((string) $a['published_at']) ?> · อ่าน ~<?= max(1, (int) ceil((int) $a['body_len'] / 900)) ?> นาที</small>
                <h2 class="h5 mt-1"><a href="<?= $base ?>/blog/<?= rawurlencode((string) $a['slug']) ?>"><?= htmlspecialchars((string) $a['title']) ?></a></h2>
                <p class="mb-0"><?= htmlspecialchars($sum) ?></p>
            </div></article></div>
        <?php endforeach; ?>
        </div>
        <?php if ($list['pages'] > 1): ?>
        <nav class="d-flex justify-content-between" aria-label="หน้าบทความ">
            <?php if ($list['page'] > 1): ?><a class="btn btn-ghost" href="<?= $link(['q' => $q, 'cat' => $cat, 'page' => $list['page'] - 1 > 1 ? $list['page'] - 1 : null]) ?>">ก่อนหน้า</a><?php else: ?><span></span><?php endif; ?>
            <span class="mi-trend-note">หน้า <?= (int) $list['page'] ?> จาก <?= (int) $list['pages'] ?></span>
            <?php if ($list['page'] < $list['pages']): ?><a class="btn btn-ghost" href="<?= $link(['q' => $q, 'cat' => $cat, 'page' => $list['page'] + 1]) ?>">ถัดไป</a><?php else: ?><span></span><?php endif; ?>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
    </div>
    </div>
</section>
<?php require ROOT_PATH . '/app/Views/components/next-step.php'; // LS-46: every page ends with a next step ?>
