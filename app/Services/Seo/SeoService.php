<?php
declare(strict_types=1);

namespace App\Services\Seo;

/**
 * Pure helpers for search/share metadata (LS-2 H-8): robots.txt, sitemap.xml, canonical URLs, meta descriptions.
 * No database, no clock. Private areas (admin, login, per-visitor result pages) are never listed or allowed.
 */
final class SeoService
{
    public const DISALLOW = ['/admin', '/login', '/logout', '/deal-checker/result/', '/seller'];

    public static function canonical(string $baseUrl, string $path): string
    {
        $p = parse_url($path, PHP_URL_PATH) ?: '/';
        $p = '/' . ltrim($p, '/');
        if ($p !== '/' && str_ends_with($p, '/')) { $p = rtrim($p, '/'); }
        return rtrim($baseUrl, '/') . ($p === '/' ? '/' : $p);
    }

    public static function robotsTxt(string $baseUrl): string
    {
        $lines = ['User-agent: *', 'Allow: /'];
        foreach (self::DISALLOW as $d) { $lines[] = 'Disallow: ' . $d; }
        $lines[] = '';
        $lines[] = 'Sitemap: ' . rtrim($baseUrl, '/') . '/sitemap.xml';
        return implode("\n", $lines) . "\n";
    }

    /** @param list<string> $paths site-relative paths, each already public */
    public static function sitemapXml(string $baseUrl, array $paths): string
    {
        $seen = []; $out = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
        foreach ($paths as $path) {
            foreach (self::DISALLOW as $d) { if (str_starts_with($path, $d)) { continue 2; } }
            $loc = self::canonical($baseUrl, $path);
            if (isset($seen[$loc])) { continue; }
            $seen[$loc] = true;
            $out[] = '  <url><loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc></url>';
        }
        $out[] = '</urlset>';
        return implode("\n", $out) . "\n";
    }

    public static function description(string $default, ?string $custom, int $max = 160): string
    {
        $t = trim((string)preg_replace('/\s+/u', ' ', strip_tags($custom !== null && trim($custom) !== '' ? $custom : $default)));
        if (mb_strlen($t, 'UTF-8') <= $max) { return $t; }
        return rtrim(mb_substr($t, 0, $max - 1, 'UTF-8')) . '…';
    }
}
