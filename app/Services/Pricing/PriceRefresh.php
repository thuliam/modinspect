<?php
declare(strict_types=1);

namespace App\Services\Pricing;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * LS-89 BE-2: bring the public price and the monthly trend of some models up to date after their observations changed.
 * It runs after the review decision is committed. If it fails, the decision stays and PublicSnapshot keeps an out-of-date price hidden,
 * so the worst case is "no price until someone recalculates", never a wrong price.
 *
 * Result per model: updated (a new snapshot was stored), unchanged (the public snapshot already matches), below_minimum (fewer than 3 usable
 * listings, so no public price can be calculated), failed (the error code is in 'error'; details go to the server log only).
 */
final class PriceRefresh
{
    private PDO $db;
    private PriceSnapshotService $snapshots;

    public function __construct(?PDO $db = null)
    {
        global $config;
        $this->db = $db ?? Database::connection($config['db']);
        $this->snapshots = new PriceSnapshotService();
    }

    /**
     * @param list<int> $productIds
     * @return list<array{product_id:int,status:string,median:?float,sample:?int,error:?string}>
     */
    public function refresh(array $productIds): array
    {
        $recompute = new SnapshotRecompute($this->db, $this->snapshots);
        $trend = new TrendBuilder($this->db);
        $out = [];
        foreach (array_values(array_unique(array_map('intval', $productIds))) as $productId) {
            $result = ['product_id' => $productId, 'status' => 'failed', 'median' => null, 'sample' => null, 'error' => null];
            try {
                $plan = $recompute->planProduct($productId);
                if ($plan['status'] === 'not_eligible' || $plan['status'] === 'none') {
                    $result['status'] = 'below_minimum';
                } elseif ($plan['status'] === 'unchanged') {
                    $result['status'] = 'unchanged';
                } else {
                    $stored = $this->snapshots->recalculateProduct($productId);
                    $result['status'] = $stored === null ? 'below_minimum' : 'updated';
                    if ($stored !== null) {
                        $result['median'] = (float)$stored['median'];
                        $result['sample'] = (int)$stored['valid_sample_size'];
                    }
                }
                $trend->rebuild($productId);
            } catch (Throwable $e) {
                error_log('PRICE_REFRESH_FAILED product=' . $productId . ' ' . get_class($e) . ': ' . $e->getMessage());
                $result['status'] = 'failed';
                $result['error'] = 'PRICE_REFRESH_FAILED';
            }
            $out[] = $result;
        }
        return $out;
    }
}
