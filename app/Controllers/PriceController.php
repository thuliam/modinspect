<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Controller;
use App\Models\Product;

final class PriceController extends Controller {
    public function index(): void {
        $q=\App\Services\Search\SearchQuery::clip(self::queryString('q')); $category=trim(self::queryString('category')) ?: null;
        $model=new Product(); $products=$model->search($q,$category);
        // LS-17 C3: with no search and no category the result IS the whole catalog, so the name list reuses it instead of running the same query twice
        $catalog=($q===''&&$category===null) ? $products : $model->search();
        // LS-16 U-1: order and "priced only" are display choices on the same rows (no new query); an unknown key falls back to the catalog order
        $sort=self::queryString('sort'); if(!isset(\App\Services\Catalog\PriceList::SORTS[$sort])){ $sort=''; }
        $pricedOnly=($_GET['priced']??'')==='1';
        // LS-21: special filters of the chosen category (socket, RAM type/size/speed, VRAM ...); options come from the models that exist, nothing is guessed
        $specFilters=[]; $specOptions=[];
        if($category!==null && isset(\App\Services\Catalog\SpecFilter::BY_CATEGORY[$category])){
            $specMap=$model->specMap();
            foreach(['socket','mem','form','cap','vram','iface','watt','chip','ctype','speed_min','speed_max'] as $k){ $v=self::queryString($k); if($v!==''){ $specFilters[$k]=$v; } }
            $specOptions=\App\Services\Catalog\SpecFilter::options($catalog,$specMap,$category);
            $products=\App\Services\Catalog\SpecFilter::apply($products,$specMap,$category,$specFilters);
        }
        $products=\App\Services\Catalog\PriceList::apply($products,$sort,$pricedOnly);
        // F-7: the names feed the browser's own autocomplete; when nothing was found, a typo-tolerant "did you mean" (suggestion only, results are never changed)
        $names=array_map(static fn(array $p):array=>['slug'=>$p['slug'],'full_name'=>$p['full_name']],$catalog);
        $didYouMean=!$products && $q!=='' ? \App\Services\Search\DidYouMean::suggest($q,$names) : [];
        $this->view('price/index',['title'=>'ดัชนีราคา','products'=>$products,'q'=>$q,'category'=>$category,'sort'=>$sort,'pricedOnly'=>$pricedOnly,'specFilters'=>$specFilters,'specOptions'=>$specOptions,'suggestions'=>$products ? [] : $model->popular(6),'names'=>$names,'didYouMean'=>$didYouMean]);
    }
    /** LS-24 C1: the price card of a model as a PNG (1200 x 630). Only for a model whose page shows a price, drawn from the same stored snapshot, cached per snapshot. */
    public function card(string $slug): void {
        $product=(new Product())->findBySlug($slug);
        if(!$product || \App\Services\Deal\ShareText::forProduct($product,'x')===null || !\App\Services\Deal\ShareCard::available(ROOT_PATH.'/public/assets/fonts')){ http_response_code(404); $this->view('errors/404',['title'=>'ไม่พบการ์ดราคา']); return; }
        $key='product-card-'.preg_replace('/[^a-z0-9-]/','',strtolower((string)$product['slug'])).'-'.substr(md5((string)($product['last_calculated_at']??'').'|'.(string)$product['median'].'|'.(string)$product['valid_sample_size']),0,10);
        $png=(new \App\Services\Http\FileCache(ROOT_PATH.'/storage/cache/http'))->remember($key,86400,static function() use($product):string {
            return \App\Services\Deal\ShareCard::render(['name'=>(string)$product['full_name'],'price'=>(float)$product['median'],'caption'=>'ราคากลางมือสองที่พบ','label'=>'ตัวอย่าง '.(int)$product['valid_sample_size'].' ประกาศ','tone'=>'neutral','range'=>[(float)$product['q1'],(float)$product['q3']],'median'=>null],ROOT_PATH.'/public/assets/fonts',ROOT_PATH.'/public/assets/brand/modinpect-lockup-dark-480.png');
        });
        header('Content-Type: image/png'); header('Content-Length: '.strlen($png)); header('Cache-Control: public, max-age=3600'); header('Content-Disposition: inline; filename="modinpect-price.png"'); header('X-Robots-Tag: noindex');
        echo $png;
    }
    public function show(string $slug): void {
        $model=new Product(); $product=$model->findBySlug($slug);
        if (!$product) { http_response_code(404); $this->view('errors/404',['title'=>'ไม่พบสินค้า']); return; }
        // description only from the stored public snapshot, never invented: range, sample size and the asking-price caveat
        $meta = 'เช็กราคามือสองของ ' . $product['full_name'] . ' และดูว่าราคามาจากข้อมูลอะไร ก่อนซื้อขาย';
        if (!empty($product['median']) && (int)($product['valid_sample_size'] ?? 0) >= 3 && (int)($product['accepted_observation_count'] ?? 0) > 0) {
            $meta = $product['full_name'] . ' ราคาประกาศมือสองที่พบ ' . number_format((float)$product['q1']) . '–' . number_format((float)$product['q3']) . ' บาท จาก ' . (int)$product['valid_sample_size'] . ' ตัวอย่าง (ไม่ใช่ราคาปิดดีล)';
        }
        // LS-19 C1/C2: share picture and structured data from the same stored snapshot the page shows (no price unless the page shows one)
        global $config; $base=(string)$config['base_url'];
        $ogImage=\App\Services\Seo\SeoService::productImage($base,(string)$product['slug'],ROOT_PATH.'/public');
        // LS-24 C1: a product with a public price is shared as its own price card (needs GD with FreeType; otherwise the brand picture stays)
        $cardUrl=(\App\Services\Deal\ShareText::forProduct($product,'x')!==null && \App\Services\Deal\ShareCard::available(ROOT_PATH.'/public/assets/fonts')) ? rtrim($base,'/').'/price/'.rawurlencode((string)$product['slug']).'/card' : null;
        if($cardUrl!==null){ $ogImage=$cardUrl; }
        $jsonLd=\App\Services\Seo\SeoService::productJsonLd($product,\App\Services\Seo\SeoService::canonical($base,'/price/'.$product['slug']),$ogImage);
        $cm=[]; if(\App\Services\Community\Forum::enabled($config)){ try { $fm=new \App\Services\Community\Forum(\App\Core\Database::connection($config['db'])); $cm=['cm_on'=>true,'cm_summary'=>$fm->reviewSummary((int)$product['id']),'cm_reviews'=>$fm->reviews((int)$product['id']),'cm_member'=>(new \App\Services\Member\MemberAuth(\App\Core\Database::connection($config['db'])))->current(),'cm_flash'=>(function(){ $f=$_SESSION['community_flash']??null; unset($_SESSION['community_flash']); return is_string($f)?$f:null; })()]; } catch(\PDOException $e) { $cm=[]; } }
        $this->view('price/show',$cm+['shareText'=>\App\Services\Deal\ShareText::forProduct($product,\App\Services\Seo\SeoService::canonical($base,'/price/'.$product['slug'])),'cardUrl'=>$cardUrl,'communityOn'=>\App\Services\Community\CommunityReports::enabled($config),'ogImage'=>$ogImage,'jsonLd'=>$jsonLd,'metaDescription'=>$meta,'title'=>$product['full_name'],'product'=>$product,'history'=>$model->history((int)$product['id']),'facts'=>\App\Services\Catalog\ProductFacts::rows($product,$model->referenceSpecs((int)$product['id']),(int)date('Y')),'evidence_grades'=>$model->evidenceGrades((int)$product['id']),'evidence'=>$model->evidence((int)$product['id']),'trend'=>$model->trend((int)$product['id']),'similar'=>$model->similar($product,(float)($product['median']??0),4)]);
    }
}

