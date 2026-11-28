<?php
declare(strict_types=1);
namespace App\Services\Review;
use App\Core\Database;
use App\Services\ProductResolver\ProductResolver;
use App\Services\Provenance\ObservationProvenanceService;
use PDO;
use App\Models\CollectionPipeline;

/** LS-89 BE-4: what the observation and review pages read (queue, filters, one row with its warnings). Read-only. */
final class ReviewQueueQuery {
    private PDO $db;
    public function __construct(?PDO $db = null){ global $config; $this->db=$db ?? Database::connection($config['db']); }
    private ?PriceImpact $impact=null;
    /** LS-90 T2: pending rows that would count towards a price if approved */
    private function impact(): array { return ($this->impact ??= new PriceImpact($this->db))->pending(); }
    private function rows(string $sql,array $params=[]): array { $s=$this->db->prepare($sql);$s->execute($params);return $s->fetchAll(); }
    public function observations(?string $status=null, string $q=''): array {
        $sql="SELECT o.id,o.product_id,o.raw_observation_id,o.price_type,o.asking_price,o.price_value,COALESCE(o.price_value,o.asking_price) display_price,o.currency,o.condition_level,o.listing_type,o.classification_confidence,o.verified_status,o.observed_at,o.created_at,p.full_name,p.image_path product_image_path,c.slug category_slug,r.raw_title,s.source_key,s.name source_name,".$this->datasetSqlExpression()." dataset_label FROM price_observations o JOIN products p ON p.id=o.product_id JOIN product_categories c ON c.id=p.category_id LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id WHERE 1=1";
        $params=[];
        if($status && in_array($status,['pending','approved','rejected','excluded'],true)){ $sql.=" AND o.verified_status=:status"; $params['status']=$status; }
        if($q!==''){
            $sql.=" AND (p.full_name LIKE :q_product OR r.raw_title LIKE :q_title)";
            $params['q_product']='%'.$q.'%';
            $params['q_title']='%'.$q.'%';
        }
        $sql.=" ORDER BY o.id DESC LIMIT 100";
        return $this->rows($sql,$params);
    }
    public function reviewQueue(array $filters): array {
        $perPage=max(1,min(50,(int)($filters['per_page'] ?? 25)));
        $page=max(1,(int)($filters['page'] ?? 1));
        $offset=($page-1)*$perPage;
        $params=[];
        $where=$this->reviewWhere($filters,$params);
        $countStmt=$this->db->prepare("SELECT COUNT(*) FROM price_observations o JOIN products p ON p.id=o.product_id JOIN product_categories c ON c.id=p.category_id LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id LEFT JOIN market_evidence e ON e.raw_observation_id=r.id LEFT JOIN observation_review_decisions d ON d.id=(SELECT id FROM observation_review_decisions WHERE price_observation_id=o.id ORDER BY id DESC LIMIT 1) WHERE $where");
        $countStmt->execute($params);
        $total=(int)$countStmt->fetchColumn();
        $sql="SELECT o.id,o.raw_observation_id,o.product_id,o.price_type,o.asking_price,o.price_value,COALESCE(o.price_value,o.asking_price) display_price,o.currency,o.condition_level,o.warranty_months,o.listing_type,o.classification_confidence,o.quality_flags,o.verified_status,o.observed_at,o.created_at,p.full_name,p.image_path product_image_path,c.slug category_slug,r.raw_title,r.raw_price_text,r.raw_condition_text,r.raw_warranty_text,r.source_url_encrypted,r.external_reference_hash,s.id source_id,s.name source_name,s.source_key,e.evidence_level evidence_quality,e.evidence_type,e.excerpt,e.content_hash,x.confidence extraction_confidence,d.lane,d.reason_codes,d.rule_result,(SELECT COUNT(*) FROM review_corrections rc WHERE rc.price_observation_id=o.id) corrections_count,".$this->datasetSqlExpression()." dataset_label FROM price_observations o JOIN products p ON p.id=o.product_id JOIN product_categories c ON c.id=p.category_id LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id LEFT JOIN market_evidence e ON e.raw_observation_id=r.id LEFT JOIN extraction_runs x ON x.raw_observation_id=r.id LEFT JOIN observation_review_decisions d ON d.id=(SELECT id FROM observation_review_decisions WHERE price_observation_id=o.id ORDER BY id DESC LIMIT 1) WHERE $where ORDER BY ".$this->reviewOrderSql($filters)." LIMIT :limit OFFSET :offset";
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
    public function reviewBatchOptions(): array {
        $sql="SELECT o.id,o.observed_at,e.excerpt FROM price_observations o LEFT JOIN raw_price_observations r ON r.id=o.raw_observation_id LEFT JOIN data_sources s ON s.id=r.source_id LEFT JOIN market_evidence e ON e.raw_observation_id=r.id WHERE o.verified_status='pending' AND ".$this->datasetSqlExpression()."='REAL' AND ".$this->provenanceSqlExpression()."='LISTING_LEVEL' ORDER BY o.id ASC";
        $groups=[];
        foreach($this->rows($sql) as $row){
            $runId=$this->evidenceMetadataValue((string)($row['excerpt'] ?? ''),'PROBE_RUN_ID');
            $key=$runId !== '' ? $runId : '__legacy_priceza_probe_no_run_id';
            if(!isset($groups[$key])){
                $groups[$key]=[
                    'value'=>$key,
                    'run_id'=>$runId,
                    'label'=>$this->reviewBatchLabel($key,$runId,(string)($row['excerpt'] ?? '')),
                    'purpose'=>$this->reviewBatchPurpose($key,$runId),
                    'count'=>0,
                    'min_id'=>(int)$row['id'],
                    'max_id'=>(int)$row['id'],
                ];
            }
            $groups[$key]['count']++;
            $groups[$key]['min_id']=min((int)$groups[$key]['min_id'],(int)$row['id']);
            $groups[$key]['max_id']=max((int)$groups[$key]['max_id'],(int)$row['id']);
        }
        uasort($groups,fn(array $a,array $b): int => ($b['max_id'] <=> $a['max_id']));
        return array_values($groups);
    }
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
        $provenance=(string)($filters['provenance'] ?? '');
        if($provenance !== ''){
            $where[]=$this->provenanceSqlExpression().'=:provenance';
            $params[':provenance']=$provenance;
        }
        $runId=trim((string)($filters['run_id'] ?? ''));
        if($runId !== ''){
            if($runId === '__legacy_priceza_probe_no_run_id'){
                $where[]="(e.excerpt IS NOT NULL AND e.excerpt NOT LIKE '%PROBE_RUN_ID=%' AND ".$this->provenanceSqlExpression()."='LISTING_LEVEL')";
            } else {
                $where[]='e.excerpt LIKE :run_id';
                $params[':run_id']='%PROBE_RUN_ID='.$runId.'%';
            }
        }
        $impact=(string)($filters['impact'] ?? '');
        if($impact==='price' || $impact==='first_price'){
            $ids=$this->impact()[$impact==='price' ? 'eligible' : 'first_price'];
            $where[]=$ids===[] ? '1=0' : 'o.id IN ('.implode(',',array_map('intval',$ids)).')';
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
            $where[]='(o.id=:q_id OR p.full_name LIKE :q_product OR r.raw_title LIKE :q_title)';
            $params[':q_id']=(int)ltrim($q,'#');
            $params[':q_product']='%'.$q.'%';
            $params[':q_title']='%'.$q.'%';
        }
        return implode(' AND ',$where);
    }
    /** the chosen sort; with the price-impact filter, rows that could give a model its first price come first, grouped by model */
    private function reviewOrderSql(array $filters): string {
        $sql=$this->reviewSortSql((string)($filters['sort'] ?? 'attention'));
        if(!in_array((string)($filters['impact'] ?? ''),['price','first_price'],true)) return $sql;
        $first=$this->impact()['first_price'];
        $lead=$first===[] ? '' : 'CASE WHEN o.id IN ('.implode(',',array_map('intval',$first)).') THEN 0 ELSE 1 END ASC,';
        return $lead.'p.full_name ASC,'.$sql;
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
            $row['dataset_label']=DatasetLabel::of((string)($row['source_key'] ?? ''),(string)($row['source_name'] ?? ''));
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
            $row['provenance_quality']=(new ObservationProvenanceService())->classify($row);
            $row['review_run_id']=$this->evidenceMetadataValue((string)($row['excerpt'] ?? ''),'PROBE_RUN_ID');
            $row['review_purpose']=$this->reviewPurpose($row);
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
        if(\App\Services\Pricing\BrandNewClaim::detect((string)($row['raw_title'] ?? ''))!==null) $codes[]='BRAND_NEW_CLAIM';
        if(preg_match('/ขายแล้ว|\bsold\b|จองแล้ว|reserved|ติดจอง/iu',(string)($row['raw_title'] ?? ''))===1) $codes[]='SOLD_OR_RESERVED';
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
        if($code==='BRAND_NEW_CLAIM' || $code==='SOLD_OR_RESERVED') return \App\Core\Lang::t('review.warn.'.$code);
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
    private function evidenceMetadataValue(string $excerpt,string $key): string {
        $needle=strtoupper($key).'=';
        foreach(preg_split('/\R/',$excerpt) ?: [] as $line){
            $line=trim((string)$line);
            if(str_starts_with(strtoupper($line),$needle)) return trim(substr($line,strlen($needle)));
        }
        return '';
    }
    private function reviewPurpose(array $row): string {
        $excerpt=(string)($row['excerpt'] ?? '');
        $runId=(string)($row['review_run_id'] ?? '');
        $quality=(string)($row['provenance_quality']['quality'] ?? '');
        if(str_starts_with($runId,'priceza-batch-') || str_contains($excerpt,'Phase 3H-R1D authoritative REAL batch')) return 'AUTHORITATIVE BATCH';
        if(str_starts_with($runId,'priceza-probe-') || str_contains($excerpt,'Phase 3H-R1C authoritative provenance probe')) return 'PROBE / CALIBRATION';
        if($quality === ObservationProvenanceService::SEARCH_RESULT_LEVEL) return 'OLD SEARCH_RESULT CALIBRATION';
        if($quality === ObservationProvenanceService::LISTING_LEVEL) return 'PROBE / CALIBRATION';
        return 'CALIBRATION / OTHER';
    }
    private function reviewBatchLabel(string $key,string $runId,string $excerpt): string {
        if($key === '__legacy_priceza_probe_no_run_id') return 'Earlier Probe / Calibration';
        if(str_starts_with($runId,'priceza-batch-') || str_contains($excerpt,'Phase 3H-R1D authoritative REAL batch')) return 'Authoritative Batch';
        if(str_starts_with($runId,'priceza-probe-') || str_contains($excerpt,'Phase 3H-R1C authoritative provenance probe')) return 'Probe / Quality Probe';
        return 'Review Batch';
    }
    private function reviewBatchPurpose(string $key,string $runId): string {
        if(str_starts_with($runId,'priceza-batch-')) return 'AUTHORITATIVE BATCH';
        if(str_starts_with($runId,'priceza-probe-') || $key === '__legacy_priceza_probe_no_run_id') return 'PROBE / CALIBRATION';
        return 'CALIBRATION / OTHER';
    }
    private function datasetSqlExpression(): string { return DatasetLabel::sqlExpression(); }
    private function provenanceSqlExpression(): string {
        return "CASE WHEN e.excerpt LIKE '%SOURCE=priceza%' AND e.excerpt LIKE '%SOURCE_SEARCH_URL=%' AND e.excerpt LIKE '%SOURCE_LISTING_URL=%' AND e.excerpt LIKE '%SOURCE_ITEM_ID=%' AND e.excerpt LIKE '%MERCHANT=%' AND e.excerpt LIKE '%LISTING_TITLE=%' AND e.excerpt LIKE '%ASKING_PRICE=%' AND e.excerpt LIKE '%OBSERVED_AT=%' AND e.excerpt LIKE '%EVIDENCE_SNAPSHOT_JSON=%' AND e.excerpt LIKE '%EVIDENCE_HASH=%' THEN 'LISTING_LEVEL' WHEN r.source_url_encrypted LIKE 'https://www.priceza.com/s/%' OR e.excerpt LIKE '%SOURCE_SEARCH_URL=%' OR LOWER(COALESCE(e.excerpt,'')) LIKE '%search result%' THEN 'SEARCH_RESULT_LEVEL' ELSE 'GENERIC_SOURCE' END";
    }
}
