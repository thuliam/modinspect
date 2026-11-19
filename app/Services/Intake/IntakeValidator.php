<?php
declare(strict_types=1);

namespace App\Services\Intake;

use App\Services\Collection\CaptureMethod;
use DateTimeImmutable;
use RuntimeException;

/**
 * LS-23 B2: checks the files collectors send back against database/contracts/intake-v1.json (docs/DATA_CONTRACT.md). Pure: it reads a file and a context array,
 * writes nothing and never returns a cell value (only the row number, the column and a code), so a report can be pasted anywhere without leaking a title,
 * a price or a link.
 *
 * context (all optional; a check is skipped when its key is missing):
 *   now            DateTimeImmutable, "today" for date checks
 *   products       array<normalizedName, category>  catalog models (known_product, must_be_new)
 *   aliases        array<normalizedAlias, true>      aliases already in the catalog (alias_unique)
 */
final class IntakeValidator
{
    private static ?array $contract = null;

    public static function contract(): array
    {
        if (self::$contract === null) {
            $j = json_decode((string)file_get_contents(dirname(__DIR__, 3) . '/database/contracts/intake-v1.json'), true);
            if (!is_array($j)) { throw new RuntimeException('INTAKE_CONTRACT_UNREADABLE'); }
            self::$contract = $j;
        }
        return self::$contract;
    }

    /** a name reduced the way the catalog reduces aliases: lower case letters and digits (Thai included) only */
    public static function norm(string $s): string
    {
        return (string)preg_replace('/[^a-z0-9\x{0E00}-\x{0E7F}]+/u', '', mb_strtolower($s, 'UTF-8'));
    }

    /** @return list<string> every spec key the contract knows (supported + proposed) */
    public static function specKeys(): array
    {
        $k = [];
        foreach (['supported', 'proposed'] as $set) { foreach (self::contract()['spec_keys'][$set] as $keys) { foreach ($keys as $x) { $k[$x] = true; } } }
        return array_keys($k);
    }

    /** @return array{header:list<string>,rows:list<array<string,string>>,problem:?string} */
    public static function readCsv(string $path): array
    {
        $raw = is_file($path) ? (string)file_get_contents($path) : null;
        if ($raw === null) { return ['header' => [], 'rows' => [], 'problem' => 'FILE_NOT_FOUND']; }
        if (str_starts_with($raw, "\xEF\xBB\xBF")) { $raw = substr($raw, 3); }
        if ($raw !== '' && !mb_check_encoding($raw, 'UTF-8')) { return ['header' => [], 'rows' => [], 'problem' => 'FILE_NOT_UTF8']; }
        $h = fopen('php://temp', 'r+'); fwrite($h, $raw); rewind($h);
        $header = fgetcsv($h, 0, ',', '"', '');
        if (!is_array($header)) { fclose($h); return ['header' => [], 'rows' => [], 'problem' => 'FILE_EMPTY']; }
        $header = array_map(static fn($c): string => trim((string)$c), $header);
        $rows = [];
        while (($r = fgetcsv($h, 0, ',', '"', '')) !== false) {
            if ($r === [null] || $r === []) { $rows[] = ['__blank' => '1']; continue; }
            $row = [];
            foreach ($header as $i => $name) { $row[$name] = isset($r[$i]) ? (string)$r[$i] : ''; }
            $row['__extra'] = count($r) > count($header) ? '1' : '';
            $rows[] = $row;
        }
        fclose($h);
        return ['header' => $header, 'rows' => $rows, 'problem' => null];
    }

    /**
     * @param array<string,mixed> $context
     * @return array{dataset:string,file:string,rows:int,valid_rows:int,header_errors:list<string>,row_errors:list<array{row:int,column:string,code:string}>,codes:array<string,int>,valid:list<array<string,string>>}
     */
    public static function validateFile(string $dataset, string $path, array $context = []): array
    {
        $spec = self::contract()['datasets'][$dataset] ?? throw new RuntimeException('INTAKE_UNKNOWN_DATASET');
        $csv = self::readCsv($path);
        $res = ['dataset' => $dataset, 'file' => $spec['file'], 'rows' => 0, 'valid_rows' => 0, 'header_errors' => [], 'row_errors' => [], 'codes' => [], 'valid' => []];
        if ($csv['problem'] !== null) { $res['header_errors'][] = $csv['problem']; return $res; }
        $cols = array_column($spec['columns'], null, 'name');
        foreach ($cols as $name => $c) { if (!in_array($name, $csv['header'], true)) { $res['header_errors'][] = 'HEADER_MISSING_COLUMN:' . $name; } }
        foreach ($csv['header'] as $name) { if (!isset($cols[$name])) { $res['header_errors'][] = 'HEADER_UNKNOWN_COLUMN:' . $name; } }
        $res['rows'] = count($csv['rows']);
        if ($res['rows'] > (int)self::contract()['limits']['max_rows_per_file']) { $res['header_errors'][] = 'FILE_TOO_MANY_ROWS'; }
        if ($res['header_errors'] !== [] && array_filter($res['header_errors'], static fn(string $e): bool => str_starts_with($e, 'HEADER_MISSING'))) { return $res; }

        $now = $context['now'] ?? new DateTimeImmutable('now');
        $seen = []; $together = [];
        foreach ($csv['rows'] as $i => $row) {
            $n = $i + 2;   // 1 = header
            if (isset($row['__blank'])) { self::err($res, $n, '-', 'ROW_BLANK'); continue; }
            $errs = [];
            if (($row['__extra'] ?? '') === '1') { $errs[] = ['-', 'ROW_TOO_MANY_CELLS']; }
            foreach ($cols as $name => $c) {
                $v = trim($row[$name] ?? '');
                if ($v === '') { if (!empty($c['required'])) { $errs[] = [$name, 'ROW_REQUIRED_EMPTY']; } continue; }
                foreach (self::checkCell($c, $v, $now, $context) as $code) { $errs[] = [$name, $code]; }
                if (!empty($c['unique'])) {
                    $k = $name . "\0" . mb_strtolower($v, 'UTF-8');
                    if (isset($seen[$k])) { $errs[] = [$name, 'ROW_DUPLICATE_KEY']; } $seen[$k] = true;
                }
                if (!empty($c['alias_unique']) && isset($context['aliases'])) {
                    $a = self::norm($v);
                    if (isset($context['aliases'][$a])) { $errs[] = [$name, 'ROW_ALIAS_ALREADY_EXISTS']; }
                    $k = 'alias' . "\0" . $a; if (isset($seen[$k])) { $errs[] = [$name, 'ROW_DUPLICATE_KEY']; } $seen[$k] = true;
                }
                if (!empty($c['known_product']) && isset($context['products']) && !isset($context['products'][self::norm($v)]) && !isset($context['batch_products'][self::norm($v)])) { $errs[] = [$name, 'ROW_UNKNOWN_PRODUCT']; }
                if (!empty($c['must_be_new']) && isset($context['products']) && isset($context['products'][self::norm($v)])) { $errs[] = [$name, 'ROW_PRODUCT_ALREADY_EXISTS']; }
            }
            if (!empty($spec['unique_together']) && count($spec['unique_together']) > 1) {
                $k = implode("\0", array_map(static fn(string $c): string => mb_strtolower(trim($row[$c] ?? ''), 'UTF-8'), $spec['unique_together']));
                if (isset($together[$k])) { $errs[] = ['-', 'ROW_DUPLICATE_KEY']; } $together[$k] = true;
            }
            if ($dataset === 'A' && CaptureMethod::problems($row, 'real') !== []) {
                foreach (CaptureMethod::problems($row, 'real') as $p) { if (!in_array($p, ['UNKNOWN_CAPTURE_METHOD', 'OWNER_TRANSCRIPTION_DOMAIN_NOT_ALLOWED', 'OWNER_TRANSCRIPTION_TITLE_HAS_PHONE_NUMBER', 'OWNER_TRANSCRIPTION_PERSONAL_PROFILE_LINK'], true)) { $errs[] = ['-', $p]; } }
            }
            if ($errs === []) { $res['valid_rows']++; $res['valid'][] = array_diff_key($row, ['__extra' => 1]); }
            foreach ($errs as [$col, $code]) { self::err($res, $n, $col, $code); }
        }
        return $res;
    }

    /** @return list<string> */
    private static function checkCell(array $c, string $v, DateTimeImmutable $now, array $ctx): array
    {
        $e = []; $lim = self::contract()['limits'];
        switch ($c['type']) {
            case 'string':
            case 'url':
                if (isset($c['max']) && mb_strlen($v, 'UTF-8') > (int)$c['max']) { $e[] = 'ROW_TOO_LONG'; }
                if (strpbrk($v[0], '=+-@') !== false) { $e[] = 'ROW_FORMULA_CELL'; }
                if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $v)) { $e[] = 'ROW_CONTROL_CHARS'; }
                if (!empty($c['provinces']) && !in_array($v, \App\Services\Listing\ListingAttributes::PROVINCES, true)) { $e[] = 'ROW_UNKNOWN_PROVINCE'; }
                if ($c['type'] === 'url') {
                    $p = parse_url($v);
                    if (!is_array($p) || !in_array(strtolower((string)($p['scheme'] ?? '')), ['http', 'https'], true) || empty($p['host'])) { $e[] = 'ROW_BAD_URL'; break; }
                    if (preg_match('/[?&](utm_[a-z]+|fbclid|gclid|igshid)=/i', $v)) { $e[] = 'ROW_TRACKING_PARAMS'; }
                    if (preg_match('#facebook\.com/(profile\.php|people/)#i', $v)) { $e[] = 'ROW_PROFILE_LINK'; }
                    if (!empty($c['owner_domains']) && !CaptureMethod::domainAllowed($v)) { $e[] = 'ROW_DOMAIN_NOT_ALLOWED'; }
                } elseif (!empty($c['pii_scan']) || (int)($c['max'] ?? 0) >= 120) {
                    if (CaptureMethod::hasPhoneNumber($v)) { $e[] = 'ROW_PHONE_IN_TEXT'; }
                    if (preg_match('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $v)) { $e[] = 'ROW_EMAIL_IN_TEXT'; }
                    if (preg_match('/(?:line\s*(?:id|official)?|ไลน์)\s*[:：]\s*@?[A-Za-z0-9._\-]{4,}/iu', $v)) { $e[] = 'ROW_LINE_ID_IN_TEXT'; }
                }
                break;
            case 'enum':
                $allowed = $c['values'] ?? (($c['values_ref'] ?? '') === 'spec_keys' ? self::specKeys() : []);
                if (!in_array($v, $allowed, true)) { $e[] = ($c['values_ref'] ?? '') === 'spec_keys' ? 'ROW_UNKNOWN_SPEC_KEY' : 'ROW_BAD_ENUM'; }
                break;
            case 'price':
                if (!preg_match('/^\d+$/', $v)) { $e[] = 'ROW_BAD_PRICE_FORMAT'; break; }
                if ((int)$v < 1 || (int)$v > (int)$lim['max_price']) { $e[] = 'ROW_PRICE_RANGE'; }
                break;
            case 'int':
                if (!preg_match('/^\d{1,6}$/', $v) || (int)$v < (int)($c['min'] ?? 0) || (int)$v > (int)($c['max'] ?? 1000000)) { $e[] = 'ROW_BAD_INT'; }
                break;
            case 'year':
                if (!preg_match('/^\d{4}$/', $v) || (int)$v < (int)$lim['min_year'] || (int)$v > (int)$lim['max_year']) { $e[] = ((int)$v >= 2400 ? 'ROW_BUDDHIST_YEAR' : 'ROW_BAD_YEAR'); }
                break;
            case 'flag01':
                if ($v !== '1' && $v !== '0') { $e[] = 'ROW_BAD_FLAG'; }
                break;
            case 'date':
            case 'datetime':
            case 'date_or_datetime':
                $fmt = $c['type'] === 'date' ? 'Y-m-d' : ($c['type'] === 'datetime' ? 'Y-m-d H:i:s' : (strlen($v) === 10 ? 'Y-m-d' : 'Y-m-d H:i:s'));
                $d = DateTimeImmutable::createFromFormat('!' . $fmt, $v);
                if (!$d || $d->format($fmt) !== $v) { $e[] = preg_match('/^(24|25)\d\d-/', $v) ? 'ROW_BUDDHIST_YEAR' : 'ROW_BAD_DATE'; break; }
                if ((int)substr($v, 0, 4) >= 2400) { $e[] = 'ROW_BUDDHIST_YEAR'; break; }
                if ($d > $now->modify('+1 day')) { $e[] = 'ROW_DATE_IN_FUTURE'; }
                if (isset($c['max_age_days']) && $d < $now->modify('-' . (int)$c['max_age_days'] . ' days')) { $e[] = 'ROW_DATE_TOO_OLD'; }
                break;
        }
        return $e;
    }

    private static function err(array &$res, int $row, string $col, string $code): void
    {
        $res['row_errors'][] = ['row' => $row, 'column' => $col, 'code' => $code];
        $res['codes'][$code] = ($res['codes'][$code] ?? 0) + 1;
    }
}
