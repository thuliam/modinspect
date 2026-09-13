<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Database;
use App\Core\Csrf;
use App\Core\Controller;
use App\Models\Dashboard;
use App\Services\Admin\AdminDataTableService;
use App\Services\Auth\AuthService;
use App\Services\Auth\AuthorizationService;
use App\Services\Collection\OfflineEvidenceImportService;
use App\Services\Collection\SourceOperationsService;
use App\Services\Review\ReviewCalibrationService;
use PDO;

final class AdminController extends Controller {
    private ?array $user = null;
    private function render(string $view,string $title,array $data=[]): void { $this->view('admin/'.$view,['title'=>$title,'auth_user'=>$this->user()]+$data); }
    private function user(): ?array { return $this->user ??= (new AuthService())->currentUser(); }
    private function requirePermission(string $permission): bool {
        $auth=new AuthService();
        $user=$auth->currentUser();
        if(!$user){
            if(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') $_SESSION['auth_return_to']=$_SERVER['REQUEST_URI'] ?? '/admin';
            $this->redirect('/login');
        }
        $this->user=$user;
        if(!(new AuthorizationService())->can($user,$permission)){
            $auth->audit((int)$user['id'],'auth_unauthorized','permission',null,['permission'=>$permission,'path'=>$_SERVER['REQUEST_URI'] ?? null]);
            http_response_code(403);
            $this->view('errors/403',['title'=>'ไม่มีสิทธิ์เข้าถึง','auth_user'=>$user]);
            return false;
        }
        return true;
    }
    public function index(): void { if(!$this->requirePermission('admin.dashboard.view')) return; $d=new Dashboard();$this->render('dashboard','Admin Data Quality',$d->adminStats()+['products'=>$d->products(),'sources'=>$d->sources(),'recent_imports'=>$d->recentImportRuns()]); }
    public function products(): void { if(!$this->requirePermission('products.view')) return; $d=new Dashboard();$this->render('products','Product Master',['products'=>$d->products(),'categories'=>$d->rows("SELECT id,name,slug FROM product_categories ORDER BY sort_order,name"),'brands'=>$d->rows("SELECT id,name FROM brands ORDER BY name")]); }
    public function aliases(): void { if(!$this->requirePermission('products.view')) return; $d=new Dashboard();$this->render('aliases','Product Aliases',['aliases'=>$d->aliases(),'products'=>$d->reviewProductOptions()]); }
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
    public function reviewCorrection(): void {
        if(!$this->requirePermission('review.correct')) return;
        if (!Csrf::verify($_POST['_token']??null)) { http_response_code(419); $this->view('errors/419',['title'=>'แบบฟอร์มหมดอายุ']); return; }
        $id=filter_input(INPUT_POST,'observation_id',FILTER_VALIDATE_INT);
        $field=(string)($_POST['field_name']??'');
        $value=$_POST['corrected_value']??null;
        $reason=trim((string)($_POST['reason']??''));
        if($id) (new Dashboard())->correctObservation((int)$id,$field,$value,$reason,(int)$this->user()['id']);
        $this->redirect($this->safeReviewReturnPath((string)($_POST['return_to'] ?? ''),$id ? '/admin/review/'.(int)$id : '/admin/review-queue'));
    }
    public function reviewDecision(): void {
        if(!$this->requirePermission('review.decide')) return;
        if (!Csrf::verify($_POST['_token']??null)) { http_response_code(419); $this->view('errors/419',['title'=>'แบบฟอร์มหมดอายุ']); return; }
        $id=filter_input(INPUT_POST,'observation_id',FILTER_VALIDATE_INT);
        $decision=(string)($_POST['decision']??'');
        if($id) (new Dashboard())->decideObservation((int)$id,$decision,trim((string)($_POST['notes']??'')),(int)$this->user()['id']);
        $this->redirect($this->safeReviewReturnPath((string)($_POST['return_to'] ?? ''),$id ? '/admin/review/'.(int)$id : '/admin/review-queue'));
    }
    public function requeueJob(): void {
        if(!$this->requirePermission('collection.jobs.requeue')) return;
        if (!Csrf::verify($_POST['_token']??null)) { http_response_code(419); $this->view('errors/419',['title'=>'แบบฟอร์มหมดอายุ']); return; }
        $id=filter_input(INPUT_POST,'job_id',FILTER_VALIDATE_INT);
        if($id) (new SourceOperationsService())->requeueFailedJob((int)$id,false,(int)$this->user()['id']);
        $this->redirect('/admin/collector-jobs');
    }
    public function sourceAction(): void {
        if(!$this->requirePermission('sources.control')) return;
        if (!Csrf::verify($_POST['_token']??null)) { http_response_code(419); $this->view('errors/419',['title'=>'แบบฟอร์มหมดอายุ']); return; }
        $id=filter_input(INPUT_POST,'source_id',FILTER_VALIDATE_INT);
        $action=(string)($_POST['action']??'');
        $reason=trim((string)($_POST['reason']??'Admin UI action'));
        if($id){
            $ops=new SourceOperationsService();
            if($action==='pause') $ops->pauseSource((int)$id,$reason ?: 'Admin pause','manually_paused',false,(int)$this->user()['id']);
            elseif($action==='resume') $ops->resumeSource((int)$id,$reason ?: 'Admin resume',false,(int)$this->user()['id']);
            elseif($action==='disable') $ops->disableSource((int)$id,$reason ?: 'Admin disable',false,(int)$this->user()['id']);
            elseif($action==='enable') $ops->enableSource((int)$id,$reason ?: 'Admin enable',false,(int)$this->user()['id']);
            elseif($action==='incident') $ops->recordIncident((int)$id,'other',$reason ?: 'Admin incident note','monitor','user:'.$this->user()['id'],(int)$this->user()['id']);
        }
        $this->redirect('/admin/sources');
    }
    public function sources(): void { if(!$this->requirePermission('sources.view')) return; $d=new Dashboard();$this->render('sources','Source Health',['sources'=>$d->sources(),'provider_status'=>$d->providerStatus()]); }
    public function jobs(): void { if(!$this->requirePermission('collection.jobs.view')) return; $d=new Dashboard();$this->render('jobs','Import Jobs',['jobs'=>$d->jobs(),'last_import_summary'=>$_SESSION['admin_import_summary'] ?? null]); }
    public function indices(): void { if(!$this->requirePermission('snapshots.view')) return; $d=new Dashboard();$this->render('indices','Price Indices',['products'=>$d->products(),'categories'=>$d->rows("SELECT id,name,slug FROM product_categories ORDER BY sort_order,name")]); }
    public function articles(): void { if(!$this->requirePermission('products.view')) return; $this->render('placeholder','Articles',['description'=>'จัดการบทความ SEO, buying guides และสถานะการเผยแพร่']); }
    public function audits(): void { if(!$this->requirePermission('audit.view')) return; $d=new Dashboard();$this->render('audits','Audit Logs',['audits'=>$d->audits()]); }
    public function articlesOperational(): void { if(!$this->requirePermission('products.view')) return; $this->render('articles','Articles',['last_import_summary'=>$_SESSION['admin_import_summary'] ?? null]); }
    public function productsData(): void { if(!$this->requirePermission('products.view')) return; $this->json((new AdminDataTableService())->products($_GET,$this->baseUrl())); }
    public function aliasesData(): void { if(!$this->requirePermission('products.view')) return; $this->json((new AdminDataTableService())->aliases($_GET,$this->baseUrl())); }
    public function observationsData(): void { if(!$this->requirePermission('review.view')) return; $this->json((new AdminDataTableService())->observations($_GET,$this->baseUrl())); }
    public function reviewData(): void { if(!$this->requirePermission('review.view')) return; $this->json((new AdminDataTableService())->reviewQueue($_GET,$this->baseUrl())); }
    public function indicesData(): void { if(!$this->requirePermission('snapshots.view')) return; $this->json((new AdminDataTableService())->priceIndices($_GET,$this->baseUrl())); }
    public function sourcesData(): void { if(!$this->requirePermission('sources.view')) return; $this->json((new AdminDataTableService())->sources($_GET,$this->baseUrl(),$this->user() ?? [])); }
    public function jobsData(): void { if(!$this->requirePermission('collection.jobs.view')) return; $this->json((new AdminDataTableService())->jobs($_GET,$this->baseUrl(),$this->user() ?? [])); }
    public function articlesData(): void { if(!$this->requirePermission('products.view')) return; $this->json((new AdminDataTableService())->articles($_GET,$this->baseUrl())); }
    public function auditsData(): void { if(!$this->requirePermission('audit.view')) return; $this->json((new AdminDataTableService())->audits($_GET)); }
    public function productSave(): void {
        if(!$this->requirePermission('products.view')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/products'); }
        global $config;
        $db=Database::connection($config['db']);
        $id=filter_input(INPUT_POST,'product_id',FILTER_VALIDATE_INT) ?: null;
        $categoryId=filter_input(INPUT_POST,'category_id',FILTER_VALIDATE_INT) ?: 0;
        $brandId=filter_input(INPUT_POST,'brand_id',FILTER_VALIDATE_INT) ?: 0;
        $model=trim((string)($_POST['model_name'] ?? ''));
        $full=trim((string)($_POST['full_name'] ?? ''));
        $slug=$this->slug(trim((string)($_POST['slug'] ?? ($full ?: $model))));
        if($categoryId<=0 || $brandId<=0 || $model==='' || $full==='' || $slug===''){
            $this->flash('danger','Product requires category, brand, model, full name, and slug.');
            $this->redirect('/admin/products');
        }
        try{
            if($id){
                $stmt=$db->prepare("UPDATE products SET category_id=:category_id,brand_id=:brand_id,model_name=:model_name,slug=:slug,full_name=:full_name,generation=:generation,spec_summary=:spec_summary,image_path=:image_path,is_active=:is_active WHERE id=:id");
                $stmt->execute(['category_id'=>$categoryId,'brand_id'=>$brandId,'model_name'=>$model,'slug'=>$slug,'full_name'=>$full,'generation'=>trim((string)($_POST['generation'] ?? '')) ?: null,'spec_summary'=>trim((string)($_POST['spec_summary'] ?? '')) ?: null,'image_path'=>trim((string)($_POST['image_path'] ?? '')) ?: null,'is_active'=>isset($_POST['is_active']) ? 1 : 0,'id'=>$id]);
                $this->audit('product_updated','product',$id,['slug'=>$slug,'full_name'=>$full]);
                $this->flash('success','Product updated.');
            } else {
                $stmt=$db->prepare("INSERT INTO products (category_id,brand_id,model_name,slug,full_name,generation,spec_summary,image_path,is_active,created_at,updated_at) VALUES (:category_id,:brand_id,:model_name,:slug,:full_name,:generation,:spec_summary,:image_path,:is_active,NOW(),NOW())");
                $stmt->execute(['category_id'=>$categoryId,'brand_id'=>$brandId,'model_name'=>$model,'slug'=>$slug,'full_name'=>$full,'generation'=>trim((string)($_POST['generation'] ?? '')) ?: null,'spec_summary'=>trim((string)($_POST['spec_summary'] ?? '')) ?: null,'image_path'=>trim((string)($_POST['image_path'] ?? '')) ?: null,'is_active'=>isset($_POST['is_active']) ? 1 : 0]);
                $this->audit('product_created','product',(int)$db->lastInsertId(),['slug'=>$slug,'full_name'=>$full]);
                $this->flash('success','Product created.');
            }
        } catch(\PDOException $e){
            $this->flash('danger',$e->getCode()==='23000' ? 'Product slug already exists.' : 'Product save failed.');
        }
        $this->redirect('/admin/products');
    }
    public function productToggle(): void {
        if(!$this->requirePermission('products.view')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/products'); }
        $id=filter_input(INPUT_POST,'product_id',FILTER_VALIDATE_INT);
        if($id){
            global $config;
            $db=Database::connection($config['db']);
            $db->prepare("UPDATE products SET is_active=IF(is_active=1,0,1),updated_at=NOW() WHERE id=:id")->execute(['id'=>$id]);
            $this->audit('product_status_toggled','product',(int)$id,[]);
            $this->flash('success','Product status updated.');
        }
        $this->redirect('/admin/products');
    }
    public function aliasSave(): void {
        if(!$this->requirePermission('products.view')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/product-aliases'); }
        global $config;
        $db=Database::connection($config['db']);
        $id=filter_input(INPUT_POST,'alias_id',FILTER_VALIDATE_INT) ?: null;
        $productId=filter_input(INPUT_POST,'product_id',FILTER_VALIDATE_INT) ?: 0;
        $alias=trim((string)($_POST['alias_text'] ?? ''));
        $normalized=$this->normalizeAlias($alias);
        if($productId<=0 || $alias==='' || $normalized===''){
            $this->flash('danger','Alias requires product and alias text.');
            $this->redirect('/admin/product-aliases');
        }
        try{
            if($id){
                $stmt=$db->prepare("UPDATE product_aliases SET product_id=:product_id,alias_text=:alias_text,normalized_alias=:normalized_alias,source_note=:source_note,confidence=:confidence WHERE id=:id");
                $stmt->execute(['product_id'=>$productId,'alias_text'=>$alias,'normalized_alias'=>$normalized,'source_note'=>trim((string)($_POST['source_note'] ?? '')) ?: null,'confidence'=>max(0,min(100,(float)($_POST['confidence'] ?? 100))),'id'=>$id]);
                $this->audit('product_alias_updated','product_alias',$id,['alias_text'=>$alias]);
                $this->flash('success','Alias updated.');
            } else {
                $stmt=$db->prepare("INSERT INTO product_aliases (product_id,alias_text,normalized_alias,source_note,confidence,created_at,updated_at) VALUES (:product_id,:alias_text,:normalized_alias,:source_note,:confidence,NOW(),NOW())");
                $stmt->execute(['product_id'=>$productId,'alias_text'=>$alias,'normalized_alias'=>$normalized,'source_note'=>trim((string)($_POST['source_note'] ?? '')) ?: null,'confidence'=>max(0,min(100,(float)($_POST['confidence'] ?? 100)))]);
                $this->audit('product_alias_created','product_alias',(int)$db->lastInsertId(),['alias_text'=>$alias]);
                $this->flash('success','Alias created.');
            }
        } catch(\PDOException $e){
            $this->flash('danger',$e->getCode()==='23000' ? 'Alias already exists after normalization.' : 'Alias save failed.');
        }
        $this->redirect('/admin/product-aliases');
    }
    public function aliasDelete(): void {
        if(!$this->requirePermission('products.view')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/product-aliases'); }
        $id=filter_input(INPUT_POST,'alias_id',FILTER_VALIDATE_INT);
        if($id){
            global $config;
            Database::connection($config['db'])->prepare("DELETE FROM product_aliases WHERE id=:id")->execute(['id'=>$id]);
            $this->audit('product_alias_deleted','product_alias',(int)$id,[]);
            $this->flash('success','Alias deleted.');
        }
        $this->redirect('/admin/product-aliases');
    }
    public function articleSave(): void {
        if(!$this->requirePermission('products.view')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/articles'); }
        global $config;
        $db=Database::connection($config['db']);
        $id=filter_input(INPUT_POST,'article_id',FILTER_VALIDATE_INT) ?: null;
        $title=trim((string)($_POST['title'] ?? ''));
        $slug=$this->slug(trim((string)($_POST['slug'] ?? $title)));
        $status=in_array((string)($_POST['status'] ?? 'draft'),['draft','published','archived'],true) ? (string)$_POST['status'] : 'draft';
        $body=trim((string)($_POST['body'] ?? ''));
        if($title==='' || $slug==='' || $body===''){
            $this->flash('danger','Article requires title, slug, and body.');
            $this->redirect('/admin/articles');
        }
        try{
            if($id){
                $stmt=$db->prepare("UPDATE articles SET title=:title,slug=:slug,excerpt=:excerpt,body=:body,status=:status,published_at=CASE WHEN :status_pub='published' AND published_at IS NULL THEN NOW() WHEN :status_pub2='published' THEN published_at ELSE NULL END,updated_at=NOW() WHERE id=:id");
                $stmt->execute(['title'=>$title,'slug'=>$slug,'excerpt'=>trim((string)($_POST['excerpt'] ?? '')) ?: null,'body'=>$body,'status'=>$status,'status_pub'=>$status,'status_pub2'=>$status,'id'=>$id]);
                $this->audit('article_updated','article',$id,['slug'=>$slug,'status'=>$status]);
                $this->flash('success','Article updated.');
            } else {
                $stmt=$db->prepare("INSERT INTO articles (author_id,title,slug,excerpt,body,status,published_at,created_at,updated_at) VALUES (:author_id,:title,:slug,:excerpt,:body,:status,CASE WHEN :status_pub='published' THEN NOW() ELSE NULL END,NOW(),NOW())");
                $stmt->execute(['author_id'=>(int)$this->user()['id'],'title'=>$title,'slug'=>$slug,'excerpt'=>trim((string)($_POST['excerpt'] ?? '')) ?: null,'body'=>$body,'status'=>$status,'status_pub'=>$status]);
                $this->audit('article_created','article',(int)$db->lastInsertId(),['slug'=>$slug,'status'=>$status]);
                $this->flash('success','Article created.');
            }
        } catch(\PDOException $e){
            $this->flash('danger',$e->getCode()==='23000' ? 'Article slug already exists.' : 'Article save failed.');
        }
        $this->redirect('/admin/articles');
    }
    public function articleArchive(): void {
        if(!$this->requirePermission('products.view')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/articles'); }
        $id=filter_input(INPUT_POST,'article_id',FILTER_VALIDATE_INT);
        if($id){
            global $config;
            Database::connection($config['db'])->prepare("UPDATE articles SET status='archived',published_at=NULL,updated_at=NOW() WHERE id=:id")->execute(['id'=>$id]);
            $this->audit('article_archived','article',(int)$id,[]);
            $this->flash('success','Article archived.');
        }
        $this->redirect('/admin/articles');
    }
    public function importDryRun(): void { $this->handleImport(true); }
    public function importConfirm(): void { $this->handleImport(false); }
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
    private function handleImport(bool $dryRun): void {
        if(!$this->requirePermission('collection.jobs.requeue')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/collector-jobs'); }
        $dataset=strtolower((string)($_POST['dataset'] ?? 'test')) === 'real' ? 'real' : 'test';
        $limit=max(1,min(1000,(int)($_POST['limit'] ?? 100)));
        $file=$this->storedUploadPath($_FILES['evidence_file'] ?? null);
        if($file===''){
            $this->flash('danger','Upload a CSV or JSON evidence file.');
            $this->redirect('/admin/collector-jobs');
        }
        $summary=(new OfflineEvidenceImportService())->import($file,$dataset,$dryRun,$limit);
        $_SESSION['admin_import_summary']=$summary;
        $this->flash(($summary['invalid_rows'] ?? 0)>0 && ($summary['valid_rows'] ?? 0)===0 ? 'danger' : 'success',($dryRun ? 'Dry run complete. ' : 'Import complete. ').'Valid rows: '.(int)($summary['valid_rows'] ?? 0).', invalid rows: '.(int)($summary['invalid_rows'] ?? 0).', duplicates: '.(int)($summary['duplicate_rows'] ?? 0).'.');
        $this->redirect('/admin/collector-jobs');
    }
    private function storedUploadPath(?array $file): string {
        if(!$file || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return '';
        $name=(string)($file['name'] ?? '');
        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if(!in_array($ext,['csv','json'],true)) return '';
        $dir=ROOT_PATH.'/storage/import_uploads';
        if(!is_dir($dir)) mkdir($dir,0775,true);
        $target=$dir.'/admin-import-'.date('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$ext;
        return move_uploaded_file((string)$file['tmp_name'],$target) ? $target : '';
    }
    private function json(array $payload): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload,JSON_UNESCAPED_UNICODE);
    }
    private function flash(string $type,string $message): void { $_SESSION['flash']=['type'=>$type,'message'=>$message]; }
    private function baseUrl(): string { global $config; return (string)$config['base_url']; }
    private function audit(string $action,string $entityType,?int $entityId,array $after): void { (new AuthService())->audit((int)$this->user()['id'],$action,$entityType,$entityId,$after); }
    private function slug(string $value): string {
        $slug=preg_replace('/[^a-z0-9ก-๙]+/iu','-',mb_strtolower(trim($value),'UTF-8')) ?? '';
        return trim($slug,'-');
    }
    private function normalizeAlias(string $value): string { return preg_replace('/[^a-z0-9ก-๙]+/iu','',mb_strtolower($value,'UTF-8')) ?? ''; }
}
