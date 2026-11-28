<?php
declare(strict_types=1);

namespace App\Services\Seo;

/**
 * Pure helpers for search/share metadata (LS-2 H-8): robots.txt, sitemap.xml, canonical URLs, meta descriptions.
 * No database, no clock. Private areas (admin, login, per-visitor result pages) are never listed or allowed.
 */
final class SeoService
{
    public const DISALLOW = ['/admin', '/login', '/logout', '/deal-checker/result/', '/seller', '/seller-check', '/account', '/report', '/watch'];   // LS-26: '/watch' also covers the personal list page '/watching'

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
        if (preg_match('/^[a-z0-9-]+$/', $slug) && is_file($publicDir . '/assets/products/' . $slug . '.jpg') && !\App\Services\Catalog\SharedPictures::isShared($publicDir . '/assets/products/' . $slug . '.jpg')) { return $b . '/assets/products/' . $slug . '.jpg'; }
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
     * addresses are absolute under the base URL, the colours are the CI canvas and lime. The service worker (serviceWorkerJs) adds the offline page.
     */
    public static function manifestJson(string $name, string $baseUrl): string
    {
        $b = rtrim($baseUrl, '/');
        return (string)json_encode(['name' => $name, 'short_name' => mb_substr($name, 0, 12), 'description' => 'เช็กราคา เช็กสภาพ ก่อนซื้อขายคอมมือสอง', 'lang' => 'th',
            'start_url' => $b . '/', 'scope' => $b . '/', 'display' => 'standalone', 'background_color' => '#0a0d14', 'theme_color' => '#0a0d14',
            'icons' => [['src' => $b . '/assets/brand/icon-192x192.png', 'sizes' => '192x192', 'type' => 'image/png'], ['src' => $b . '/assets/brand/icon-512x512.png', 'sizes' => '512x512', 'type' => 'image/png'], ['src' => $b . '/assets/brand/icon-maskable-512x512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable']]],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "
";
    }

    /**
     * PWA P1: the service worker is a few lines of configuration for the self-hosted Workbox library (public/assets/vendor/workbox, MIT).
     * Pages: network first, then the device copy, then /offline. Static assets: stale while revalidate. Private and write paths are never handled.
     * $version goes into the cache names, so a release drops the old copies.
     */
    public static function serviceWorkerJs(string $base, string $version): string
    {
        $b = rtrim($base, '/');
        $prefix = rtrim((string)parse_url($b, PHP_URL_PATH), '/');
        $private = array_map(static fn(string $p): string => $prefix . $p, array_merge(self::DISALLOW, ['/api', '/csp-report', '/sw.js', '/health']));
        $js = <<<'JS'
importScripts(BASE + '/assets/vendor/workbox/workbox-sw.js');
workbox.setConfig({ modulePathPrefix: BASE + '/assets/vendor/workbox/', debug: false });
const PAGES = 'mi-pages-' + VERSION, ASSETS = 'mi-assets-' + VERSION, OFFLINE = BASE + '/offline';
self.addEventListener('install', (e) => { e.waitUntil(caches.open(PAGES).then((c) => c.add(OFFLINE)).then(() => self.skipWaiting())); });
self.addEventListener('activate', (e) => { e.waitUntil(caches.keys().then((ks) => Promise.all(ks.filter((k) => k.startsWith('mi-') && !k.endsWith(VERSION)).map((k) => caches.delete(k)))).then(() => self.clients.claim())); });
const { registerRoute, setCatchHandler } = workbox.routing;
const { NetworkFirst, StaleWhileRevalidate } = workbox.strategies;
const { ExpirationPlugin } = workbox.expiration;
const { CacheableResponsePlugin } = workbox.cacheableResponse;
const isPrivate = (url) => PRIVATE.some((p) => url.pathname.startsWith(p));
const ok = new CacheableResponsePlugin({ statuses: [200] });
registerRoute(({ request, url }) => request.mode === 'navigate' && url.origin === self.location.origin && !isPrivate(url),
  new NetworkFirst({ cacheName: PAGES, networkTimeoutSeconds: 4, plugins: [ok, new ExpirationPlugin({ maxEntries: 40, maxAgeSeconds: 7 * 24 * 3600 })] }));
registerRoute(({ request, url }) => request.method === 'GET' && url.origin === self.location.origin && url.pathname.startsWith(ASSET_PREFIX),
  new StaleWhileRevalidate({ cacheName: ASSETS, plugins: [ok, new ExpirationPlugin({ maxEntries: 120, maxAgeSeconds: 30 * 24 * 3600 })] }));
self.addEventListener('push', (e) => { let d = {}; try { d = e.data ? e.data.json() : {}; } catch (x) { /* plain text or empty */ } e.waitUntil(self.registration.showNotification(d.title || 'ModInspect', { body: d.body || '', icon: BASE + '/assets/brand/icon-192x192.png', data: { url: d.url || (BASE + '/watching') } })); });
self.addEventListener('notificationclick', (e) => { e.notification.close(); const u = new URL(e.notification.data && e.notification.data.url || (BASE + '/watching'), self.location.origin); e.waitUntil(u.origin === self.location.origin ? clients.openWindow(u.href) : undefined); });
setCatchHandler(async ({ request }) => (request.mode === 'navigate' ? (await caches.match(OFFLINE)) || Response.error() : Response.error()));
JS;
        $head = 'const BASE = ' . json_encode($prefix, JSON_UNESCAPED_SLASHES) . ', VERSION = ' . json_encode($version) . ', ASSET_PREFIX = ' . json_encode($prefix . '/assets/', JSON_UNESCAPED_SLASHES)
            . ', PRIVATE = ' . json_encode($private, JSON_UNESCAPED_SLASHES) . ";\n";
        return $head . $js . "\n";
    }

    public static function description(string $default, ?string $custom, int $max = 160): string
    {
        $t = trim((string)preg_replace('/\s+/u', ' ', strip_tags($custom !== null && trim($custom) !== '' ? $custom : $default)));
        if (mb_strlen($t, 'UTF-8') <= $max) { return $t; }
        return rtrim(mb_substr($t, 0, $max - 1, 'UTF-8')) . '…';
    }
    /** LS-24 M8: schema.org Article data for a published article; JSON_HEX_TAG so it can never close the script tag it sits in */
    public static function articleJsonLd(array $a, string $url, string $image, string $siteName = 'ModInspect'): string
    {
        $d = ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => mb_substr((string)($a['title'] ?? ''), 0, 110, 'UTF-8'), 'url' => $url, 'mainEntityOfPage' => $url, 'image' => $image,
            'datePublished' => date('c', (int)strtotime((string)($a['published_at'] ?? 'now'))), 'dateModified' => date('c', (int)strtotime((string)($a['updated_at'] ?? $a['published_at'] ?? 'now'))),
            'author' => ['@type' => 'Organization', 'name' => $siteName], 'publisher' => ['@type' => 'Organization', 'name' => $siteName], 'inLanguage' => 'th'];
        return (string)json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    /** LS-24 M8: RSS 2.0 of the newest published articles (title, link, summary, date); every text field is XML-escaped @param list<array<string,mixed>> $items rows with title, url, summary, published_at */
    public static function rssXml(string $siteTitle, string $siteUrl, string $description, array $items): string
    {
        $x = static fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $out = ['<?xml version="1.0" encoding="UTF-8"?>', '<rss version="2.0"><channel>', '<title>' . $x($siteTitle) . '</title>', '<link>' . $x($siteUrl) . '</link>', '<description>' . $x($description) . '</description>', '<language>th</language>'];
        foreach ($items as $i) {
            $out[] = '<item><title>' . $x((string)$i['title']) . '</title><link>' . $x((string)$i['url']) . '</link><guid isPermaLink="true">' . $x((string)$i['url']) . '</guid><description>' . $x((string)$i['summary']) . '</description><pubDate>' . date('r', (int)strtotime((string)$i['published_at'])) . '</pubDate></item>';
        }
        $out[] = '</channel></rss>';
        return implode("\n", $out) . "\n";
    }
}
