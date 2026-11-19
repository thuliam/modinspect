<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Controller;
use App\Models\Product;

final class PriceController extends Controller {
    public function index(): void {
        $q=trim((string)($_GET['q']??'')); $category=trim((string)($_GET['category']??'')) ?: null;
        $model=new Product(); $products=$model->search($q,$category);
        // LS-16 U-1: order and "priced only" are display choices on the same rows (no new query); an unknown key falls back to the catalog order
        $sort=(string)($_GET['sort']??''); if(!isset(\App\Services\Catalog\PriceList::SORTS[$sort])){ $sort=''; }
        $pricedOnly=($_GET['priced']??'')==='1';
        $products=\App\Services\Catalog\PriceList::apply($products,$sort,$pricedOnly);
        // F-7: the names feed the browser's own autocomplete; when nothing was found, a typo-tolerant "did you mean" (suggestion only, results are never changed)
        $names=array_map(static fn(array $p):array=>['slug'=>$p['slug'],'full_name'=>$p['full_name']],$model->search());
        $didYouMean=!$products && $q!=='' ? \App\Services\Search\DidYouMean::suggest($q,$names) : [];
        $this->view('price/index',['title'=>'ดัชนีราคา','products'=>$products,'q'=>$q,'category'=>$category,'sort'=>$sort,'pricedOnly'=>$pricedOnly,'suggestions'=>$products ? [] : $model->popular(6),'names'=>$names,'didYouMean'=>$didYouMean]);
    }
    public function show(string $slug): void {
        $model=new Product(); $product=$model->findBySlug($slug);
        if (!$product) { http_response_code(404); $this->view('errors/404',['title'=>'ไม่พบสินค้า']); return; }
        // description only from the stored public snapshot, never invented: range, sample size and the asking-price caveat
        $meta = 'เช็กราคามือสองของ ' . $product['full_name'] . ' และดูว่าราคามาจากข้อมูลอะไร ก่อนซื้อขาย';
        if (!empty($product['median']) && (int)($product['valid_sample_size'] ?? 0) >= 3 && (int)($product['accepted_observation_count'] ?? 0) > 0) {
            $meta = $product['full_name'] . ' ราคาประกาศมือสองที่พบ ' . number_format((float)$product['q1']) . '–' . number_format((float)$product['q3']) . ' บาท จาก ' . (int)$product['valid_sample_size'] . ' ตัวอย่าง (ไม่ใช่ราคาปิดดีล)';
        }
        $this->view('price/show',['metaDescription'=>$meta,'title'=>$product['full_name'],'product'=>$product,'history'=>$model->history((int)$product['id']),'facts'=>\App\Services\Catalog\ProductFacts::rows($product,$model->referenceSpecs((int)$product['id']),(int)date('Y')),'evidence_grades'=>$model->evidenceGrades((int)$product['id']),'evidence'=>$model->evidence((int)$product['id']),'trend'=>$model->trend((int)$product['id']),'similar'=>$model->similar($product,(float)($product['median']??0),4)]);
    }
}

