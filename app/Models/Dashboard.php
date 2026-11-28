<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Database;
use App\Services\Collection\ProviderRegistry;
use App\Services\Collection\SourceHealthService;
use App\Services\Pricing\PublicSnapshot;
use App\Services\Review\DatasetLabel;
use App\Services\Review\ReviewCorrectionService;
use App\Services\Review\ReviewDecisionService;
use PDO;
use App\Services\Review\ReviewQueueQuery;

/** Numbers and lists for the admin pages; review reading is in Services/Review, review decisions in ReviewDecisionService. */
final class Dashboard {
    private PDO $db;
    public function __construct(){ global $config; $this->db=Database::connection($config['db']); }
    public function scalar(string $sql): int|float { return (float)$this->db->query($sql)->fetchColumn(); }
    public function rows(string $sql,array $params=[]): array { $s=$this->db->prepare($sql);$s->execute($params);return $s->fetchAll(); }
    public function adminStats(): array {
        $health=(new SourceHealthService())->report();
        $healthSummary=['healthy'=>0,'degraded'=>0,'paused'=>0,'unknown'=>0];
        foreach($health['items'] as $item) $healthSummary[$item['status']]=($healthSummary[$item['status']]??0)+1;
        return [
            'products'=>(int)$this->scalar("SELECT COUNT(*) FROM products"),
            'cpu_products'=>(int)$this->scalar("SELECT COUNT(*) FROM products p JOIN product_categories c ON c.id=p.category_id WHERE c.slug='cpu'"),
            'gpu_products'=>(int)$this->scalar("SELECT COUNT(*) FROM products p JOIN product_categories c ON c.id=p.category_id WHERE c.slug='gpu'"),
            'observations'=>(int)$this->scalar("SELECT COUNT(*) FROM price_observations"),
            'accepted_observations'=>(int)$this->scalar("SELECT COUNT(*) FROM price_observations WHERE verified_status='approved'"),
            'pending'=>(int)$this->scalar("SELECT COUNT(*) FROM price_observations WHERE verified_status='pending'"),
            'real_pending_reviews'=>(int)$this->scalar("SELECT COUNT(*) FROM price_observations o LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id WHERE o.verified_status='pending' AND ".DatasetLabel::sqlExpression()."='REAL'"),
            'mock_test_pending_reviews'=>(int)$this->scalar("SELECT COUNT(*) FROM price_observations o LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id WHERE o.verified_status='pending' AND ".DatasetLabel::sqlExpression()."='MOCK_TEST'"),
            'approved_real_observations'=>(int)$this->scalar("SELECT COUNT(*) FROM price_observations o LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id WHERE o.verified_status='approved' AND ".DatasetLabel::sqlExpression()."='REAL'"),
            'public_eligible_snapshots'=>(int)$this->scalar("SELECT COUNT(*) FROM products p WHERE p.is_active=1 AND ".PublicSnapshot::idSql('p.id')." IS NOT NULL"),
            'sources'=>(int)$this->scalar("SELECT COUNT(*) FROM data_sources"),
            'paused_sources'=>(int)$this->scalar("SELECT COUNT(*) FROM data_sources WHERE is_paused=1 OR is_active=0"),
            'unresolved_incidents'=>(int)$this->scalar("SELECT COUNT(*) FROM source_incidents WHERE resolved_at IS NULL"),
            'jobs_queued'=>(int)$this->scalar("SELECT COUNT(*) FROM collector_jobs WHERE status='queued'"),
            'jobs_running'=>(int)$this->scalar("SELECT COUNT(*) FROM collector_jobs WHERE status='running'"),
            'jobs_completed'=>(int)$this->scalar("SELECT COUNT(*) FROM collector_jobs WHERE status='completed'"),
            'jobs_failed'=>(int)$this->scalar("SELECT COUNT(*) FROM collector_jobs WHERE status='failed'"),
            'collection_runs'=>(int)$this->scalar("SELECT COUNT(*) FROM collection_runs"),
            'candidates'=>(int)$this->scalar("SELECT COUNT(*) FROM raw_price_observations"),
            'evidence'=>(int)$this->scalar("SELECT COUNT(*) FROM market_evidence"),
            'extractions'=>(int)$this->scalar("SELECT COUNT(*) FROM extraction_runs"),
            'reviews'=>(int)$this->scalar("SELECT COUNT(*) FROM observation_review_decisions"),
            'latest_snapshots'=>(int)$this->scalar("SELECT COUNT(*) FROM price_indices"),
            'source_health_summary'=>$healthSummary,
            'provider_status'=>$this->providerStatus(),
        ];
    }
    public function products(): array {
        $rows=$this->rows("SELECT p.id,p.full_name,p.slug,p.model_name,p.generation,p.spec_summary,p.image_path,p.is_active,b.name brand,c.name category,c.slug category_slug,pi.id snapshot_id,pi.price_type,pi.q1,pi.median,pi.q3,pi.valid_sample_size,pi.fresh_sample_ratio,pi.confidence_score,pi.confidence_label,pi.confidence_method_version,pi.formula_version,pi.cohort_version,pi.calculation_hash,pi.calculation_manifest,pi.provenance_status,pi.last_calculated_at,(SELECT COUNT(*) FROM price_snapshot_observations so WHERE so.snapshot_id=pi.id AND so.inclusion_status='included') provenance_included_count,(SELECT COUNT(*) FROM price_snapshot_observations so WHERE so.snapshot_id=pi.id AND so.inclusion_status='excluded') provenance_excluded_count,(SELECT COUNT(*) FROM price_observations po WHERE po.product_id=p.id AND po.verified_status='pending') pending_observation_count,(SELECT COUNT(*) FROM price_observations po WHERE po.product_id=p.id AND po.verified_status='approved') approved_observation_count FROM products p JOIN brands b ON b.id=p.brand_id JOIN product_categories c ON c.id=p.category_id LEFT JOIN price_indices pi ON pi.id=(SELECT id FROM price_indices WHERE product_id=p.id ORDER BY last_calculated_at DESC LIMIT 1) ORDER BY p.id DESC");
        foreach($rows as &$row){
            $manifest=json_decode((string)($row['calculation_manifest'] ?? ''),true);
            $row['confidence_breakdown']=is_array($manifest) ? ($manifest['confidence'] ?? null) : null;
        }
        return $rows;
    }
    public function aliases(): array { return $this->rows("SELECT pa.*,p.full_name FROM product_aliases pa JOIN products p ON p.id=pa.product_id ORDER BY pa.id DESC"); }
    public function sourceHealth(): array { return (new SourceHealthService())->report()['items']; }
    public function sources(): array {
        $health=[]; foreach($this->sourceHealth() as $item) $health[(int)$item['source_id']]=$item;
        $sources=$this->rows("SELECT * FROM data_sources ORDER BY is_active DESC,is_paused,name");
        foreach($sources as &$source) $source['health']=$health[(int)$source['id']]??null;
        return $sources;
    }
    public function jobs(): array { return $this->rows("SELECT j.*,s.name source_name,s.source_key,p.full_name FROM collector_jobs j JOIN data_sources s ON s.id=j.source_id LEFT JOIN products p ON p.id=j.product_id ORDER BY j.id DESC LIMIT 100"); }
    public function recentImportRuns(): array { return $this->rows("SELECT cr.id,cr.run_id,cr.provider,cr.job_type,cr.execution_mode,cr.candidates_created,cr.evidence_created,cr.extractions_created,cr.reviews_created,cr.green_count,cr.amber_count,cr.red_count,cr.error_count,cr.started_at,cr.finished_at,s.name source_name,s.source_key FROM collection_runs cr JOIN data_sources s ON s.id=cr.source_id ORDER BY cr.started_at DESC LIMIT 8"); }
    public function observations(?string $status=null, string $q=''): array { return $this->queue()->observations($status,$q); }
    public function reviewQueue(array $filters): array { return $this->queue()->reviewQueue($filters); }
    public function reviewObservations(array $filters=[]): array { return $this->queue()->reviewObservations($filters); }
    public function reviewObservationDetail(int $id): ?array { return $this->queue()->reviewObservationDetail($id); }
    public function reviewQueueSummary(): array { return $this->queue()->reviewQueueSummary(); }
    public function reviewBatchOptions(): array { return $this->queue()->reviewBatchOptions(); }
    public function reviewSummary(): array { return (new ReviewCorrectionService())->summary(); }
    public function reviewProductOptions(): array { return (new ReviewCorrectionService())->productOptions(); }
    public function reviewSourceOptions(): array { return $this->rows("SELECT id,name,source_key FROM data_sources ORDER BY name,source_key"); }
    public function correctObservation(int $id,string $field,mixed $value,string $reason,?int $actorId=null): array { return (new ReviewCorrectionService())->correct($id,$field,$value,$reason,$actorId); }
    public function audits(): array { return $this->rows("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 100"); }
    public function providerStatus(): array {
        global $config;
        $collection=$config['collection'];
        $registry=new ProviderRegistry($collection);
        $preflight=$registry->preflight('gemini',1,1);
        $gemini=$collection['providers']['gemini'];
        return [
            'adapter_available'=>$preflight['adapter_loaded']??false,
            'provider_enabled'=>(bool)$gemini['enabled'],
            'api_key_configured'=>$gemini['api_key']!=='',
            'live_collection_enabled'=>(bool)$collection['live_collection_enabled'],
            'paid_provider_calls_enabled'=>(bool)$collection['paid_provider_calls_enabled'],
            'model'=>$gemini['model'],
            'request_limit'=>(int)$gemini['max_requests_per_run'],
            'live_execution_allowed'=>(bool)($preflight['live_execution_allowed']??false),
            'blockers'=>$preflight['blockers']??[],
        ];
    }
    public function decideObservation(int $id,string $decision,string $notes='',?int $actorId=null): bool {
        return (new ReviewDecisionService($this->db))->decide($id,$decision,$notes,$actorId)['ok'];
    }
    public function inventory(): array { return $this->rows("SELECT i.*,p.full_name,pi.market_min,pi.market_max,DATEDIFF(CURDATE(),i.acquired_at) age_days FROM shop_inventory_items i LEFT JOIN products p ON p.id=i.product_id LEFT JOIN price_indices pi ON pi.id=".PublicSnapshot::idSql('p.id')." WHERE i.shop_id=1 ORDER BY age_days DESC"); }
    private function queue(): ReviewQueueQuery { return new ReviewQueueQuery($this->db); }
}
