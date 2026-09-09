<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Controller;
use App\Models\Dashboard;
use App\Models\Product;

final class HomeController extends Controller {
    public function index(): void { $this->view('home/index',['title'=>'เช็กราคาคอมมือสอง','products'=>(new Product())->popular(),'stats'=>(new Dashboard())->adminStats()]); }
    public function methodology(): void { $this->view('pages/methodology',['title'=>'วิธีคำนวณและความโปร่งใส']); }
}
