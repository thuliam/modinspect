<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Product;
use App\Services\Catalog\WatchList;

/**
 * LS-25 C5: "models I am watching". The list is a cookie in the visitor's browser (see WatchList); this controller only toggles an entry (POST, CSRF-checked) and lists
 * them with today's checked price. A model without a checked price is listed as such. Nothing is stored on the server and nothing here touches a price.
 */
final class WatchController extends Controller
{
    public function index(): void
    {
        $model = new Product();
        $list = WatchList::parse(is_string($_COOKIE[WatchList::COOKIE] ?? null) ? $_COOKIE[WatchList::COOKIE] : null);
        $rows = [];
        foreach ($list as $w) {
            $p = $model->findBySlug($w['slug']);
            if (!$p) { continue; }
            $priced = !empty($p['median']) && (int)($p['valid_sample_size'] ?? 0) > 0 && (int)($p['accepted_observation_count'] ?? 0) > 0;
            $now = $priced ? (float)$p['median'] : null;
            $rows[] = ['product' => $p, 'then' => $w['median'], 'now' => $now, 'change' => WatchList::change($w['median'], $now)];
        }
        global $config;
        // PWA P3: the opt-in button is offered only when push is on and a public VAPID key is set; it carries the visitor's own list so the server can compare later
        $pushKey = (!empty($config['push']['enabled']) && preg_match('/^[A-Za-z0-9_-]{80,100}$/', (string)($config['push']['vapid_public'] ?? '')) === 1) ? (string)$config['push']['vapid_public'] : '';
        $flash = is_string($_SESSION['watch_flash'] ?? null) ? $_SESSION['watch_flash'] : null; unset($_SESSION['watch_flash']);
        $this->view('pages/watching', ['flash' => $flash, 'title' => 'รุ่นที่ติดตาม', 'metaDescription' => 'รายการรุ่นที่คุณติดตาม พร้อมราคามือสองที่ตรวจแล้วเทียบกับตอนที่เริ่มติดตาม', 'rows' => $rows, 'pushKey' => $pushKey, 'watch' => array_map(static fn(array $w): array => ['slug' => $w['slug'], 'median' => $w['median']], $list)]);
    }

    public function toggle(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) { http_response_code(419); $this->view('errors/419', ['title' => 'แบบฟอร์มหมดอายุ']); return; }
        $slug = is_string($_POST['slug'] ?? null) ? trim($_POST['slug']) : '';
        $p = $slug !== '' ? (new Product())->findBySlug($slug) : null;
        if (!$p) { http_response_code(404); $this->view('errors/404', ['title' => 'ไม่พบหน้า']); return; }
        $list = WatchList::parse(is_string($_COOKIE[WatchList::COOKIE] ?? null) ? $_COOKIE[WatchList::COOKIE] : null);
        $priced = !empty($p['median']) && (int)($p['valid_sample_size'] ?? 0) > 0 && (int)($p['accepted_observation_count'] ?? 0) > 0;
        $r = WatchList::toggle($list, (string)$p['slug'], $priced ? (float)$p['median'] : null);
        if (!defined('MODINSPECT_TESTING')) {
            setcookie(WatchList::COOKIE, WatchList::serialize($r['list']), ['expires' => time() + 86400 * 180, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']);
        }
        $_COOKIE[WatchList::COOKIE] = WatchList::serialize($r['list']);
        $_SESSION['watch_flash'] = $r['full'] ? 'ติดตามได้สูงสุด ' . WatchList::MAX . ' รุ่น เอารุ่นเก่าออกก่อน' : ($r['added'] ? 'เพิ่มในรายการที่ติดตามแล้ว' : 'เอาออกจากรายการที่ติดตามแล้ว');
        // LS-26: the list page can remove an entry and come back to itself; only that one fixed destination is accepted, never a given address
        $this->redirect((($_POST['back'] ?? '') === 'watching') ? '/watching' : '/price/' . rawurlencode((string)$p['slug']));
    }
}
