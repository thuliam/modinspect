<?php
declare(strict_types=1);

namespace App\Services\Collection;

use App\Core\Database;
use PDO;

final class LiveQueryPlanner
{
    public const VERSION = 'live_query_v1';

    private PDO $db;

    public function __construct()
    {
        global $config;
        $this->db = Database::connection($config['db']);
    }

    public function queriesForProduct(int $productId, int $limit = 3): array
    {
        $product = $this->product($productId);
        if (!$product) {
            return [];
        }

        $aliases = $this->aliases($productId);
        $primaryAlias = $aliases[0] ?? $product['full_name'];
        $queries = [
            $product['full_name'] . ' มือสอง',
            $primaryAlias . ' ขาย มือสอง',
            $product['model_name'] . ' used Thailand',
        ];

        return array_slice(array_values(array_unique($queries)), 0, max(1, $limit));
    }

    public function product(int $productId): ?array
    {
        $stmt = $this->db->prepare("SELECT id,model_name,full_name FROM products WHERE id=:id AND is_active=1 LIMIT 1");
        $stmt->execute(['id' => $productId]);
        return $stmt->fetch() ?: null;
    }

    private function aliases(int $productId): array
    {
        $stmt = $this->db->prepare("SELECT alias_text FROM product_aliases WHERE product_id=:product_id ORDER BY confidence DESC, CHAR_LENGTH(alias_text) ASC, alias_text ASC LIMIT 5");
        $stmt->execute(['product_id' => $productId]);
        return array_map(static fn(array $row): string => (string)$row['alias_text'], $stmt->fetchAll());
    }
}
