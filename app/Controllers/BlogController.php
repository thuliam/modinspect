<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Blog;
use App\Models\Product;
use App\Services\Blog\BlogRenderer;
use App\Services\Seo\SeoService;

/**
 * LS-24 M8: the reader side of the blog. Only published articles whose publish time has come (Blog model); the body is plain text turned into safe HTML by
 * BlogRenderer; a {{price:slug}} line becomes the live price card of that model, drawn from the same stored snapshot the price page shows.
 */
final class BlogController extends Controller
{
    private const MAX_PAGE = 500;

    public function index(): void
    {
        global $config; $base = (string)$config['base_url'];
        $q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
        $page = is_string($_GET['page'] ?? null) && ctype_digit($_GET['page']) ? min((int)$_GET['page'], self::MAX_PAGE) : 1;
        $cat = \App\Services\Blog\ArticleCategory::filter($_GET['cat'] ?? null);
        $blog = new Blog();
        $list = $blog->page($page, 9, $q, $cat);
        $catCounts = $blog->categoryCounts($q);
        $this->view('blog/index', ['title' => 'บทความ', 'metaDescription' => 'บทความเรื่องการซื้อขายคอมมือสอง ราคาตลาด และวิธีตรวจของ จาก ModInspect', 'q' => $q, 'list' => $list, 'cat' => $cat, 'catCounts' => $catCounts, 'canonical' => SeoService::canonical($base, '/blog')]);
    }

    public function show(string $slug): void
    {
        global $config; $base = (string)$config['base_url'];
        $a = (new Blog())->findBySlug($slug);
        if (!$a) { http_response_code(404); $this->view('errors/404', ['title' => 'ไม่พบบทความ']); return; }
        $html = BlogRenderer::render((string)$a['body'], fn(string $s): string => $this->priceCard($s));
        $url = SeoService::canonical($base, '/blog/' . rawurlencode((string)$a['slug']));
        $image = rtrim($base, '/') . '/assets/brand/hero-hardware.png';
        $this->view('blog/show', ['title' => (string)$a['title'], 'metaDescription' => trim((string)($a['excerpt'] ?? '')) !== '' ? mb_substr(trim((string)$a['excerpt']), 0, 160, 'UTF-8') : BlogRenderer::summary((string)$a['body']),
            'article' => $a, 'html' => $html, 'minutes' => BlogRenderer::readingMinutes((string)$a['body']), 'jsonLd' => SeoService::articleJsonLd($a, $url, $image), 'ogImage' => $image]);
    }

    public function feed(): void
    {
        global $config; $base = (string)$config['base_url'];
        $items = array_map(static fn(array $r): array => ['title' => (string)$r['title'], 'url' => SeoService::canonical($base, '/blog/' . rawurlencode((string)$r['slug'])),
            'summary' => trim((string)($r['excerpt'] ?? '')) !== '' ? (string)$r['excerpt'] : BlogRenderer::summary((string)$r['body_head']), 'published_at' => (string)$r['published_at']], (new Blog())->recent(30));
        header('Content-Type: application/rss+xml; charset=UTF-8');
        echo SeoService::rssXml('ModInspect บทความ', SeoService::canonical($base, '/blog'), 'เช็กราคา เช็กสภาพ ก่อนซื้อขายคอมมือสอง', $items);
    }

    /** the live price card of a model inside an article: a model with no public price says so, an unknown slug draws nothing */
    private function priceCard(string $slug): string
    {
        global $config; $base = (string)$config['base_url'];
        $p = (new Product())->findBySlug($slug);
        if (!$p) { return ''; }
        $priced = !empty($p['median']) && (int)($p['valid_sample_size'] ?? 0) > 0 && (int)($p['accepted_observation_count'] ?? 0) > 0 && !empty($p['q1']) && !empty($p['q3']);
        ob_start();
        require ROOT_PATH . '/app/Views/components/price-embed.php';
        return (string)ob_get_clean();
    }
}
