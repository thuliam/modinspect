<?php
declare(strict_types=1);

namespace App\Services\Search;

/**
 * F-7: "did you mean" for a search that found nothing. It only suggests; it never changes a result list.
 * Every typed word must come close to a word of the product name. Letters-only words may be one character off (four or five letters) or two (six or more);
 * a word with a digit must match exactly, because a model number one digit off is another product (3060 is not 3070). Thai-only text gets nothing. Pure.
 */
final class DidYouMean
{
    /** Optimal string alignment distance: insertion, deletion, substitution and swap of two neighbours each cost 1 */
    public static function distance(string $a, string $b): int
    {
        $n = strlen($a); $m = strlen($b);
        if ($n === 0) { return $m; }
        if ($m === 0) { return $n; }
        $d = [];
        for ($i = 0; $i <= $n; $i++) { $d[$i][0] = $i; }
        for ($j = 0; $j <= $m; $j++) { $d[0][$j] = $j; }
        for ($i = 1; $i <= $n; $i++) {
            for ($j = 1; $j <= $m; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + $cost);
                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) { $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1); }
            }
        }
        return $d[$n][$m];
    }

    /** how many characters a typed word may be off */
    private static function allowance(string $word): int
    {
        if (preg_match('/\d/', $word)) { return 0; }
        $len = strlen($word);
        return $len <= 3 ? 0 : ($len <= 5 ? 1 : 2);
    }

    /**
     * @param list<array{slug:string,full_name:string}> $products
     * @return list<array{slug:string,full_name:string}> closest first, at most $limit
     */
    public static function suggest(string $query, array $products, int $limit = 5): array
    {
        $words = SearchQuery::words($query);
        if ($words === []) { return []; }
        foreach ($words as $w) { if (!preg_match('/^[a-z0-9]+$/', $w)) { return []; } }
        $scored = [];
        foreach ($products as $p) {
            $tokens = array_values(array_filter(preg_split('/[^a-z0-9]+/', strtolower((string)$p['full_name'])) ?: [], static fn(string $t): bool => $t !== ''));
            $total = 0;
            foreach ($words as $w) {
                $best = null; $k = self::allowance($w);
                foreach ($tokens as $t) { $d = self::distance($w, $t); if ($d <= $k && ($best === null || $d < $best)) { $best = $d; } }
                if ($best === null) { continue 2; }
                $total += $best;
            }
            $scored[] = [$total, strlen((string)$p['full_name']), ['slug' => (string)$p['slug'], 'full_name' => (string)$p['full_name']]];
        }
        usort($scored, static fn(array $x, array $y): int => [$x[0], $x[1]] <=> [$y[0], $y[1]]);
        return array_slice(array_column($scored, 2), 0, max(0, $limit));
    }
}
