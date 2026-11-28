<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Database;
use App\Core\Csrf;
use App\Models\Dashboard;
use App\Services\Admin\AdminDataTableService;
use App\Services\Auth\AuthService;
use App\Services\Auth\MfaService;
use App\Services\Ops\JobRunner;
use App\Services\Ops\SystemHealth;

/** LS-89 BE-4: the dashboard, users, audit log and read-only reports. */
final class AdminController extends AdminBaseController {
    /** LS-89 BE-3: the detailed health list (admins only); the public /health stays a yes/no */
    public function systemHealth(): void {
        if(!$this->requirePermission('audit.view')) return;
        global $config; $db=Database::connection($config['db']);
        $runner=new JobRunner($db);
        $this->render('system-health','System health',['checks'=>(new SystemHealth($db))->checks(),'runs'=>$runner->recent(30),'jobsTable'=>$runner->available()]);
    }
    /** LS-89 BE-3: every signed-in staff member manages the second factor of their own account */
    public function accountSecurity(): void {
        if(!$this->requirePermission('admin.dashboard.view')) return;
        $this->accountSecurityPage([]);
    }
    public function accountSecurityAction(): void {
        if(!$this->requirePermission('admin.dashboard.view')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger',t('flash.csrf')); $this->redirect('/admin/account-security'); }
        $mfa=new MfaService();
        $id=(int)$this->user()['id'];
        $code=is_string($_POST['code'] ?? null) ? $_POST['code'] : '';
        $action=(string)($_POST['action']??'');
        if($action==='begin') {
            $r=$mfa->begin($id,(string)$this->user()['email']);
            if(!$r['ok']) $this->flash('danger',t('mfa.err.'.$r['error']));
        } elseif($action==='confirm') {
            $r=$mfa->confirm($id,$code);
            // the recovery codes are shown on this response only: they are never put in the session or a redirect
            if($r['ok']) { $this->flash('success',t('mfa.done.enabled')); $this->accountSecurityPage($r['recovery_codes']); return; }
            $this->flash('danger',t('mfa.err.'.$r['error']));
        } elseif($action==='disable') {
            $checked=(new AuthService())->checkSecondFactor($this->user(),$code,$_SERVER['REMOTE_ADDR'] ?? null);
            if($checked['ok'] && $mfa->remove($id,$id,'self')) $this->flash('success',t('mfa.done.disabled'));
            else $this->flash('danger',t('mfa.err.'.($checked['error'] ?? 'MFA_CODE_WRONG')));
        }
        $this->redirect('/admin/account-security');
    }
    private function accountSecurityPage(array $recoveryCodes): void {
        $mfa=new MfaService();
        $id=(int)$this->user()['id'];
        $this->render('account-security','Account security',['featureOn'=>$mfa->featureOn(),'canEnrol'=>$mfa->canEnrol(),'enrolled'=>$mfa->isEnrolled($id),'pending'=>$mfa->pending($id,(string)$this->user()['email']),'recoveryCodes'=>$recoveryCodes]);
    }
    public function index(): void { if(!$this->requirePermission('admin.dashboard.view')) return; $d=new Dashboard();$this->render('dashboard','Admin Data Quality',$d->adminStats()+['products'=>$d->products(),'sources'=>$d->sources(),'recent_imports'=>$d->recentImportRuns()]); }
    public function snapshotEvidence(string $id): void {
        if(!$this->requirePermission('snapshots.view')) return;
        $report=ctype_digit($id) ? (new \App\Services\Admin\SnapshotEvidenceReport())->forSnapshot((int)$id) : null;
        if(!$report) { http_response_code(404); $this->view('errors/404',['title'=>'Snapshot not found']); return; }
        $this->view('admin/snapshot-evidence',['title'=>'Snapshot #'.(int)$id.' evidence','report'=>$report,'history'=>(new \App\Services\Admin\SnapshotHistory())->forSnapshot((int)$id)]);
    }
    public function betaReadiness(): void {
        if(!$this->requirePermission('products.manage')) return;
        $this->render('beta-readiness','Beta readiness',['panel'=>(new \App\Services\Admin\BetaReadiness())->build()]);
    }
    /** LS-90 D4: give or take the content moderator capability */
    public function userCapability(): void {
        if(!$this->requirePermission('users.manage')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger',t('flash.csrf')); $this->redirect('/admin/users'); }
        global $config;
        $r=(new \App\Services\Auth\UserCapabilities(\App\Core\Database::connection($config['db'])))->set((int)(is_scalar($_POST['user_id']??null) ? $_POST['user_id'] : 0),'content_moderator',($_POST['held']??'')==='1',(int)$this->user()['id']);
        $this->flash($r['ok']?'success':'danger',t($r['ok']?'mod.done':'common.fail'));
        $this->redirect('/admin/users');
    }
    public function users(): void {
        if(!$this->requirePermission('users.manage')) return;
        $users=(new \App\Services\Admin\UserAdministration())->list();
        global $config; $caps=new \App\Services\Auth\UserCapabilities(\App\Core\Database::connection($config['db']));
        foreach($users as &$u) $u['capabilities']=$caps->listFor((int)$u['id']);
        unset($u);
        $this->render('users','Users',['users'=>$users,'me'=>(int)$this->user()['id'],'roles'=>\App\Services\Admin\UserAdministration::ROLES]);
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
    public function indices(): void { if(!$this->requirePermission('snapshots.view')) return; $d=new Dashboard();$this->render('indices','Price Indices',['products'=>$d->products(),'categories'=>$d->rows("SELECT id,name,slug FROM product_categories ORDER BY sort_order,name")]); }
    public function audits(): void { if(!$this->requirePermission('audit.view')) return; $d=new Dashboard();$this->render('audits','Audit Logs',['audits'=>$d->audits()]); }
    public function indicesData(): void { if(!$this->requirePermission('snapshots.view')) return; $this->json((new AdminDataTableService())->priceIndices($_GET,$this->baseUrl())); }
    public function auditsData(): void { if(!$this->requirePermission('audit.view')) return; $this->json((new AdminDataTableService())->audits($_GET)); }
    /** LS-62: reference page of the admin form/table/button pattern; admin only, no data */
    public function uiKitPage(): void {
        if(!$this->requirePermission('products.view')) return;
        $this->render('ui-kit','Admin UI kit',[]);
    }
}
