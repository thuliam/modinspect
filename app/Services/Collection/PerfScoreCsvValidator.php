<?php
declare(strict_types=1);
namespace App\Services\Collection;

/**
 * LS-11 D: checks the performance-figure CSV that comes back from the research helper (docs/GPT_HANDOFF_PERF_DATA.md). Read-only: it takes parsed rows and a
 * catalog (slug => CPU|GPU) and returns a report. Nothing is written anywhere; accepting the figures into the system is a separate, owner-approved step.
 *
 * A row with a figure is accepted only when the figure is a plain positive number, the source can be opened (name + http(s) URL), the read date is a real
 * date that is not in the future, and a percentage names the card or chip that is 100. The first source (Tom's Hardware) is accepted but does not count as
 * an independent source: the point of the second pass is to cross-check it.
 */
final class PerfScoreCsvValidator
{
    public const REQUIRED = ['category', 'model_slug', 'model_name', 'metric', 'value', 'scale_or_unit', 'baseline_100_is', 'source_name', 'source_url', 'page_date', 'date_read', 'test_setup', 'terms_url', 'reuse_note', 'note'];
    /** two sources measuring the same thing on the same scale and baseline may differ by this factor before the report flags them */
    public const MAX_SPREAD = 1.25;
    private const FIRST_SOURCE_HOST = 'tomshardware.com';

    /**
     * @param list<array<string,string>> $records rows from OwnerRowPreparer::readCsv (each has '_row', the file line)
     * @param array<string,string> $catalog model slug => 'CPU' or 'GPU'
     * @return array{ok: bool, errors: list<array>, warnings: list<array>, summary: array<string,int>, coverage: array<string,array>, disagreements: list<array>}
     */
    public static function validate(array $records, array $catalog): array
    {
        $errors = []; $warnings = []; $accepted = []; $seen = [];
        $err = static function (int $row, string $code, string $msg) use (&$errors): void { $errors[] = ['row' => $row, 'code' => $code, 'message' => $msg]; };
        $warn = static function (int $row, string $code, string $msg) use (&$warnings): void { $warnings[] = ['row' => $row, 'code' => $code, 'message' => $msg]; };

        if ($records) {
            $missing = array_values(array_diff(self::REQUIRED, array_keys($records[0])));
            if ($missing) {
                $err(1, 'HEADER_MISSING', 'ไฟล์ไม่มีคอลัมน์: ' . implode(', ', $missing));
                return self::report($errors, $warnings, count($records), [], $catalog);
            }
        }
        $today = date('Y-m-d');
        foreach ($records as $rec) {
            $row = (int)($rec['_row'] ?? 0);
            $f = array_map(static fn($v): string => trim((string)$v), $rec);
            $before = count($errors);
            $category = strtoupper($f['category']);
            $slug = $f['model_slug'];
            if (!in_array($category, ['CPU', 'GPU'], true)) { $err($row, 'BAD_CATEGORY', 'category ต้องเป็น CPU หรือ GPU'); }
            if (!isset($catalog[$slug])) { $err($row, 'UNKNOWN_SLUG', "ไม่พบ model_slug ในแคตตาล็อก: $slug"); }
            elseif (in_array($category, ['CPU', 'GPU'], true) && $catalog[$slug] !== $category) { $err($row, 'CATEGORY_MISMATCH', "$slug เป็น {$catalog[$slug]} ในแคตตาล็อก แต่แถวนี้ระบุ $category"); }
            if ($f['value'] === '') {
                if ($f['note'] === '') { $warn($row, 'EMPTY_NO_NOTE', 'ไม่มีตัวเลขและไม่มีเหตุผลในช่อง note'); }
                continue;   // an empty value is a stated gap, not a figure
            }
            if ($f['metric'] === '') { $err($row, 'EMPTY_METRIC', 'metric ว่าง'); }
            if (!is_numeric($f['value'])) { $err($row, 'VALUE_NOT_NUMBER', "value ต้องเป็นตัวเลขล้วน: {$f['value']}"); }
            elseif ((float)$f['value'] <= 0 || (float)$f['value'] > 10000000) { $err($row, 'VALUE_RANGE', "value อยู่นอกช่วงที่ยอมรับ: {$f['value']}"); }
            if ($f['source_name'] === '' || $f['source_url'] === '') { $err($row, 'MISSING_SOURCE', 'ต้องมีทั้ง source_name และ source_url'); }
            else {
                $parts = parse_url($f['source_url']);
                if (!in_array($parts['scheme'] ?? '', ['http', 'https'], true) || !filter_var($f['source_url'], FILTER_VALIDATE_URL) || !str_contains((string)($parts['host'] ?? ''), '.')) { $err($row, 'BAD_URL', 'source_url ต้องเป็น http(s) ที่เปิดอ่านได้'); }
            }
            $readOk = preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['date_read']) === 1 && strtotime($f['date_read']) !== false && $f['date_read'] <= $today;
            if (!$readOk) { $err($row, 'BAD_DATE_READ', 'date_read ต้องเป็นวันที่รูปแบบ YYYY-MM-DD และไม่เกินวันนี้'); }
            if ($f['page_date'] === '') { $warn($row, 'NO_PAGE_DATE', 'หน้าต้นทางไม่ระบุวันที่'); }
            elseif (preg_match('/^\d{4}(-\d{2}(-\d{2})?)?$/', $f['page_date']) !== 1) { $err($row, 'BAD_PAGE_DATE', 'page_date ต้องเป็น YYYY, YYYY-MM หรือ YYYY-MM-DD'); }
            if (preg_match('/percent|%/i', $f['scale_or_unit']) === 1 && $f['baseline_100_is'] === '') { $err($row, 'PERCENT_NEEDS_BASELINE', 'ค่าเปอร์เซ็นต์ต้องระบุว่าอะไรคือ 100 ใน baseline_100_is'); }
            if ($f['terms_url'] === '') { $warn($row, 'NO_TERMS_URL', 'ไม่มีลิงก์ข้อกำหนดการใช้ข้อมูลของแหล่งนี้'); }
            $host = self::host($f['source_url']);
            if ($host === self::FIRST_SOURCE_HOST) { $warn($row, 'FIRST_SOURCE', 'แหล่งนี้คือแหล่งแรก (Tom\'s Hardware) ไม่นับเป็นแหล่งที่สอง'); }
            if (count($errors) === $before) {
                $key = $slug . '|' . $f['metric'] . '|' . $f['source_url'] . '|' . $f['test_setup'];
                if (isset($seen[$key])) { $err($row, 'DUPLICATE', "ซ้ำกับแถว {$seen[$key]}"); }
                else { $seen[$key] = $row; $accepted[] = $f + ['_row' => (string)$row, '_host' => $host]; }
            }
        }
        return self::report($errors, $warnings, count($records), $accepted, $catalog);
    }

    private static function report(array $errors, array $warnings, int $rows, array $accepted, array $catalog): array
    {
        $coverage = [];
        foreach ($catalog as $slug => $cat) { $coverage[$slug] = ['category' => $cat, 'sources' => 0, 'metrics' => []]; }
        $hosts = [];
        foreach ($accepted as $a) {
            $slug = $a['model_slug'];
            $coverage[$slug]['metrics'][$a['metric']] = true;
            if ($a['_host'] !== self::FIRST_SOURCE_HOST) { $hosts[$slug][$a['_host']] = true; }
        }
        foreach ($coverage as $slug => &$c) { $c['sources'] = count($hosts[$slug] ?? []); $c['metrics'] = array_keys($c['metrics']); }
        unset($c);
        $buckets = array_count_values(array_map(static fn(array $c): int => min(2, $c['sources']), $coverage));
        $summary = [
            'rows' => $rows, 'accepted' => count($accepted), 'rejected' => count(array_unique(array_column($errors, 'row'))),
            'models_total' => count($catalog), 'models_with_0' => $buckets[0] ?? 0, 'models_with_1' => $buckets[1] ?? 0, 'models_with_2plus' => $buckets[2] ?? 0,
        ];
        return ['ok' => !$errors && $rows > 0, 'errors' => $errors, 'warnings' => $warnings, 'summary' => $summary, 'coverage' => $coverage, 'disagreements' => self::disagreements($accepted)];
    }

    private static function disagreements(array $accepted): array
    {
        $groups = [];
        foreach ($accepted as $a) { $groups[$a['model_slug'] . '|' . $a['metric'] . '|' . strtolower($a['scale_or_unit']) . '|' . strtolower($a['baseline_100_is'])][] = $a; }
        $out = [];
        foreach ($groups as $rows) {
            if (count(array_unique(array_column($rows, '_host'))) < 2) { continue; }
            $vals = array_map(static fn(array $r): float => (float)$r['value'], $rows);
            $min = min($vals); $max = max($vals);
            if ($max / $min > self::MAX_SPREAD) {
                $out[] = ['slug' => $rows[0]['model_slug'], 'metric' => $rows[0]['metric'], 'scale' => $rows[0]['scale_or_unit'], 'baseline' => $rows[0]['baseline_100_is'], 'min' => $min, 'max' => $max, 'ratio' => round($max / $min, 2), 'sources' => array_values(array_unique(array_column($rows, 'source_name')))];
            }
        }
        return $out;
    }

    /** the site a page belongs to: host without "www", last two labels (good enough to tell two sites apart; it does not know about suffixes like co.uk) */
    private static function host(string $url): string
    {
        $h = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
        $h = preg_replace('/^www\./', '', $h) ?? $h;
        $labels = explode('.', $h);
        return count($labels) > 2 ? implode('.', array_slice($labels, -2)) : $h;
    }
}
