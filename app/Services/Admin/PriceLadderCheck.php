<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use PDO;

/**
 * Cross-model plausibility flags for GPU snapshots (LS-2 H-4). Within one family and generation (RTX 40xx, RX 6xxx, GTX 16xx ...) a higher
 * tier should not be priced clearly below a lower tier (4070 below 4060, 3060 Ti below 3060). A violation points at a bad sample, a wrong
 * product assignment or a stale cohort. It is a FLAG for a human: it never changes a price, a snapshot, a label or an approval.
 * Only GPUs are checked: CPUs and RAM have no clean single ladder (X3D, F/K variants, speed grades).
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

    /**
     * @param list<array{name:string,median:float}> $items
     * @return list<array{higher:string,higher_median:float,lower:string,lower_median:float,ratio:float}>
     */
    public static function violations(array $items): array
    {
        $by = [];
        foreach ($items as $it) {
            $p = self::parse($it['name']);
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

    /** number of GPU snapshots that took part in the last forPublicSnapshots() call */
    public int $checked = 0;

    /** @return list<array{higher:string,higher_median:float,lower:string,lower_median:float,ratio:float}> */
    public function forPublicSnapshots(): array
    {
        global $config;
        $db = Database::connection($config['db']);
        $rows = $db->query("SELECT p.full_name name, pi.median FROM price_indices pi JOIN products p ON p.id = pi.product_id
            WHERE pi.id IN (SELECT MAX(id) FROM price_indices WHERE price_type = 'asking' AND product_variant_id IS NULL AND COALESCE(provenance_status, 'legacy_unavailable') <> 'legacy_unavailable' GROUP BY product_id)
              AND pi.valid_sample_size >= 3 AND pi.median > 0 AND p.is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
        $this->checked = count(array_filter($rows, static fn(array $r): bool => self::parse((string)$r['name']) !== null));
        return self::violations(array_map(static fn(array $r): array => ['name' => (string)$r['name'], 'median' => (float)$r['median']], $rows));
    }
}
