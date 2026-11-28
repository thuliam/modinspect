<?php
declare(strict_types=1);

/** LS-89 BE-5: brands, categories, reference specs and pictures from the admin pages: validation, permissions, audit and file safety. */
require __DIR__ . '/bootstrap.php';

use App\Controllers\AdminMasterDataController;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\RedirectException;
use App\Services\Auth\AuthService;
use App\Services\Catalog\ImageImport;
use App\Services\Catalog\MasterData;

function assert_md5(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

global $config;
$db = Database::connection($config['db']);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SESSION = [];
$db->beginTransaction();
$made = [];
try {
    $auth = new AuthService();
    $suffix = bin2hex(random_bytes(4));
    $admin = (int)$auth->createUser("md-admin-$suffix@example.test", 'MD Admin', bin2hex(random_bytes(12)), 'admin')['user_id'];
    $reviewer = (int)$auth->createUser("md-reviewer-$suffix@example.test", 'MD Reviewer', bin2hex(random_bytes(12)), 'reviewer')['user_id'];
    $login = static function (int $id): void { $_SESSION = [AuthService::SESSION_USER_ID => $id, AuthService::SESSION_LOGIN_AT => time(), AuthService::SESSION_LAST_SEEN_AT => time()]; };
    $call = static function (string $method, array $args = [], ?array $post = null, bool $token = true): array {
        $_SERVER['REQUEST_METHOD'] = $post === null ? 'GET' : 'POST'; $_SERVER['REQUEST_URI'] = '/admin/catalog-lists';
        $_POST = $post === null ? [] : $post + ($token ? ['_token' => Csrf::token()] : []);
        unset($_SESSION['flash']); http_response_code(200); $path = null; ob_start();
        try { (new AdminMasterDataController())->$method(...$args); } catch (RedirectException $e) { $path = $e->path; }
        return ['path' => $path, 'page' => (string)ob_get_clean(), 'code' => http_response_code(), 'flash' => $_SESSION['flash'] ?? null];
    };
    $data = new MasterData($db);
    $one = static function (string $sql, array $p = []) use ($db) { $s = $db->prepare($sql); $s->execute($p); return $s->fetchColumn(); };

    // ---- brands and categories ----
    $login($admin);
    $r = $call('listAction', [], ['action' => 'brand_save', 'name' => "Fixture Brand $suffix"]);
    $brand = (int)$one('SELECT id FROM brands WHERE name = ?', ["Fixture Brand $suffix"]);
    assert_md5($brand > 0 && $r['flash']['type'] === 'success', 'brand added: ' . json_encode($r['flash'], JSON_UNESCAPED_UNICODE));
    assert_md5((int)$one("SELECT COUNT(*) FROM audit_logs WHERE action='master_brand_save' AND entity_id=? AND user_id=?", [$brand, $admin]) === 1, 'brand audit with actor');
    assert_md5($data->saveBrand(null, " fixture brand $suffix ")['error'] === 'NAME_TAKEN' && $data->saveBrand(null, '')['error'] === 'NAME_INVALID', 'brand name rules');
    $slug = (string)$one('SELECT slug FROM brands WHERE id = ?', [$brand]);
    $call('listAction', [], ['action' => 'brand_save', 'id' => $brand, 'name' => "Renamed $suffix"]);
    assert_md5($one('SELECT name FROM brands WHERE id = ?', [$brand]) === "Renamed $suffix" && $one('SELECT slug FROM brands WHERE id = ?', [$brand]) === $slug, 'rename keeps the slug');
    $used = (int)$one('SELECT brand_id FROM products LIMIT 1');
    assert_md5($data->deleteBrand($used)['error'] === 'IN_USE', 'a used brand cannot be deleted');
    $call('listAction', [], ['action' => 'brand_delete', 'id' => $brand]);
    assert_md5((bool)$one('SELECT 1 FROM brands WHERE id = ?', [$brand]), 'no delete without the confirmation box');
    $call('listAction', [], ['action' => 'brand_delete', 'id' => $brand, 'confirm' => '1']);
    assert_md5(!$one('SELECT 1 FROM brands WHERE id = ?', [$brand]), 'confirmed delete of an unused brand');

    $cat = $data->saveCategory(null, "Fixture cat $suffix", "fixture-$suffix", 90);
    assert_md5($cat['ok'] === true, 'category added');
    assert_md5($data->saveCategory(null, "Other $suffix", 'cpu', 1)['error'] === 'SLUG_TAKEN' && $data->saveCategory(null, "Other $suffix", 'bad slug!', 1)['error'] === 'SLUG_INVALID' && $data->saveCategory($cat['id'], 'x', 'ignored', 10000)['error'] === 'SORT_INVALID', 'category rules');
    $data->saveCategory($cat['id'], "Moved $suffix", 'another-slug', 5);
    assert_md5($one('SELECT slug FROM product_categories WHERE id = ?', [$cat['id']]) === "fixture-$suffix", 'an edit never changes the slug');
    $cpu = (int)$one("SELECT id FROM product_categories WHERE slug='cpu'");
    assert_md5($data->deleteCategory($cpu)['error'] === 'PROTECTED' && $data->deleteCategory($cat['id'])['ok'] === true, 'built-in protected, unused custom deleted');

    // ---- permissions ----
    $login($reviewer);
    $brands = (int)$one('SELECT COUNT(*) FROM brands');
    assert_md5($call('listAction', [], ['action' => 'brand_save', 'name' => "No $suffix"])['code'] === 403 && (int)$one('SELECT COUNT(*) FROM brands') === $brands, 'a reviewer cannot write');
    $page = $call('lists');
    assert_md5($page['code'] === 200 && !str_contains($page['page'], '/admin/catalog-lists/action'), 'a reviewer sees the lists without forms');
    $login($admin);
    assert_md5($call('listAction', [], ['action' => 'brand_save', 'name' => "No token $suffix"], false)['path'] === '/admin/catalog-lists' && (int)$one('SELECT COUNT(*) FROM brands') === $brands, 'no token, no write');

    // ---- specs ----
    $category = (int)$one('SELECT category_id FROM products LIMIT 1');
    $db->prepare('INSERT INTO products (category_id,brand_id,model_name,full_name,slug,is_active) VALUES (?,?,?,?,?,1)')->execute([$category, $used, "md-$suffix", "MD fixture $suffix", "md-fixture-$suffix"]);
    $pid = (int)$db->lastInsertId();
    $spec = ['product_id' => $pid, 'action' => 'save', 'spec_key' => 'vram_gb', 'spec_value' => '12', 'unit' => 'GB', 'source_url' => 'https://example.test/spec', 'date_checked' => date('Y-m-d'), 'review_status' => 'official_checked', 'review_note' => ''];
    assert_md5($call('specAction', [], $spec)['path'] === "/admin/products/$pid/specs" && $data->specs($pid)[0]['spec_value'] === '12', 'spec added');
    $call('specAction', [], ['spec_value' => '16'] + $spec);
    assert_md5(count($data->specs($pid)) === 1 && $data->specs($pid)[0]['spec_value'] === '16', 'the same key is replaced');
    $r = $call('specAction', [], ['source_url' => 'javascript:alert(1)'] + $spec);
    assert_md5($r['flash']['type'] === 'danger' && $data->specs($pid)[0]['source_url'] === 'https://example.test/spec', 'a spec without a real source is refused');
    $audit = $db->query("SELECT after_data FROM audit_logs WHERE action='master_spec_saved' AND entity_id=$pid ORDER BY id DESC LIMIT 1")->fetchColumn();
    assert_md5(json_decode((string)$audit, true)['before']['spec_value'] === '12', 'the audit keeps the earlier value');
    assert_md5(str_contains($call('specs', [(string)$pid])['page'], 'vram_gb') && $call('specs', ['999999999'])['code'] === 404, 'spec page');
    $call('specAction', [], ['product_id' => $pid, 'action' => 'delete', 'spec_key' => 'vram_gb', 'confirm' => '1']);
    assert_md5($data->specs($pid) === [], 'spec deleted');

    // ---- pictures ----
    $png = static function (int $shade): string {
        $im = imagecreatetruecolor(220, 220); imagefill($im, 0, 0, imagecolorallocate($im, $shade, 10, 10));
        $file = tempnam(sys_get_temp_dir(), 'md'); imagepng($im, $file); imagedestroy($im); return $file;
    };
    if (function_exists('imagecreatetruecolor')) {
        $upload = static function (int $slot, string $file, array $more = []) use ($call, $pid): array {
            $_FILES = ['picture' => ['name' => 'x.png', 'tmp_name' => $file, 'error' => UPLOAD_ERR_OK, 'size' => filesize($file)]];
            return $call('pictureAction', [], $more + ['product_id' => $pid, 'action' => 'upload', 'slot' => $slot, 'source_name' => 'Own photo', 'license_note' => 'Owned by the shop']);
        };
        $a = $png(40); $b = $png(80); $c = $png(120);
        $r = $upload(1, $a, ['license_note' => '']);
        assert_md5($r['flash']['type'] === 'danger' && $data->pictures($pid)['slots'] === [], 'no picture without a licence');
        $upload(1, $a); $upload(2, $b);
        $slots = $data->pictures($pid)['slots'];
        foreach ($slots as $s) { $made[] = ROOT_PATH . '/public/' . $s['path']; }
        assert_md5(count($slots) === 2 && $data->product($pid)['image_path'] === $slots[0]['path'] && is_file(ROOT_PATH . '/public/' . $slots[1]['path']), 'two pictures, slot 1 is the main one');
        $call('pictureAction', [], ['product_id' => $pid, 'action' => 'move', 'slot' => 2, 'to' => 1]);
        $after = $data->pictures($pid)['slots'];
        assert_md5($after[0]['path'] === $slots[1]['path'] && $after[1]['path'] === $slots[0]['path'] && $data->product($pid)['image_path'] === $slots[1]['path'], 'swap changes the main picture');
        // replacing slot 2 (whose natural file name is now shown in slot 1) must not overwrite or delete that file
        $kept = ROOT_PATH . '/public/' . $after[0]['path'];
        $hash = hash_file('sha256', $kept);
        $upload(2, $c);
        $now = $data->pictures($pid)['slots'];
        foreach ($now as $s) { $made[] = ROOT_PATH . '/public/' . $s['path']; }
        assert_md5(is_file($kept) && hash_file('sha256', $kept) === $hash, 'the other slot keeps its own file');
        assert_md5($now[1]['path'] !== $now[0]['path'] && hash_file('sha256', ROOT_PATH . '/public/' . $now[1]['path']) === hash_file('sha256', $c), 'the new picture is stored under its own name');
        // a file shared with another model is reported and survives removal
        $other = (int)$one('SELECT id FROM products WHERE id <> ? LIMIT 1', [$pid]);
        $db->prepare('UPDATE products SET image_path = ? WHERE id = ?')->execute([$now[0]['path'], $other]);
        assert_md5($data->pictures($pid)['shared'][$now[0]['path']] === 1, 'shared file reported');
        assert_md5(str_contains($call('pictures', [(string)$pid])['page'], $now[0]['path']), 'picture page');
        $call('pictureAction', [], ['product_id' => $pid, 'action' => 'remove', 'slot' => 1]);
        assert_md5(count($data->pictures($pid)['slots']) === 2, 'no removal without the confirmation box');
        $call('pictureAction', [], ['product_id' => $pid, 'action' => 'remove', 'slot' => 1, 'confirm' => '1']);
        assert_md5(count($data->pictures($pid)['slots']) === 1 && $data->product($pid)['image_path'] === $now[1]['path'] && is_file($kept), 'removed reference, next picture becomes the main one, file kept');
        $call('pictureAction', [], ['product_id' => $pid, 'action' => 'remove', 'slot' => 2, 'confirm' => '1']);
        assert_md5($data->pictures($pid)['slots'] === [] && $data->product($pid)['image_path'] === null, 'no picture left');
        assert_md5((int)$one("SELECT COUNT(*) FROM audit_logs WHERE action LIKE 'master_picture_%' AND entity_id=? AND user_id=?", [$pid, $admin]) === 6, 'picture audit lines');
        $db->prepare('UPDATE products SET image_path = ? WHERE id = ?')->execute(['assets/products/legacy.png', $pid]);
        $call('pictureAction', [], ['product_id' => $pid, 'action' => 'clear_primary', 'confirm' => '1']);
        assert_md5($data->product($pid)['image_path'] === null, 'the older single picture can be removed');
        foreach ([$a, $b, $c] as $f) { @unlink($f); }
        assert_md5(ImageImport::references($db, $now[0]['path'], $pid, 1) === 1, 'reference count');
    } else {
        echo "master_data_test: picture checks skipped (no GD)\n";
    }

    echo "master_data_test: PASS\n";
} finally {
    $_FILES = [];
    $_SESSION = [];
    $db->rollBack();
    foreach (array_unique($made) as $file) { if (is_file($file)) { @unlink($file); } }
}
