<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Database;
use App\Services\Collection\ProviderRegistry;
use App\Services\Collection\SourceHealthService;
use App\Services\ProductResolver\ProductResolver;
use App\Services\Review\ListingTitleNormalizer;
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
            'real_pending_reviews'=>(int)$this->scalar("SELECT COUNT(*) FROM price_observations o LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id WHERE o.verified_status='pending' AND ".$this->datasetSqlExpression()."='REAL'"),
            'mock_test_pending_reviews'=>(int)$this->scalar("SELECT COUNT(*) FROM price_observations o LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id WHERE o.verified_status='pending' AND ".$this->datasetSqlExpression()."='MOCK_TEST'"),
            'approved_real_observations'=>(int)$this->scalar("SELECT COUNT(*) FROM price_observations o LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id WHERE o.verified_status='approved' AND ".$this->datasetSqlExpression()."='REAL'"),
            'public_eligible_snapshots'=>(int)$this->scalar("SELECT COUNT(*) FROM price_indices WHERE provenance_status='recorded' AND valid_sample_size>0"),
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
    public function observations(?string $status=null, string $q=''): array {
        $sql="SELECT o.id,o.product_id,o.raw_observation_id,o.price_type,o.asking_price,o.price_value,COALESCE(o.price_value,o.asking_price) display_price,o.currency,o.condition_level,o.listing_type,o.classification_confidence,o.verified_status,o.observed_at,o.created_at,p.full_name,p.image_path product_image_path,c.slug category_slug,r.raw_title,s.source_key,s.name source_name,".$this->datasetSqlExpression()." dataset_label FROM price_observations o JOIN products p ON p.id=o.product_id JOIN product_categories c ON c.id=p.category_id LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id WHERE 1=1";
        $params=[];
        if($status && in_array($status,['pending','approved','rejected','excluded'],true)){ $sql.=" AND o.verified_status=:status"; $params['status']=$status; }
        if($q!==''){ $sql.=" AND (p.full_name LIKE :q OR r.raw_title LIKE :q)"; $params['q']='%'.$q.'%'; }
        $sql.=" ORDER BY o.id DESC LIMIT 100";
        return $this->rows($sql,$params);
    }
    public function reviewQueue(array $filters): array {
        $perPage=max(1,min(50,(int)($filters['per_page'] ?? 25)));
        $page=max(1,(int)($filters['page'] ?? 1));
        $offset=($page-1)*$perPage;
        $params=[];
        $where=$this->reviewWhere($filters,$params);
        $countStmt=$this->db->prepare("SELECT COUNT(*) FROM price_observations o JOIN products p ON p.id=o.product_id JOIN product_categories c ON c.id=p.category_id LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id LEFT JOIN observation_review_decisions d ON d.id=(SELECT id FROM observation_review_decisions WHERE price_observation_id=o.id ORDER BY id DESC LIMIT 1) WHERE $where");
        $countStmt->execute($params);
        $total=(int)$countStmt->fetchColumn();
        $sql="SELECT o.id,o.raw_observation_id,o.product_id,o.price_type,o.asking_price,o.price_value,COALESCE(o.price_value,o.asking_price) display_price,o.currency,o.condition_level,o.warranty_months,o.listing_type,o.classification_confidence,o.quality_flags,o.verified_status,o.observed_at,o.created_at,p.full_name,p.image_path product_image_path,c.slug category_slug,r.raw_title,r.raw_price_text,r.raw_condition_text,r.raw_warranty_text,r.source_url_encrypted,r.external_reference_hash,s.id source_id,s.name source_name,s.source_key,e.evidence_level evidence_quality,e.evidence_type,x.confidence extraction_confidence,d.lane,d.reason_codes,d.rule_result,(SELECT COUNT(*) FROM review_corrections rc WHERE rc.price_observation_id=o.id) corrections_count,".$this->datasetSqlExpression()." dataset_label FROM price_observations o JOIN products p ON p.id=o.product_id JOIN product_categories c ON c.id=p.category_id LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id LEFT JOIN market_evidence e ON e.raw_observation_id=r.id LEFT JOIN extraction_runs x ON x.raw_observation_id=r.id LEFT JOIN observation_review_decisions d ON d.id=(SELECT id FROM observation_review_decisions WHERE price_observation_id=o.id ORDER BY id DESC LIMIT 1) WHERE $where ORDER BY ".$this->reviewSortSql((string)($filters['sort'] ?? 'attention'))." LIMIT :limit OFFSET :offset";
        $stmt=$this->db->prepare($sql);
        foreach($params as $key=>$value) $stmt->bindValue($key,$value,is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $stmt->bindValue(':limit',$perPage,PDO::PARAM_INT);
        $stmt->bindValue(':offset',$offset,PDO::PARAM_INT);
        $stmt->execute();
        return [
            'items'=>$this->hydrateReviewRows($stmt->fetchAll(),false),
            'total'=>$total,
            'page'=>$page,
            'per_page'=>$perPage,
            'pages'=>max(1,(int)ceil($total/$perPage)),
            'from'=>$total===0 ? 0 : $offset+1,
            'to'=>min($total,$offset+$perPage),
        ];
    }
    public function reviewObservations(array $filters=[]): array {
        $filters=$filters ?: ['dataset'=>'ALL','status'=>'pending','lane'=>'','sort'=>'attention','page'=>1,'per_page'=>100,'q'=>'','product_id'=>null,'source_id'=>null];
        return $this->reviewQueue($filters)['items'];
    }
    public function reviewObservationDetail(int $id): ?array {
        $stmt=$this->db->prepare("SELECT o.id,o.raw_observation_id,o.product_id,o.product_variant_id,o.price_type,o.asking_price,o.price_value,COALESCE(o.price_value,o.asking_price) display_price,o.currency,o.condition_level,o.warranty_months,o.has_box,o.has_receipt,o.has_benchmark,o.listing_type,o.is_deposit,o.is_defective,o.is_duplicate,o.evidence_level,o.evidence_weight,o.freshness_weight,o.source_weight,o.classification_confidence,o.final_weight,o.quality_flags,o.verified_status,o.observed_at,o.created_at,o.updated_at,p.full_name,p.image_path product_image_path,c.slug category_slug,r.raw_title,r.raw_price_text,r.raw_condition_text,r.raw_warranty_text,r.source_url_encrypted,r.external_reference_hash,r.evidence_path,r.captured_at,s.id source_id,s.name source_name,s.source_key,s.domain source_domain,e.id evidence_id,e.evidence_level evidence_quality,e.evidence_type,e.storage_path,e.excerpt,e.content_hash,e.captured_at evidence_captured_at,x.id extraction_run_id,x.provider_name,x.model_name,x.prompt_version,x.schema_version,x.confidence extraction_confidence,x.extracted_data,d.id review_decision_id,d.lane,d.decision,d.reason_codes,d.ai_value,d.rule_result,d.human_value,d.notes,d.created_at review_created_at,".$this->datasetSqlExpression()." dataset_label FROM price_observations o JOIN products p ON p.id=o.product_id JOIN product_categories c ON c.id=p.category_id LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id LEFT JOIN market_evidence e ON e.raw_observation_id=r.id LEFT JOIN extraction_runs x ON x.raw_observation_id=r.id LEFT JOIN observation_review_decisions d ON d.id=(SELECT id FROM observation_review_decisions WHERE price_observation_id=o.id ORDER BY id DESC LIMIT 1) WHERE o.id=:id LIMIT 1");
        $stmt->execute(['id'=>$id]);
        $row=$stmt->fetch();
        if(!$row) return null;
        return $this->hydrateReviewRows([$row],true)[0];
    }
    public function reviewQueueSummary(): array {
        $summary=[
            'by_dataset'=>[],
            'status_totals'=>['pending'=>0,'approved'=>0,'rejected'=>0,'excluded'=>0],
        ];
        $sql="SELECT ".$this->datasetSqlExpression()." dataset_label,o.verified_status,COUNT(*) c FROM price_observations o LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id GROUP BY dataset_label,o.verified_status";
        foreach($this->db->query($sql) as $row){
            $dataset=(string)$row['dataset_label'];
            $status=(string)$row['verified_status'];
            $summary['by_dataset'][$dataset][$status]=(int)$row['c'];
            if(isset($summary['status_totals'][$status])) $summary['status_totals'][$status]+=(int)$row['c'];
        }
        return $summary;
    }
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
    private function reviewWhere(array $filters,array &$params): string {
        $where=['1=1'];
        $dataset=(string)($filters['dataset'] ?? 'REAL');
        if($dataset !== 'ALL'){
            $where[]=$this->datasetSqlExpression().'=:dataset';
            $params[':dataset']=$dataset;
        }
        $status=(string)($filters['status'] ?? 'pending');
        if($status !== 'all'){
            $where[]='o.verified_status=:status';
            $params[':status']=$status;
        }
        $lane=(string)($filters['lane'] ?? '');
        if($lane !== ''){
            $where[]='d.lane=:lane';
            $params[':lane']=$lane;
        }
        if(!empty($filters['product_id'])){
            $where[]='o.product_id=:product_id';
            $params[':product_id']=(int)$filters['product_id'];
        }
        if(!empty($filters['source_id'])){
            $where[]='s.id=:source_id';
            $params[':source_id']=(int)$filters['source_id'];
        }
        $q=trim((string)($filters['q'] ?? ''));
        if($q !== ''){
            $where[]='(o.id=:q_id OR p.full_name LIKE :q_like OR r.raw_title LIKE :q_like)';
            $params[':q_id']=(int)ltrim($q,'#');
            $params[':q_like']='%'.$q.'%';
        }
        return implode(' AND ',$where);
    }
    private function reviewSortSql(string $sort): string {
        return match($sort){
            'newest'=>'o.observed_at DESC,o.id DESC',
            'oldest'=>'o.observed_at ASC,o.id ASC',
            'price_asc'=>'COALESCE(o.price_value,o.asking_price) ASC,o.id DESC',
            'price_desc'=>'COALESCE(o.price_value,o.asking_price) DESC,o.id DESC',
            default=>"CASE d.lane WHEN 'amber' THEN 0 WHEN 'green' THEN 1 WHEN 'red' THEN 2 ELSE 3 END ASC,o.observed_at DESC,o.id DESC",
        };
    }
    private function hydrateReviewRows(array $rows,bool $withCorrections): array {
        $corrections=$withCorrections ? new ReviewCorrectionService() : null;
        $normalizer=new ListingTitleNormalizer();
        foreach($rows as &$row){
            $row['dataset_label']=$this->datasetLabel((string)($row['source_key'] ?? ''),(string)($row['source_name'] ?? ''));
            $row['reason_codes_list']=$this->decodeJsonList($row['reason_codes'] ?? null);
            $storedFlags=$this->decodeJsonList($row['quality_flags'] ?? null);
            $row['quality_flags']=$storedFlags;
            $row['rule_result_data']=$this->decodeJsonMap($row['rule_result'] ?? null);
            $row['extracted_data_map']=$this->decodeJsonMap($row['extracted_data'] ?? null);
            $rawTitle=trim((string)($row['raw_title'] ?? ''));
            $displayTitle=$normalizer->normalize($rawTitle);
            $row['display_title']=$displayTitle !== '' ? $displayTitle : $rawTitle;
            $row['title_was_normalized']=$row['display_title'] !== $rawTitle;
            $extractedTitle=(string)($row['extracted_data_map']['raw']['title'] ?? $row['extracted_data_map']['title'] ?? '');
            $extractedDisplayTitle=$normalizer->normalize($extractedTitle !== '' ? $extractedTitle : $rawTitle);
            $row['extracted_display_title']=$extractedDisplayTitle !== '' ? $extractedDisplayTitle : ($extractedTitle !== '' ? $extractedTitle : $rawTitle);
            if($withCorrections) $row['resolution_diagnostic']=$this->reviewResolutionDiagnostic($row);
            $row['warning_items']=$this->warningItems($row);
            if($withCorrections && $corrections){
                $row['corrections']=$corrections->correctionsFor((int)$row['id']);
                $row['final_values']=$corrections->finalValues($row);
                $row['quality_flags']=array_values(array_unique(array_merge($storedFlags,$corrections->qualityFlags($row,$row['final_values']))));
                $row['warning_items']=$this->warningItems($row);
            }
        }
        return $rows;
    }
    private function warningItems(array $row): array {
        $codes=array_values(array_unique(array_merge($row['reason_codes_list'] ?? [],$row['quality_flags'] ?? [])));
        $items=[];
        foreach($codes as $code){
            $code=(string)$code;
            if($code==='') continue;
            if(str_starts_with($code,'validation_version:') || str_starts_with($code,'category_validation_version:') || $code === 'PASSES_RULES') continue;
            $items[]=['code'=>$code,'label'=>$this->warningLabel($code,$row)];
        }
        return $items;
    }
    private function decodeJsonList(mixed $value): array {
        if($value === null || $value === '') return [];
        $decoded=json_decode((string)$value,true);
        if(!is_array($decoded)) return [];
        if(array_is_list($decoded)) return array_values(array_filter(array_map('strval',$decoded),fn($item)=>$item !== ''));
        return array_keys($decoded);
    }
    private function decodeJsonMap(mixed $value): array {
        if($value === null || $value === '') return [];
        $decoded=json_decode((string)$value,true);
        return is_array($decoded) ? $decoded : [];
    }
    private function warningLabel(string $code,array $row=[]): string {
        if($code === 'LOW_CONFIDENCE'){
            $resolution=$row['resolution_diagnostic'] ?? null;
            if(is_array($resolution) && isset($resolution['confidence']) && (float)$resolution['confidence'] < 0.95){
                return 'Original imported title product-resolution confidence is below the Green threshold; verify the resolved Product Master before approval.';
            }
            return 'A validation confidence sub-check did not meet the Green threshold; verify the resolved product and listing evidence before approval.';
        }
        $labels=[
            'BOARD_PARTNER_CONTEXT'=>'Board partner wording is present; confirm the title still matches the resolved GPU model.',
            'VARIANT_AMBIGUOUS'=>'Variant or suffix may be ambiguous; confirm the exact Product Master match.',
            'BUNDLE'=>'Listing may include a bundle; verify the price is for the target item only.',
            'WHOLE_PC'=>'Listing may be for a whole PC; exclude unless the price is clearly for the component.',
            'WANTED'=>'Wanted/buying post; reject or exclude if it is not a seller listing.',
            'DEPOSIT'=>'Price may be a deposit; verify before approval.',
            'DEFECTIVE'=>'Listing suggests a defect; verify condition before approval.',
            'DUPLICATE'=>'Potential duplicate lineage; check source reference before approval.',
            'STALE'=>'Observation is old; confirm it is still useful for calibration.',
            'MISSING_PRICE'=>'No usable price was extracted.',
            'MISSING_URL'=>'Source URL is missing.',
            'WEAK_EVIDENCE'=>'Evidence is weak; inspect the source before approval.',
            'MOCK_SOURCE'=>'Fixture/mock source; do not mix with REAL calibration decisions.',
        ];
        return $labels[$code] ?? ucwords(strtolower(str_replace('_',' ',$code)));
    }
    private function reviewResolutionDiagnostic(array $row): ?array {
        $title=trim((string)($row['raw_title'] ?? ''));
        if($title === '') return null;
        $resolution=(new ProductResolver((new CollectionPipeline())->catalog()))->resolve($title);
        return [
            'product_id'=>$resolution->productId,
            'matched_name'=>$resolution->matchedName,
            'confidence'=>$resolution->confidence,
            'method'=>$resolution->method,
        ];
    }
    private function datasetSqlExpression(): string {
        return "CASE WHEN LOWER(COALESCE(s.source_key,'')) LIKE 'offline_real_%' OR LOWER(COALESCE(s.name,'')) LIKE '%offline real import%' THEN 'REAL' WHEN LOWER(COALESCE(s.source_key,'')) LIKE '%mock%' OR LOWER(COALESCE(s.source_key,'')) LIKE '%fixture%' OR LOWER(COALESCE(s.source_key,'')) LIKE '%test%' OR LOWER(COALESCE(s.name,'')) LIKE '%mock%' OR LOWER(COALESCE(s.name,'')) LIKE '%fixture%' OR LOWER(COALESCE(s.name,'')) LIKE '%test%' THEN 'MOCK_TEST' ELSE 'UNKNOWN' END";
    }
    private function datasetLabel(string $sourceKey,string $sourceName): string {
        $key=strtolower($sourceKey);
        $name=strtolower($sourceName);
        if(str_starts_with($key,'offline_real_') || str_contains($name,'offline real import')) return 'REAL';
        foreach(['mock','fixture','test'] as $marker) {
            if(str_contains($key,$marker) || str_contains($name,$marker)) return 'MOCK_TEST';
        }
        return 'UNKNOWN';
    }
}
