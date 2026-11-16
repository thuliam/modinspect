<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use PDO;

/**
 * Admin-only (LS-2 H-6): the public snapshots of one product over time and what changed since the previous one.
 * Reads what was stored at calculation time (price_indices + price_snapshot_observations); it never recalculates a price.
 * Shows dates, counts, medians and labels only: no source names, URLs or listing text.
 */
final class SnapshotHistory
{
    private PDO $db;
    public function __construct() { global $config; $this->db = Database::connection($config['db']); }

    /**
     * @param list<int> $previous observation ids included in the previous snapshot
     * @param list<int> $current  observation ids included in this snapshot
     * @return array{added:int,removed:int,kept:int}
     */
    public static function diffSets(array $previous, array $current): array
    {
        $p = array_flip($previous); $c = array_flip($current);
        $kept = count(array_intersect_key($p, $c));
        return ['added' => count($c) - $kept, 'removed' => count($p) - $kept, 'kept' => $kept];
    }

    /** @return array{change_pct:?float,delta:float} */
    public static function medianChange(float $previous, float $current): array
    {
        return ['delta' => round($current - $previous, 2), 'change_pct' => $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null];
    }

    /** @return array{history:list<array>,previous:?array,diff:?array,median:?array}|null */
    public function forSnapshot(int $snapshotId): ?array
    {
        $cur = $this->db->prepare('SELECT id, product_id, median FROM price_indices WHERE id = :id');
        $cur->execute(['id' => $snapshotId]);
        $c = $cur->fetch(PDO::FETCH_ASSOC);
        if (!$c) { return null; }

        $h = $this->db->prepare("SELECT id, last_calculated_at calculated_at, median, valid_sample_size included, LOWER(confidence_label) label
            FROM price_indices WHERE product_id = :p AND price_type = 'asking' AND product_variant_id IS NULL
              AND COALESCE(provenance_status, 'legacy_unavailable') <> 'legacy_unavailable' ORDER BY id DESC");
        $h->execute(['p' => (int)$c['product_id']]);
        $all = array_map(static fn(array $r): array => ['id' => (int)$r['id'], 'calculated_at' => (string)$r['calculated_at'], 'median' => (float)$r['median'], 'included' => (int)$r['included'], 'label' => (string)$r['label']], $h->fetchAll(PDO::FETCH_ASSOC));

        $previous = null;
        foreach ($all as $row) { if ($row['id'] < (int)$c['id']) { $previous = $row; break; } }
        $diff = null; $median = null;
        if ($previous !== null) {
            $ids = function (int $snap): array {
                $s = $this->db->prepare("SELECT observation_id FROM price_snapshot_observations WHERE snapshot_id = :s AND inclusion_status = 'included'");
                $s->execute(['s' => $snap]);
                return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
            };
            $diff = self::diffSets($ids($previous['id']), $ids((int)$c['id']));
            $median = self::medianChange($previous['median'], (float)$c['median']);
        }
        return ['history' => array_slice($all, 0, 8), 'previous' => $previous, 'diff' => $diff, 'median' => $median];
    }
}
