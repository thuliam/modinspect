<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\DealCheck;
use App\Models\Product;
use App\Services\PriceEngine;

final class DealCheckerController extends Controller {
    public function form(): void { $this->view('deal/form',['title'=>'Deal Checker','products'=>(new Product())->search(),'errors'=>[]]); }
    public function store(): void {
        // public POST that writes a row: 20 per 10 minutes per client address (REMOTE_ADDR; behind a proxy every visitor shares the proxy address, see the runbook)
        $limit = (new \App\Services\Http\RateLimiter(ROOT_PATH . '/storage/cache/ratelimit'))->attempt('deal-checker', (string)($_SERVER['REMOTE_ADDR'] ?? ''), 20, 600);
        if (!$limit['allowed']) { http_response_code(429); header('Retry-After: ' . $limit['retry_after']); $this->view('errors/429', ['title' => 'ส่งคำขอบ่อยเกินไป', 'retryAfter' => $limit['retry_after']]); return; }
        if (!Csrf::verify($_POST['_token']??null)) { http_response_code(419); $this->view('errors/419',['title'=>'แบบฟอร์มหมดอายุ']); return; }
        $id=filter_input(INPUT_POST,'product_id',FILTER_VALIDATE_INT); $price=filter_input(INPUT_POST,'user_price',FILTER_VALIDATE_FLOAT);
        $product=$id ? (new Product())->find((int)$id) : null;
        if (!$product || $price===false || $price<=0 || $price>10000000) {
            $this->view('deal/form',['title'=>'Deal Checker','products'=>(new Product())->search(),'errors'=>['กรุณาเลือกสินค้าและกรอกราคาที่ถูกต้อง']]); return;
        }
        $result=(new PriceEngine())->evaluate($product,(float)$price);
        $uuid=(new DealCheck())->create([
            'product_id'=>$id,'user_price'=>$price,'condition_level'=>in_array((string)($_POST['condition_level']??''),['like_new','good','fair','poor','unknown'],true) ? (string)$_POST['condition_level'] : 'unknown',
            'warranty_months'=>($_POST['warranty_months']??'')!=='' ? max(0,min(120,(int)$_POST['warranty_months'])) : null,
            'has_box'=>isset($_POST['has_box'])?1:0,'has_receipt'=>isset($_POST['has_receipt'])?1:0,
            'has_benchmark'=>isset($_POST['has_benchmark'])?1:0,'result_label'=>$result['label'],
            'min'=>$product['q1'],'max'=>$product['q3'],'recommendation'=>$result['recommendation'],
            'warning'=>$result['warning'],'consent'=>isset($_POST['consent'])?1:0,
        ]);
        $this->redirect('/deal-checker/result/'.$uuid);
    }
    /** LS-21: the share card of a saved result as a PNG. Drawn once per result and kept for a day; carries nothing the result page does not show. */
    public function card(string $uuid): void {
        $deal=(\App\Services\Deal\ShareCard::available(ROOT_PATH.'/public/assets/fonts') && preg_match('/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/',$uuid)) ? (new DealCheck())->findByUuid($uuid) : null;
        if (!$deal) { http_response_code(404); $this->view('errors/404',['title'=>'ไม่พบผลการประเมิน']); return; }
        $png=(new \App\Services\Http\FileCache(ROOT_PATH.'/storage/cache/http'))->remember('deal-card-'.$uuid,86400,static function() use($deal):string {
            $x=\App\Services\DealExplanation::build($deal);
            $labels=['below_range'=>'ต่ำกว่าช่วงราคาที่พบ','in_range'=>'อยู่ในช่วงตลาด','above_range'=>'สูงกว่าช่วงที่พบ','insufficient'=>'ข้อมูลยังไม่เพียงพอ'];
            $q1=(float)($deal['suggested_price_min']??0) ?: (float)($deal['q1']??0); $q3=(float)($deal['suggested_price_max']??0) ?: (float)($deal['q3']??0);
            return \App\Services\Deal\ShareCard::render(['name'=>(string)$deal['full_name'],'price'=>(float)$deal['user_price'],'label'=>(string)(($x['label']??null) ?: ($labels[$deal['result_label']]??'')),
                'tone'=>(string)($x['tone']??'neutral'),'range'=>($deal['result_label']!=='insufficient'&&$q1>0&&$q3>0)?[$q1,$q3]:null,'median'=>($deal['result_label']!=='insufficient'&&((float)($deal['median']??0))>0)?(float)$deal['median']:null],ROOT_PATH.'/public/assets/fonts',ROOT_PATH.'/public/assets/brand/modinpect-lockup-dark-480.png');
        });
        header('Content-Type: image/png'); header('Content-Length: '.strlen($png)); header('Cache-Control: public, max-age=3600'); header('Content-Disposition: inline; filename="modinpect-deal.png"'); header('X-Robots-Tag: noindex');
        echo $png;
    }
    /** LS-28 B1: "what happened after this result". Stores the outcome (and the price actually paid, if given) of this one result; never touches a price snapshot. */
    public function outcome(string $uuid): void {
        if (!\App\Core\Csrf::verify($_POST['_token']??null)) { http_response_code(419); $this->view('errors/419',['title'=>'แบบฟอร์มหมดอายุ']); return; }
        $model=new DealCheck(); $deal=preg_match('/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/',$uuid) ? $model->findByUuid($uuid) : null;
        if (!$deal || !$model->outcomeAvailable()) { http_response_code(404); $this->view('errors/404',['title'=>'ไม่พบผลการประเมิน']); return; }
        $v=\App\Services\Deal\DealOutcome::validate($_POST);
        if (!$v['ok']) { $_SESSION['deal_outcome_flash']=$v['error']==='price' ? 'ราคาที่จ่ายจริงต้องเป็นตัวเลขที่มากกว่า 0' : 'เลือกว่าเกิดอะไรขึ้นหลังดูผลนี้'; $this->redirect('/deal-checker/result/'.$uuid.'#outcome'); }
        $model->saveOutcome((int)$deal['id'],(string)$v['outcome'],$v['final_price']);
        $_SESSION['deal_outcome_flash']='บันทึกแล้ว ขอบคุณที่บอกให้เราทราบ';
        $this->redirect('/deal-checker/result/'.$uuid.'#outcome');
    }
    public function result(string $uuid): void {
        $deal=(new DealCheck())->findByUuid($uuid);
        if (!$deal) { http_response_code(404); $this->view('errors/404',['title'=>'ไม่พบผลการประเมิน']); return; }
        $outcomeModel=new DealCheck(); $outcomeAvailable=$outcomeModel->outcomeAvailable(); $outcomeSaved=$outcomeAvailable ? $outcomeModel->outcomeFor((int)$deal['id']) : null;
        $outcomeFlash=is_string($_SESSION['deal_outcome_flash']??null) ? $_SESSION['deal_outcome_flash'] : null; unset($_SESSION['deal_outcome_flash']);
        $model=new Product(); $product=$model->find((int)$deal['product_id']);
        // LS-21: the picture a chat app shows for the shared link (and the page's save button) is the card of this very result
        global $config; $cardOk=\App\Services\Deal\ShareCard::available(ROOT_PATH.'/public/assets/fonts');   // needs GD with FreeType; without it nothing is offered
        $ogImage=$cardOk ? rtrim((string)$config['base_url'],'/').'/deal-checker/result/'.rawurlencode((string)$deal['uuid']).'/card' : null;
        $this->view('deal/result',['outcomeAvailable'=>$outcomeAvailable,'outcomeSaved'=>$outcomeSaved,'outcomeFlash'=>$outcomeFlash,'title'=>'ผลการประเมินราคา','ogImage'=>$ogImage,'cardUrl'=>$ogImage,'ogWidth'=>$cardOk?\App\Services\Deal\ShareCard::WIDTH:null,'ogHeight'=>$cardOk?\App\Services\Deal\ShareCard::HEIGHT:null,'deal'=>$deal,'product'=>$product,'trend'=>$product?$model->trend((int)$product['id']):[],'similar'=>$product?$model->similar($product,(float)$deal['user_price'],4):[],'evidence_grades'=>$product?$model->evidenceGrades((int)$product['id']):['total'=>0,'owner_text'=>0]]);
    }
}

