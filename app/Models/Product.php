<?php
declare(strict_types=1);
namespace App\Models;

use App\Core\Database;
use PDO;

final class Product
{
    private PDO $db;
    public function __construct() { global $config; $this->db = Database::connection($config['db']); }
    private string $select = "SELECT p.*, b.name brand_name, c.name category_name, pi.price_low, pi.q1, pi.median, pi.q3, pi.price_high, pi.market_min, pi.market_max, pi.sample_size, pi.valid_sample_size, pi.fresh_sample_ratio, pi.confidence_score, pi.confidence_label, pi.confidence_method_version, pi.calculation_manifest, pi.last_calculated_at, (SELECT COUNT(*) FROM price_observations po LEFT JOIN raw_price_observations r ON r.id=po.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id WHERE po.product_id=p.id AND po.verified_status='approved' AND (r.id IS NULL OR (LOWER(COALESCE(s.source_key,'')) NOT LIKE '%mock%' AND LOWER(COALESCE(s.name,'')) NOT LIKE '%mock%' AND LOWER(COALESCE(s.domain,'')) NOT LIKE 'mock.%'))) accepted_observation_count FROM products p JOIN brands b ON b.id=p.brand_id JOIN product_categories c ON c.id=p.category_id LEFT JOIN price_indices pi ON pi.id=(SELECT x.id FROM price_indices x WHERE x.product_id=p.id AND x.product_variant_id IS NULL ORDER BY x.last_calculated_at DESC LIMIT 1)";
    public function popular(int $limit = 6): array {
        $stmt = $this->db->prepare($this->select . " WHERE p.is_active=1 ORDER BY p.is_popular DESC, pi.valid_sample_size DESC LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT); $stmt->execute(); return $stmt->fetchAll();
    }
    public function search(string $query = '', ?string $category = null): array {
        $sql = $this->select . " WHERE p.is_active=1";
        $params = [];
        if ($query !== '') {
            $sql .= " AND (p.full_name LIKE :q_full OR p.model_name LIKE :q_model OR EXISTS(SELECT 1 FROM product_aliases pa WHERE pa.product_id=p.id AND pa.alias_text LIKE :q_alias))";
            $params['q_full'] = $params['q_model'] = $params['q_alias'] = "%$query%";
        }
        if ($category) { $sql .= " AND c.slug=:category"; $params['category'] = $category; }
        $sql .= " ORDER BY p.is_popular DESC, p.full_name";
        $stmt=$this->db->prepare($sql); $stmt->execute($params); return $stmt->fetchAll();
    }
    public function findBySlug(string $slug): ?array {
        $stmt=$this->db->prepare($this->select . " WHERE p.slug=:slug AND p.is_active=1 LIMIT 1");
        $stmt->execute(['slug'=>$slug]); return $stmt->fetch() ?: null;
    }
    public function find(int $id): ?array {
        $stmt=$this->db->prepare($this->select . " WHERE p.id=:id LIMIT 1");
        $stmt->execute(['id'=>$id]); return $stmt->fetch() ?: null;
    }
    public function history(int $id): array {
        $stmt=$this->db->prepare("SELECT * FROM price_histories WHERE product_id=:id ORDER BY snapshot_date");
        $stmt->execute(['id'=>$id]); return $stmt->fetchAll();
    }
}
