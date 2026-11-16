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
    public function result(string $uuid): void {
        $deal=(new DealCheck())->findByUuid($uuid);
        if (!$deal) { http_response_code(404); $this->view('errors/404',['title'=>'ไม่พบผลการประเมิน']); return; }
        $model=new Product(); $product=$model->find((int)$deal['product_id']);
        $this->view('deal/result',['title'=>'ผลการประเมินราคา','deal'=>$deal,'product'=>$product,'trend'=>$product?$model->trend((int)$product['id']):[],'similar'=>$product?$model->similar($product,(float)$deal['user_price'],4):[],'evidence_grades'=>$product?$model->evidenceGrades((int)$product['id']):['total'=>0,'owner_text'=>0]]);
    }
}

