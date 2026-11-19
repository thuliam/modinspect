<?php
declare(strict_types=1);

namespace App\Services\Seo;

/**
 * Pure helpers for search/share metadata (LS-2 H-8): robots.txt, sitemap.xml, canonical URLs, meta descriptions.
 * No database, no clock. Private areas (admin, login, per-visitor result pages) are never listed or allowed.
 */
final class SeoService
{
    public const DISALLOW = ['/admin', '/login', '/logout', '/deal-checker/result/', '/seller', '/seller-check', '/account'];

    public static function canonical(string $baseUrl, string $path): string
    {
        $p = parse_url($path, PHP_URL_PATH) ?: '/';
        $p = '/' . ltrim($p, '/');
        if ($p !== '/' && str_ends_with($p, '/')) { $p = rtrim($p, '/'); }
        return rtrim($baseUrl, '/') . ($p === '/' ? '/' : $p);
    }

    /** a closed pilot is not indexed: everything is disallowed and no sitemap is announced */
    public static function robotsTxt(string $baseUrl, bool $pilot = false): string
    {
        if ($pilot) { return "User-agent: *
Disallow: /
"; }
        $lines = ['User-agent: *', 'Allow: /'];
        foreach (self::DISALLOW as $d) { $lines[] = 'Disallow: ' . $d; }
        $lines[] = '';
        $lines[] = 'Sitemap: ' . rtrim($baseUrl, '/') . '/sitemap.xml';
        return implode("\n", $lines) . "\n";
    }

    /** the sitemap of a closed pilot: valid, and empty */
    public static function pilotSitemapXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "
" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>' . "
";
    }

    /** @param list<string|array{path:string,lastmod?:?string}> $paths site-relative paths, each already public; lastmod is the last snapshot time when known */
    public static function sitemapXml(string $baseUrl, array $paths): string
    {
        $seen = []; $out = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
        foreach ($paths as $entry) {
            $path = is_array($entry) ? (string)$entry['path'] : (string)$entry;
            $ts = is_array($entry) && !empty($entry['lastmod']) ? strtotime((string)$entry['lastmod']) : false;
            foreach (self::DISALLOW as $d) { if (str_starts_with($path, $d)) { continue 2; } }
            $loc = self::canonical($baseUrl, $path);
            if (isset($seen[$loc])) { continue; }
            $seen[$loc] = true;
            $out[] = '  <url><loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>' . ($ts !== false ? '<lastmod>' . date('Y-m-d', $ts) . '</lastmod>' : '') . '</url>';
        }
        $out[] = '</urlset>';
        return implode("\n", $out) . "\n";
    }

    /**
     * LS-19 C2: the share picture of a product page: its own photograph when one is stored (public/assets/products/{slug}.jpg), the brand picture otherwise.
     * Always an absolute URL (Open Graph requires it). $publicDir is the public folder, passed in so this stays pure.
     */
    public static function productImage(string $baseUrl, string $slug, string $publicDir): string
    {
        $b = rtrim($baseUrl, '/');
        if (preg_match('/^[a-z0-9-]+$/', $slug) && is_file($publicDir . '/assets/products/' . $slug . '.jpg')) { return $b . '/assets/products/' . $slug . '.jpg'; }
        return $b . '/assets/brand/hero-hardware.png';
    }

    /**
     * LS-19 C1: schema.org Product for a product page, as a JSON string safe inside <script type="application/ld+json"> (no "</" can close it).
     * The price part (AggregateOffer, used condition) is added ONLY when the page itself shows a public price: the same rule as the meta description
     * (median, at least 3 valid samples, an accepted observation). It is the common asking range (Q1–Q3) and its sample count, said to be asking prices,
     * never a sold price, a rating or a review. Without a public price the object has no offers at all, so nothing invented reaches search results.
     */
    public static function productJsonLd(array $p, string $url, string $image): string
    {
        $d = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => (string)($p['full_name'] ?? ''), 'url' => $url, 'image' => $image,
            'category' => (string)($p['category_name'] ?? '')];
        if (trim((string)($p['brand_name'] ?? '')) !== '' && strtolower((string)$p['brand_name']) !== 'generic') { $d['brand'] = ['@type' => 'Brand', 'name' => (string)$p['brand_name']]; }
        $priced = !empty($p['median']) && (int)($p['valid_sample_size'] ?? 0) >= 3 && (int)($p['accepted_observation_count'] ?? 0) > 0 && !empty($p['q1']) && !empty($p['q3']);
        $d['description'] = $priced ? 'ช่วงราคาประกาศขายมือสองที่พบบ่อย จากข้อมูลที่ผ่านการตรวจ (ราคาประกาศ ไม่ใช่ราคาปิดดีล)' : 'ข้อมูลรุ่นและสเปกจากผู้ผลิต ยังไม่มีราคาตลาดที่ตรวจแล้ว';
        if ($priced) {
            $d['offers'] = ['@type' => 'AggregateOffer', 'priceCurrency' => 'THB', 'lowPrice' => (string)round((float)$p['q1']), 'highPrice' => (string)round((float)$p['q3']),
                'offerCount' => (int)$p['valid_sample_size'], 'itemCondition' => 'https://schema.org/UsedCondition'];
        }
        return (string)json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    /**
     * LS-20 D4: web app manifest, so a phone can add the site to its home screen. Name comes from the site config (it follows the brand decision, HG-5),
     * addresses are absolute under the base URL, the colours are the CI canvas and lime. No service worker and no offline promise: it is only a shortcut.
     */
    public static function manifestJson(string $name, string $baseUrl): string
    {
        $b = rtrim($baseUrl, '/');
        return (string)json_encode(['name' => $name, 'short_name' => mb_substr($name, 0, 12), 'description' => 'เช็กราคา เช็กสภาพ ก่อนซื้อขายคอมมือสอง', 'lang' => 'th',
            'start_url' => $b . '/', 'scope' => $b . '/', 'display' => 'standalone', 'background_color' => '#0a0d14', 'theme_color' => '#0a0d14',
            'icons' => [['src' => $b . '/assets/brand/icon-192x192.png', 'sizes' => '192x192', 'type' => 'image/png'], ['src' => $b . '/assets/brand/icon-512x512.png', 'sizes' => '512x512', 'type' => 'image/png']]],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "
";
    }

    public static function description(string $default, ?string $custom, int $max = 160): string
    {
        $t = trim((string)preg_replace('/\s+/u', ' ', strip_tags($custom !== null && trim($custom) !== '' ? $custom : $default)));
        if (mb_strlen($t, 'UTF-8') <= $max) { return $t; }
        return rtrim(mb_substr($t, 0, $max - 1, 'UTF-8')) . '…';
    }
}
