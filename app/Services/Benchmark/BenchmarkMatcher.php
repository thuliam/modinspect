<?php
declare(strict_types=1);

namespace App\Services\Benchmark;

/**
 * LS-32 A: matches published benchmark rows (InnoReviews CSV) to catalog models by name. Deterministic and strict: a row is linked only when its normalised name
 * equals exactly one catalog model's normalised full name and no other row has that name. Anything else is reported, never guessed.
 */
final class BenchmarkMatcher
{
    public const METRICS = [
        'gpu' => ['inno_score' => 'คะแนนรวม', 'score_rasterization' => 'เรนเดอร์ปกติ', 'score_rayTracing' => 'เรย์เทรซิ่ง', 'score_memory' => 'หน่วยความจำ', 'score_efficiency' => 'ประสิทธิภาพต่อวัตต์', 'score_compute' => 'การประมวลผล'],
        'cpu' => ['inno_score' => 'คะแนนรวม', 'score_singleCore' => 'คอร์เดี่ยว', 'score_multiCore' => 'หลายคอร์', 'score_gaming' => 'เล่นเกม'],
    ];

    public static function normalize(string $name): string
    {
        $s = mb_strtolower($name);
        $s = (string)preg_replace('/\b(nvidia|geforce|amd|radeon|intel|core|graphics|processor)\b/u', ' ', $s);
        return (string)preg_replace('/[^a-z0-9]+/', '', $s);
    }

    /**
     * @param list<array<string,string>> $csvRows rows keyed by the CSV header
     * @param list<array{id:int|string,full_name:string}> $products active catalog models of the same category
     * @return array{matched:list<array{product_id:int,row:array<string,string>}>,ambiguous:list<string>,unmatched_products:list<string>,unmatched_rows:int}
     */
    public static function plan(array $csvRows, array $products): array
    {
        $byName = [];
        foreach ($csvRows as $row) { $byName[self::normalize((string)($row['name'] ?? ''))][] = $row; }
        $matched = []; $ambiguous = []; $unmatchedProducts = []; $used = [];
        foreach ($products as $p) {
            $key = self::normalize((string)$p['full_name']);
            $hits = $byName[$key] ?? [];
            if ($key === '' || $hits === []) { $unmatchedProducts[] = (string)$p['full_name']; continue; }
            if (count($hits) > 1) { $ambiguous[] = (string)$p['full_name']; continue; }
            $matched[] = ['product_id' => (int)$p['id'], 'row' => $hits[0]];
            $used[$key] = true;
        }
        return ['matched' => $matched, 'ambiguous' => $ambiguous, 'unmatched_products' => $unmatchedProducts, 'unmatched_rows' => count($csvRows) - count($used)];
    }
}
