<?php
declare(strict_types=1);

namespace App\Services\Review;

use App\Services\Pricing\CohortRules;
use App\Services\Pricing\PublicSnapshot;
use PDO;

/**
 * LS-90 T2: which pending asking prices would count towards a price if a person approved them, judged by the same rule the
 * calculation uses (CohortRules), and which of those could give a model its first public price. Read-only: it approves nothing,
 * and a correction made during review can still change the answer.
 */
final class PriceImpact
{
    private ?array $cache = null;

    public function __construct(private PDO $db)
    {
    }

    /** @return array{eligible:list<int>,first_price:list<int>} pending observation ids; first_price is a subset of eligible */
    public function pending(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        $public = array_flip($this->db->query('SELECT p.id FROM products p WHERE ' . PublicSnapshot::idSql('p.id') . ' IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN));
        $stmt = $this->db->query(CohortRules::selectSql() . " WHERE o.verified_status IN ('approved','pending') AND o.price_type='asking' ORDER BY o.id");
        $seen = [];
        $approved = [];
        $pending = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int)$row['observation_id'];
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $status = (string)$row['verified_status'];
            $row['verified_status'] = 'approved';
            if (CohortRules::exclusionReason($row, 'asking') !== null) {
                continue;
            }
            $product = (int)$row['product_id'];
            if ($status === 'pending') {
                $pending[$product][] = $id;
            } else {
                $approved[$product] = ($approved[$product] ?? 0) + 1;
            }
        }
        $eligible = [];
        $first = [];
        foreach ($pending as $product => $ids) {
            array_push($eligible, ...$ids);
            if (!isset($public[$product]) && ($approved[$product] ?? 0) + count($ids) >= PublicSnapshot::MIN_SAMPLE) {
                array_push($first, ...$ids);
            }
        }
        sort($eligible);
        sort($first);
        return $this->cache = ['eligible' => $eligible, 'first_price' => $first];
    }
}
