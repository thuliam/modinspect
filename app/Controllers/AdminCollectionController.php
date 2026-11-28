<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Database;
use App\Core\Csrf;
use App\Models\Dashboard;
use App\Services\Admin\AdminDataTableService;
use App\Services\Collection\ImportFieldContract;
use App\Services\Collection\OfflineEvidenceImportService;
use App\Services\Collection\SourceOperationsService;

/** LS-89 BE-4: sources, collection jobs, evidence imports and the owner intake. */
final class AdminCollectionController extends AdminBaseController {
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
    public function sourcesData(): void { if(!$this->requirePermission('sources.view')) return; $this->json((new AdminDataTableService())->sources($_GET,$this->baseUrl(),$this->user() ?? [])); }
    public function jobsData(): void { if(!$this->requirePermission('collection.jobs.view')) return; $this->json((new AdminDataTableService())->jobs($_GET,$this->baseUrl(),$this->user() ?? [])); }
    public function importCsvTemplate(): void { $this->downloadImportTemplate('csv'); }
    public function importJsonTemplate(): void { $this->downloadImportTemplate('json'); }
    public function importDryRun(): void { $this->handleImport(true); }
    public function importConfirm(): void { $this->handleImport(false); }
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
        $summary=(new OfflineEvidenceImportService())->import($file,$dataset,$dryRun,$limit,(int)$this->user()['id']);
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
}
