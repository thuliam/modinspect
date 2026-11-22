<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Controller;
use App\Models\Product;

final class HomeController extends Controller {
    public function index(): void { $this->view('home/index',['title'=>'เช็กราคาคอมมือสอง','products'=>(new Product())->popular(24),'counts'=>(new Product())->publicCounts(),'brands'=>(new Product())->brandsInCatalog(),'articles'=>$this->recentArticles()]); }
    /** LS-37: the latest three published articles for the home page; an empty list when the blog tables are not there. */
    private function recentArticles(): array { try { return (new \App\Models\Blog())->recent(3); } catch (\Throwable) { return []; } }
    public function glossary(): void { $this->view('pages/glossary',['title'=>'ศัพท์ตลาดมือสอง','metaDescription'=>'ตัวโชว์ ตัวขุด ประกันศูนย์ ถอดเครื่อง และคำอื่น ๆ ในประกาศขายคอมมือสอง แปลว่าอะไร และควรถามอะไรก่อนซื้อ','terms'=>\App\Services\Catalog\Glossary::terms()]); }
    public function methodology(): void { $this->view('pages/methodology',['title'=>'วิธีคำนวณและความโปร่งใส']); }
}
