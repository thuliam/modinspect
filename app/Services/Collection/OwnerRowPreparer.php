<?php
declare(strict_types=1);

namespace App\Services\Collection;

use App\Services\Research\OwnerIntakeDeduper;

/**
 * LS-6 M-1: turns the owner's loose daily rows (link, title, price, date) into the strict import template of
 * docs/OWNER_DAILY_COLLECTION_TH.md. Pure and offline: no database, no network, nothing is fetched from a link.
 * It normalises (canonical URL, derived item id, numeric price, full timestamp) but never invents a title, price, date or seller detail,
 * and a row it cannot make valid is reported by code, never silently repaired. The importer's own rules (CaptureMethod::problems) stay the single
 * source of truth for domain, item id, phone number and profile-link checks.
 */
final class OwnerRowPreparer
{
    public const OUT_COLUMNS = ['capture_method', 'source_listing_url', 'source_item_id', 'listing_title', 'asking_price', 'observed_at', 'condition_hint'];
    private const ALIASES = [
        'url' => ['source_listing_url', 'url', 'link', 'source_url'],
        'item' => ['source_item_id', 'item_id', 'id'],
        'title' => ['listing_title', 'title'],
        'price' => ['asking_price', 'price', 'displayed_price'],
        'date' => ['observed_at', 'date', 'observed'],
        'condition' => ['condition_hint', 'condition'],
    ];
    private const CONDITIONS = ['used', 'like new', 'new'];
    private const FB_QUERY_KEYS = ['story_fbid', 'id', 'fbid', 'v'];   // the only query parameters that identify a Facebook page; everything else is tracking

    /** the same listing gives the same URL whatever the share link carried (tracking, mobile host, fragment, trailing slash); null when it is not a usable link */
    public static function canonicalUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') { return null; }
        if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $url) !== 1) { $url = 'https://' . $url; }
        $p = parse_url($url);
        if ($p === false || !in_array(strtolower((string)($p['scheme'] ?? '')), ['http', 'https'], true)) { return null; }
        $host = strtolower((string)($p['host'] ?? ''));
        if (preg_match('/^[a-z0-9.\-]+\.[a-z]{2,}$/', $host) !== 1) { return null; }
        if (preg_match('/^(?:www|m|web|mbasic|touch)\.facebook\.com$/', $host) === 1 || $host === 'facebook.com') { $host = 'www.facebook.com'; }
        elseif (preg_match('/^(?:www|m)\.pantip\.com$/', $host) === 1) { $host = 'pantip.com'; }
        elseif (preg_match('/^m\.kaidee\.com$/', $host) === 1) { $host = 'www.kaidee.com'; }
        $path = rtrim((string)($p['path'] ?? ''), '/');
        $query = '';
        if ($host === 'www.facebook.com' && (str_ends_with($path, '.php') || $path === '/watch')) {
            parse_str((string)($p['query'] ?? ''), $q);
            $keep = [];
            foreach (self::FB_QUERY_KEYS as $k) {
                if (isset($q[$k]) && is_string($q[$k]) && preg_match('/^[A-Za-z0-9._\-]+$/', $q[$k]) === 1) { $keep[] = $k . '=' . $q[$k]; }
            }
            $query = $keep === [] ? '' : '?' . implode('&', $keep);
        }
        return 'https://' . $host . $path . $query;
    }

    /** a short stable id taken from the URL, only when the URL names one listing; null otherwise (the owner then supplies it) */
    public static function itemId(string $canonicalUrl): ?string
    {
        $p = parse_url($canonicalUrl);
        $host = (string)($p['host'] ?? '');
        $path = (string)($p['path'] ?? '');
        parse_str((string)($p['query'] ?? ''), $q);
        if ($host === 'www.facebook.com') {
            if (preg_match('#^/marketplace/item/(\d+)$#', $path, $m) === 1) { return 'FB-' . $m[1]; }
            foreach (['/permalink.php' => 'story_fbid', '/photo.php' => 'fbid', '/watch' => 'v'] as $route => $key) {
                if ($path === $route && isset($q[$key]) && is_string($q[$key]) && $q[$key] !== '') { return 'FB-' . $q[$key]; }
            }
            if (preg_match('#/(?:posts|permalink|videos|reel|reels)/([A-Za-z0-9]+)$#', $path, $m) === 1) { return 'FB-' . $m[1]; }
            if (preg_match('#/photos/(?:[^/]+/)*(\d{6,})$#', $path, $m) === 1) { return 'FB-' . $m[1]; }
            return null;
        }
        if ($host === 'pantip.com' && preg_match('#^/topic/(\d+)$#', $path, $m) === 1) { return 'PT-' . $m[1]; }
        if (str_ends_with($host, 'kaidee.com') && preg_match('#/product-(\d+)$#', $path, $m) === 1) { return 'KD-' . $m[1]; }
        return null;
    }

    /**
     * @param list<array<string,mixed>> $records loose rows (keys are column names, `_row` the file line)
     * @param string $defaultDate YYYY-MM-DD used only for rows with no date; '' means such rows are rejected
     * @return array{rows:list<array<string,string>>,issues:list<array{row:int,level:string,codes:list<string>}>,summary:array<string,mixed>}
     */
    public static function prepare(array $records, string $defaultDate = ''): array
    {
        $rows = [];
        $rowNumbers = [];
        $issues = [];
        $seenItems = [];
        $seenReposts = [];
        $defaulted = 0;
        $warned = 0;
        $known = ['capture_method', '_row'];
        foreach (self::ALIASES as $names) { $known = array_merge($known, $names); }
        $ignored = [];

        foreach ($records as $i => $raw) {
            $rec = [];
            foreach ($raw as $k => $v) {
                $key = strtolower(trim((string)$k));
                $rec[$key] = is_scalar($v) ? trim((string)$v) : '';
                if (!in_array($key, $known, true) && $key !== '') { $ignored[$key] = true; }
            }
            $rowNo = (int)($raw['_row'] ?? ($i + 2));
            $errors = [];
            $warnings = [];

            $canon = self::canonicalUrl(self::pick($rec, 'url'));
            if ($canon === null) { $errors[] = 'URL_INVALID'; }
            $item = self::pick($rec, 'item');
            if ($item === '' && $canon !== null) { $item = self::itemId($canon) ?? ''; }
            $title = trim((string)preg_replace('/\s+/u', ' ', self::pick($rec, 'title')));
            if ($title === '') { $errors[] = 'TITLE_MISSING'; }

            $price = '';
            $priceRaw = str_replace([',', '฿', 'บาท', 'THB', 'thb', ' '], '', self::pick($rec, 'price'));
            if ($priceRaw === '' || !is_numeric($priceRaw)) { $errors[] = 'PRICE_NOT_NUMERIC'; }
            elseif ((float)$priceRaw <= 0 || (float)$priceRaw > 10000000) { $errors[] = 'PRICE_OUT_OF_RANGE'; }
            else { $n = (float)$priceRaw; $price = floor($n) === $n ? (string)(int)$n : (string)$n; }

            $when = self::pick($rec, 'date');
            if ($when === '') {
                if ($defaultDate !== '') { $when = $defaultDate; $defaulted++; } else { $errors[] = 'OBSERVED_AT_MISSING'; }
            }
            $observed = '';
            if ($when !== '') {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $when) === 1) { $when .= ' 00:00:00'; }
                elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $when) === 1) { $when .= ':00'; }
                $dt = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $when);
                if (!$dt || $dt->format('Y-m-d H:i:s') !== $when) { $errors[] = 'OBSERVED_AT_INVALID'; }
                elseif ($dt->getTimestamp() > time() + 86400) { $errors[] = 'OBSERVED_AT_IN_FUTURE'; }
                else { $observed = $when; }
            }

            $condition = strtolower(self::pick($rec, 'condition'));
            if ($condition !== '' && !in_array($condition, self::CONDITIONS, true)) { $warnings[] = 'CONDITION_HINT_DROPPED'; $condition = ''; }

            $row = array_combine(self::OUT_COLUMNS, ['owner_transcription', $canon ?? '', $item, $title, $price, $observed, $condition]);
            if ($canon !== null) {
                foreach (CaptureMethod::problems($row, 'real') as $code) { $errors[] = $code; }   // domain, item id, phone number, profile link: the importer's own rules
            }
            $errors = array_values(array_unique($errors));

            if ($errors === []) {
                if (isset($seenItems[$item])) { $errors[] = 'DUPLICATE_IN_FILE'; }
                else {
                    $seenItems[$item] = true;
                    $repostKey = OwnerIntakeDeduper::keyFor($title, (int)$price);
                    if (isset($seenReposts[$repostKey])) { $warnings[] = 'REPOST_IN_FILE'; }
                    $seenReposts[$repostKey] = true;
                }
            }
            if ($errors === []) {
                $rows[] = $row;
                $rowNumbers[] = $rowNo;
                if ($warnings !== []) { $warned++; }
            }
            if ($errors !== [] || $warnings !== []) {
                $issues[] = ['row' => $rowNo, 'level' => $errors !== [] ? 'error' : 'warning', 'codes' => array_merge($errors, $warnings)];
            }
        }
        ksort($ignored);
        return [
            'rows' => $rows,
            'row_numbers' => $rowNumbers,   // the input line of each output row, so later checks can name the row
            'issues' => $issues,
            'summary' => [
                'read' => count($records), 'ready' => count($rows), 'rejected' => count($records) - count($rows), 'warnings' => $warned,
                'observed_defaulted' => $defaulted, 'ignored_columns' => array_keys($ignored),
            ],
        ];
    }

    /** @return list<array<string,mixed>> records keyed by lower-case header, with `_row` = the line number; handles Excel's BOM and CRLF */
    public static function readCsv(string $path): array
    {
        $content = (string)file_get_contents($path);
        $content = ltrim($content, "\xEF\xBB\xBF");
        $h = fopen('php://temp', 'r+b');
        fwrite($h, $content);
        rewind($h);
        $header = fgetcsv($h, 0, ',', '"', '\\');
        if (!is_array($header)) { fclose($h); return []; }
        $header = array_map(static fn($v): string => strtolower(trim((string)$v)), $header);
        $records = [];
        $line = 1;
        while (($row = fgetcsv($h, 0, ',', '"', '\\')) !== false) {
            $line++;
            if ($row === [null]) { continue; }   // blank line
            $rec = [];
            foreach ($header as $idx => $name) { $rec[$name] = $row[$idx] ?? ''; }
            $rec['_row'] = $line;
            $records[] = $rec;
        }
        fclose($h);
        return $records;
    }

    /** @param list<array<string,string>> $rows */
    public static function toCsv(array $rows): string
    {
        $h = fopen('php://temp', 'r+b');
        fputcsv($h, self::OUT_COLUMNS, ',', '"', '\\', "\n");
        foreach ($rows as $r) { fputcsv($h, array_values($r), ',', '"', '\\', "\n"); }
        rewind($h);
        $out = (string)stream_get_contents($h);
        fclose($h);
        return $out;
    }

    /** @param array<string,string> $rec */
    private static function pick(array $rec, string $field): string
    {
        foreach (self::ALIASES[$field] as $name) {
            if (isset($rec[$name]) && trim((string)$rec[$name]) !== '') { return trim((string)$rec[$name]); }
        }
        return '';
    }
}
