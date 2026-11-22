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
use App\Services\Collection\ImportFieldContract;
use App\Services\Collection\OfflineEvidenceImportService;
use App\Services\Collection\SourceOperationsService;
use App\Services\Review\ReviewCalibrationService;
use PDO;

final class AdminController extends Controller {
    private ?array $user = null;
    /** the page title is written in English in the code; Lang::label shows it in the chosen language when a text exists */
    private function render(string $view,string $title,array $data=[]): void { $this->view('admin/'.$view,['title'=>\App\Core\Lang::label($title) ?? $title,'auth_user'=>$this->user()]+$data); }
    /** LS-14 J-0: the server already refuses the write (403); this only stops the page from offering a button that would fail */
    private function canManage(): bool { return (new AuthorizationService())->can($this->user(), 'products.manage'); }
    /** drops the action cell (last column) of every row for a read-only role; the column stays so the table keeps its shape */
    private function withoutActions(array $payload): array {
        if($this->canManage() || empty($payload['data'])) return $payload;
        foreach($payload['data'] as $i=>$row){ if(is_array($row) && $row){ $row[array_key_last($row)]=''; $payload['data'][$i]=$row; } }
        return $payload;
    }
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
    public function products(): void { if(!$this->requirePermission('products.view')) return; $d=new Dashboard();$this->render('products','Product Master',['canManage'=>$this->canManage(),'products'=>$d->products(),'categories'=>$d->rows("SELECT id,name,slug FROM product_categories ORDER BY sort_order,name"),'brands'=>$d->rows("SELECT id,name FROM brands ORDER BY name")]); }
    public function aliases(): void { if(!$this->requirePermission('products.view')) return; $d=new Dashboard();$this->render('aliases','Product Aliases',['canManage'=>$this->canManage(),'aliases'=>$d->aliases(),'products'=>$d->reviewProductOptions()]); }
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
    public function snapshotEvidence(string $id): void {
        if(!$this->requirePermission('snapshots.view')) return;
        $report=ctype_digit($id) ? (new \App\Services\Admin\SnapshotEvidenceReport())->forSnapshot((int)$id) : null;
        if(!$report) { http_response_code(404); $this->view('errors/404',['title'=>'Snapshot not found']); return; }
        $this->view('admin/snapshot-evidence',['title'=>'Snapshot #'.(int)$id.' evidence','report'=>$report,'history'=>(new \App\Services\Admin\SnapshotHistory())->forSnapshot((int)$id)]);
    }
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
    public function betaReadiness(): void {
        if(!$this->requirePermission('products.manage')) return;
        $this->render('beta-readiness','Beta readiness',['panel'=>(new \App\Services\Admin\BetaReadiness())->build()]);
    }
    public function intake(?array $result=null,?string $error=null): void {
        if(!$this->requirePermission('products.manage')) return;
        global $config;
        $built=\App\Services\Research\OwnerApprovalPlan::build(Database::connection($config['db']));
        $this->render('intake','Owner intake',['plan'=>$built['plan'],'leftPending'=>$built['left_pending'],'result'=>$result,'error'=>$error]);
    }
    public function intakeDryRun(): void {
        if(!$this->requirePermission('products.manage')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/intake'); }
        $file=$_FILES['file']??null;
        if(!is_array($file) || ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file((string)$file['tmp_name'])) { $this->intake(null,'Choose a CSV file.'); return; }
        if((int)$file['size']>\App\Services\Research\OwnerIntakeDryRun::MAX_BYTES) { $this->intake(null,'The file is larger than 5 MB.'); return; }
        if(strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION))!=='csv') { $this->intake(null,'Only .csv files are accepted.'); return; }
        $dir=ROOT_PATH.'/storage/import_uploads';
        if(!is_dir($dir)) @mkdir($dir,0775,true);
        $tmp=$dir.'/intake-upload-'.bin2hex(random_bytes(6)).'.csv';
        if(!move_uploaded_file((string)$file['tmp_name'],$tmp)) { $this->intake(null,'The upload could not be stored.'); return; }
        try {
            $category=strtolower((string)($_POST['category']??'gpu'));
            $spec=$category==='spec';
            $result=(new \App\Services\Research\OwnerIntakeDryRun())->run($tmp,$spec?null:(in_array($category,['gpu','cpu'],true)?$category:null),$spec);
        } finally { @unlink($tmp); }
        $this->intake($result['ok']?$result:null,$result['ok']?null:'The file cannot be used: '.str_replace('MISSING_COLUMNS:','missing columns: ',(string)$result['error']));
    }
    /** LS-14 Module 6.5 (OD-2): new retail prices for the Build page, entered by an admin with the shop link and check date */
    public function newPrices(): void {
        if(!$this->requirePermission('products.manage')) return;
        global $config; $db=Database::connection($config['db']);
        try { $rows=(new \App\Services\Admin\NewPriceReferences($db))->list(); $ready=true; } catch(\PDOException) { $rows=[]; $ready=false; }
        $products=(new \App\Models\Product())->search();
        $this->render('new-prices','ราคาของใหม่อ้างอิง',['rows'=>$rows,'ready'=>$ready,'products'=>$products]);
    }
    public function newPriceAction(): void {
        if(!$this->requirePermission('products.manage')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger',t('flash.csrf')); $this->redirect('/admin/new-prices'); }
        global $config; $svc=new \App\Services\Admin\NewPriceReferences(Database::connection($config['db']));
        $actor=(int)$this->user()['id'];
        $messages=['PRODUCT_NOT_FOUND'=>'ไม่พบสินค้านี้','INVALID_PRICE'=>'ราคาต้องมากกว่า 0 และไม่เกิน 10,000,000 บาท','INVALID_URL'=>'ลิงก์ร้านต้องขึ้นต้นด้วย http:// หรือ https://','INVALID_DATE'=>'วันที่ตรวจต้องเป็นวันที่จริง ไม่เกินวันนี้','NOT_FOUND'=>'ไม่พบรายการนี้'];
        $action=(string)($_POST['action']??'');
        if($action==='add') { $r=$svc->add($actor,(int)($_POST['product_id']??0),(string)($_POST['price']??''),(string)($_POST['source_url']??''),(string)($_POST['checked_on']??''),(string)($_POST['note']??'')); $ok='บันทึกราคาของใหม่อ้างอิงแล้ว'; }
        elseif($action==='delete') { $r=$svc->delete($actor,(int)($_POST['id']??0)); $ok='ลบรายการแล้ว'; }
        else { $r=['ok'=>false,'error'=>'NOT_FOUND']; $ok=''; }
        $this->flash($r['ok']?'success':'danger',$r['ok']?$ok:($messages[$r['error']]??'บันทึกไม่สำเร็จ'));
        $this->redirect('/admin/new-prices');
    }
    /** LS-14 Module 9: moderation of seller listings (approve / reject with a reason). Approval creates no price (HG-17). */
    public function sellerListings(): void {
        if(!$this->requirePermission('listings.moderate')) return;
        global $config;
        try { $svc=new \App\Services\Listing\SellerListings(Database::connection($config['db'])); $rows=$svc->moderationQueue(); $photos=$svc->photosFor(array_column($rows,'id')); $ready=true; } catch(\PDOException) { $rows=[]; $photos=[]; $ready=false; }
        $this->render('seller-listings','Seller listings',['rows'=>$rows,'photos'=>$photos,'ready'=>$ready]);
    }
    public function sellerListingAction(): void {
        if(!$this->requirePermission('listings.moderate')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger',t('flash.csrf')); $this->redirect('/admin/seller-listings'); }
        global $config;
        $r=(new \App\Services\Listing\SellerListings(Database::connection($config['db'])))->moderate((int)$this->user()['id'],(int)($_POST['id']??0),(string)($_POST['decision']??''),(string)($_POST['note']??''));
        $this->flash($r['ok']?'success':'danger',$r['ok']?'บันทึกผลการตรวจแล้ว':(['NOTE_REQUIRED'=>'การไม่ผ่านต้องมีเหตุผล','BAD_STATE'=>'ประกาศนี้ไม่ได้อยู่ในสถานะรอตรวจ','BAD_ACTION'=>'คำสั่งไม่ถูกต้อง'][$r['error']]??'บันทึกไม่สำเร็จ'));
        $this->redirect('/admin/seller-listings');
    }
    /** LS-14 Module 8 (ADR-007): moderation of seller reports. Works even while the public feature is off, so the queue can be prepared. */
    private function sellerCheckService(): ?\App\Services\Listing\SellerCheck {
        global $config; $key=(string)($config['seller_check']['hmac_key']??'');
        return strlen($key)>=32 ? new \App\Services\Listing\SellerCheck(Database::connection($config['db']),$key) : null;
    }
    /** LS-21 (R-11): community report queue. Reads and decisions never hash an address, so a placeholder key is enough (the public form is what needs the real one). */
    private function communityReportService(): \App\Services\Community\CommunityReports {
        global $config; $key=(string)($config['community_reports']['hmac_key']??'');
        return new \App\Services\Community\CommunityReports(Database::connection($config['db']),strlen($key)>=32 ? $key : str_repeat('x',32));
    }
    /** LS-24 M9: the forum moderation queue: items with open flags, and the actions on them. Same permission as the report queue. */
    public function communityModeration(): void {
        if(!$this->requirePermission('reports.moderate')) return;
        global $config;
        try { $f=new \App\Services\Community\Forum(Database::connection($config['db'])); $queue=$f->queue(); $counts=$f->counts(); $ready=true; } catch(\PDOException) { $queue=[]; $counts=[]; $ready=false; }
        $this->render('community-moderation','Community moderation',['queue'=>$queue,'counts'=>$counts,'ready'=>$ready,'featureOn'=>\App\Services\Community\Forum::enabled($config)]);
    }
    public function communityModerationAction(): void {
        if(!$this->requirePermission('reports.moderate')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger',t('flash.csrf')); $this->redirect('/admin/community-moderation'); }
        global $config; $f=new \App\Services\Community\Forum(Database::connection($config['db']));
        $type=(string)($_POST['type']??''); $id=(int)($_POST['id']??0); $action=(string)($_POST['action']??'');
        $r=$f->moderate((int)$this->user()['id'],$type,$id,$action);
        $this->flash($r['ok']?'success':'danger',$r['ok']?'Done.':t('msg.could_not_apply',['e'=>($r['error']??'error')]));
        $this->redirect('/admin/community-moderation');
    }
    public function communityReports(): void {
        if(!$this->requirePermission('reports.moderate')) return;
        global $config; $status=(string)($_GET['status']??'new'); if(!in_array($status,['new','accepted','rejected','spam','all'],true)){ $status='new'; }
        try {
            $svc=$this->communityReportService(); $rows=$svc->queue($status); $counts=$svc->counts(); $ready=true;
        } catch(\PDOException) { $rows=[]; $counts=[]; $ready=false; }
        $this->render('community-reports','Community reports',['rows'=>$rows,'counts'=>$counts,'status'=>$status,'ready'=>$ready,'featureOn'=>\App\Services\Community\CommunityReports::enabled($config)]);
    }
    public function communityReportAction(): void {
        if(!$this->requirePermission('reports.moderate')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger',t('flash.csrf')); $this->redirect('/admin/community-reports'); }
        $svc=$this->communityReportService(); $actor=(int)$this->user()['id']; $id=(int)($_POST['id']??0); $decision=(string)($_POST['decision']??'');
        $backStatus=(string)($_POST['status']??'new'); if(!in_array($backStatus,['new','accepted','rejected','spam','all'],true)){ $backStatus='new'; }   // read once (a second read of a missing key warns and gives '')
        $back='/admin/community-reports?status='.rawurlencode($backStatus);
        $errors=['BAD_ACTION'=>'การกระทำไม่ถูกต้อง','NOTE_REQUIRED'=>'การไม่ผ่านหรือทำเครื่องหมายสแปมต้องมีเหตุผล','BAD_STATE'=>'รายงานนี้ตัดสินไปแล้ว','NOT_FOUND'=>'ไม่พบรายงาน','NOT_A_SOLD_PRICE'=>'ใช้ได้กับรายงานราคาที่ขายได้เท่านั้น','NOT_ACCEPTED'=>'ต้องรับรายงานก่อน',
            'ALREADY_CANDIDATE'=>'ส่งเข้าคิวตรวจราคาไปแล้ว','CANDIDATE_CAP'=>'ผู้แจ้งรหัสเดียวกันส่งเข้าคิวได้ 1 ครั้งต่อรุ่นต่อสัปดาห์','BAD_PAYLOAD'=>'ข้อมูลในรายงานไม่ครบ'];
        $r=$decision==='candidate' ? $svc->createCandidate($actor,$id) : $svc->moderate($actor,$id,$decision,(string)($_POST['note']??''));
        $this->flash($r['ok']?'success':'danger',$r['ok']?($decision==='candidate'?'ส่งเข้าคิวตรวจราคาแล้ว (รอผู้ตรวจอนุมัติ ยังไม่กระทบราคา)':'บันทึกผลการตรวจแล้ว'):($errors[$r['error']??'']??'ทำรายการไม่สำเร็จ'));
        $this->redirect($back);
    }
    /** Marketplace P1-A: member trust admin. Admin-only on purpose (privacy.handle, legal.manage, members.manage exist only through the admin wildcard); every action is audited with the admin's id. */
    private function trustDb(): PDO { global $config; return Database::connection($config['db']); }
    public function privacyRequests(): void {
        if(!$this->requirePermission('privacy.handle')) return;
        $status=(string)($_GET['status']??'received'); if(!in_array($status,['received','in_progress','completed','rejected'],true)){ $status='received'; }
        try { $svc=new \App\Services\Member\PrivacyRequests($this->trustDb(),new \App\Services\Member\MemberAudit($this->trustDb())); $rows=$svc->queue($status); $counts=$svc->counts(); $ready=true; }
        catch(\PDOException) { $rows=[]; $counts=[]; $ready=false; }
        $this->render('privacy-requests',t('pr.title'),['rows'=>$rows,'counts'=>$counts,'status'=>$status,'ready'=>$ready]);
    }
    public function privacyRequestAction(): void {
        if(!$this->requirePermission('privacy.handle')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger',t('flash.csrf')); $this->redirect('/admin/privacy-requests'); }
        $back=(string)($_POST['status']??'received'); if(!in_array($back,['received','in_progress','completed','rejected'],true)){ $back='received'; }
        $svc=new \App\Services\Member\PrivacyRequests($this->trustDb(),new \App\Services\Member\MemberAudit($this->trustDb()));
        $to=(string)($_POST['to']??''); $note=is_string($_POST['note']??null)?$_POST['note']:'';
        if(in_array($to,['completed','rejected'],true) && trim($note)==='') { $this->flash('danger',t('pr.flash.note_required')); $this->redirect('/admin/privacy-requests?status='.rawurlencode($back)); }
        try { $svc->transition((int)($_POST['id']??0),$to,(int)$this->user()['id'],$note); $this->flash('success',t('pr.flash.saved')); }
        catch(\RuntimeException $e) { $this->flash('danger',['NOT_FOUND'=>t('pr.flash.not_found'),'ILLEGAL_TRANSITION'=>t('pr.flash.illegal')][$e->getMessage()]??t('common.fail')); }
        $this->redirect('/admin/privacy-requests?status='.rawurlencode($back));
    }
    public function legalDocuments(): void {
        if(!$this->requirePermission('legal.manage')) return;
        try { $svc=new \App\Services\Member\LegalDocuments($this->trustDb(),new \App\Services\Member\MemberAudit($this->trustDb())); $rows=$svc->all(); $docView=isset($_GET['view'])?$svc->find((int)$_GET['view']):null; $ready=true; }
        catch(\PDOException) { $rows=[]; $docView=null; $ready=false; }
        $this->render('legal-documents',t('ld.title'),['rows'=>$rows,'docView'=>$docView,'ready'=>$ready,'types'=>\App\Services\Member\LegalDocuments::TYPES]);
    }
    public function legalDocumentCreate(): void {
        if(!$this->requirePermission('legal.manage')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger',t('flash.csrf')); $this->redirect('/admin/legal-documents'); }
        $svc=new \App\Services\Member\LegalDocuments($this->trustDb(),new \App\Services\Member\MemberAudit($this->trustDb()));
        $str=static fn(string $k): string => is_string($_POST[$k]??null)?trim($_POST[$k]):'';
        if($str('version')===''||$str('title')===''||$str('body')==='') { $this->flash('danger',t('ld.flash.fields')); $this->redirect('/admin/legal-documents'); }
        try { $id=$svc->createDraft($str('doc_type'),$str('version'),$str('title'),$str('body')); $this->flash('success',t('ld.flash.draft_saved',['id'=>$id])); }
        catch(\InvalidArgumentException) { $this->flash('danger',t('ld.flash.bad_type')); }
        catch(\PDOException) { $this->flash('danger',t('ld.flash.dup')); }
        $this->redirect('/admin/legal-documents');
    }
    public function legalDocumentPublish(): void {
        if(!$this->requirePermission('legal.manage')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger',t('flash.csrf')); $this->redirect('/admin/legal-documents'); }
        if(($_POST['confirm']??'')!=='1') { $this->flash('danger',t('ld.flash.confirm_required')); $this->redirect('/admin/legal-documents'); }
        $svc=new \App\Services\Member\LegalDocuments($this->trustDb(),new \App\Services\Member\MemberAudit($this->trustDb()));
        try { $svc->publish((int)($_POST['id']??0),(int)$this->user()['id']); $this->flash('success',t('ld.flash.published')); }
        catch(\RuntimeException $e) { $this->flash('danger',['NOT_A_DRAFT'=>t('ld.flash.not_draft'),'EMPTY_BODY'=>t('ld.flash.empty_body')][$e->getMessage()]??t('common.fail')); }
        $this->redirect('/admin/legal-documents');
    }
    public function memberCapabilities(): void {
        if(!$this->requirePermission('members.manage')) return;
        try { $svc=new \App\Services\Member\MemberCapabilities($this->trustDb(),new \App\Services\Member\MemberAudit($this->trustDb())); $rows=$svc->holders(); $ready=true; }
        catch(\PDOException) { $rows=[]; $ready=false; }
        $this->render('member-capabilities',t('mc.title'),['rows'=>$rows,'ready'=>$ready,'allowed'=>\App\Services\Member\MemberCapabilities::ALLOWED]);
    }
    public function memberCapabilityAction(): void {
        if(!$this->requirePermission('members.manage')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger',t('flash.csrf')); $this->redirect('/admin/member-capabilities'); }
        $svc=new \App\Services\Member\MemberCapabilities($this->trustDb(),new \App\Services\Member\MemberAudit($this->trustDb()));
        $member=(int)($_POST['member_id']??0); $cap=is_string($_POST['capability']??null)?$_POST['capability']:''; $do=(string)($_POST['do']??'');
        if(!$svc->memberExists($member)) { $this->flash('danger',t('mc.flash.not_found')); $this->redirect('/admin/member-capabilities'); }
        try {
            if($do==='grant') { $svc->grant($member,$cap,(int)$this->user()['id']); $this->flash('success',t('mc.flash.granted')); }
            elseif($do==='revoke') { $svc->revoke($member,$cap,(int)$this->user()['id']); $this->flash('success',t('mc.flash.revoked')); }
            else { $this->flash('danger',t('mc.flash.bad_action')); }
        } catch(\InvalidArgumentException) { $this->flash('danger',t('mc.flash.bad_cap')); }
        $this->redirect('/admin/member-capabilities');
    }
    public function sellerReports(): void {
        if(!$this->requirePermission('reports.moderate')) return;
        $svc=$this->sellerCheckService();
        try {
            $svc ??= new \App\Services\Listing\SellerCheck(Database::connection($GLOBALS['config']['db']),str_repeat('x',32)); // queue reads never hash; no key is needed to list
            $rows=$svc->queue(); $disputes=$svc->openDisputes(); $ready=true;
        } catch(\PDOException) { $rows=[]; $disputes=[]; $ready=false; }
        $this->render('seller-reports','Seller reports',['rows'=>$rows,'disputes'=>$disputes,'ready'=>$ready]);
    }
    public function sellerReportAction(): void {
        if(!$this->requirePermission('reports.moderate')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger',t('flash.csrf')); $this->redirect('/admin/seller-reports'); }
        global $config;
        $svc=new \App\Services\Listing\SellerCheck(Database::connection($config['db']),(string)($config['seller_check']['hmac_key']??''));
        $decision=(string)($_POST['decision']??''); $actor=(int)$this->user()['id'];
        if($decision==='close_dispute') { $ok=$svc->closeDispute($actor,(int)($_POST['dispute_id']??0)); $this->flash($ok?'success':'danger',$ok?'ปิดคำโต้แย้งแล้ว':'ปิดไม่สำเร็จ'); $this->redirect('/admin/seller-reports'); }
        $r=$svc->moderate($actor,(int)($_POST['id']??0),$decision,(string)($_POST['note']??''));
        $this->flash($r['ok']?'success':'danger',$r['ok']?'บันทึกผลการตรวจแล้ว':(['NOTE_REQUIRED'=>'การไม่ผ่านต้องมีเหตุผล','BAD_STATE'=>'รายงานนี้ตัดสินแล้ว','BAD_ACTION'=>'คำสั่งไม่ถูกต้อง'][$r['error']]??'บันทึกไม่สำเร็จ'));
        $this->redirect('/admin/seller-reports');
    }
    /** Evidence image of a report, for moderators only. */
    public function sellerReportEvidence(string $uuid,string $file): void {
        if(!$this->requirePermission('reports.moderate')) return;
        $path=(new \App\Services\Listing\ListingPhotoStore(ROOT_PATH.'/storage/uploads/reports'))->path($uuid,$file);
        if($path===null){ http_response_code(404); $this->view('errors/404',['title'=>'ไม่พบไฟล์']); return; }
        header('Content-Type: image/jpeg'); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, no-store'); header('Content-Length: '.filesize($path)); readfile($path);
    }
    public function users(): void {
        if(!$this->requirePermission('users.manage')) return;
        $this->render('users','Users',['users'=>(new \App\Services\Admin\UserAdministration())->list(),'me'=>(int)$this->user()['id'],'roles'=>\App\Services\Admin\UserAdministration::ROLES]);
    }
    public function userAction(): void {
        if(!$this->requirePermission('users.manage')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/users'); }
        $svc=new \App\Services\Admin\UserAdministration();
        $actor=(int)$this->user()['id'];
        $uid=(int)(filter_input(INPUT_POST,'user_id',FILTER_VALIDATE_INT) ?: 0);
        $messages=['INVALID_EMAIL'=>'That email address is not valid.','INVALID_ROLE'=>'Unknown role.','PASSWORD_TOO_SHORT'=>'The password must be at least 12 characters.','EMAIL_ALREADY_EXISTS'=>'A user with that email already exists.','USER_NOT_FOUND'=>'User not found.','CANNOT_CHANGE_OWN_ROLE'=>'You cannot change your own role.','CANNOT_DISABLE_SELF'=>'You cannot disable your own account.','LAST_ADMIN'=>'This is the last active admin: it cannot be demoted or disabled.'];
        $action=(string)($_POST['action']??'');
        if($action==='create') { $r=$svc->create($actor,(string)($_POST['email']??''),(string)($_POST['name']??''),(string)($_POST['password']??''),(string)($_POST['role']??'')); $ok='User created.'; }
        elseif($action==='role') { $r=$svc->changeRole($actor,$uid,(string)($_POST['role']??'')); $ok='Role updated.'; }
        elseif($action==='disable') { $r=$svc->setActive($actor,$uid,false); $ok='User disabled. They are signed out on their next request.'; }
        elseif($action==='enable') { $r=$svc->setActive($actor,$uid,true); $ok='User enabled.'; }
        elseif($action==='password') { $r=$svc->setPassword($actor,$uid,(string)($_POST['password']??'')); $ok='Password updated. Sessions that are already open stay open: disable the account first if one must end.'; }
        else { $this->flash('danger','Unknown action.'); $this->redirect('/admin/users'); }
        $this->flash($r['ok']?'success':'danger',$r['ok']?$ok:($messages[$r['error']]??'Could not complete the action.'));
        $this->redirect('/admin/users');
    }
    public function dataHealth(): void {
        global $config;
        if(!$this->requirePermission('snapshots.view')) return;
        // LS-21 (R-11): open community reports per product, an attention signal (never a vote); null on a database without the table
        try { $cr=$this->communityReportService(); $community=['counts'=>$cr->counts(),'products'=>$cr->productsNeedingAttention(5)]; } catch(\PDOException) { $community=null; }
        $this->view('admin/data-health',['title'=>'Data health','report'=>(new \App\Services\Admin\DataHealthReport())->build(),'community'=>$community,'dealOutcomes'=>\App\Services\Admin\DealOutcomeReport::build(Database::connection($config['db']))]);
    }
    public function marketTrends(): void {
        if(!$this->requirePermission('snapshots.view')) return;
        $this->view('admin/market-trends',['title'=>'Market Trends & Locations','report'=>(new \App\Services\Admin\MarketTrendReport())->build(),'dup'=>(new \App\Services\Admin\DuplicatePostReport())->build()]);
    }
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
    public function jobs(): void {
        if(!$this->requirePermission('collection.jobs.view')) return;
        $d=new Dashboard();
        $this->render('jobs','Import Jobs',[
            'jobs'=>$d->jobs(),
            'last_import_summary'=>$_SESSION['admin_import_summary'] ?? null,
            'pending_import'=>$_SESSION['admin_import_pending'] ?? null,
            'import_fields'=>ImportFieldContract::fields(),
        ]);
    }
    public function indices(): void { if(!$this->requirePermission('snapshots.view')) return; $d=new Dashboard();$this->render('indices','Price Indices',['products'=>$d->products(),'categories'=>$d->rows("SELECT id,name,slug FROM product_categories ORDER BY sort_order,name")]); }
    public function articles(): void { if(!$this->requirePermission('products.view')) return; $this->render('placeholder','Articles',['description'=>'จัดการบทความ SEO, buying guides และสถานะการเผยแพร่']); }
    public function audits(): void { if(!$this->requirePermission('audit.view')) return; $d=new Dashboard();$this->render('audits','Audit Logs',['audits'=>$d->audits()]); }
    public function articlesOperational(): void { if(!$this->requirePermission('products.view')) return; $this->render('articles','Articles',['last_import_summary'=>$_SESSION['admin_import_summary'] ?? null]); }
    public function productsData(): void { if(!$this->requirePermission('products.view')) return; $this->json($this->withoutActions((new AdminDataTableService())->products($_GET,$this->baseUrl()))); }
    public function aliasesData(): void { if(!$this->requirePermission('products.view')) return; $this->json($this->withoutActions((new AdminDataTableService())->aliases($_GET,$this->baseUrl()))); }
    public function observationsData(): void { if(!$this->requirePermission('review.view')) return; $this->json((new AdminDataTableService())->observations($_GET,$this->baseUrl())); }
    public function reviewData(): void { if(!$this->requirePermission('review.view')) return; $this->json((new AdminDataTableService())->reviewQueue($_GET,$this->baseUrl())); }
    public function indicesData(): void { if(!$this->requirePermission('snapshots.view')) return; $this->json((new AdminDataTableService())->priceIndices($_GET,$this->baseUrl())); }
    public function sourcesData(): void { if(!$this->requirePermission('sources.view')) return; $this->json((new AdminDataTableService())->sources($_GET,$this->baseUrl(),$this->user() ?? [])); }
    public function jobsData(): void { if(!$this->requirePermission('collection.jobs.view')) return; $this->json((new AdminDataTableService())->jobs($_GET,$this->baseUrl(),$this->user() ?? [])); }
    public function articlesData(): void { if(!$this->requirePermission('products.view')) return; $this->json($this->withoutActions((new AdminDataTableService())->articles($_GET,$this->baseUrl()))); }
    public function auditsData(): void { if(!$this->requirePermission('audit.view')) return; $this->json((new AdminDataTableService())->audits($_GET)); }
    public function importCsvTemplate(): void { $this->downloadImportTemplate('csv'); }
    public function importJsonTemplate(): void { $this->downloadImportTemplate('json'); }
    public function productSave(): void {
        if(!$this->requirePermission('products.manage')) return;
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
        if(!$this->requirePermission('products.manage')) return;
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
        if(!$this->requirePermission('products.manage')) return;
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
        if(!$this->requirePermission('products.manage')) return;
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
    /**
     * LS-26: bulk alias import. Two steps on one endpoint and nothing kept between them: "check" shows what each line would do and writes nothing; "confirm" re-reads the
     * same text, inserts only the lines that are new (one transaction), and audits the batch with the acting admin.
     */
    public function aliasImport(): void {
        if(!$this->requirePermission('products.manage')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/product-aliases'); }
        global $config;
        $db=Database::connection($config['db']);
        $text=substr((string)($_POST['alias_lines'] ?? ''),0,200000);
        $ids=[]; foreach($db->query("SELECT id,slug FROM products")->fetchAll(\PDO::FETCH_ASSOC) as $r){ $ids[strtolower((string)$r['slug'])]=(int)$r['id']; }
        $existing=[]; foreach($db->query("SELECT normalized_alias,product_id FROM product_aliases")->fetchAll(\PDO::FETCH_ASSOC) as $r){ $existing[(string)$r['normalized_alias']]=(int)$r['product_id']; }
        $plan=\App\Services\Catalog\AliasImport::plan($text,$ids,$existing);
        if(($_POST['mode'] ?? '')==='confirm' && $plan['counts']['new']>0){
            $db->beginTransaction();
            try{
                $stmt=$db->prepare("INSERT INTO product_aliases (product_id,alias_text,normalized_alias,source_note,confidence,created_at,updated_at) VALUES (:p,:a,:n,:s,100,NOW(),NOW())");
                $added=0;
                foreach($plan['rows'] as $r){ if($r['status']!=='new') continue; $stmt->execute(['p'=>$ids[$r['slug']],'a'=>$r['alias'],'n'=>$this->normalizeAlias($r['alias']),'s'=>$r['source'] ?? 'Bulk import']); $added++; }
                $db->commit();
            }catch(\Throwable $e){ if($db->inTransaction()) $db->rollBack(); $this->flash('danger','Alias import failed; nothing was saved.'); $this->redirect('/admin/product-aliases'); }
            $this->audit('product_aliases_imported','product_alias',null,['added'=>$added,'skipped'=>array_sum($plan['counts'])-$added]);
            $this->flash('success',t('msg.imported_aliases',['n'=>$added]));
            $this->redirect('/admin/product-aliases');
        }
        $d=new Dashboard();
        $this->render('aliases','Product Aliases',['canManage'=>$this->canManage(),'aliases'=>$d->aliases(),'products'=>$d->reviewProductOptions(),'import'=>$plan,'importText'=>$text]);
    }
    /** LS-55: the optional category of an article, written only when the database has the column (migration 030) */
    private function saveArticleCategory(\PDO $db,int $id): void {
        if($id<1 || !\App\Services\Blog\ArticleCategory::columnExists($db)) return;
        $db->prepare('UPDATE articles SET category=:c WHERE id=:id')->execute(['c'=>\App\Services\Blog\ArticleCategory::normalize($_POST['category'] ?? null),'id'=>$id]);
    }

    public function articleSave(): void {
        if(!$this->requirePermission('products.manage')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/articles'); }
        global $config;
        $db=Database::connection($config['db']);
        $id=filter_input(INPUT_POST,'article_id',FILTER_VALIDATE_INT) ?: null;
        $title=trim((string)($_POST['title'] ?? ''));
        $slug=$this->slug(trim((string)($_POST['slug'] ?? $title)));
        $status=(string)($_POST['status'] ?? 'draft'); if(!in_array($status,['draft','published','archived'],true)){ $status='draft'; }   // LS-20 C4: same fix, a missing status used to be saved as ''
        $body=trim((string)($_POST['body'] ?? ''));
        if($title==='' || $slug==='' || $body===''){
            $this->flash('danger','Article requires title, slug, and body.');
            $this->redirect('/admin/articles');
        }
        $pubAt=\App\Services\Blog\PublishAt::parse(isset($_POST['publish_at']) ? (string)$_POST['publish_at'] : null);   // LS-25: optional future publish time
        try{
            if($id){
                $stmt=$db->prepare("UPDATE articles SET title=:title,slug=:slug,excerpt=:excerpt,body=:body,status=:status,published_at=CASE WHEN :status_pub0='published' AND :pub_at0 IS NOT NULL THEN :pub_at1 WHEN :status_pub='published' AND published_at IS NULL THEN NOW() WHEN :status_pub2='published' THEN published_at ELSE NULL END,updated_at=NOW() WHERE id=:id");
                $stmt->execute(['title'=>$title,'slug'=>$slug,'excerpt'=>trim((string)($_POST['excerpt'] ?? '')) ?: null,'body'=>$body,'status'=>$status,'status_pub'=>$status,'status_pub2'=>$status,'status_pub0'=>$status,'pub_at0'=>$pubAt,'pub_at1'=>$pubAt,'id'=>$id]);
                $this->saveArticleCategory($db,(int)$id);
                $this->audit('article_updated','article',$id,['slug'=>$slug,'status'=>$status]);
                $this->flash('success','Article updated.');
            } else {
                $stmt=$db->prepare("INSERT INTO articles (author_id,title,slug,excerpt,body,status,published_at,created_at,updated_at) VALUES (:author_id,:title,:slug,:excerpt,:body,:status,CASE WHEN :status_pub0='published' AND :pub_at0 IS NOT NULL THEN :pub_at1 WHEN :status_pub='published' THEN NOW() ELSE NULL END,NOW(),NOW())");
                $stmt->execute(['author_id'=>(int)$this->user()['id'],'title'=>$title,'slug'=>$slug,'excerpt'=>trim((string)($_POST['excerpt'] ?? '')) ?: null,'body'=>$body,'status'=>$status,'status_pub'=>$status,'status_pub0'=>$status,'pub_at0'=>$pubAt,'pub_at1'=>$pubAt]);
                $this->saveArticleCategory($db,(int)$db->lastInsertId());
                $this->audit('article_created','article',(int)$db->lastInsertId(),['slug'=>$slug,'status'=>$status]);
                $this->flash('success','Article created.');
            }
        } catch(\PDOException $e){
            $this->flash('danger',$e->getCode()==='23000' ? 'Article slug already exists.' : 'Article save failed.');
        }
        $this->redirect('/admin/articles');
    }
    public function articleArchive(): void {
        if(!$this->requirePermission('products.manage')) return;
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
    /** LS-60: add, list and delete in-game FPS results (CPU/GPU pages); same validation as cli/game_fps_import.php */
    public function gameFpsPage(): void {
        if(!$this->requirePermission('products.view')) return;
        global $config; $db=Database::connection($config['db']);
        $has=\App\Services\Catalog\GameFpsImport::tableExists($db);
        $rows=$has ? $db->query("SELECT r.id,r.game_key,r.resolution,r.quality,r.avg_fps,r.low1_fps,r.paired_part,r.source_name,r.source_url,r.sponsor_name,p.full_name FROM game_fps_results r JOIN products p ON p.id=r.product_id ORDER BY r.id DESC LIMIT 200")->fetchAll() : [];
        $this->render('game-fps','Game FPS',['has_table'=>$has,'rows'=>$rows,'products'=>$has ? \App\Services\Catalog\GameFpsImport::products($db) : [],'can_manage'=>$this->canManage()]);
    }
    public function gameFpsSave(): void {
        if(!$this->requirePermission('products.manage')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/game-fps'); }
        global $config; $db=Database::connection($config['db']);
        if(!\App\Services\Catalog\GameFpsImport::tableExists($db)) { $this->flash('danger','Migration 031 is not applied on this database.'); $this->redirect('/admin/game-fps'); }
        $pid=(int)($_POST['product_id'] ?? 0);
        $known=array_map('intval',array_column(\App\Services\Catalog\GameFpsImport::products($db),'id'));
        $c=\App\Services\Catalog\GameFpsImport::clean($_POST);
        if(!in_array($pid,$known,true)) { $this->flash('danger','Choose a CPU or GPU model.'); }
        elseif($c['data']===null) { $this->flash('danger','Not saved: '.$c['error']); }
        else {
            \App\Services\Catalog\GameFpsImport::store($db,$pid,$c['data']);
            $this->audit('game_fps_saved','product',$pid,['game'=>$c['data']['game_key'],'resolution'=>$c['data']['resolution'],'sponsor'=>$c['data']['sponsor_name']]);
            $this->flash('success','Result saved.');
        }
        $this->redirect('/admin/game-fps');
    }
    public function gameFpsDelete(): void {
        if(!$this->requirePermission('products.manage')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/game-fps'); }
        $id=filter_input(INPUT_POST,'result_id',FILTER_VALIDATE_INT);
        if($id){
            global $config;
            Database::connection($config['db'])->prepare("DELETE FROM game_fps_results WHERE id=:id")->execute(['id'=>$id]);
            $this->audit('game_fps_deleted','game_fps_result',(int)$id,[]);
            $this->flash('success','Result deleted.');
        }
        $this->redirect('/admin/game-fps');
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
        if(!$dryRun){
            $pending=$_SESSION['admin_import_pending'] ?? null;
            if(!is_array($pending) || empty($pending['file']) || empty($pending['hash'])){
                $this->flash('danger','Run a successful Dry Run before confirming an import.');
                $this->redirect('/admin/collector-jobs');
            }
            $file=(string)$pending['file'];
            if(!is_file($file) || !is_readable($file) || hash_file('sha256',$file) !== (string)$pending['hash']){
                unset($_SESSION['admin_import_pending']);
                $this->flash('danger','The dry-run file is no longer available or has changed. Upload it and run Dry Run again.');
                $this->redirect('/admin/collector-jobs');
            }
            $dataset=(string)($pending['dataset'] ?? 'test');
            $limit=max(1,min(1000,(int)($pending['limit'] ?? 100)));
        } else {
            $dataset=strtolower((string)($_POST['dataset'] ?? 'test')) === 'real' ? 'real' : 'test';
            $limit=max(1,min(1000,(int)($_POST['limit'] ?? 100)));
            $file=$this->storedUploadPath($_FILES['evidence_file'] ?? null);
            if($file==='' && (int)($_FILES['evidence_file']['size'] ?? 0) > OfflineEvidenceImportService::MAX_FILE_BYTES){
                unset($_SESSION['admin_import_pending']);
                $this->flash('danger',t('msg.file_too_large',['mb'=>(OfflineEvidenceImportService::MAX_FILE_BYTES/1048576)]));
                $this->redirect('/admin/collector-jobs');
            }
            if($file===''){
                unset($_SESSION['admin_import_pending']);
                $this->flash('danger','Upload a CSV or JSON evidence file.');
                $this->redirect('/admin/collector-jobs');
            }
        }
        $summary=(new OfflineEvidenceImportService())->import($file,$dataset,$dryRun,$limit);
        $_SESSION['admin_import_summary']=$summary;
        if($dryRun){
            if((int)($summary['valid_rows'] ?? 0)>0){
                $_SESSION['admin_import_pending']=[
                    'file'=>$file,
                    'hash'=>hash_file('sha256',$file),
                    'dataset'=>$dataset,
                    'limit'=>$limit,
                    'name'=>basename($file),
                    'summary'=>$summary,
                    'created_at'=>date('Y-m-d H:i:s'),
                ];
            } else {
                unset($_SESSION['admin_import_pending']);
            }
        } else {
            unset($_SESSION['admin_import_pending']);
        }
        $this->flash(($summary['invalid_rows'] ?? 0)>0 && ($summary['valid_rows'] ?? 0)===0 ? 'danger' : 'success',t($dryRun ? 'msg.dry_run_done' : 'msg.import_done',['valid'=>(int)($summary['valid_rows'] ?? 0),'invalid'=>(int)($summary['invalid_rows'] ?? 0),'dup'=>(int)($summary['duplicate_rows'] ?? 0)]));
        $this->redirect('/admin/collector-jobs');
    }
    private function storedUploadPath(?array $file): string {
        if(!$file || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return '';
        $name=(string)($file['name'] ?? '');
        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if(!in_array($ext,['csv','json'],true)) return '';
        if((int)($file['size'] ?? 0) > OfflineEvidenceImportService::MAX_FILE_BYTES) return ''; // same cap the importer enforces, checked before the file is stored
        $dir=ROOT_PATH.'/storage/import_uploads';
        if(!is_dir($dir)) mkdir($dir,0775,true);
        $target=$dir.'/admin-import-'.date('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$ext;
        $tmp=(string)($file['tmp_name'] ?? '');
        if(move_uploaded_file($tmp,$target)) return $target;
        if(defined('MODINSPECT_TESTING') && is_file($tmp) && copy($tmp,$target)) return $target;
        return '';
    }
    private function downloadImportTemplate(string $format): void {
        if(!$this->requirePermission('collection.jobs.view')) return;
        $format=strtolower($format);
        if($format==='csv'){
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="modinspect-market-evidence-template.csv"');
            echo ImportFieldContract::csvTemplate();
            return;
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="modinspect-market-evidence-template.json"');
        echo ImportFieldContract::jsonTemplate();
    }
    private function json(array $payload): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload,JSON_UNESCAPED_UNICODE);
    }
    /** the message is a fixed text in the code (English or Thai); it is shown in the language chosen when the page is displayed after the redirect */
    private function flash(string $type,string $message): void { $_SESSION['flash']=['type'=>$type,'message'=>\App\Core\Lang::message($message)]; }
    private function baseUrl(): string { global $config; return (string)$config['base_url']; }
    private function audit(string $action,string $entityType,?int $entityId,array $after): void { (new AuthService())->audit((int)$this->user()['id'],$action,$entityType,$entityId,$after); }
    private function slug(string $value): string {
        $slug=preg_replace('/[^a-z0-9ก-๙]+/iu','-',mb_strtolower(trim($value),'UTF-8')) ?? '';
        return trim($slug,'-');
    }
    private function normalizeAlias(string $value): string { return \App\Services\Catalog\AliasImport::normalize($value); }
}
