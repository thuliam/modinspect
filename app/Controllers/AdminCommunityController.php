<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Database;
use App\Core\Csrf;

/** LS-89 BE-4: moderation of seller listings, seller reports and community content. */
final class AdminCommunityController extends AdminBaseController {
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
}
