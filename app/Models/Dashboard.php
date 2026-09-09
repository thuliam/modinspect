<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Database;
use App\Services\Collection\ProviderRegistry;
use App\Services\Collection\SourceHealthService;
use App\Services\Review\ReviewCorrectionService;
use PDO;

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
        $rows=$this->rows("SELECT p.id,p.full_name,p.slug,b.name brand,c.name category,p.is_active,pi.id snapshot_id,pi.q1,pi.median,pi.q3,pi.valid_sample_size,pi.fresh_sample_ratio,pi.confidence_score,pi.confidence_label,pi.confidence_method_version,pi.formula_version,pi.cohort_version,pi.calculation_hash,pi.calculation_manifest,pi.provenance_status,(SELECT COUNT(*) FROM price_snapshot_observations so WHERE so.snapshot_id=pi.id AND so.inclusion_status='included') provenance_included_count,(SELECT COUNT(*) FROM price_snapshot_observations so WHERE so.snapshot_id=pi.id AND so.inclusion_status='excluded') provenance_excluded_count FROM products p JOIN brands b ON b.id=p.brand_id JOIN product_categories c ON c.id=p.category_id LEFT JOIN price_indices pi ON pi.id=(SELECT id FROM price_indices WHERE product_id=p.id ORDER BY last_calculated_at DESC LIMIT 1) ORDER BY p.id DESC");
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
    public function observations(?string $status=null, string $q=''): array {
        $sql="SELECT o.*,p.full_name,r.raw_title,s.source_key,s.name source_name FROM price_observations o JOIN products p ON p.id=o.product_id LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id WHERE 1=1";
        $params=[];
        if($status && in_array($status,['pending','approved','rejected','excluded'],true)){ $sql.=" AND o.verified_status=:status"; $params['status']=$status; }
        if($q!==''){ $sql.=" AND (p.full_name LIKE :q OR r.raw_title LIKE :q)"; $params['q']='%'.$q.'%'; }
        $sql.=" ORDER BY o.id DESC LIMIT 100";
        return $this->rows($sql,$params);
    }
    public function reviewObservations(): array {
        $rows=$this->rows("SELECT o.*,COALESCE(o.price_value,o.asking_price) display_price,p.full_name,r.raw_title,r.source_url_encrypted,s.name source_name,s.source_key,e.evidence_level evidence_quality,e.evidence_type,x.confidence extraction_confidence,x.extracted_data,d.lane,d.reason_codes,d.rule_result FROM price_observations o JOIN products p ON p.id=o.product_id LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id LEFT JOIN market_evidence e ON e.raw_observation_id=r.id LEFT JOIN extraction_runs x ON x.raw_observation_id=r.id LEFT JOIN observation_review_decisions d ON d.id=(SELECT id FROM observation_review_decisions WHERE price_observation_id=o.id ORDER BY id DESC LIMIT 1) WHERE o.verified_status='pending' ORDER BY o.id DESC LIMIT 100");
        $corrections=new ReviewCorrectionService();
        foreach($rows as &$row){
            $row['corrections']=$corrections->correctionsFor((int)$row['id']);
            $row['final_values']=$corrections->finalValues($row);
            $row['quality_flags']=$corrections->qualityFlags($row,$row['final_values']);
        }
        return $rows;
    }
    public function reviewSummary(): array { return (new ReviewCorrectionService())->summary(); }
    public function reviewProductOptions(): array { return (new ReviewCorrectionService())->productOptions(); }
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
        $allowed=['approved'=>'approved','rejected'=>'rejected','excluded'=>'excluded'];
        if(!isset($allowed[$decision])) return false;
        $current=$this->rows("SELECT * FROM price_observations WHERE id=:id LIMIT 1",['id'=>$id])[0]??null;
        if(!$current) return false;
        if(($current['verified_status'] ?? '') !== 'pending') return false;
        $reviewService=new ReviewCorrectionService();
        $finalApply=['ok'=>true,'values'=>[],'quality_flags'=>[]];
        if($decision==='approved'){
            $finalApply=$reviewService->applyApprovedValues($id);
            if(($finalApply['ok'] ?? false) !== true) return false;
            $current=$this->rows("SELECT * FROM price_observations WHERE id=:id LIMIT 1",['id'=>$id])[0]??$current;
        }
        $stmt=$this->db->prepare("UPDATE price_observations SET verified_status=:status,updated_at=NOW() WHERE id=:id AND verified_status='pending'");
        $stmt->execute(['status'=>$allowed[$decision],'id'=>$id]);
        if($stmt->rowCount() !== 1) return false;
        $review=$this->db->prepare("INSERT INTO observation_review_decisions (price_observation_id,raw_observation_id,lane,decision,reason_codes,human_value,notes) VALUES (:observation_id,:raw_id,:lane,:decision,:reasons,:human_value,:notes)");
        $review->execute(['observation_id'=>$id,'raw_id'=>$current['raw_observation_id'],'lane'=>$decision==='approved'?'green':'red','decision'=>$decision==='approved'?'approved':'rejected','reasons'=>json_encode(['human_'.$decision]),'human_value'=>json_encode(['verified_status'=>$allowed[$decision],'final_values'=>$finalApply['values']??[],'quality_flags'=>$finalApply['quality_flags']??[]],JSON_UNESCAPED_UNICODE),'notes'=>$notes]);
        $reviewId=(int)$this->db->lastInsertId();
        $this->db->prepare("UPDATE review_corrections SET review_decision_id=:review_id WHERE price_observation_id=:id AND review_decision_id IS NULL")->execute(['review_id'=>$reviewId,'id'=>$id]);
        $audit=$this->db->prepare("INSERT INTO audit_logs (user_id,action,entity_type,entity_id,before_data,after_data) VALUES (:user_id,:action,'price_observation',:id,:before_data,:after_data)");
        $audit->execute(['user_id'=>$actorId,'action'=>'review_'.$decision,'id'=>$id,'before_data'=>json_encode($current,JSON_UNESCAPED_UNICODE),'after_data'=>json_encode(['verified_status'=>$allowed[$decision],'notes'=>$notes,'final_values'=>$finalApply['values']??[],'quality_flags'=>$finalApply['quality_flags']??[]],JSON_UNESCAPED_UNICODE)]);
        return true;
    }
    public function inventory(): array { return $this->rows("SELECT i.*,p.full_name,pi.market_min,pi.market_max,DATEDIFF(CURDATE(),i.acquired_at) age_days FROM shop_inventory_items i LEFT JOIN products p ON p.id=i.product_id LEFT JOIN price_indices pi ON pi.id=(SELECT id FROM price_indices WHERE product_id=p.id ORDER BY last_calculated_at DESC LIMIT 1) WHERE i.shop_id=1 ORDER BY age_days DESC"); }
}
