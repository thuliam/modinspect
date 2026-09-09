<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Csrf;
use App\Core\Controller;
use App\Models\Dashboard;
use App\Services\Auth\AuthService;
use App\Services\Auth\AuthorizationService;
use App\Services\Collection\SourceOperationsService;
use App\Services\Review\ReviewCalibrationService;

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
    public function index(): void { if(!$this->requirePermission('admin.dashboard.view')) return; $d=new Dashboard();$this->render('dashboard','Admin Data Quality',$d->adminStats()+['products'=>$d->products(),'sources'=>$d->sources()]); }
    public function products(): void { if(!$this->requirePermission('products.view')) return; $d=new Dashboard();$this->render('products','Product Master',['products'=>$d->products()]); }
    public function aliases(): void { if(!$this->requirePermission('products.view')) return; $d=new Dashboard();$this->render('aliases','Product Aliases',['aliases'=>$d->aliases()]); }
    public function observations(): void { if(!$this->requirePermission('review.view')) return; $d=new Dashboard();$this->render('observations','Price Observations',['observations'=>$d->observations($_GET['status']??null,trim((string)($_GET['q']??''))),'status'=>$_GET['status']??'','q'=>trim((string)($_GET['q']??''))]); }
    public function review(): void { if(!$this->requirePermission('review.view')) return; $d=new Dashboard();$this->render('review','Observation Review',['observations'=>$d->reviewObservations(),'summary'=>$d->reviewSummary(),'products'=>$d->reviewProductOptions()]); }
    public function reviewAnalytics(): void { if(!$this->requirePermission('review.analytics.view')) return; $filters=['category'=>$_GET['category']??null,'source_id'=>$_GET['source_id']??null,'provider'=>$_GET['provider']??null,'from'=>$_GET['from']??null,'to'=>$_GET['to']??null,'lane'=>$_GET['lane']??null,'outcome'=>$_GET['outcome']??null]; $this->render('review-analytics','Review Analytics',['report'=>(new ReviewCalibrationService())->report($filters)]); }
    public function reviewCorrection(): void {
        if(!$this->requirePermission('review.correct')) return;
        if (!Csrf::verify($_POST['_token']??null)) { http_response_code(419); $this->view('errors/419',['title'=>'แบบฟอร์มหมดอายุ']); return; }
        $id=filter_input(INPUT_POST,'observation_id',FILTER_VALIDATE_INT);
        $field=(string)($_POST['field_name']??'');
        $value=$_POST['corrected_value']??null;
        $reason=trim((string)($_POST['reason']??''));
        if($id) (new Dashboard())->correctObservation((int)$id,$field,$value,$reason,(int)$this->user()['id']);
        $this->redirect('/admin/review-queue');
    }
    public function reviewDecision(): void {
        if(!$this->requirePermission('review.decide')) return;
        if (!Csrf::verify($_POST['_token']??null)) { http_response_code(419); $this->view('errors/419',['title'=>'แบบฟอร์มหมดอายุ']); return; }
        $id=filter_input(INPUT_POST,'observation_id',FILTER_VALIDATE_INT);
        $decision=(string)($_POST['decision']??'');
        if($id) (new Dashboard())->decideObservation((int)$id,$decision,trim((string)($_POST['notes']??'')),(int)$this->user()['id']);
        $this->redirect('/admin/review-queue');
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
    public function jobs(): void { if(!$this->requirePermission('collection.jobs.view')) return; $d=new Dashboard();$this->render('jobs','Import Jobs',['jobs'=>$d->jobs()]); }
    public function indices(): void { if(!$this->requirePermission('snapshots.view')) return; $d=new Dashboard();$this->render('indices','Price Indices',['products'=>$d->products()]); }
    public function articles(): void { if(!$this->requirePermission('products.view')) return; $this->render('placeholder','Articles',['description'=>'จัดการบทความ SEO, buying guides และสถานะการเผยแพร่']); }
    public function audits(): void { if(!$this->requirePermission('audit.view')) return; $d=new Dashboard();$this->render('audits','Audit Logs',['audits'=>$d->audits()]); }
}
