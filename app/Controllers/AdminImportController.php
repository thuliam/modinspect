<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Database;
use App\Core\Csrf;
use PDO;

/** LS-89 BE-4: the import centre (catalog, specs, pictures, FPS files). */
final class AdminImportController extends AdminBaseController {
    /** LS-67/68: import centre. One page, one flow (template, upload, dry run, confirm) for Product Master, reference specs, product pictures and in-game FPS; history is the audit log (`import_applied`). */
    private const IMPORT_TYPES = ['products', 'ram', 'motherboard', 'storage', 'psu', 'specs', 'images', 'game_fps'];
    private const IMPORT_MAX_ROWS = 2000;
    private const IMPORT_MAX_IMAGES = 20;
    // PHP's default max_file_uploads; larger batches use cli/image_import.php
    private function importType(?string $t): string { $t=strtolower(trim((string)$t)); return in_array($t,self::IMPORT_TYPES,true) ? $t : 'products'; }
    private function importPlan(string $type, PDO $db, array $rows): array {
        return match($type){ 'game_fps'=>\App\Services\Catalog\GameFpsImport::plan($db,$rows), 'specs'=>\App\Services\Catalog\SpecImport::plan($db,$rows), 'ram'=>\App\Services\Catalog\RamImport::plan($db,$rows), 'motherboard'=>\App\Services\Catalog\MotherboardImport::plan($db,$rows), 'storage'=>\App\Services\Catalog\StorageImport::plan($db,$rows), 'psu'=>\App\Services\Catalog\PsuImport::plan($db,$rows), default=>\App\Services\Catalog\ProductImport::plan($db,$rows) };
    }
    private function importApplyPlan(string $type, PDO $db, array $plan): array {
        return match($type){ 'game_fps'=>\App\Services\Catalog\GameFpsImport::apply($db,$plan), 'specs'=>\App\Services\Catalog\SpecImport::apply($db,$plan), 'ram'=>\App\Services\Catalog\RamImport::apply($db,$plan), 'motherboard'=>\App\Services\Catalog\MotherboardImport::apply($db,$plan), 'storage'=>\App\Services\Catalog\StorageImport::apply($db,$plan), 'psu'=>\App\Services\Catalog\PsuImport::apply($db,$plan), default=>\App\Services\Catalog\ProductImport::apply($db,$plan) };
    }
    public function importCentrePage(): void {
        if(!$this->requirePermission('products.view')) return;
        global $config; $db=Database::connection($config['db']);
        $type=$this->importType($_GET['type'] ?? null);
        $result=$_SESSION['admin_import_centre_result'] ?? null;
        if(is_array($result) && ($result['type'] ?? '')!==$type) $result=null;
        $this->render('import-centre','Import centre',['type'=>$type,'result'=>$result,'can_manage'=>$this->canManage(),'fps_table'=>\App\Services\Catalog\GameFpsImport::tableExists($db),'images_table'=>\App\Services\Catalog\ImageImport::tableExists($db),'ram_ready'=>\App\Services\Catalog\RamImport::ready($db),'sp_ready'=>\App\Services\Catalog\ProductImport::hasColumns($db,['interface_type','media_type','power_watt','efficiency_rating','modular_type']),'max_images'=>self::IMPORT_MAX_IMAGES,'recent'=>$this->recentImports($db)]);
    }
    private function recentImports(PDO $db): array {
        try { return $db->query("SELECT created_at, action, after_data FROM audit_logs WHERE action IN ('import_applied') ORDER BY id DESC LIMIT 10")->fetchAll(); } catch(\PDOException) { return []; }
    }
    public function importCentreTemplate(): void {
        if(!$this->requirePermission('products.view')) return;
        $type=$this->importType($_GET['type'] ?? null);
        if($type==='images'){ http_response_code(404); echo 'No template: name the files after the model slug.'; return; }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="modinspect-'.str_replace('_','-',$type).'-template.csv"');
        echo match($type){ 'game_fps'=>\App\Services\Catalog\GameFpsImport::csvTemplate(), 'specs'=>\App\Services\Catalog\SpecImport::csvTemplate(), 'ram'=>\App\Services\Catalog\RamImport::csvTemplate(), 'motherboard'=>\App\Services\Catalog\MotherboardImport::csvTemplate(), 'storage'=>\App\Services\Catalog\StorageImport::csvTemplate(), 'psu'=>\App\Services\Catalog\PsuImport::csvTemplate(), default=>\App\Services\Catalog\ProductImport::csvTemplate() };
    }
    private function importResult(string $type,string $name,int $rows,array $plan): void {
        $count=['create'=>0,'update'=>0,'refused'=>0]; foreach($plan as $p) $count[$p['action']]=($count[$p['action']] ?? 0)+1;
        $_SESSION['admin_import_centre_result']=['type'=>$type,'name'=>$name,'rows'=>$rows,'count'=>$count,'lines'=>array_map(static fn($p)=>['line'=>$p['line'],'action'=>$p['action'],'name'=>$p['name'],'slug'=>$p['slug'],'error'=>$p['error']],array_slice($plan,0,200))];
    }
    public function importCentreDryRun(): void {
        if(!$this->requirePermission('products.manage')) return;
        $type=$this->importType($_POST['type'] ?? null); $back='/admin/import?type='.$type;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect($back); }
        $this->importForget();
        global $config; $db=Database::connection($config['db']);
        if($type==='images'){ $this->importImagesDryRun($db,$back); return; }
        $file=$this->storedUploadPath($_FILES['import_file'] ?? null);
        if($file===''){ $this->flash('danger','Upload a CSV or JSON file (within the size limit).'); $this->redirect($back); }
        if(in_array($type,['storage','psu'],true) && !\App\Services\Catalog\ProductImport::hasColumns($db,['interface_type','media_type','power_watt','efficiency_rating','modular_type'])){ @unlink($file); $this->flash('danger','The storage and PSU columns are not on this database yet.'); $this->redirect($back); }
        if($type==='ram' && !\App\Services\Catalog\RamImport::ready($db)){ @unlink($file); $this->flash('danger','The RAM columns are not on this database yet.'); $this->redirect($back); }
        if($type==='game_fps' && !\App\Services\Catalog\GameFpsImport::tableExists($db)){ @unlink($file); $this->flash('danger','The game FPS table is not on this database yet (migration 031).'); $this->redirect($back); }
        $rows=\App\Services\Catalog\GameFpsImport::readFile($file);
        if($rows===[] || count($rows)>self::IMPORT_MAX_ROWS){ @unlink($file); $this->flash('danger',$rows===[] ? 'The file has no readable rows (check the header line).' : 'The file has too many rows (most 2000).'); $this->redirect($back); }
        $plan=$this->importPlan($type,$db,$rows);
        $name=basename((string)($_FILES['import_file']['name'] ?? 'file'));
        $_SESSION['admin_import_centre']=['type'=>$type,'file'=>$file,'hash'=>hash_file('sha256',$file),'name'=>$name];
        $this->importResult($type,$name,count($rows),$plan);
        $this->redirect($back);
    }
    private function importForget(): void {
        $p=$_SESSION['admin_import_centre'] ?? null;
        if(is_array($p)){ if(!empty($p['file'])) @unlink((string)$p['file']); if(!empty($p['dir'])) $this->importRemoveDir((string)$p['dir']); }
        unset($_SESSION['admin_import_centre'],$_SESSION['admin_import_centre_result']);
    }
    private function importRemoveDir(string $dir): void {
        $base=realpath(ROOT_PATH.'/storage/import_uploads'); $real=realpath($dir);
        if($base===false || $real===false || !str_starts_with($real,$base.DIRECTORY_SEPARATOR)) return; // only ever inside the upload folder
        foreach(glob($real.'/*') ?: [] as $f) if(is_file($f)) @unlink($f);
        @rmdir($real);
    }
    private function importImagesDryRun(PDO $db,string $back): void {
        if(!\App\Services\Catalog\ImageImport::tableExists($db)){ $this->flash('danger','The product pictures table is not on this database yet (migration 033).'); $this->redirect($back); }
        $up=$_FILES['import_files'] ?? null; $names=(array)($up['name'] ?? []);
        if($names===[] || (int)($up['error'][0] ?? UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE){ $this->flash('danger','Choose the picture files.'); $this->redirect($back); }
        if(count($names)>self::IMPORT_MAX_IMAGES){ $this->flash('danger','Too many pictures in one batch (most 20).'); $this->redirect($back); }
        $dir=ROOT_PATH.'/storage/import_uploads/img-'.date('YmdHis').'-'.bin2hex(random_bytes(4));
        if(!is_dir($dir) && !mkdir($dir,0775,true)){ $this->flash('danger','The picture batch could not be stored.'); $this->redirect($back); }
        $list=[]; $hashes='';
        foreach($names as $i=>$orig){
            if((int)$up['error'][$i]!==UPLOAD_ERR_OK) continue;
            $target=$dir.'/'.$i.'-'.preg_replace('/[^A-Za-z0-9._-]/','_',basename((string)$orig));
            $tmp=(string)$up['tmp_name'][$i];
            if(!(move_uploaded_file($tmp,$target) || (defined('MODINSPECT_TESTING') && is_file($tmp) && copy($tmp,$target)))) continue;
            $list[]=['name'=>basename((string)$orig),'path'=>$target]; $hashes.=hash_file('sha256',$target);
        }
        if($list===[]){ $this->importRemoveDir($dir); $this->flash('danger','Choose the picture files.'); $this->redirect($back); }
        $meta=['source_name'=>trim((string)($_POST['source_name'] ?? '')),'source_url'=>trim((string)($_POST['source_url'] ?? '')),'license_note'=>trim((string)($_POST['license_note'] ?? '')),'alt'=>trim((string)($_POST['alt'] ?? ''))];
        $plan=\App\Services\Catalog\ImageImport::plan($db,$list,$meta);
        $name=count($list).' files';
        $_SESSION['admin_import_centre']=['type'=>'images','dir'=>$dir,'files'=>$list,'meta'=>$meta,'hash'=>hash('sha256',$hashes),'name'=>$name];
        $this->importResult('images',$name,count($list),$plan);
        $this->redirect($back);
    }
    public function importCentreApply(): void {
        if(!$this->requirePermission('products.manage')) return;
        $type=$this->importType($_POST['type'] ?? null); $back='/admin/import?type='.$type;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect($back); }
        $pending=$_SESSION['admin_import_centre'] ?? null;
        $ok=is_array($pending) && ($pending['type'] ?? '')===$type;
        if($ok && $type==='images'){
            $h=''; foreach((array)($pending['files'] ?? []) as $f){ if(!is_file((string)$f['path'])){ $ok=false; break; } $h.=hash_file('sha256',(string)$f['path']); }
            $ok=$ok && hash('sha256',$h)===(string)$pending['hash'];
        } elseif($ok){ $ok=is_file((string)$pending['file']) && hash_file('sha256',(string)$pending['file'])===(string)$pending['hash']; }
        if(!$ok){ $this->importForget(); $this->flash('danger','Run a successful Dry Run before confirming an import.'); $this->redirect($back); }
        global $config; $db=Database::connection($config['db']);
        $plan=$type==='images' ? \App\Services\Catalog\ImageImport::plan($db,(array)$pending['files'],(array)$pending['meta']) : $this->importPlan($type,$db,\App\Services\Catalog\GameFpsImport::readFile((string)$pending['file']));
        $ready=array_filter($plan,static fn($p)=>$p['data']!==null);
        if($ready===[]){ $this->flash('danger','No valid row to import.'); $this->redirect($back); }
        try {
            $done=$type==='images' ? \App\Services\Catalog\ImageImport::apply($db,$plan,ROOT_PATH.'/public/assets/products') : $this->importApplyPlan($type,$db,$plan);
        } catch(\Throwable $e){ $this->flash('danger','The import failed and nothing was saved.'); $this->redirect($back); }
        $this->audit('import_applied','import_'.$type,null,['type'=>$type,'file_sha256'=>substr((string)$pending['hash'],0,16),'file'=>(string)$pending['name'],'created'=>$done['created'],'updated'=>$done['updated'],'refused'=>count($plan)-count($ready)]+($type==='images' ? ['source'=>(string)($pending['meta']['source_name'] ?? ''),'licence'=>(string)($pending['meta']['license_note'] ?? '')] : []));
        $this->importForget();
        $this->flash('success',t('msg.import_centre_done',['created'=>$done['created'],'updated'=>$done['updated'],'refused'=>count($plan)-count($ready)]));
        $this->redirect($back);
    }
}
