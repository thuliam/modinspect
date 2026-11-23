<?php
declare(strict_types=1);

namespace App\Services\Catalog;

/**
 * LS-70: finds candidate pictures of a hardware model on Wikimedia Commons (free, openly licensed). It only searches and downloads candidates into a review
 * folder; nothing reaches the product pages until an admin approves rows and runs cli/commons_image_apply.php.
 * Rules: only licences that allow reuse (CC0, public domain, CC BY, CC BY-SA), never NC/ND; the file title must contain the model's own number (the check the
 * existing CREDITS.json calls "matchedModelInTitle"); the author and licence travel with every picture; one request at a time with a pause (Commons asks bots to be gentle).
 */
final class CommonsImages
{
    public const API = 'https://commons.wikimedia.org/w/api.php';
    private const UA = 'ModInspectImageHelper/1.0 (admin tool; reads Wikimedia Commons)';

    /** @return bool true for licences that allow reuse with attribution or none */
    public static function licenceOk(string $short): bool
    {
        $s = strtolower(trim($short));
        if ($s === '' || preg_match('/\b(nc|nd)\b|non-?commercial|no ?derivs|fair use|all rights reserved/', $s)) { return false; }
        return (bool) preg_match('/^(cc0|cc[- ]zero|public domain|pd\b|cc[- ]by(-sa)?\b)/', $s);
    }

    /** The tokens that identify the model (anything with a digit, for example 5600X, RTX 4070, 7800X3D). Brand-only names have none and then no file can be matched. */
    public static function modelTokens(string $fullName): array
    {
        preg_match_all('/[A-Za-z]*\d[A-Za-z0-9]*/', $fullName, $m);
        $t = []; foreach ($m[0] as $x) { if (strlen($x) >= 3 || ctype_digit($x) === false) { $t[] = strtolower($x); } }
        return array_values(array_unique($t));
    }

    /** die shots, diagrams, screenshots and the like are not pictures of the product itself */
    public static function notAProductPhoto(string $title): bool
    {
        return (bool) preg_match('/\b(die|dies|wafer|polysilicon|diagram|schematic|logo|screenshot|benchmark|chart|graph|poster|vs|comparison|sample|cropped_?die)\b|@\d+\s?nm|DSCx?\d/i', $title);
    }

    /** search texts to try in order: the full name, the name without the maker, and the series words around the model number (Commons search is literal, a shorter text finds more) */
    public static function queries(string $fullName): array
    {
        $noMaker = trim(preg_replace('/^(AMD|Intel|NVIDIA|ASUS|MSI|Gigabyte|Corsair|Kingston|Samsung|Crucial|Western Digital|WD|Seagate)\s+/i', '', $fullName) ?? $fullName);
        $noMaker = trim(preg_replace('/^(GeForce|Core)\s+/i', '', $noMaker) ?? $noMaker);
        $tokens = self::modelTokens($fullName); $q = [$fullName, $noMaker];
        if ($tokens !== []) { $q[] = $noMaker . ' ' . $tokens[0]; $q[] = $tokens[0]; }
        return array_values(array_unique(array_filter(array_map(static fn ($s) => trim(preg_replace('/\s+/', ' ', $s) ?? ''), $q))));
    }

    /**
     * every model token must appear in the file title as a whole word (so "13400" does not match "13400F"), and the variant words Ti, Super, XT, XTX, GRE, X3D
     * must be present in the title exactly when they are in the model name (so "RTX 4070" does not match "RTX 4070 Ti")
     */
    public static function titleMatches(string $title, string $fullName): bool
    {
        $tokens = self::modelTokens($fullName);
        if ($tokens === []) { return false; }
        $words = static fn (string $v): array => array_values(array_filter(preg_split('/[^a-z0-9]+/', strtolower($v)) ?: []));
        $tw = $words(pathinfo(preg_replace('/^File:/i', '', $title), PATHINFO_FILENAME)); $mw = $words($fullName);
        $joined = ' ' . implode(' ', $tw) . ' ';
        foreach ($tokens as $t) { if (!str_contains($joined, ' ' . $t . ' ')) { return false; } }
        // a bare number is not enough (a Nokia 5500 is not a Ryzen 5 5500): the title must also carry one of the model's own words (Ryzen, GeForce, RTX, Core ...)
        $anchors = array_values(array_filter($mw, static fn ($w) => preg_match('/^[a-z]{3,}$/', $w) && !in_array($w, ['the', 'and', 'with', 'for'], true)));
        if ($anchors === [] || array_intersect($anchors, $tw) === []) { return false; }
        foreach (['ti', 'super', 'xt', 'xtx', 'gre'] as $variant) { if (in_array($variant, $mw, true) !== in_array($variant, $tw, true)) { return false; } }
        return true;
    }

    public static function plain(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    /** @return array<string,mixed> decoded JSON of one API call; throws on a network or HTTP error */
    public static function get(array $params): array
    {
        $url = self::API . '?' . http_build_query($params + ['format' => 'json', 'formatversion' => '2']);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_USERAGENT => self::UA, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
        $body = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($body === false || $code !== 200) { throw new \RuntimeException('Commons request failed (HTTP ' . $code . ')'); }
        $d = json_decode((string) $body, true);
        if (!is_array($d)) { throw new \RuntimeException('Commons answered with something that is not JSON'); }
        return $d;
    }

    /** @return list<string> file titles ("File:...") that match the text, best first */
    public static function search(string $text, int $limit = 20): array
    {
        $d = self::get(['action' => 'query', 'list' => 'search', 'srsearch' => $text, 'srnamespace' => '6', 'srlimit' => (string) $limit]);
        return array_values(array_map(static fn ($r) => (string) $r['title'], (array) ($d['query']['search'] ?? [])));
    }

    /**
     * @param list<string> $titles
     * @return list<array{title:string,page:string,thumb:string,mime:string,width:int,height:int,author:string,licence:string,licence_url:string}> usable pictures only
     */
    public static function details(array $titles, string $fullName): array
    {
        if ($titles === []) { return []; }
        $d = self::get(['action' => 'query', 'prop' => 'imageinfo', 'titles' => implode('|', array_slice($titles, 0, 20)), 'iiprop' => 'url|mime|size|extmetadata', 'iiurlwidth' => '1000']);
        $out = [];
        foreach ((array) ($d['query']['pages'] ?? []) as $p) {
            $ii = $p['imageinfo'][0] ?? null; if (!is_array($ii)) { continue; }
            $title = (string) ($p['title'] ?? ''); $em = (array) ($ii['extmetadata'] ?? []);
            $mime = (string) ($ii['mime'] ?? ''); $w = (int) ($ii['width'] ?? 0); $h = (int) ($ii['height'] ?? 0);
            $lic = self::plain((string) ($em['LicenseShortName']['value'] ?? ''));
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || min($w, $h) < 400) { continue; }
            if (!self::licenceOk($lic) || !self::titleMatches($title, $fullName) || self::notAProductPhoto($title)) { continue; }
            $thumb = (string) ($ii['thumburl'] ?? $ii['url'] ?? ''); if (!preg_match('~^https://upload\.wikimedia\.org/~', $thumb)) { continue; }
            $out[] = ['title' => $title, 'page' => (string) ($ii['descriptionurl'] ?? ''), 'thumb' => $thumb, 'mime' => $mime, 'width' => $w, 'height' => $h,
                'author' => mb_substr(self::plain((string) ($em['Artist']['value'] ?? '')), 0, 100), 'licence' => mb_substr($lic, 0, 60), 'licence_url' => self::plain((string) ($em['LicenseUrl']['value'] ?? ''))];
        }
        return $out;
    }

    /** downloads one picture to $dest (only from upload.wikimedia.org or thumb.wikimedia.org, at most 3 MB); returns the file size */
    public static function download(string $url, string $dest): int
    {
        if (!preg_match('~^https://upload\.wikimedia\.org/~', $url)) { throw new \RuntimeException('refusing a download from another host'); }
        $fh = fopen($dest, 'wb'); $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_FILE => $fh, CURLOPT_TIMEOUT => 40, CURLOPT_USERAGENT => self::UA, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_MAXFILESIZE => ImageImport::MAX_BYTES]);
        $ok = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); fclose($fh);
        $size = (int) @filesize($dest);
        if ($ok === false || $code !== 200 || $size < 1 || $size > ImageImport::MAX_BYTES) { @unlink($dest); throw new \RuntimeException('download failed (HTTP ' . $code . ')'); }
        return $size;
    }
}
