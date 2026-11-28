<?php
declare(strict_types=1);

namespace App\Services\Review;

use App\Core\Database;
use App\Services\Pricing\BrandNewClaim;
use PDO;

/**
 * LS-90 T8: one decision for several pending listings. Nothing here is a new rule: every row goes through ReviewDecisionService,
 * so each has its own transaction, decision record and audit line with the acting user, and a row that cannot be approved is
 * reported while the others are saved. The reviewer sees the rows and their warnings before anything is written.
 */
final class BulkReview
{
    public const MAX_ROWS = 50;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        global $config;
        $this->db = $db ?? Database::connection($config['db']);
    }

    /** @return list<int> the distinct positive ids in $input, at most MAX_ROWS */
    public static function ids(mixed $input): array
    {
        $values = is_array($input) ? $input : explode(',', is_string($input) ? $input : '');
        $ids = [];
        foreach ($values as $value) {
            if (is_scalar($value) && preg_match('/^\s*[0-9]{1,18}\s*$/', (string)$value) === 1 && (int)$value > 0) {
                $ids[(int)$value] = true;
            }
        }
        return array_slice(array_keys($ids), 0, self::MAX_ROWS);
    }

    /**
     * The pending rows among the chosen ids, with what a reviewer should see before approving. A flagged row is one whose text
     * says it is new, sold or reserved: it starts unticked on the confirmation page.
     * @param list<int> $ids
     * @return list<array{id:int,model:string,price:float,price_type:string,title:string,warnings:list<string>}>
     */
    public function preview(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $stmt = $this->db->prepare('SELECT o.id,p.full_name,o.price_type,COALESCE(o.price_value,o.asking_price) price,r.raw_title
            FROM price_observations o JOIN products p ON p.id=o.product_id LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id
            WHERE o.verified_status=\'pending\' AND o.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY p.full_name,o.id');
        $stmt->execute($ids);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $title = (string)$r['raw_title'];
            $warnings = [];
            if (BrandNewClaim::detect($title) !== null) {
                $warnings[] = 'BRAND_NEW_CLAIM';
            }
            if (preg_match('/ขายแล้ว|\bsold\b|จองแล้ว|reserved|ติดจอง/iu', $title) === 1) {
                $warnings[] = 'SOLD_OR_RESERVED';
            }
            $rows[] = ['id' => (int)$r['id'], 'model' => (string)$r['full_name'], 'price' => (float)$r['price'], 'price_type' => (string)$r['price_type'], 'title' => $title, 'warnings' => $warnings];
        }
        return $rows;
    }

    /**
     * @param list<int> $ids
     * @return array{done:int,failed:array<int,string>,product_ids:list<int>} failed maps an observation id to its error code
     */
    public function apply(array $ids, string $decision, int $actorId, string $note = ''): array
    {
        $service = new ReviewDecisionService($this->db);
        $done = 0;
        $failed = [];
        $products = [];
        foreach (array_slice($ids, 0, self::MAX_ROWS) as $id) {
            $result = $service->decide((int)$id, $decision, trim('[bulk] ' . $note), $actorId);
            if ($result['ok']) {
                $done++;
                array_push($products, ...$result['product_ids']);
            } else {
                $failed[(int)$id] = (string)$result['error'];
            }
        }
        return ['done' => $done, 'failed' => $failed, 'product_ids' => array_values(array_unique($products))];
    }
}
