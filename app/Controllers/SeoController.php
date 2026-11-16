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
        echo SeoService::robotsTxt((string)$config['base_url']);
    }

    public function sitemap(): void
    {
        global $config;
        $db = Database::connection($config['db']);
        // only products that have a public price (same gate as the price pages) get a sitemap entry: empty pages are not worth indexing
        $slugs = $db->query("SELECT p.slug FROM price_indices pi JOIN products p ON p.id = pi.product_id
            WHERE pi.id IN (SELECT MAX(id) FROM price_indices WHERE price_type = 'asking' AND product_variant_id IS NULL AND COALESCE(provenance_status, 'legacy_unavailable') <> 'legacy_unavailable' GROUP BY product_id)
              AND pi.valid_sample_size >= 3 AND pi.median > 0 AND p.is_active = 1 ORDER BY p.slug")->fetchAll(\PDO::FETCH_COLUMN);
        $paths = ['/', '/price', '/methodology', '/deal-checker', '/build', '/compare'];
        foreach ($slugs as $s) { $paths[] = '/price/' . rawurlencode((string)$s); }
        header('Content-Type: application/xml; charset=utf-8');
        echo SeoService::sitemapXml((string)$config['base_url'], $paths);
    }
}
