<?php
declare(strict_types=1);

namespace App\Services\Benchmark;

/**
 * LS-45: the data behind the Compare page radar. Up to four models are drawn on one radar, one colour each (the colour is tied to the model's position in the
 * Compare boxes, so the dot on its card matches its shape). Only published scores are used (product_benchmark_scores, InnoReviews), never anything derived.
 * A model without at least three published axes is left out and named in "missing"; models of different categories are never drawn together (their axes differ);
 * only axes that every drawn model has are used. Fewer than two drawn models or fewer than three shared axes means no radar (null). Pure.
 */
final class CompareRadar
{
    public const COLORS = ['#bbf246', '#22d3ee', '#e879f9', '#fb923c'];

    /**
     * @param list<array{product:array,scores?:list<array>}> $items chosen models in Compare order
     * @return array{labels:list<string>,series:list<array{name:string,color:string,values:list<float>}>,missing:list<string>,models:list<array{name:string,color:string,scored:bool}>}|null
     */
    public static function build(array $items): ?array
    {
        $scored = []; $missing = [];
        foreach (array_slice($items, 0, 4, true) as $i => $it) {
            $p = (array)($it['product'] ?? []);
            $name = (string)($p['full_name'] ?? '');
            $cat = strtolower((string)($p['category_slug'] ?? ''));
            $vals = [];
            foreach ((array)($it['scores'] ?? []) as $r) {
                if (($r['metric_key'] ?? '') !== 'inno_score' && isset($r['score_value'])) { $vals[(string)$r['metric_key']] = (float)$r['score_value']; }
            }
            if (!isset(BenchmarkMatcher::METRICS[$cat]) || count($vals) < 3) { $missing[] = $name; continue; }
            $scored[(int)$i] = ['cat' => $cat, 'name' => $name, 'vals' => $vals];
        }
        if (count($scored) < 2) { return null; }
        $cat = reset($scored)['cat'];
        foreach ($scored as $i => $m) { if ($m['cat'] !== $cat) { $missing[] = $m['name']; unset($scored[$i]); } }
        if (count($scored) < 2) { return null; }
        $axes = [];
        foreach (BenchmarkMatcher::METRICS[$cat] as $key => $label) {
            if ($key === 'inno_score') { continue; }
            $all = true; foreach ($scored as $m) { if (!isset($m['vals'][$key])) { $all = false; break; } }
            if ($all) { $axes[$key] = $label; }
        }
        if (count($axes) < 3) { return null; }
        $series = [];
        foreach ($scored as $i => $m) {
            $series[] = ['name' => $m['name'], 'color' => self::COLORS[$i % 4], 'values' => array_map(static fn(string $k): float => $m['vals'][$k], array_keys($axes))];
        }
        $models = [];
        foreach (array_slice($items, 0, 4, true) as $i => $it) {
            $models[] = ['name' => (string)($it['product']['full_name'] ?? ''), 'color' => self::COLORS[(int)$i % 4], 'scored' => isset($scored[(int)$i])];
        }
        return ['labels' => array_values($axes), 'series' => $series, 'missing' => $missing, 'models' => $models];
    }
}
