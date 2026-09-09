<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Controller;
use App\Models\Product;

final class PriceController extends Controller {
    public function index(): void {
        $q=trim((string)($_GET['q']??'')); $category=trim((string)($_GET['category']??'')) ?: null;
        $this->view('price/index',['title'=>'ดัชนีราคา','products'=>(new Product())->search($q,$category),'q'=>$q,'category'=>$category]);
    }
    public function show(string $slug): void {
        $model=new Product(); $product=$model->findBySlug($slug);
        if (!$product) { http_response_code(404); $this->view('errors/404',['title'=>'ไม่พบสินค้า']); return; }
        $this->view('price/show',['title'=>$product['full_name'],'product'=>$product,'history'=>$model->history((int)$product['id'])]);
    }
}

