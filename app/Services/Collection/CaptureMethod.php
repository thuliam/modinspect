<?php
declare(strict_types=1);

namespace App\Services\Collection;

/**
 * How a piece of evidence was captured (LS-4 K-3 P1, DL-079). One pattern for the human route and the agent route: an import record carries
 * evidence_snapshot_json + evidence_hash.
 *  - owner_transcription: a person typed the listing into the template; the importer FREEZES the row (canonical JSON + SHA-256) at import time.
 *    It proves what was recorded and when, not that the page said so.
 *  - machine_capture: an agent saved the page itself and supplies its own snapshot and hash (e.g. PricezaProbeCollector); the importer never invents one.
 */
final class CaptureMethod
{
    public const OWNER_TRANSCRIPTION = 'owner_transcription';
    public const MACHINE_CAPTURE = 'machine_capture';
    public const ALL = [self::OWNER_TRANSCRIPTION, self::MACHINE_CAPTURE];
    /** marketplaces the owner collects from by hand; a host must equal one of these or be a subdomain of it */
    public const OWNER_DOMAINS = ['facebook.com', 'pantip.com', 'kaidee.com'];

    public static function domainAllowed(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) { return false; }
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        foreach (self::OWNER_DOMAINS as $d) {
            if ($host === $d || str_ends_with($host, '.' . $d)) { return true; }
        }
        return false;
    }

    /** a Thai mobile or landline number written in the usual ways (081-234-5678, 0812345678, +66 81 234 5678); prices and model numbers do not match */
    public static function hasPhoneNumber(string $text): bool
    {
        return preg_match('/(?<!\d)(?:\+?66[\s.\-]?|0)[2-9]\d(?:[\s.\-]?\d){6,7}(?!\d)/', $text) === 1;
    }

    /** @param array<string,mixed> $record @return array{snapshot:string,hash:string} */
    public static function freeze(array $record): array
    {
        $price = str_replace([',', '฿', 'บาท', 'THB', 'thb', ' '], '', (string)($record['asking_price'] ?? $record['displayed_price'] ?? ''));
        $fields = [
            'asking_price' => $price,
            'capture_method' => self::OWNER_TRANSCRIPTION,
            'condition_hint' => trim((string)($record['condition_hint'] ?? '')),
            'listing_title' => trim((string)($record['listing_title'] ?? $record['title'] ?? '')),
            'observed_at' => trim((string)($record['observed_at'] ?? '')),
            'source_item_id' => trim((string)($record['source_item_id'] ?? '')),
            'source_listing_url' => trim((string)($record['source_listing_url'] ?? $record['source_url'] ?? '')),
        ];
        $snapshot = (string)json_encode($fields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return ['snapshot' => $snapshot, 'hash' => hash('sha256', $snapshot)];
    }

    public static function fromExcerpt(string $excerpt): ?string
    {
        return preg_match('/^CAPTURE_METHOD=([a-z_]+)\s*$/mi', $excerpt, $m) === 1 ? strtolower($m[1]) : null;
    }

    /** the record with the frozen snapshot added when it is an owner transcription (any supplied snapshot is replaced: the importer owns the freeze) */
    public static function apply(array $record): array
    {
        if (strtolower(trim((string)($record['capture_method'] ?? ''))) === self::OWNER_TRANSCRIPTION) {
            $f = self::freeze($record);
            $record['evidence_snapshot_json'] = $f['snapshot'];
            $record['evidence_hash'] = $f['hash'];
        }
        return $record;
    }

    /** @return list<string> error codes for the capture_method of one import record (empty = fine or not used) */
    public static function problems(array $record, string $dataset): array
    {
        $method = strtolower(trim((string)($record['capture_method'] ?? '')));
        if ($method === '') { return []; }
        if (!in_array($method, self::ALL, true)) { return ['UNKNOWN_CAPTURE_METHOD']; }
        $e = [];
        $url = trim((string)($record['source_listing_url'] ?? ''));
        if ($method === self::OWNER_TRANSCRIPTION) {
            if ($dataset !== 'real') { $e[] = 'OWNER_TRANSCRIPTION_REQUIRES_REAL_DATASET'; }
            if (trim((string)($record['source_item_id'] ?? '')) === '') { $e[] = 'OWNER_TRANSCRIPTION_REQUIRES_ITEM_ID'; }
            if (!self::domainAllowed($url)) { $e[] = 'OWNER_TRANSCRIPTION_DOMAIN_NOT_ALLOWED'; }
            if (self::hasPhoneNumber((string)($record['listing_title'] ?? $record['title'] ?? ''))) { $e[] = 'OWNER_TRANSCRIPTION_TITLE_HAS_PHONE_NUMBER'; }   // minimise seller PII: remove the number and retry
            if (preg_match('#facebook\.com/(profile\.php|people/)#i', $url) === 1) { $e[] = 'OWNER_TRANSCRIPTION_PERSONAL_PROFILE_LINK'; }   // minimise seller PII (AGENTS.md)
        } else {
            $hash = strtolower(trim((string)($record['evidence_hash'] ?? '')));
            if (trim((string)($record['evidence_snapshot_json'] ?? '')) === '' || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) { $e[] = 'MACHINE_CAPTURE_REQUIRES_SNAPSHOT_AND_HASH'; }
        }
        return $e;
    }
}
