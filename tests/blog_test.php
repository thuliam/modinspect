<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Controllers\BlogController;
use App\Core\Database;
use App\Models\Blog;
use App\Services\Blog\BlogRenderer as B;
use App\Services\Seo\SeoService;

/**
 * LS-24 M8: the blog reader. A writer's text cannot put a tag, an attribute or a script on the page; only published articles whose time has come are readable,
 * listed, in the feed or in the sitemap; a {{price:slug}} line is the live price card; the page carries Article data and the feed is well formed XML.
 */
function ok_bl(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

// ---- renderer: escape first ----
$card = static fn(string $slug): string => '[[card:' . $slug . ']]';
$h = B::render("<script>alert(1)</script>\n\n## Head <b>x</b>\n\n- one <i>1</i>\n- two **bold**\n\n> quote <u>q</u>\n\n[ok](https://example.com/a?b=1&c=2) [bad](javascript:alert(1)) [rel](/price/x) [data](data:text/html,x) \"><img src=x onerror=alert(1)>", $card);
$bare = (string)preg_replace('#</?(?:p|h2|h3|ul|li|strong|blockquote)(?: class="blockquote")?>|<br>|<a href="[^"<>]*" rel="noopener">|</a>#', '', $h);
ok_bl(!str_contains($bare, '<') && !str_contains($h, 'href="javascript') && !str_contains($h, 'href="data:'), 'After the few tags we make are removed no angle bracket is left, and no link points at javascript or data: ' . $h);
ok_bl(str_contains($h, '&lt;script&gt;') && str_contains($h, '<h2>Head &lt;b&gt;x&lt;/b&gt;</h2>') && str_contains($h, '<ul><li>one &lt;i&gt;1&lt;/i&gt;</li><li>two <strong>bold</strong></li></ul>') && str_contains($h, '<blockquote'), 'Headings, lists, bold and quotes are made from escaped text');
ok_bl(str_contains($h, '<a href="https://example.com/a?b=1&amp;c=2" rel="noopener">ok</a>') && str_contains($h, '<a href="/price/x" rel="noopener">rel</a>') && !str_contains($h, 'href="javascript') && str_contains($h, '[bad](javascript:alert(1))'), 'Only http, https and root paths become links');
ok_bl(B::render("before\n\n{{price:rtx-3070}}\n\nafter", $card) === "<p>before</p>\n[[card:rtx-3070]]\n<p>after</p>", 'A shortcode on its own line becomes the card');
ok_bl(!str_contains(B::render('text {{price:rtx-3070}} inline', $card), '[[card:') && !str_contains(B::render('{{price:../../etc}}', $card), '[[card:') && !str_contains(B::render("{{price:A B}}", $card), '[[card:'), 'An inline or malformed shortcode is plain escaped text, never a card');
ok_bl(B::readingMinutes('ก') === 1 && B::readingMinutes(str_repeat('ก', 1900)) === 3, 'Reading time counts Thai characters');
ok_bl(!str_contains(B::summary("## T\n\n{{price:x}}\n\nสวัสดี **โลก** [link](https://a.b)"), '{{') && str_contains(B::summary('สวัสดี **โลก**'), 'โลก') && mb_strlen(B::summary(str_repeat('ก', 400), 50), 'UTF-8') <= 50, 'The summary drops markers and is cut to length');

// ---- SEO: Article data and the feed ----
$ld = SeoService::articleJsonLd(['title' => 'A </script><script>alert(1)</script>', 'published_at' => '2026-11-19 10:00:00', 'updated_at' => '2026-11-20 10:00:00'], 'https://example.test/blog/a', 'https://example.test/i.png');
ok_bl(!str_contains($ld, '</script>') && json_decode($ld, true)['@type'] === 'Article' && json_decode($ld, true)['inLanguage'] === 'th', 'Article JSON-LD cannot close its script tag');
$xml = SeoService::rssXml('T & <t>', 'https://example.test/blog', 'd', [['title' => 'a & <b>', 'url' => 'https://example.test/blog/a?x=1&y=2', 'summary' => '</description>', 'published_at' => '2026-11-19 10:00:00']]);
$sx = simplexml_load_string($xml);
ok_bl($sx !== false && (string)$sx->channel->item[0]->title === 'a & <b>' && (string)$sx->channel->title === 'T & <t>', 'The feed is well formed and escapes every field');

// ---- model: only live articles (test DB, rolled back) ----
global $config;
$db = Database::connection($config['db']);
ok_bl($config['db']['database'] === 'modinspect_test', 'Must run on the isolated test database');
$tag = bin2hex(random_bytes(3));
$db->beginTransaction();
try {
    $ins = $db->prepare("INSERT INTO articles (title, slug, excerpt, body, status, published_at) VALUES (:t, :s, :e, :b, :st, :p)");
    $rows = [['live', 'published', '-1 day'], ['future', 'published', '+2 days'], ['draft', 'draft', null], ['archived', 'archived', '-3 days'], ['nopub', 'published', null]];
    foreach ($rows as [$k, $st, $when]) { $ins->execute(['t' => "T $k $tag", 's' => "bl-$k-$tag", 'e' => null, 'b' => $k === 'live' ? "ย่อหน้าแรก <script>alert(1)</script>\n\n{{price:nvidia-geforce-rtx-3070}}\n\n{{price:no-such-model}}" : 'x', 'st' => $st, 'p' => $when ? date('Y-m-d H:i:s', strtotime($when)) : null]); }
    $m = new Blog();
    ok_bl($m->findBySlug("bl-live-$tag") !== null, 'A published article whose time has come is readable');
    foreach (['future', 'draft', 'archived', 'nopub'] as $k) { ok_bl($m->findBySlug("bl-$k-$tag") === null, "$k is not readable"); }
    $titles = array_column($m->page(1, 30, $tag)['rows'], 'title');
    ok_bl($titles === ["T live $tag"], 'The list shows only the live article: ' . json_encode($titles));
    ok_bl(count(array_filter($m->recent(200), static fn(array $r): bool => str_contains((string)$r['slug'], $tag))) === 1, 'The feed and the sitemap source see only the live article');
    ok_bl($m->findBySlug("x' OR '1'='1") === null && $m->findBySlug('../etc') === null, 'A hostile slug finds nothing');
    ok_bl($m->page(999, 9, $tag)['page'] === 1 && $m->page(1, 9, '%')['total'] >= 0, 'An out-of-range page and a wildcard search do not break');

    // the whole page (controller + layout), as the visitor gets it
    $_SERVER['REQUEST_URI'] = "/blog/bl-live-$tag"; $_GET = [];
    ob_start(); (new BlogController())->show("bl-live-$tag"); $page = (string)ob_get_clean();
    ok_bl(str_contains($page, 'ย่อหน้าแรก &lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($page, '<script>alert(1)</script>'), 'The page shows the writer text escaped');
    ok_bl(str_contains($page, 'ราคาตลาดมือสอง') && str_contains($page, 'NVIDIA GeForce RTX 3070') && !str_contains($page, 'no-such-model'), 'The shortcode drew the live price card, and an unknown model drew nothing');
    ok_bl(str_contains($page, '"@type":"Article"') && str_contains($page, 'application/ld+json'), 'The page carries Article data');
    http_response_code(200);
    ob_start(); (new BlogController())->show("bl-draft-$tag"); $nf = (string)ob_get_clean();
    ok_bl(http_response_code() === 404 && !str_contains($nf, "T draft $tag"), 'A draft answers 404 and does not leak its title');
    http_response_code(200);
} finally {
    $db->rollBack();
}

// ---- wiring ----
$idx = (string)file_get_contents(ROOT_PATH . '/public/index.php');
$posFeed = strpos($idx, "'/blog/feed.xml'"); $posSlug = strpos($idx, "'/blog/{slug}'");
ok_bl($posFeed !== false && $posSlug !== false && $posFeed < $posSlug && str_contains($idx, "get('/blog',"), 'The routes exist and the feed comes before the slug route');
ok_bl(str_contains((string)file_get_contents(ROOT_PATH . '/app/Controllers/SeoController.php'), "(new \\App\\Models\\Blog())->recent(200)"), 'The sitemap lists published articles only (through the Blog model)');
ok_bl(!preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP)\b/', (string)file_get_contents(ROOT_PATH . '/app/Models/Blog.php') . (string)file_get_contents(ROOT_PATH . '/app/Controllers/BlogController.php')), 'The reader side never writes');
echo "blog_test: PASS\n";
