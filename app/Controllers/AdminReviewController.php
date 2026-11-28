<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Csrf;
use App\Models\Dashboard;
use App\Services\Admin\AdminDataTableService;
use App\Services\Auth\AuthorizationService;
use App\Services\Pricing\PriceRefresh;
use App\Services\Review\ReviewDecisionService;
use App\Services\Review\ReviewCalibrationService;

/** LS-89 BE-4: price observations, the review queue, review decisions and unresolved listings. */
final class AdminReviewController extends AdminBaseController {
    public function observations(): void { if(!$this->requirePermission('review.view')) return; $d=new Dashboard();$this->render('observations','Price Observations',['observations'=>$d->observations($_GET['status']??null,trim((string)($_GET['q']??''))),'products'=>$d->reviewProductOptions(),'sources'=>$d->reviewSourceOptions(),'batch_options'=>$d->reviewBatchOptions(),'status'=>$_GET['status']??'','q'=>trim((string)($_GET['q']??''))]); }
    public function review(): void {
        if(!$this->requirePermission('review.view')) return;
        $d=new Dashboard();
        $filters=$this->reviewFilters();
        $this->render('review','Observation Review',[
            'queue'=>$d->reviewQueue($filters),
            'summary'=>$d->reviewQueueSummary(),
            'products'=>$d->reviewProductOptions(),
            'sources'=>$d->reviewSourceOptions(),
            'batch_options'=>$d->reviewBatchOptions(),
            'filters'=>$filters,
        ]);
    }
    public function reviewDetail(string $id): void {
        if(!$this->requirePermission('review.view')) return;
        $observationId=filter_var($id,FILTER_VALIDATE_INT);
        if(!$observationId){
            http_response_code(404);
            $this->render('placeholder','Review record not found',['description'=>'Observation ID is invalid.']);
            return;
        }
        $d=new Dashboard();
        $observation=$d->reviewObservationDetail((int)$observationId);
        if(!$observation){
            http_response_code(404);
            $this->render('placeholder','Review record not found',['description'=>'Observation not found.']);
            return;
        }
        $backPath=$this->safeReviewReturnPath((string)($_GET['return_to'] ?? ''),'/admin/review-queue');
        $this->render('review-detail','Review Observation #'.(int)$observation['id'],[
            'observation'=>$observation,
            'products'=>$d->reviewProductOptions(),
            'back_path'=>$backPath,
        ]);
    }
    public function reviewAnalytics(): void { if(!$this->requirePermission('review.analytics.view')) return; $filters=['category'=>$_GET['category']??null,'source_id'=>$_GET['source_id']??null,'provider'=>$_GET['provider']??null,'from'=>$_GET['from']??null,'to'=>$_GET['to']??null,'lane'=>$_GET['lane']??null,'outcome'=>$_GET['outcome']??null]; $this->render('review-analytics','Review Analytics',['report'=>(new ReviewCalibrationService())->report($filters)]); }
    public function unresolved(): void {
        if(!$this->requirePermission('products.view')) return;
        // LS-20 C4: read the value once; the old form read $_GET['status'] again after testing its default, so a missing parameter gave '' and a PHP warning instead of 'open'
        $status=(string)($_GET['status'] ?? 'open'); if(!in_array($status,['open','stale','decided','all'],true)){ $status='open'; }
        $page=max(1,(int)($_GET['page']??1)); $per=25;
        $svc=new \App\Services\Admin\UnresolvedListingQueue();
        $this->render('unresolved','Unresolved listings',['rows'=>$svc->page($per,($page-1)*$per,$status),'total'=>$svc->count($status),'status'=>$status,'page'=>$page,'per'=>$per,
            'actionsAvailable'=>$svc->actionsAvailable(),'canManage'=>(new AuthorizationService())->can($this->user(),'products.manage')]);
    }
    public function unresolvedAction(): void {
        if(!$this->requirePermission('products.manage')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/unresolved-listings'); }
        $svc=new \App\Services\Admin\UnresolvedListingQueue();
        $rawId=(int)(filter_input(INPUT_POST,'raw_id',FILTER_VALIDATE_INT) ?: 0);
        $actor=(int)$this->user()['id'];
        $note=trim((string)($_POST['note'] ?? '')) ?: null;
        $messages=['MIGRATION_019_NOT_APPLIED'=>'The decision table is not installed yet (migration 019).','ALIAS_TOO_SHORT'=>'The alias is too short.','ALIAS_ONLY_DIGITS'=>'A number alone is not an alias.','ALIAS_HAS_NO_MODEL_NUMBER'=>'The alias must contain a model number.','ALIAS_SUFFIX_DIFFERS_FROM_PRODUCT'=>'The alias and the product must agree on Ti / Super / XT: a Super alias cannot point at a plain model, and a plain alias cannot point at a Ti.','LISTING_NOT_IN_QUEUE'=>'That listing is not in the queue.','PRODUCT_NOT_FOUND'=>'Choose a product.','ALIAS_DOES_NOT_RESOLVE_THIS_LISTING'=>'With this alias the listing would still not resolve to the chosen product.','ALREADY_DECIDED'=>'This listing already has a decision.','ALIAS_OR_DECISION_ALREADY_EXISTS'=>'That alias or decision already exists.'];
        if(!$rawId) { $this->flash('danger','Choose a listing.'); $this->redirect('/admin/unresolved-listings'); }
        if(($_POST['action']??'')==='dismiss') {
            $r=$svc->dismiss($rawId,$actor,$note);
            $this->flash($r['ok']?'success':'danger',$r['ok']?'Listing dismissed.':($messages[$r['error']]??'Could not dismiss.'));
        } elseif(($_POST['action']??'')==='alias') {
            $r=$svc->addAlias($rawId,(int)(filter_input(INPUT_POST,'product_id',FILTER_VALIDATE_INT) ?: 0),trim((string)($_POST['alias_text']??'')),$actor,$note);
            $err=(string)($r['error']??'');
            $this->flash($r['ok']?'success':'danger',$r['ok']?'Alias added. It applies to future imports; no price was changed.':(str_starts_with($err,'ALIAS_WOULD_CHANGE_OTHER_LISTINGS')?t('msg.alias_would_change',['n'=>substr($err,strrpos($err,':')+1)]):($messages[$err]??'Could not save the alias.')));
        } else { $this->flash('danger','Unknown action.'); }
        $this->redirect('/admin/unresolved-listings');
    }
    public function reviewCorrection(): void {
        if(!$this->requirePermission('review.correct')) return;
        if (!Csrf::verify($_POST['_token']??null)) { http_response_code(419); $this->view('errors/419',['title'=>'แบบฟอร์มหมดอายุ']); return; }
        $id=$this->postId('observation_id');
        $field=(string)($_POST['field_name']??'');
        $value=$_POST['corrected_value']??null;
        $reason=trim((string)($_POST['reason']??''));
        if($id){
            $r=(new Dashboard())->correctObservation((int)$id,$field,$value,$reason,(int)$this->user()['id']);
            if(($r['ok'] ?? false)===true) $this->flash('success',t('review.done.corrected'));
            else $this->flash('danger',t('review.err.'.($r['errors'][0] ?? 'UNSUPPORTED_FIELD')));
        }
        $this->redirect($this->safeReviewReturnPath((string)($_POST['return_to'] ?? ''),$id ? '/admin/review/'.(int)$id : '/admin/review-queue'));
    }
    public function reviewDecision(): void {
        if(!$this->requirePermission('review.decide')) return;
        if (!Csrf::verify($_POST['_token']??null)) { http_response_code(419); $this->view('errors/419',['title'=>'แบบฟอร์มหมดอายุ']); return; }
        $id=$this->postId('observation_id');
        $decision=(string)($_POST['decision']??'');
        if($id){
            $result=(new ReviewDecisionService())->decide((int)$id,$decision,trim((string)($_POST['notes']??'')),(int)$this->user()['id']);
            // only an approval changes what a price is calculated from; rejecting a pending row leaves every price as it was
            $this->reviewOutcome($result,'review.done.'.$decision,$decision==='approved');
        }
        $this->redirect($this->safeReviewReturnPath((string)($_POST['return_to'] ?? ''),$id ? '/admin/review/'.(int)$id : '/admin/review-queue'));
    }
    /** LS-89 BE-2: take an approved listing out of the price data, with a reason, and bring that model's price up to date */
    public function reviewWithdraw(): void {
        if(!$this->requirePermission('review.decide')) return;
        if (!Csrf::verify($_POST['_token']??null)) { http_response_code(419); $this->view('errors/419',['title'=>'แบบฟอร์มหมดอายุ']); return; }
        $id=$this->postId('observation_id');
        $to=(string)($_POST['to']??'');
        if($id){
            $result=(new ReviewDecisionService())->withdraw((int)$id,$to,(string)($_POST['reason']??''),(int)$this->user()['id']);
            $this->reviewOutcome($result,$to==='pending'?'review.done.reopened':'review.done.withdrawn',true);
        }
        $this->redirect($this->safeReviewReturnPath((string)($_POST['return_to'] ?? ''),$id ? '/admin/review/'.(int)$id : '/admin/review-queue'));
    }
    /** says what a decision did; when it changed the price data, recalculates the models concerned and says what happened to their price */
    /** a positive id posted with a form, or 0 */
    private function postId(string $key): int { $v=$_POST[$key] ?? null; return is_scalar($v) ? max(0,(int)filter_var($v,FILTER_VALIDATE_INT)) : 0; }
    private function reviewOutcome(array $result,string $doneKey,bool $refreshPrices): void {
        if(!$result['ok']){
            $this->flash('danger',t('review.err.'.$result['error'],['codes'=>implode(', ',$result['errors'])]));
            return;
        }
        if(!$refreshPrices){ $this->flash('success',t($doneKey)); return; }
        $price=$this->priceResultMessage((new PriceRefresh())->refresh($result['product_ids']));
        $this->flash($price['type'],t($doneKey).($price['text']==='' ? '' : ' · '.$price['text']));
    }
    public function observationsData(): void { if(!$this->requirePermission('review.view')) return; $this->json((new AdminDataTableService())->observations($_GET,$this->baseUrl())); }
    public function reviewData(): void { if(!$this->requirePermission('review.view')) return; $this->json((new AdminDataTableService())->reviewQueue($_GET,$this->baseUrl())); }
    private function reviewFilters(): array {
        $dataset=strtoupper(trim((string)($_GET['dataset'] ?? 'REAL')));
        if(!in_array($dataset,['REAL','MOCK_TEST','UNKNOWN','ALL'],true)) $dataset='REAL';
        $status=strtolower(trim((string)($_GET['status'] ?? 'pending')));
        if(!in_array($status,['pending','approved','rejected','excluded','all'],true)) $status='pending';
        $lane=strtolower(trim((string)($_GET['lane'] ?? '')));
        if(!in_array($lane,['','green','amber','red'],true)) $lane='';
        $provenance=strtoupper(trim((string)($_GET['provenance'] ?? '')));
        if(!in_array($provenance,['','LISTING_LEVEL','SEARCH_RESULT_LEVEL','GENERIC_SOURCE'],true)) $provenance='';
        $runId=trim((string)($_GET['run_id'] ?? ''));
        $runId=preg_replace('/[^A-Za-z0-9_.:-]/','',$runId) ?? '';
        if(strlen($runId)>120) $runId=substr($runId,0,120);
        $sort=strtolower(trim((string)($_GET['sort'] ?? 'attention')));
        if(!in_array($sort,['attention','newest','oldest','price_asc','price_desc'],true)) $sort='attention';
        $productId=filter_input(INPUT_GET,'product_id',FILTER_VALIDATE_INT) ?: null;
        $sourceId=filter_input(INPUT_GET,'source_id',FILTER_VALIDATE_INT) ?: null;
        $page=filter_input(INPUT_GET,'page',FILTER_VALIDATE_INT) ?: 1;
        return [
            'dataset'=>$dataset,
            'status'=>$status,
            'lane'=>$lane,
            'provenance'=>$provenance,
            'run_id'=>$runId,
            'sort'=>$sort,
            'product_id'=>$productId,
            'source_id'=>$sourceId,
            'q'=>trim((string)($_GET['q'] ?? '')),
            'page'=>max(1,$page),
            'per_page'=>25,
        ];
    }
    private function safeReviewReturnPath(string $path,string $fallback): string {
        $path=trim($path);
        if($path==='' || $path[0] !== '/' || str_starts_with($path,'//')) return $fallback;
        if(preg_match('#^/admin/review-queue(\?.*)?$#',$path) || preg_match('#^/admin/review/[0-9]+(\?.*)?$#',$path)) return $path;
        return $fallback;
    }
}
