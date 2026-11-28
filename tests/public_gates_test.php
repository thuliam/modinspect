<?php
declare(strict_types=1);

/** LS-90 T3-T5: the unsupported price helper and the legal pages stay closed until switched on; a photograph shared by two models is not drawn. */
require __DIR__ . '/bootstrap.php';
require_once ROOT_PATH . '/app/Views/components/hw-art.php';

use App\Controllers\LegalPageController;
use App\Controllers\ModuleController;
use App\Core\Database;
use App\Services\Auth\AuthService;
use App\Services\Catalog\SharedPictures;
use App\Services\Member\LegalDocuments;
use App\Services\Member\MemberAudit;
use App\Services\Seo\SeoService;

function assert_gate(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

global $config;
$call = static function (callable $action, string $uri): array {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = $uri;
    $_GET = [];
    http_response_code(200);
    ob_start();
    $action();
    return ['code' => http_response_code(), 'page' => (string)ob_get_clean()];
};

assert_gate(($config['seller_advisor']['enabled'] ?? null) === false && ($config['legal_pages']['enabled'] ?? null) === false, 'both are off unless configured');
$r = $call(fn() => (new ModuleController())->sellerAdvisor(), '/seller-price-advisor');
assert_gate($r['code'] === 404 && !str_contains($r['page'], 'ผู้ช่วยตั้งราคาขาย'), 'the helper is closed: ' . $r['code']);
$config['seller_advisor']['enabled'] = true;
$r = $call(fn() => (new ModuleController())->sellerAdvisor(), '/seller-price-advisor');
assert_gate($r['code'] === 200, 'the helper opens when switched on');
$config['seller_advisor']['enabled'] = false;

// shared photographs, on a folder made for the test
$dir = sys_get_temp_dir() . '/mi-shared-' . bin2hex(random_bytes(4));
mkdir($dir);
try {
    file_put_contents("$dir/model-a.jpg", 'same-bytes');
    file_put_contents("$dir/model-b.jpg", 'same-bytes');
    file_put_contents("$dir/model-c.jpg", 'other-bite');   // same size, different content
    file_put_contents("$dir/model-d.jpg", 'a picture of its own');
    file_put_contents("$dir/model-d_extra shot.jpg", 'a picture of its own');   // not a file a model page draws
    assert_gate(SharedPictures::isShared("$dir/model-a.jpg") && SharedPictures::isShared("$dir/model-b.jpg"), 'identical files under two names');
    assert_gate(!SharedPictures::isShared("$dir/model-c.jpg") && !SharedPictures::isShared("$dir/model-d.jpg"), 'distinct pictures stay');
} finally {
    array_map('unlink', glob("$dir/*"));
    rmdir($dir);
}
// the real folder: whatever is shared is not drawn and not used as a share picture; everything else still is
$drawn = 0;
$hidden = 0;
foreach (glob(ROOT_PATH . '/public/assets/products/*.jpg') ?: [] as $file) {
    $slug = basename($file, '.jpg');
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) { continue; }
    $shared = SharedPictures::isShared($file);
    $photo = mi_product_photo(['slug' => $slug, 'full_name' => 'x'], '');
    assert_gate(($photo === null) === $shared, "picture of $slug");
    assert_gate(str_contains(SeoService::productImage('', $slug, ROOT_PATH . '/public'), $slug . '.jpg') !== $shared, "share picture of $slug");
    $shared ? $hidden++ : $drawn++;
}
assert_gate($drawn > 0, 'some pictures are still drawn');

// legal pages
$db = Database::connection($config['db']);
$db->beginTransaction();
$_SESSION = [];
try {
    $admin = (int)(new AuthService())->createUser('legal-' . bin2hex(random_bytes(4)) . '@example.test', 'Legal Admin', bin2hex(random_bytes(12)), 'admin')['user_id'];
    $legal = new LegalDocuments($db, new MemberAudit($db));
    $db->exec("UPDATE legal_documents SET status='retired' WHERE status='published'");
    $page = static fn() => $call(fn() => (new LegalPageController())->privacy(), '/privacy');
    assert_gate($page()['code'] === 404, 'off: 404');
    $config['legal_pages']['enabled'] = true;
    assert_gate($page()['code'] === 404, 'on with nothing published: 404');
    $blank = $legal->createDraft('privacy', 't-blank', 'Privacy', 'Contact: [email to be filled]');
    try { $legal->publish($blank, $admin); throw new LogicException('published with a blank'); }
    catch (RuntimeException $e) { assert_gate($e->getMessage() === 'UNFILLED_BLANK', 'unfilled blank refused: ' . $e->getMessage()); }
    assert_gate($page()['code'] === 404, 'still nothing public');
    $ready = $legal->createDraft('privacy', 't-1', 'Privacy test title', "First line <b>x</b>\nSee [the guide](https://example.test)");
    $legal->publish($ready, $admin);
    $r = $page();
    assert_gate($r['code'] === 200 && str_contains($r['page'], 'Privacy test title') && str_contains($r['page'], 'First line &lt;b&gt;x&lt;/b&gt;'), 'the published text is shown escaped');
    assert_gate(str_contains($r['page'], 'href="/privacy"') || preg_match('~href="[^"]*/terms"~', $r['page']) === 1, 'the footer links to the pages');
    assert_gate($call(fn() => (new LegalPageController())->terms(), '/terms')['code'] === 404, 'terms not published: 404');
    $config['legal_pages']['enabled'] = false;
    $r = $page();
    assert_gate($r['code'] === 404 && preg_match('~href="[^"]*/terms"~', $r['page']) !== 1, 'off again: 404 and no link');
    echo "public_gates_test: PASS (pictures drawn $drawn, hidden $hidden)\n";
} finally {
    $config['seller_advisor']['enabled'] = false;
    $config['legal_pages']['enabled'] = false;
    $_SESSION = [];
    $db->rollBack();
}
