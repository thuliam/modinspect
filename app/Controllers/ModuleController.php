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
        $model=new Product(); $primary=$model->findBySlug($slug) ?? $model->popular(1)[0] ?? null;
        $this->view('modules/compare',['title'=>'เปรียบเทียบสินค้า','primary'=>$primary,'products'=>$model->search()]);
    }
    public function build(string $slug=''): void {
        $result=$slug!=='' ? (new SpecBuild())->find($slug) : null;
        if($slug!=='' && !$result){ http_response_code(404);$this->view('errors/404',['title'=>'ไม่พบสเปก']);return; }
        $this->view('modules/build',['title'=>'จัดสเปกคอมมือสอง','products'=>(new Product())->search(),'result'=>$result,'errors'=>[],'old'=>[]]);
    }
    public function storeBuild(): void {
        $products=(new Product())->search();
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
        if($errors){$this->view('modules/build',['title'=>'จัดสเปกคอมมือสอง','products'=>$products,'result'=>null,'errors'=>$errors,'old'=>$_POST]);return;}
        $componentKeys=['cpu','motherboard','gpu','ram','storage','psu','case','cooling'];
        $selected=[]; foreach($componentKeys as $key) $selected[$key]=(int)($_POST['parts'][$key]??0);
        $catalog=[];foreach($products as $product)$catalog[(int)$product['id']]=strtolower((string)$product['category_name']);
        foreach(array_filter($selected) as $key=>$selectedId){
            if(!isset($catalog[$selectedId])){$errors[]='ชิ้นส่วนที่เลือกไม่อยู่ในฐานข้อมูล';break;}
            if($catalog[$selectedId]!==$key){$errors[]='ชิ้นส่วนที่เลือกไม่ตรงกับหมวด '.strtoupper($key);break;}
        }
        if($errors){$this->view('modules/build',['title'=>'จัดสเปกคอมมือสอง','products'=>$products,'result'=>null,'errors'=>$errors,'old'=>$_POST]);return;}
        $plan=(new SpecBuilderService())->generate($products,(float)$budget,$use,$resolution,$mix,$selected);
        if(!$plan['items']){$this->view('modules/build',['title'=>'จัดสเปกคอมมือสอง','products'=>$products,'result'=>null,'errors'=>['ข้อมูลราคายังไม่เพียงพอสำหรับสร้างสเปก'],'old'=>$_POST]);return;}
        if($plan['compatibilityError']){$this->view('modules/build',['title'=>'จัดสเปกคอมมือสอง','products'=>$products,'result'=>null,'errors'=>[$plan['compatibilityError']],'old'=>$_POST]);return;}
        $uuid=(new SpecBuild())->create(['budget_min'=>(float)$budget*.9,'budget_max'=>(float)$budget,'intended_use'=>$use,'target_resolution'=>$resolution,'mix_preference'=>$mix,'estimated_low'=>$plan['low'],'estimated_expected'=>$plan['expected'],'estimated_high'=>$plan['high'],'compatibility_notes'=>$plan['notes']],$plan['items']);
        $this->redirect('/build/'.$uuid);
    }
    public function sellerAdvisor(): void {
        $products=(new Product())->search(); $result=null;
        if(isset($_GET['product_id'],$_GET['condition'])) foreach($products as $p) if((int)$p['id']===(int)$_GET['product_id']){
            if(empty($p['median']) || (int)($p['valid_sample_size']??0)<=0 || (int)($p['accepted_observation_count']??0)<=0) break;
            $factor=['like_new'=>1.08,'good'=>1,'fair'=>.9,'poor'=>.78][$_GET['condition']]??1;
            $result=['product'=>$p,'fast'=>(float)$p['q1']*$factor,'market'=>(float)$p['median']*$factor,'premium'=>(float)$p['q3']*$factor];
        }
        $this->view('modules/seller-advisor',['title'=>'Seller Price Advisor','products'=>$products,'result'=>$result]);
    }
    public function guide(string $slug): void { $this->view('modules/guide',['title'=>'คู่มือซื้อคอมมือสอง','slug'=>$slug]); }
    public function sellerDashboard(): void { $this->view('modules/seller-dashboard',['title'=>'แดชบอร์ดผู้ขาย','products'=>(new Product())->popular()]); }
    public function localMatch(): void { $this->view('modules/local-match',['title'=>'Local Match','products'=>(new Product())->popular()]); }
    public function marketReport(): void { $this->view('modules/market-report',['title'=>'รายงานตลาด','products'=>(new Product())->search()]); }
    public function shop(): void { $d=new Dashboard();$this->view('modules/shop-dashboard',['title'=>'B2B Shop Dashboard','items'=>$d->inventory()]); }
    public function inventory(): void { $d=new Dashboard();$this->view('modules/inventory',['title'=>'จัดการสต็อก','items'=>$d->inventory()]); }
}
