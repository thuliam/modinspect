<?php
declare(strict_types=1);
namespace App\Services\Catalog;

/**
 * F-3: the side-by-side table of the Compare page. Up to four models; rows are the union of their fact labels (ProductFacts rows) in order of first appearance,
 * so a model that lacks a fact shows a gap and never an invented value. A row is marked "differs" when two models show different values (a gap is unknown, so it never counts).
 * Price uses the one "has a market price" rule (median, valid sample, accepted observation); an unpriced model is shown as unpriced, nothing is borrowed.
 * It never judges: no winner, score, rank or benchmark. Pure.
 */
final class CompareTable
{
    public const MAX_MODELS = 4;

    /**
     * LS-16 U-2: ready-made pairs for the empty Compare page, so a first visit shows a table in one tap. Two priced models of the SAME category
     * (a cross-category table is mostly gaps), taken in the catalog's own order (popular first), at most one pair per category. Pure.
     * @param list<array> $products Product::search rows
     * @return list<array{category:string,a:array{slug:string,name:string},b:array{slug:string,name:string}}>
     */
    public static function suggestPairs(array $products, int $max = 4): array
    {
        $byCat = [];
        foreach ($products as $p) {
            if (!PriceList::isPriced($p)) { continue; }
            $cat = (string)($p['category_name'] ?? '');
            if ($cat === '' || count($byCat[$cat] ?? []) >= 2) { continue; }
            $byCat[$cat][] = ['slug' => (string)$p['slug'], 'name' => (string)($p['full_name'] ?? $p['slug'])];
        }
        $out = [];
        foreach ($byCat as $cat => $two) {
            if (count($two) < 2) { continue; }
            $out[] = ['category' => $cat, 'a' => $two[0], 'b' => $two[1]];
            if (count($out) >= $max) { break; }
        }
        return $out;
    }

    /**
     * @param list<array{product:array<string,mixed>,facts:list<array<string,mixed>>}> $items
     * @return array{columns:list<array<string,mixed>>,rows:list<array<string,mixed>>,mixed_category:bool,any_priced:bool}
     */
    public static function build(array $items): array
    {
        $seen = []; $kept = [];
        foreach ($items as $it) {
            $slug = (string)($it['product']['slug'] ?? '');
            if ($slug === '' || isset($seen[$slug])) { continue; }
            $seen[$slug] = true; $kept[] = $it;
            if (count($kept) === self::MAX_MODELS) { break; }
        }
        $columns = []; $labels = []; $categories = [];
        foreach ($kept as $i => $it) {
            $p = $it['product'];
            $priced = !empty($p['median']) && (int)($p['valid_sample_size'] ?? 0) > 0 && (int)($p['accepted_observation_count'] ?? 0) > 0;
            $columns[] = [
                'slug' => (string)$p['slug'], 'name' => (string)($p['full_name'] ?? $p['slug']), 'category' => (string)($p['category_name'] ?? ''),
                'priced' => $priced,
                'median' => $priced ? (float)$p['median'] : null,
                'range' => $priced ? [(float)$p['q1'], (float)$p['q3']] : null,
                'samples' => $priced ? (int)$p['valid_sample_size'] : 0,
                'confidence' => $priced ? strtolower((string)($p['confidence_label'] ?? '')) : null,
            ];
            $categories[(string)($p['category_slug'] ?? '')] = true;
            foreach ($it['facts'] as $f) { $labels[(string)$f['label']] = true; }
        }
        $rows = [];
        foreach (array_keys($labels) as $label) {
            $cells = []; $values = [];
            foreach ($kept as $it) {
                $hit = null;
                foreach ($it['facts'] as $f) { if ((string)$f['label'] === $label) { $hit = $f; break; } }
                $cells[] = ['value' => $hit === null ? null : (string)$hit['value'], 'source' => $hit['source'] ?? null, 'note' => $hit['note'] ?? null];
                if ($hit !== null) { $values[] = (string)$hit['value']; }   // a gap is unknown, not a different value
            }
            $rows[] = ['label' => $label, 'cells' => $cells, 'differs' => count(array_unique($values)) > 1];
        }
        return [
            'columns' => $columns, 'rows' => $rows,
            'mixed_category' => count($categories) > 1,
            'any_priced' => (bool)array_filter($columns, static fn(array $c): bool => $c['priced']),
        ];
    }
}
