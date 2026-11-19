<?php /** LS-24 M8: one article. @var array $article @var string $html @var int $minutes */
$months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
$t = strtotime((string) $article['published_at']);
$date = $t ? (int) date('j', $t) . ' ' . $months[(int) date('n', $t) - 1] . ' ' . ((int) date('Y', $t) + 543) : '';
?>
<section class="container page">
    <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?= $base ?>/">หน้าแรก</a> / <a href="<?= $base ?>/blog">บทความ</a> / <span><?= htmlspecialchars((string) $article['title']) ?></span></nav>
    <article class="narrow-copy mi-article">
        <header class="mi-page-heading">
            <span class="eyebrow">บทความ</span>
            <h1><?= htmlspecialchars((string) $article['title']) ?></h1>
            <p class="mi-trend-note"><?= $date ?> · อ่าน ~<?= (int) $minutes ?> นาที</p>
            <?php if (trim((string) ($article['excerpt'] ?? '')) !== ''): ?><p class="lead"><?= htmlspecialchars((string) $article['excerpt']) ?></p><?php endif; ?>
        </header>
        <div class="mi-article-body"><?= $html /* BlogRenderer: escaped before any tag is added */ ?></div>
        <footer class="mt-4">
            <a class="btn btn-ghost" href="<?= $base ?>/blog">บทความอื่น</a>
            <a class="btn btn-ghost" href="<?= $base ?>/price">เช็กราคา</a>
            <a class="btn btn-ghost" href="<?= $base ?>/read-listing">วางประกาศให้ระบบอ่าน</a>
        </footer>
    </article>
</section>
