<?php
declare(strict_types=1);
namespace App\Models;

use App\Core\Database;
use App\Services\Pricing\PublicFreshness;
use App\Services\Pricing\PublicSnapshot;
use App\Services\Pricing\SourceIdentity;
use PDO;

final class Product
{
    private PDO $db;
    public function __construct() { global $config; $this->db = Database::connection($config['db']); }
    private function selectSql(): string { return "SELECT p.*, b.name brand_name, c.name category_name, c.slug category_slug, pi.price_low, pi.q1, pi.median, pi.q3, pi.price_high, pi.market_min, pi.market_max, pi.sample_size, pi.valid_sample_size, pi.fresh_sample_ratio, pi.confidence_score, pi.confidence_label, pi.confidence_method_version, pi.calculation_manifest, pi.last_calculated_at, pi.id snapshot_id, (SELECT COUNT(*) FROM price_observations po LEFT JOIN raw_price_observations r ON r.id=po.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id WHERE po.product_id=p.id AND po.verified_status='approved' AND (r.id IS NULL OR LOWER(COALESCE(s.source_key,'')) LIKE 'offline_real_%' OR LOWER(COALESCE(s.name,'')) LIKE '%offline real import%' OR (LOWER(COALESCE(s.source_key,'')) NOT LIKE '%mock%' AND LOWER(COALESCE(s.source_key,'')) NOT LIKE '%fixture%' AND LOWER(COALESCE(s.source_key,'')) NOT LIKE '%test%' AND LOWER(COALESCE(s.name,'')) NOT LIKE '%mock%' AND LOWER(COALESCE(s.name,'')) NOT LIKE '%fixture%' AND LOWER(COALESCE(s.name,'')) NOT LIKE '%test%' AND LOWER(COALESCE(s.domain,'')) NOT LIKE 'mock.%'))) accepted_observation_count FROM products p JOIN brands b ON b.id=p.brand_id JOIN product_categories c ON c.id=p.category_id LEFT JOIN price_indices pi ON pi.id=" . PublicSnapshot::idSql('p.id'); }
// Public eligibility (LS-88 adds (3): a snapshot with an excluded, moved or re-priced member is not joined, see PublicSnapshot): (1) the mock/fixture/test source classification matches Dashboard::datasetLabel (REAL wins), and
// (2) legacy snapshots with unverifiable provenance are never joined, so they can never be shown as a market price.
    /** LS-88 T3: adds newest_observed_at, current_fresh_ratio and current_freshness_code (all for today) to rows that show a public price; one extra query per call */
    private function withFreshness(array $rows): array {
        $ids = array_values(array_filter(array_map(static fn(array $r): int => (int)($r['snapshot_id'] ?? 0), $rows)));
        $dates = [];
        if ($ids) {
            $stmt = $this->db->prepare("SELECT snapshot_id, snapshot_observed_at FROM price_snapshot_observations WHERE inclusion_status='included' AND snapshot_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll() as $m) { $dates[(int)$m['snapshot_id']][] = (string)$m['snapshot_observed_at']; }
        }
        $today = date('Y-m-d');
        foreach ($rows as &$row) {
            $now = PublicFreshness::evaluate($dates[(int)($row['snapshot_id'] ?? 0)] ?? [], $today);
            $row['newest_observed_at'] = $now['newest_observed_at']; $row['current_fresh_ratio'] = $now['fresh_ratio']; $row['current_freshness_code'] = $now['code'];
        }
        unset($row);
        return $rows;
    }
    /** LS-20 D3: models that show a public price come first (the same "has a price" rule as the product card), then popular ones, so the first cards on the home page are never empty when priced models exist */
    public function popular(int $limit = 6): array {
        $stmt = $this->db->prepare($this->selectSql() . " WHERE p.is_active=1 ORDER BY (pi.median IS NOT NULL AND pi.valid_sample_size > 0 AND accepted_observation_count > 0) DESC, p.is_popular DESC, pi.valid_sample_size DESC, p.full_name LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT); $stmt->execute(); return $this->withFreshness($stmt->fetchAll());
    }
    public function search(string $query = '', ?string $category = null): array {
        $sql = $this->selectSql() . " WHERE p.is_active=1";
        $params = [];
        $query = \App\Services\Search\SearchQuery::clip($query);
        if ($query !== '') {
            // the original substring match stays as it was; SearchQuery adds the compact and any-order-words matches (they only add results)
            $extra = \App\Services\Search\SearchQuery::extraConditions($query);
            $sql .= " AND (p.full_name LIKE :q_full OR p.model_name LIKE :q_model OR EXISTS(SELECT 1 FROM product_aliases pa WHERE pa.product_id=p.id AND pa.alias_text LIKE :q_alias)" . ($extra['sql'] !== '' ? ' OR ' . $extra['sql'] : '') . ")";
            $params['q_full'] = $params['q_model'] = $params['q_alias'] = "%$query%";
            $params += $extra['params'];
        }
        if ($category) { $sql .= " AND c.slug=:category"; $params['category'] = $category; }
        // LS-31: a model whose name, model name or alias IS the query comes first ("RTX 3060" before "RTX 3060 Ti"); the popularity order is unchanged inside each group
        $exact = '';
        if ($query !== '') {
            $exact = "(p.full_name = :q_ex1 OR p.model_name = :q_ex2 OR EXISTS(SELECT 1 FROM product_aliases pe WHERE pe.product_id=p.id AND pe.alias_text = :q_ex3)) DESC, ";
            $params['q_ex1'] = $params['q_ex2'] = $params['q_ex3'] = $query;
        }
        $sql .= " ORDER BY " . $exact . "p.is_popular DESC, p.full_name";
        $stmt=$this->db->prepare($sql); $stmt->execute($params); return $this->withFreshness($stmt->fetchAll());
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
        $stmt = $this->db->prepare("SELECT COUNT(*) total, COALESCE(SUM(CASE WHEN LOWER(COALESCE(s.source_key,'')) LIKE 'offline_owner_text_%' THEN 1 ELSE 0 END),0) owner_text, COALESCE(SUM(CASE WHEN EXISTS (SELECT 1 FROM market_evidence me WHERE me.raw_observation_id=r.id AND me.excerpt LIKE '%CAPTURE_METHOD=owner_transcription%') THEN 1 ELSE 0 END),0) transcribed FROM price_snapshot_observations so JOIN price_observations o ON o.id=so.observation_id LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id WHERE so.inclusion_status='included' AND so.snapshot_id=" . PublicSnapshot::idSql(':pid'));
        $stmt->execute(['pid' => $productId]);
        $row = $stmt->fetch() ?: [];
        return ['total' => (int)($row['total'] ?? 0), 'owner_text' => (int)($row['owner_text'] ?? 0), 'transcribed' => (int)($row['transcribed'] ?? 0)];
    }
    public function findBySlug(string $slug): ?array {
        $stmt=$this->db->prepare($this->selectSql() . " WHERE p.slug=:slug AND p.is_active=1 LIMIT 1");
        $stmt->execute(['slug'=>$slug]); return $this->withFreshness($stmt->fetchAll())[0] ?? null;
    }
    public function find(int $id): ?array {
        $stmt=$this->db->prepare($this->selectSql() . " WHERE p.id=:id LIMIT 1");
        $stmt->execute(['id'=>$id]); return $this->withFreshness($stmt->fetchAll())[0] ?? null;
    }
    /** LS-32 A: published benchmark scores for the model (reference only, never a price input). Empty when migration 029 has not been applied yet. */
    public function benchmarks(int $id): array {
        try {
            $stmt=$this->db->prepare("SELECT metric_key, score_value, scale_note, source_name, source_url, license_note, source_updated_on FROM product_benchmark_scores WHERE product_id=:id");
            $stmt->execute(['id'=>$id]); return $stmt->fetchAll();
        } catch (\PDOException) { return []; }
    }
    /** LS-69: up to five pictures of a model (migration 033), slot order; an older database without the table returns none and the page keeps its single legacy picture */
    public function images(int $id): array {
        try {
            $stmt=$this->db->prepare("SELECT slot, path, alt_text, source_name, source_url, license_note FROM product_images WHERE product_id=:id ORDER BY slot");
            $stmt->execute(['id'=>$id]); return $stmt->fetchAll();
        } catch (\PDOException) { return []; }
    }
    /** LS-59: published in-game frame rates of a CPU/GPU (migration 031); an older database without the table returns none */
    public function gameFps(int $id): array {
        try {
            $stmt=$this->db->prepare("SELECT game_key, resolution, quality, avg_fps, low1_fps, paired_part, source_name, source_url, license_note, sponsor_name, tested_on FROM game_fps_results WHERE product_id=:id");
            $stmt->execute(['id'=>$id]); return $stmt->fetchAll();
        } catch (\PDOException) { return []; }
    }
    /** Monthly trend buckets (>=3 approved samples per month), oldest first. Derived by cli/price_trend_build.php. low_price/high_price (migration 029) are the wicks of the candle chart; older databases fall back to the quartiles only. */
    public function trend(int $id): array {
        // LS-88: when a recorded snapshot exists but is no longer public, the stored months may hold the same excluded listings: show none
        $state = $this->db->prepare("SELECT COUNT(*) recorded, " . PublicSnapshot::idSql(':b') . " public_id FROM price_indices WHERE product_id=:a AND product_variant_id IS NULL AND price_type='asking' AND COALESCE(provenance_status,'legacy_unavailable')<>'legacy_unavailable'");
        $state->execute(['a' => $id, 'b' => $id]); $seen = $state->fetch() ?: [];
        if ((int)($seen['recorded'] ?? 0) > 0 && ($seen['public_id'] ?? null) === null) { return []; }
        try {
            $stmt=$this->db->prepare("SELECT period_start, q1, median, q3, low_price, high_price, sample_size FROM price_trend_points WHERE product_id=:id ORDER BY period_start");
            $stmt->execute(['id'=>$id]);
        } catch (\PDOException) {
            $stmt=$this->db->prepare("SELECT period_start, q1, median, q3, sample_size FROM price_trend_points WHERE product_id=:id ORDER BY period_start");
            $stmt->execute(['id'=>$id]);
        }
        return $stmt->fetchAll();
    }
    /**
     * LS-51: neighbouring models by performance: same category, nearest overall published score (InnoReviews inno_score) first, the model itself excluded. Empty when this model has no score or the table is missing. Reference only.
     * @return list<array> search() rows plus inno and inno_diff
     */
    public function similarByScore(array $product, int $limit = 4): array {
        try {
            $st = $this->db->prepare("SELECT s.product_id, s.score_value FROM product_benchmark_scores s JOIN products p ON p.id = s.product_id WHERE s.source_key = 'innoreviews' AND s.metric_key = 'inno_score' AND p.is_active = 1 AND p.category_id = :c");
            $st->execute(['c' => (int)$product['category_id']]);
            $scores = $st->fetchAll(\PDO::FETCH_KEY_PAIR);
        } catch (\PDOException) { return []; }
        $mine = $scores[(int)$product['id']] ?? null;
        if ($mine === null) { return []; }
        $out = [];
        foreach ($this->search('', (string)($product['category_slug'] ?? '')) as $p) {
            $id = (int)$p['id'];
            if ($id === (int)$product['id'] || !isset($scores[$id])) { continue; }
            $p['inno'] = (float)$scores[$id]; $p['inno_diff'] = (float)$scores[$id] - (float)$mine; $out[] = $p;
        }
        usort($out, static fn(array $a, array $b): int => abs($a['inno_diff']) <=> abs($b['inno_diff']));
        return array_slice($out, 0, $limit);
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
    /**
     * Evidence behind the price shown publicly: the members of the snapshot that feeds the public read model.
     * Public-safe: date, asking price, evidence grade, inclusion. No seller, URL, raw text or reference ids.
     * @return array{snapshot_id:int,included:array,excluded:array,sources:int}|null
     */
    public function evidence(int $productId): ?array {
        $snap = $this->db->prepare("SELECT " . PublicSnapshot::idSql(':p'));
        $snap->execute(['p' => $productId]);
        $sid = (int)$snap->fetchColumn();
        if ($sid <= 0) { return null; }
        $stmt = $this->db->prepare("SELECT so.inclusion_status, so.exclusion_reason, so.calculation_price, so.snapshot_observed_at observed_at, so.snapshot_source_id source_id, s.domain source_domain, s.name source_name, s.source_key,
                CASE WHEN LOWER(COALESCE(s.source_key,'')) LIKE 'offline_owner_text_%' THEN 'owner_text' ELSE 'other' END grade
            FROM price_snapshot_observations so LEFT JOIN data_sources s ON s.id=so.snapshot_source_id
            WHERE so.snapshot_id=:sid ORDER BY so.snapshot_observed_at DESC, so.id DESC");
        $stmt->execute(['sid' => $sid]);
        $included = []; $excluded = []; $sources = []; $excludedRows = [];
        foreach ($stmt->fetchAll() as $r) {
            $source = SourceIdentity::ofRow($r);
            unset($r['source_domain'], $r['source_name'], $r['source_key']); // public-safe rows: the platform is only counted, never listed
            if ($r['inclusion_status'] === 'included') { $included[] = $r; $sources[$source] = true; }
            else {
                $reason = (string)($r['exclusion_reason'] ?: 'other'); $excluded[$reason] = ($excluded[$reason] ?? 0) + 1;
                $r['reason'] = $reason; $excludedRows[] = $r; // LS-61: shown greyed in the listings table (date, price, reason only)
            }
        }
        return ['snapshot_id' => $sid, 'included' => $included, 'excluded' => $excluded, 'excluded_rows' => $excludedRows, 'sources' => count($sources)];
    }
    /** Manufacturer specs with their source (product_reference_specs). Fails soft: a missing table or a database hiccup must never break the product page. */
    /** LS-21: one query for the filter facts of the whole catalog: product_id => [spec_key => spec_value] (read-only; the filters never write) */
    /** LS-24 A5: the latest NEW retail price the owner recorded for a model, or null (also when migration 022 is not applied). Read-only. */
    public function latestNewPrice(int $productId): ?array {
        try {
            $s = $this->db->prepare('SELECT price, source_url, checked_on FROM new_price_references WHERE product_id = :p ORDER BY checked_on DESC, id DESC LIMIT 1'); $s->execute(['p' => $productId]);
            return $s->fetch() ?: null;
        } catch (\PDOException $e) { return null; }
    }
    public function specMap(): array {
        try {
            $rows = $this->db->query("SELECT product_id, spec_key, spec_value FROM product_reference_specs WHERE spec_key IN ('socket','memory_type','form_factor','vram_gb','capacity_gb','speed_mhz','interface','psu_capacity_watt','cooler_type')")->fetchAll();
        } catch (\PDOException $e) { return []; }
        $map = []; foreach ($rows as $r) { $map[(int)$r['product_id']][(string)$r['spec_key']] = (string)$r['spec_value']; }
        return $map;
    }
    /** LS-25: spec values for the given keys across one category (one query), product id => key => value. */
    public function specsFor(array $keys, string $categorySlug): array {
        if ($keys === []) { return []; }
        $in = implode(",", array_fill(0, count($keys), "?"));
        try {
            $st = $this->db->prepare("SELECT s.product_id, s.spec_key, s.spec_value FROM product_reference_specs s JOIN products p ON p.id=s.product_id JOIN product_categories c ON c.id=p.category_id WHERE c.slug=? AND p.is_active=1 AND s.spec_key IN ($in)");
            $st->execute(array_merge([$categorySlug], array_values($keys)));
        } catch (\PDOException $e) { return []; }
        $map = []; foreach ($st->fetchAll() as $r) { $map[(int)$r["product_id"]][(string)$r["spec_key"]] = (string)$r["spec_value"]; }
        return $map;
    }
    public function referenceSpecs(int $productId): array {
        try {
            $stmt = $this->db->prepare("SELECT spec_key, spec_value, unit, source_url, date_checked, review_status FROM product_reference_specs WHERE product_id=:id ORDER BY id");
            $stmt->execute(['id' => $productId]);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            return [];
        }
    }
    public function history(int $id): array {
        $stmt=$this->db->prepare("SELECT * FROM price_histories WHERE product_id=:id ORDER BY snapshot_date");
        $stmt->execute(['id'=>$id]); return $stmt->fetchAll();
    }
}
