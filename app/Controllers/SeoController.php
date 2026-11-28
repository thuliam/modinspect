<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\Seo\SeoService;

final class SeoController extends Controller
{
    public function robots(): void
    {
        global $config;
        header('Content-Type: text/plain; charset=utf-8');
        echo SeoService::robotsTxt((string)$config['base_url'], !empty($config['pilot']));
    }

    public function manifest(): void
    {
        global $config;
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo SeoService::manifestJson((string)$config['name'], (string)$config['base_url']);
    }

    public function serviceWorker(): void
    {
        global $config;
        $base = rtrim((string)$config['base_url'], '/');
        header('Content-Type: text/javascript; charset=utf-8');
        header('Cache-Control: no-cache');   // the browser re-checks the worker on every visit, so a release reaches devices
        header('Service-Worker-Allowed: ' . rtrim((string)parse_url($base, PHP_URL_PATH), '/') . '/');
        echo SeoService::serviceWorkerJs($base, (string)@filemtime(ROOT_PATH . '/public/assets/app.css'));
    }

    public function sitemap(): void
    {
        global $config;
        header('Content-Type: application/xml; charset=utf-8');
        if (!empty($config['pilot'])) { echo SeoService::pilotSitemapXml(); return; }
        // the sitemap changes only when a snapshot changes: serve a 10-minute copy instead of querying on every request
        echo (new \App\Services\Http\FileCache(ROOT_PATH . '/storage/cache/http'))->remember('sitemap.xml', 600, fn(): string => $this->buildSitemap());
    }

    private function buildSitemap(): string
    {
        global $config;
        $db = Database::connection($config['db']);
        // only products that have a public price (same gate as the price pages) get a sitemap entry: empty pages are not worth indexing
        $rows = $db->query("SELECT p.slug, pi.last_calculated_at FROM products p JOIN price_indices pi ON pi.id = " . \App\Services\Pricing\PublicSnapshot::idSql('p.id') . "
            WHERE p.is_active = 1 ORDER BY p.slug")->fetchAll();
        $paths = ['/', '/price', '/methodology', '/deal-checker', '/read-listing', '/glossary', '/build', '/compare', '/upgrade', '/condition-guide', '/blog'];
        foreach ((new \App\Models\Blog())->recent(200) as $a) { $paths[] = ['path' => '/blog/' . rawurlencode((string)$a['slug']), 'lastmod' => $a['updated_at'] ?? $a['published_at']]; }   // LS-24 M8: published articles only
        foreach (\App\Services\Catalog\ConditionGuide::categories() as $g) { $paths[] = '/condition-guide/' . rawurlencode((string)$g['slug']); }
        foreach ($rows as $r) { $paths[] = ['path' => '/price/' . rawurlencode((string)$r['slug']), 'lastmod' => $r['last_calculated_at'] ?? null]; }
        return SeoService::sitemapXml((string)$config['base_url'], $paths);
    }
}
