<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Database;
use App\Core\Csrf;
use PDO;

/** LS-89 BE-4: privacy requests, legal documents and member capabilities. */
final class AdminMemberController extends AdminBaseController {
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
        catch(\RuntimeException $e) { $this->flash('danger',['NOT_A_DRAFT'=>t('ld.flash.not_draft'),'EMPTY_BODY'=>t('ld.flash.empty_body'),'UNFILLED_BLANK'=>t('ld.flash.unfilled')][$e->getMessage()]??t('common.fail')); }
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
}
