<?php
declare(strict_types=1);

namespace App\Services\Catalog;

/**
 * LS-25 A6: models in the same category that are a step up from the one a visitor has, judged ONLY by published spec facts (more cores or threads, more VRAM,
 * more capacity) and listed by used price. It never says "faster", "better" or "worth it": a bigger number is a bigger number. A candidate is listed only with a
 * checked used price (the one public price rule); an unpriced model is counted, not guessed. Compatibility notes come from the same facts (socket, memory type,
 * board power) and only state what to check. Pure: callers pass the catalog rows and a spec map.
 */
final class UpgradeFinder
{
    public const LIMIT = 6;
    /** @var array<string,list<string>> the spec keys that define a "step up" per category slug, in order of importance */
    private const STEP = ['cpu' => ['cores', 'threads'], 'gpu' => ['vram_gb'], 'ram' => ['capacity_gb']];

    /** @return list<string> the spec keys the caller must load for the category (empty = category not supported) */
    public static function specKeys(string $categorySlug): array
    {
        return match ($categorySlug) {
            'cpu' => ['cores', 'threads', 'socket', 'memory_type'],
            'gpu' => ['vram_gb', 'board_power_watt'],
            'ram' => ['capacity_gb', 'memory_type'],
            default => [],
        };
    }

    public static function supports(string $categorySlug): bool { return isset(self::STEP[$categorySlug]); }

    /**
     * @param array<string,mixed> $base the model the visitor has (a catalog row with category_slug, median ...)
     * @param list<array<string,mixed>> $all catalog rows
     * @param array<int,array<string,string>> $specs product id => spec key => value
     * @return array{supported:bool,base_priced:bool,unpriced_hidden:int,items:list<array<string,mixed>>}
     */
    public static function find(array $base, array $all, array $specs): array
    {
        $cat = (string)($base['category_slug'] ?? '');
        $out = ['supported' => self::supports($cat), 'base_priced' => self::priced($base), 'unpriced_hidden' => 0, 'items' => []];
        if (!$out['supported']) { return $out; }
        $bs = $specs[(int)$base['id']] ?? [];
        foreach ($all as $p) {
            if ((int)$p['id'] === (int)$base['id'] || (string)($p['category_slug'] ?? '') !== $cat) { continue; }
            $cs = $specs[(int)$p['id']] ?? [];
            $steps = self::steps($cat, $bs, $cs);
            if ($steps === []) { continue; }
            if (!self::priced($p)) { $out['unpriced_hidden']++; continue; }
            $median = (float)$p['median'];
            $out['items'][] = ['product' => $p, 'median' => $median, 'delta' => $out['base_priced'] ? $median - (float)$base['median'] : null,
                'steps' => $steps, 'notes' => self::notes($cat, $bs, $cs)];
        }
        usort($out['items'], static fn(array $a, array $b): int => $a['median'] <=> $b['median']);
        $out['items'] = array_slice($out['items'], 0, self::LIMIT);
        return $out;
    }

    /** @return list<array{key:string,from:float,to:float}> the facts where the candidate is larger; empty when it is not larger in any, or a fact is missing on either side */
    private static function steps(string $cat, array $bs, array $cs): array
    {
        $steps = [];
        foreach (self::STEP[$cat] as $k) {
            if (!isset($bs[$k], $cs[$k]) || !is_numeric($bs[$k]) || !is_numeric($cs[$k])) { continue; }
            if ((float)$cs[$k] > (float)$bs[$k]) { $steps[] = ['key' => $k, 'from' => (float)$bs[$k], 'to' => (float)$cs[$k]]; }
        }
        if ($cat === 'ram' && ($bs['memory_type'] ?? null) !== ($cs['memory_type'] ?? null)) { return []; }   // RAM of another generation is not a step up of the same part
        return $steps;
    }

    /** @return list<string> things to check, Thai */
    private static function notes(string $cat, array $bs, array $cs): array
    {
        $n = [];
        if ($cat === 'cpu') {
            if (isset($bs['socket'], $cs['socket'])) {
                $n[] = strcasecmp($bs['socket'], $cs['socket']) === 0 ? 'ซ็อกเก็ตเดียวกัน (' . $cs['socket'] . ') ตรวจเวอร์ชัน BIOS ของเมนบอร์ดว่ารองรับ' : 'ซ็อกเก็ตต่างกัน (' . $bs['socket'] . ' เป็น ' . $cs['socket'] . ') ต้องเปลี่ยนเมนบอร์ด';
            }
            if (isset($bs['memory_type'], $cs['memory_type']) && strcasecmp($bs['memory_type'], $cs['memory_type']) !== 0) { $n[] = 'ชนิด RAM ต่างกัน (' . $bs['memory_type'] . ' เป็น ' . $cs['memory_type'] . ') ต้องเปลี่ยน RAM'; }
        }
        if ($cat === 'gpu' && isset($bs['board_power_watt'], $cs['board_power_watt']) && is_numeric($bs['board_power_watt']) && is_numeric($cs['board_power_watt']) && (float)$cs['board_power_watt'] > (float)$bs['board_power_watt']) {
            $n[] = 'กำลังไฟของการ์ดเพิ่มจาก ' . (int)$bs['board_power_watt'] . ' เป็น ' . (int)$cs['board_power_watt'] . ' วัตต์ ตรวจกำลังของ PSU และช่องจ่ายไฟ';
        }
        if ($cat === 'gpu') { $n[] = 'ตรวจความยาวการ์ดกับเคสก่อนซื้อ'; }
        return $n;
    }

    private static function priced(array $p): bool
    {
        return !empty($p['median']) && (int)($p['valid_sample_size'] ?? 0) > 0 && (int)($p['accepted_observation_count'] ?? 0) > 0;
    }
}
