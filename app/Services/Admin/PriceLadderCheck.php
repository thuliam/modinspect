<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use PDO;

/**
 * Cross-model plausibility flags for GPU snapshots (LS-2 H-4). Within one family and generation (RTX 40xx, RX 6xxx, GTX 16xx ...) a higher
 * tier should not be priced clearly below a lower tier (4070 below 4060, 3060 Ti below 3060). A violation points at a bad sample, a wrong
 * product assignment or a stale cohort. It is a FLAG for a human: it never changes a price, a snapshot, a label or an approval.
 * Three ladders, each deliberately narrow so a flag means something:
 *  - GPU: same family and generation, ordered by model number then suffix (Ti, Super, XT ...);
 *  - CPU: same brand line and generation (Ryzen 5xxx, Core 12th gen), same suffix class (none, X, F, K, KF, G, X3D), ordered by model number;
 *  - RAM: same DDR generation and speed, ordered by capacity (a bigger kit must not cost clearly less than a smaller one).
 * Anything that does not fit a ladder (mixed suffix classes, different generations, different speeds) is not compared.
 */
final class PriceLadderCheck
{
    /** a higher tier must be at least this share of the lower tier's median before it is flagged */
    public const TOLERANCE = 0.90;
    private const SUFFIX_RANK = ['' => 0, 'gre' => 1, 'super' => 2, 'xt' => 2, 'ti' => 3, 'xtx' => 3, 'ti super' => 4];

    /** @return array{family:string,number:int,rank:int}|null */
    public static function parse(string $fullName): ?array
    {
        if (!preg_match('/\b(RTX|GTX|RX)\s?(\d{4})(?:\s+(Ti\s+SUPER|Ti|SUPER|XTX|XT|GRE))?\b/i', $fullName, $m)) { return null; }
        $family = strtolower($m[1]); $number = (int)$m[2];
        $gen = $family === 'gtx' ? intdiv($number, 100) : intdiv($number, 1000);
        $suffix = strtolower(preg_replace('/\s+/', ' ', trim((string)($m[3] ?? ''))) ?? '');
        if (!array_key_exists($suffix, self::SUFFIX_RANK)) { return null; }
        return ['family' => $family . $gen, 'number' => $number, 'rank' => self::SUFFIX_RANK[$suffix]];
    }

    /** @return array{family:string,number:int,rank:int}|null a CPU ladder position: Ryzen/Core generation + suffix class form the family, the model number orders it */
    public static function parseCpu(string $fullName): ?array
    {
        if (preg_match('/\bRyzen\s+[3579]\s+(\d)(\d{3})(X3D|XT|X|G|GE|F)?\b/i', $fullName, $m)) {
            return ['family' => 'ryzen' . $m[1] . '-' . strtolower($m[3] ?? ''), 'number' => (int)($m[1] . $m[2]), 'rank' => 0];
        }
        if (preg_match('/\bCore\s+i[3579][\s-]+(\d{2})(\d{3})(KF|KS|K|F|T)?\b/i', $fullName, $m)) {
            return ['family' => 'core' . $m[1] . '-' . strtolower($m[3] ?? ''), 'number' => (int)($m[1] . $m[2]), 'rank' => 0];
        }
        return null;
    }

    /** @return array{family:string,number:int,rank:int}|null a RAM ladder position: DDR generation + speed form the family, the capacity in GB orders it */
    public static function parseRam(string $fullName): ?array
    {
        if (!preg_match('/\bDDR([345])-(\d{3,5})\s+(\d{1,3})\s?GB\b/i', $fullName, $m)) { return null; }
        return ['family' => 'ddr' . $m[1] . '-' . $m[2], 'number' => (int)$m[3], 'rank' => 0];
    }

    /** @return array{family:string,number:int,rank:int}|null the ladder position of a product name, whichever ladder it belongs to */
    public static function parseAny(string $fullName): ?array
    {
        return self::parse($fullName) ?? self::parseCpu($fullName) ?? self::parseRam($fullName);
    }
    /**
     * @param list<array{name:string,median:float}> $items
     * @return list<array{higher:string,higher_median:float,lower:string,lower_median:float,ratio:float}>
     */
    public static function violations(array $items): array
    {
        $by = [];
        foreach ($items as $it) {
            $p = self::parseAny($it['name']);
            if ($p !== null && $it['median'] > 0) { $by[$p['family']][] = $p + ['name' => $it['name'], 'median' => (float)$it['median']]; }
        }
        $out = [];
        foreach ($by as $list) {
            usort($list, static fn(array $a, array $b): int => [$a['number'], $a['rank']] <=> [$b['number'], $b['rank']]);
            $n = count($list);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $lo = $list[$i]; $hi = $list[$j];
                    if ($hi['number'] === $lo['number'] && $hi['rank'] === $lo['rank']) { continue; }
                    if ($hi['median'] < $lo['median'] * self::TOLERANCE) {
                        $out[] = ['higher' => $hi['name'], 'higher_median' => $hi['median'], 'lower' => $lo['name'], 'lower_median' => $lo['median'], 'ratio' => round($hi['median'] / $lo['median'], 3)];
                    }
                }
            }
        }
        usort($out, static fn(array $a, array $b): int => $a['ratio'] <=> $b['ratio']);
        return $out;
    }

    /** number of snapshots (GPU, CPU and RAM) that took part in the last forPublicSnapshots() call */
    public int $checked = 0;

    /** @return list<array{higher:string,higher_median:float,lower:string,lower_median:float,ratio:float}> */
    public function forPublicSnapshots(): array
    {
        global $config;
        $db = Database::connection($config['db']);
        $rows = $db->query("SELECT p.full_name name, pi.median FROM price_indices pi JOIN products p ON p.id = pi.product_id
            WHERE " . \App\Services\Pricing\PublicSnapshot::intactSql('pi') . " AND pi.id IN (SELECT MAX(id) FROM price_indices WHERE price_type = 'asking' AND product_variant_id IS NULL AND COALESCE(provenance_status, 'legacy_unavailable') <> 'legacy_unavailable' GROUP BY product_id)
              AND pi.valid_sample_size >= 3 AND pi.median > 0 AND p.is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
        $this->checked = count(array_filter($rows, static fn(array $r): bool => self::parseAny((string)$r['name']) !== null));
        return self::violations(array_map(static fn(array $r): array => ['name' => (string)$r['name'], 'median' => (float)$r['median']], $rows));
    }
}
