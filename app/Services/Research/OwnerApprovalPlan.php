<?php
declare(strict_types=1);

namespace App\Services\Research;

use PDO;

/**
 * The approval plan for pending owner-text observations: which products would be approved, how many rows are excluded by the outlier rule,
 * and which products stay pending (too few usable rows, or a bimodal sample). Read-only: it builds the plan, it never writes a decision.
 * This is the plan computation that used to sit inside cli/owner_intake_approve.php; the command line (which applies the plan) and the admin
 * intake screen (which only shows it, LS-3 J-3) now share it, so what the screen shows is exactly what `--apply` would do.
 * Only asking prices count; the median basis is the pending rows plus the already approved, non-duplicate, non-defective, non-deposit asking rows.
 */
final class OwnerApprovalPlan
{
    /**
     * @return array{plan:array<int,array{name:string,median:float,keep:list<array{id:int,price:float}>,drop:list<array{id:int,price:float}>,already:int}>,left_pending:list<array{0:string,1:int,2:string}>}
     */
    public static function build(PDO $db, string $sourceLike = 'offline_owner_text_%', bool $trustedOnly = false): array
    {
        $st = $db->prepare("SELECT o.id, o.product_id, p.full_name, COALESCE(o.price_value,o.asking_price) price, o.is_deposit, o.is_defective, o.is_duplicate, o.listing_type, r.raw_title,
                p.memory_type, p.memory_speed_mhz, p.capacity_gb
            FROM price_observations o
            JOIN raw_price_observations r ON r.id=o.raw_observation_id
            JOIN data_sources s ON s.id=r.source_id
            JOIN products p ON p.id=o.product_id
            WHERE s.source_key LIKE :src AND o.verified_status='pending' AND o.price_type='asking' AND COALESCE(o.price_value,o.asking_price) > 0
            ORDER BY o.product_id, o.id");
        $st->execute(['src' => $sourceLike]);
        $rows = $st->fetchAll();
        $by = [];
        foreach ($rows as $r) {
            // LS-76 $trustedOnly: a row a reviewer would not trust as one used single item (bundle, sold or reserved, new, deposit, defective, duplicate) is left pending for a person
            if ($trustedOnly && (self::untrusted($r))) { continue; }
            $by[(int)$r['product_id']][] = $r;
        }
        $approvedBy = [];
        foreach ($db->query("SELECT product_id, COALESCE(price_value,asking_price) price FROM price_observations WHERE verified_status='approved' AND price_type='asking' AND is_duplicate=0 AND is_defective=0 AND is_deposit=0 AND COALESCE(price_value,asking_price) > 0")->fetchAll() as $a) {
            $approvedBy[(int)$a['product_id']][] = (float)$a['price'];
        }

        $plan = []; $leftPending = [];
        foreach ($by as $pid => $list) {
            $pending = array_map(static fn($r) => ['id' => (int)$r['id'], 'price' => (float)$r['price']], $list);
            $res = OwnerIntakePlanner::plan($pending, $approvedBy[$pid] ?? []);
            if ($res['status'] !== 'approve') { $leftPending[] = [$list[0]['full_name'], $res['usable'], $res['status']]; continue; }
            $plan[$pid] = ['name' => $list[0]['full_name'], 'median' => $res['median'], 'keep' => $res['keep'], 'drop' => $res['drop'], 'already' => count($approvedBy[$pid] ?? [])];
        }
        return ['plan' => $plan, 'left_pending' => $leftPending];
    }

    /** @param array<string,mixed> $r */
    public static function untrusted(array $r): bool
    {
        // LS-77: a title that contradicts the RAM product it sits under (16GB kit under a 32GB class) is a misfiled row, never trusted
        if (!empty($r['memory_type']) && \App\Services\Catalog\SpecConsistency::ramConflicts((string)$r['raw_title'], $r) !== []) { return true; }
        return (int)$r['is_deposit'] === 1 || (int)$r['is_defective'] === 1 || (int)$r['is_duplicate'] === 1 || (string)$r['listing_type'] !== 'single_item'
            || preg_match('/(ขายแล้ว|\bsold\b|จองแล้ว|reserved|ติดจอง|มือ ?1\b|มือหนึ่ง|brand new|sealed|ใหม่แกะกล่อง|ของใหม่|มัดจำ|deposit)/iu', (string)$r['raw_title']) === 1;
    }
}
