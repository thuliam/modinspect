<?php
declare(strict_types=1);

namespace App\Services\Catalog;

/**
 * LS-26: bulk alias import for the admin. One line per alias: "product-slug, alias text[, source note]" (comma or tab; quotes allowed). plan() judges every line
 * against the catalog and the aliases that already exist and never writes; the admin controller inserts only the lines judged "new", after the person has seen the plan.
 * Statuses: new, exists (same alias already points at the same product), conflict (the alias already belongs to another product), unknown_product, invalid, duplicate_in_file.
 */
final class AliasImport
{
    public const MAX_LINES = 500;
    public const MAX_ALIAS = 255;

    /** what each verdict means and what to do, shown beside the verdict in the admin */
    public const HINTS = [
        'new' => 'Will be added on import',
        'exists' => 'Already points to this product; skipped',
        'conflict' => 'This alias already belongs to another product; skipped. Resolve it on that product first',
        'unknown_product' => 'No product has this slug; check the slug on the product page',
        'invalid' => 'Needs a slug and alias text; the alias is at most 255 characters',
        'duplicate_in_file' => 'The same alias appears earlier in this paste; skipped',
    ];

    public static function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9ก-๙]+/iu', '', mb_strtolower($value, 'UTF-8')) ?? '';
    }

    /**
     * @param array<string,int> $productIdBySlug
     * @param array<string,int> $existing normalized alias => product id
     * @return array{rows:list<array{line:int,slug:string,alias:string,source:?string,status:string}>,counts:array<string,int>,truncated:bool}
     */
    public static function plan(string $text, array $productIdBySlug, array $existing): array
    {
        $rows = []; $counts = ['new' => 0, 'exists' => 0, 'conflict' => 0, 'unknown_product' => 0, 'invalid' => 0, 'duplicate_in_file' => 0];
        $seen = []; $n = 0; $truncated = false;
        foreach (preg_split('/\R/u', $text) ?: [] as $i => $raw) {
            if (trim($raw) === '' || str_starts_with(ltrim($raw), '#')) { continue; }
            if (++$n > self::MAX_LINES) { $truncated = true; break; }
            $f = str_getcsv($raw, str_contains($raw, "\t") ? "\t" : ',', '"', '');
            $slug = strtolower(trim((string) ($f[0] ?? ''))); $alias = trim((string) ($f[1] ?? '')); $source = trim((string) ($f[2] ?? ''));
            $norm = self::normalize($alias);
            if ($slug === '' || $alias === '' || $norm === '' || mb_strlen($alias) > self::MAX_ALIAS || mb_strlen($source) > 255) { $st = 'invalid'; }
            elseif (!isset($productIdBySlug[$slug])) { $st = 'unknown_product'; }
            elseif (isset($seen[$norm])) { $st = 'duplicate_in_file'; }
            elseif (isset($existing[$norm])) { $st = $existing[$norm] === $productIdBySlug[$slug] ? 'exists' : 'conflict'; }
            else { $st = 'new'; }
            if ($norm !== '' && !isset($seen[$norm])) { $seen[$norm] = true; }
            $counts[$st]++;
            $rows[] = ['line' => $i + 1, 'slug' => $slug, 'alias' => $alias, 'source' => $source !== '' ? $source : null, 'status' => $st];
        }
        return ['rows' => $rows, 'counts' => $counts, 'truncated' => $truncated];
    }
}
