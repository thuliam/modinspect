<?php
declare(strict_types=1);

namespace App\Services\Search;

/**
 * Turns what a visitor typed into the match conditions of the public product search (LS-3 J-5).
 * The old search was a substring match on name, model and alias. That stays, so every query that worked still works; two forgiving
 * matches are added on top (they can only add results, never remove one):
 *  - joined: the typed letters and digits are matched in order from the START of a word of the name, ignoring spaces, hyphens, dots and slashes
 *    ("3070ti" finds "RTX 3070 Ti", "ddr4 3200 16gb" finds "DDR4-3200 16GB", "i512400f" finds "i5-12400F"). Starting at a word boundary keeps
 *    "r5 5600" from matching the tail of "DDR5-5600";
 *  - words: every typed word must be a whole word of the name, in any order ("nvme 1tb" finds "NVMe M.2 SSD 1TB").
 * Thai words typed next to a model number ("การ์ดจอ 3070") are ignored for matching when another word carries the model.
 */
final class SearchQuery
{
    private const SEP = '[^a-z0-9]*';

    /** lower-case letters, combining marks and digits only (Thai text keeps its vowel and tone marks) */
    public static function compact(string $text): string
    {
        // letters, combining marks (Thai vowel and tone marks are marks, not letters) and digits survive
        return (string)preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '', mb_strtolower($text, 'UTF-8'));
    }

    /** @return list<string> compact words; Thai-only words are dropped when at least one other word is left */
    /** LS-17 A2 (S-2): the longest catalog name is under 60 characters; a longer query only makes every condition heavier (1,200 words took 0.8 s) */
    public const MAX_LENGTH = 80;

    public static function clip(string $query): string
    {
        return trim(mb_substr(trim($query), 0, self::MAX_LENGTH));
    }

    public static function words(string $query): array
    {
        $all = [];
        foreach (preg_split('/\s+/u', trim($query)) ?: [] as $w) {
            $c = self::compact($w);
            if ($c !== '') { $all[] = $c; }
        }
        $nonThai = array_values(array_filter($all, static fn(string $w): bool => !preg_match('/^[\x{0E00}-\x{0E7F}]+$/u', $w)));
        return $nonThai !== [] ? $nonThai : $all;
    }

    /** regular expression (works in MySQL and MariaDB) matching the compact text from a word start with optional separators between characters; null when the text is not plain ASCII letters and digits of a sensible length */
    public static function joinedPattern(string $compact): ?string
    {
        if (!preg_match('/^[a-z0-9]{2,40}$/', $compact)) { return null; }
        return '(^|[^a-z0-9])' . implode(self::SEP, str_split($compact));
    }

    /** SQL expression that turns a column into space-separated lower-case words with a space on both ends, so ' 5600 ' matches the whole word only */
    public static function sqlWords(string $column): string
    {
        return "CONCAT(' ', REPLACE(REPLACE(REPLACE(REPLACE(LOWER($column),'-',' '),'_',' '),'.',' '),'/',' '), ' ')";
    }

    /**
     * Extra conditions to OR with the original substring match.
     * @return array{sql:string,params:array<string,string>} sql is '' when the query has nothing usable
     */
    public static function extraConditions(string $query): array
    {
        $words = self::words($query);
        $whole = self::compact($query);
        if ($whole === '' || $words === []) { return ['sql' => '', 'params' => []]; }
        // native prepared statements do not allow one named placeholder twice: every occurrence gets its own name and the same value
        $params = []; $n = 0;
        $fields = static function (string $value, string $op) use (&$params, &$n): string {
            $names = [];
            foreach (['full', 'model', 'alias'] as $part) { $names[$part] = 'qc_' . (++$n); $params[$names[$part]] = $value; }
            $x = static fn(string $col): string => $op === 'LIKE' ? self::sqlWords($col) : "LOWER($col)";
            return '(' . $x('p.full_name') . " $op :{$names['full']} OR " . $x('p.model_name') . " $op :{$names['model']} OR EXISTS(SELECT 1 FROM product_aliases pa WHERE pa.product_id=p.id AND " . $x('pa.alias_text') . " $op :{$names['alias']}))";
        };
        $parts = [];
        if (($pattern = self::joinedPattern($whole)) !== null) { $parts[] = $fields($pattern, 'REGEXP'); }
        if (count($words) > 1) {
            $each = [];
            foreach ($words as $w) { $each[] = $fields('% ' . $w . ' %', 'LIKE'); }
            $parts[] = '(' . implode(' AND ', $each) . ')';
        } elseif ($words[0] !== $whole && ($pattern = self::joinedPattern($words[0])) !== null) {
            $parts[] = $fields($pattern, 'REGEXP');   // a single word is left after the Thai words were dropped
        }
        return ['sql' => implode(' OR ', $parts), 'params' => $params];
    }
}
