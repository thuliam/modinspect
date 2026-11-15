<?php
declare(strict_types=1);
namespace App\Models;

use App\Core\Database;
use PDO;

final class Product
{
    private PDO $db;
    public function __construct() { global $config; $this->db = Database::connection($config['db']); }
    private string $select = "SELECT p.*, b.name brand_name, c.name category_name, c.slug category_slug, pi.price_low, pi.q1, pi.median, pi.q3, pi.price_high, pi.market_min, pi.market_max, pi.sample_size, pi.valid_sample_size, pi.fresh_sample_ratio, pi.confidence_score, pi.confidence_label, pi.confidence_method_version, pi.calculation_manifest, pi.last_calculated_at, (SELECT COUNT(*) FROM price_observations po LEFT JOIN raw_price_observations r ON r.id=po.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id WHERE po.product_id=p.id AND po.verified_status='approved' AND (r.id IS NULL OR LOWER(COALESCE(s.source_key,'')) LIKE 'offline_real_%' OR LOWER(COALESCE(s.name,'')) LIKE '%offline real import%' OR (LOWER(COALESCE(s.source_key,'')) NOT LIKE '%mock%' AND LOWER(COALESCE(s.source_key,'')) NOT LIKE '%fixture%' AND LOWER(COALESCE(s.source_key,'')) NOT LIKE '%test%' AND LOWER(COALESCE(s.name,'')) NOT LIKE '%mock%' AND LOWER(COALESCE(s.name,'')) NOT LIKE '%fixture%' AND LOWER(COALESCE(s.name,'')) NOT LIKE '%test%' AND LOWER(COALESCE(s.domain,'')) NOT LIKE 'mock.%'))) accepted_observation_count FROM products p JOIN brands b ON b.id=p.brand_id JOIN product_categories c ON c.id=p.category_id LEFT JOIN price_indices pi ON pi.id=(SELECT x.id FROM price_indices x WHERE x.product_id=p.id AND x.product_variant_id IS NULL AND COALESCE(x.provenance_status,'legacy_unavailable')<>'legacy_unavailable' ORDER BY x.last_calculated_at DESC LIMIT 1)";
// Public eligibility: (1) the mock/fixture/test source classification matches Dashboard::datasetLabel (REAL wins), and
// (2) legacy snapshots with unverifiable provenance are never joined, so they can never be shown as a market price.
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
    /** Plain public counts for the home page: models listed, names recognised, models with an eligible price. No admin data. */
    /** Brands that have at least one active product, most products first (feeds the brand strip on the home page). */
    public function brandsInCatalog(): array {
        return array_column($this->db->query("SELECT b.name, COUNT(*) n FROM products p JOIN brands b ON b.id=p.brand_id WHERE p.is_active=1 AND b.slug<>'generic' GROUP BY b.id, b.name ORDER BY n DESC, b.name")->fetchAll(), 'name');
    }

    public function publicCounts(): array {
        $all = $this->search();
        $priced = count(array_filter($all, static fn(array $p): bool => !empty($p['median']) && (int)($p['valid_sample_size'] ?? 0) > 0 && (int)($p['accepted_observation_count'] ?? 0) > 0));
        $aliases = (int)$this->db->query("SELECT COUNT(*) FROM product_aliases pa JOIN products p ON p.id=pa.product_id WHERE p.is_active=1")->fetchColumn();
        $categories = count(array_unique(array_column($all, 'category_id')));
        return ['models' => count($all), 'aliases' => $aliases, 'priced' => $priced, 'categories' => $categories];
    }
    /** Share of the included evidence behind a snapshot that is owner-collected text without a listing URL (DL-013). */
    public function evidenceGrades(int $productId): array {
        $stmt = $this->db->prepare("SELECT COUNT(*) total, COALESCE(SUM(CASE WHEN LOWER(COALESCE(s.source_key,'')) LIKE 'offline_owner_text_%' THEN 1 ELSE 0 END),0) owner_text FROM price_snapshot_observations so JOIN price_observations o ON o.id=so.observation_id LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id WHERE so.inclusion_status='included' AND so.snapshot_id=(SELECT x.id FROM price_indices x WHERE x.product_id=:pid AND x.product_variant_id IS NULL AND COALESCE(x.provenance_status,'legacy_unavailable')<>'legacy_unavailable' ORDER BY x.last_calculated_at DESC LIMIT 1)");
        $stmt->execute(['pid' => $productId]);
        $row = $stmt->fetch() ?: [];
        return ['total' => (int)($row['total'] ?? 0), 'owner_text' => (int)($row['owner_text'] ?? 0)];
    }
    public function findBySlug(string $slug): ?array {
        $stmt=$this->db->prepare($this->select . " WHERE p.slug=:slug AND p.is_active=1 LIMIT 1");
        $stmt->execute(['slug'=>$slug]); return $stmt->fetch() ?: null;
    }
    public function find(int $id): ?array {
        $stmt=$this->db->prepare($this->select . " WHERE p.id=:id LIMIT 1");
        $stmt->execute(['id'=>$id]); return $stmt->fetch() ?: null;
    }
    /** Monthly trend buckets (>=3 approved samples per month), oldest first. Derived by cli/price_trend_build.php. */
    public function trend(int $id): array {
        $stmt=$this->db->prepare("SELECT period_start, q1, median, q3, sample_size FROM price_trend_points WHERE product_id=:id ORDER BY period_start");
        $stmt->execute(['id'=>$id]); return $stmt->fetchAll();
    }
    /**
     * Neighbouring models with an eligible price: same category, nearest median to $refPrice first (own model excluded).
     * Each row gets 'same_brand' so the view can say why it is related.
     */
    public function similar(array $product, float $refPrice, int $limit = 4): array {
        $out = [];
        foreach ($this->search('', (string)($product['category_slug'] ?? '')) as $p) {
            if ((int)$p['id'] === (int)$product['id'] || empty($p['median']) || (int)($p['valid_sample_size'] ?? 0) <= 0) { continue; }
            $p['same_brand'] = (int)$p['brand_id'] === (int)$product['brand_id'];
            $p['distance'] = abs(log(max(1.0, (float)$p['median']) / max(1.0, $refPrice)));
            $out[] = $p;
        }
        usort($out, static fn(array $a, array $b): int => $a['distance'] <=> $b['distance']);
        return array_slice($out, 0, $limit);
    }
    public function history(int $id): array {
        $stmt=$this->db->prepare("SELECT * FROM price_histories WHERE product_id=:id ORDER BY snapshot_date");
        $stmt->execute(['id'=>$id]); return $stmt->fetchAll();
    }
}
