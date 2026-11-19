<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Controller;
use App\Models\Dashboard;
use App\Models\Product;
use App\Models\SpecBuild;
use App\Core\Csrf;
use App\Services\SpecBuilderService;

final class ModuleController extends Controller {
    public function compare(string $slug=''): void {
        $model=new Product();
        // the model in the path comes first, then the picker's m[] (a visitor may also type the query by hand); only known active slugs survive, at most four
        $wanted=array_merge($slug!=='' ? [$slug] : [], array_values(array_filter((array)($_GET['m']??[]),'is_string')));
        $items=[]; $chosen=[];
        foreach($wanted as $s){
            $s=trim($s); if($s===''||isset($chosen[$s])||count($chosen)>=\App\Services\Catalog\CompareTable::MAX_MODELS){ continue; }
            $p=$model->findBySlug($s); if(!$p){ continue; }
            $chosen[$s]=true;
            $items[]=['product'=>$p,'facts'=>\App\Services\Catalog\ProductFacts::rows($p,$model->referenceSpecs((int)$p['id']),(int)date('Y'))];
        }
        $options=array_map(static fn(array $p):array=>['slug'=>$p['slug'],'name'=>$p['full_name'],'category'=>$p['category_name']],$model->search());
        $this->view('modules/compare',['title'=>'เปรียบเทียบสินค้า','table'=>\App\Services\Catalog\CompareTable::build($items),'options'=>$options,'chosen'=>array_keys($chosen)]);
    }
    public function build(string $slug=''): void {
        $result=$slug!=='' ? (new SpecBuild())->find($slug) : null;
        if($slug!=='' && !$result){ http_response_code(404);$this->view('errors/404',['title'=>'ไม่พบสเปก']);return; }
        $products=(new Product())->search(); $catalog=$this->buildCatalog(); $newRefs=$catalog->newPriceRefs();
        $buildStats=[]; $buildChecks=[];
        if($result){
            // LS-14 OD-1: the six spec-based stats and the compatibility checks of the saved parts, recomputed from today's catalog (nothing stored)
            $byId=[]; foreach($products as $p) $byId[(int)$p['id']]=$p;
            $byRole=[]; foreach($result['items'] as $it) if(isset($byId[(int)$it['product_id']])) $byRole[strtolower((string)$it['component_role'])]=$byId[(int)$it['product_id']];
            $parts=$catalog->parts($byRole,$newRefs);
            $buildStats=\App\Services\Build\BuildStats::axes($parts,$catalog->statsReference($products,$newRefs));
            $buildChecks=\App\Services\Build\BuildCompatibility::check($parts);
        }
        $this->view('modules/build',['title'=>'จัดสเปกคอมมือสอง','products'=>$products,'result'=>$result,'errors'=>[],'old'=>[],'newRefs'=>$newRefs,'buildStats'=>$buildStats,'buildChecks'=>$buildChecks]);
    }
    private function buildCatalog(): \App\Services\Build\BuildCatalog { global $config; return new \App\Services\Build\BuildCatalog(\App\Core\Database::connection($config['db'])); }
    public function storeBuild(): void {
        // public POST: 30 per 10 minutes per client address (REMOTE_ADDR; behind a proxy every visitor shares the proxy address, see the runbook)
        $limit=(new \App\Services\Http\RateLimiter(ROOT_PATH.'/storage/cache/ratelimit'))->attempt('build',(string)($_SERVER['REMOTE_ADDR']??''),30,600);
        if(!$limit['allowed']) { http_response_code(429); header('Retry-After: '.$limit['retry_after']); $this->view('errors/429',['title'=>'ส่งคำขอบ่อยเกินไป','retryAfter'=>$limit['retry_after']]); return; }
        $products=(new Product())->search(); $buildCatalog=$this->buildCatalog(); $newRefs=$buildCatalog->newPriceRefs();
        $budget=filter_input(INPUT_POST,'budget',FILTER_VALIDATE_FLOAT);
        $use=(string)($_POST['intended_use']??'');
        $resolution=(string)($_POST['resolution']??'');
        $mix=(string)($_POST['mix_preference']??'');
        $errors=[];
        if(!Csrf::verify($_POST['_token']??null)) $errors[]='แบบฟอร์มหมดอายุ กรุณาลองใหม่';
        if($budget===false || $budget<5000 || $budget>500000) $errors[]='งบประมาณต้องอยู่ระหว่าง 5,000–500,000 บาท';
        if(!in_array($use,['gaming','work','streaming','office','ai'],true)) $errors[]='กรุณาเลือกการใช้งาน';
        if(!in_array($resolution,['1080p','1440p','4k','none'],true)) $errors[]='กรุณาเลือกความละเอียด';
        if(!in_array($mix,['used','mixed','new'],true)) $errors[]='กรุณาเลือกประเภทอุปกรณ์';
        if($errors){$this->view('modules/build',['title'=>'จัดสเปกคอมมือสอง','products'=>$products,'newRefs'=>$newRefs??[],'result'=>null,'errors'=>$errors,'old'=>$_POST]);return;}
        if(($_POST['action']??'')==='recommend'){
            // LS-11 A: choose parts for the visitor, show them ticked in the same form (nothing is saved until the visitor presses save)
            $preset=(string)($_POST['preset']??'balanced');
            if(!in_array($preset,['saving','balanced','full'],true)){$this->view('modules/build',['title'=>'จัดสเปกคอมมือสอง','products'=>$products,'newRefs'=>$newRefs??[],'result'=>null,'errors'=>['กรุณาเลือกรูปแบบการแนะนำ'],'old'=>$_POST]);return;}
            $rec=(new SpecBuilderService())->recommend($products,(float)$budget,$use,$resolution,$preset);
            if(!$rec['selected']){$this->view('modules/build',['title'=>'จัดสเปกคอมมือสอง','products'=>$products,'newRefs'=>$newRefs??[],'result'=>null,'errors'=>['ข้อมูลราคายังไม่เพียงพอสำหรับแนะนำสเปก'],'old'=>$_POST]);return;}
            $this->view('modules/build',['title'=>'จัดสเปกคอมมือสอง','products'=>$products,'newRefs'=>$newRefs??[],'result'=>null,'errors'=>[],'old'=>['budget'=>$_POST['budget'],'intended_use'=>$use,'resolution'=>$resolution,'preset'=>$preset,'parts'=>$rec['selected']],'recommendation'=>$rec]);
            return;
        }
        $componentKeys=['cpu','motherboard','gpu','ram','storage','psu','case','cooling'];
        $selected=[]; foreach($componentKeys as $key) $selected[$key]=(int)($_POST['parts'][$key]??0);
        $catalog=[];foreach($products as $product)$catalog[(int)$product['id']]=strtolower((string)$product['category_name']);
        foreach(array_filter($selected) as $key=>$selectedId){
            if(!isset($catalog[$selectedId])){$errors[]='ชิ้นส่วนที่เลือกไม่อยู่ในฐานข้อมูล';break;}
            if($catalog[$selectedId]!==$key){$errors[]='ชิ้นส่วนที่เลือกไม่ตรงกับหมวด '.strtoupper($key);break;}
        }
        if($errors){$this->view('modules/build',['title'=>'จัดสเปกคอมมือสอง','products'=>$products,'newRefs'=>$newRefs??[],'result'=>null,'errors'=>$errors,'old'=>$_POST]);return;}
        $plan=(new SpecBuilderService())->generate($products,(float)$budget,$use,$resolution,$mix,$selected,$newRefs);
        if(!$plan['compatibilityError']){
            $byId=[]; foreach($products as $p) $byId[(int)$p['id']]=$p;
            $byRole=[]; foreach(array_filter($selected) as $role=>$id) if(isset($byId[$id])) $byRole[$role]=$byId[$id];
            $checks=\App\Services\Build\BuildCompatibility::check($buildCatalog->parts($byRole,$newRefs));
            if(\App\Services\Build\BuildCompatibility::blocked($checks)) $plan['compatibilityError']=implode('; ',array_column(array_filter($checks,static fn($c)=>$c['status']==='fail'&&$c['blocking']),'text'));
        }
        if(!$plan['items']){$this->view('modules/build',['title'=>'จัดสเปกคอมมือสอง','products'=>$products,'newRefs'=>$newRefs??[],'result'=>null,'errors'=>['ข้อมูลราคายังไม่เพียงพอสำหรับสร้างสเปก'],'old'=>$_POST]);return;}
        if($plan['compatibilityError']){$this->view('modules/build',['title'=>'จัดสเปกคอมมือสอง','products'=>$products,'newRefs'=>$newRefs??[],'result'=>null,'errors'=>[$plan['compatibilityError']],'old'=>$_POST]);return;}
        $uuid=(new SpecBuild())->create(['budget_min'=>(float)$budget*.9,'budget_max'=>(float)$budget,'intended_use'=>$use,'target_resolution'=>$resolution,'mix_preference'=>$mix,'member_id'=>!empty($_SESSION[\App\Services\Member\MemberAuth::SESSION_MEMBER_ID])?(int)$_SESSION[\App\Services\Member\MemberAuth::SESSION_MEMBER_ID]:null,'estimated_low'=>$plan['low'],'estimated_expected'=>$plan['expected'],'estimated_high'=>$plan['high'],'compatibility_notes'=>$plan['notes']],$plan['items']);
        $this->redirect('/build/'.$uuid);
    }
    public function sellerAdvisor(): void {
        $products=(new Product())->search(); $result=null;
        if(isset($_GET['product_id'],$_GET['condition'])) foreach($products as $p) if((int)$p['id']===(int)$_GET['product_id']){
            if(empty($p['median']) || (int)($p['valid_sample_size']??0)<=0 || (int)($p['accepted_observation_count']??0)<=0) break;
            $factor=['like_new'=>1.08,'good'=>1,'fair'=>.9,'poor'=>.78][$_GET['condition']]??1;
            $result=['product'=>$p,'fast'=>(float)$p['q1']*$factor,'market'=>(float)$p['median']*$factor,'premium'=>(float)$p['q3']*$factor];
        }
        $this->view('modules/seller-advisor',['title'=>'ผู้ช่วยตั้งราคาขาย','products'=>$products,'result'=>$result]);
    }
    public function conditionGuide(string $category=''): void {
        $guide=$category!=='' ? \App\Services\Catalog\ConditionGuide::for($category) : null;
        if($category!=='' && !$guide){ http_response_code(404);$this->view('errors/404',['title'=>'ไม่พบคู่มือ']);return; }
        $this->view('modules/condition-guide',['title'=>$guide ? 'คู่มือเช็กสภาพ '.$guide['name'] : 'คู่มือเช็กสภาพก่อนซื้อ','guide'=>$guide,'categories'=>\App\Services\Catalog\ConditionGuide::categories()]);
    }
    public function guide(string $slug): void { $this->view('modules/guide',['title'=>'คู่มือซื้อคอมมือสอง','slug'=>$slug]); }
    /** F-6: unfinished modules are not public; the views stay for later (earlier bodies are in git history), the address answers 404 like an unknown page */
    private function notPublic(): void { http_response_code(404); $this->view('errors/404',['title'=>'ไม่พบหน้า']); }
    public function sellerDashboard(): void { $this->notPublic(); }
    public function localMatch(): void { $this->notPublic(); }
    public function marketReport(): void { $this->notPublic(); }
    public function shop(): void { $this->notPublic(); }
    public function inventory(): void { $this->notPublic(); }
}
