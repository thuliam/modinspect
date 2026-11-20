<?php
declare(strict_types=1);

define('MODINSPECT_TESTING', true);

require __DIR__ . '/bootstrap.php';

use App\Controllers\BlogController;
use App\Controllers\CommunityController;
use App\Controllers\CommunityReportController;
use App\Controllers\ListingReaderController;
use App\Controllers\SellerCheckController;
use App\Core\Database;
use App\Core\RedirectException;

/**
 * LS-24 M6: the rehearsal of every switch (docs/MODULE_SWITCHES_TH.md), in process on the test database: each closed module answers 404 while off, works when it
 * is switched on with what it needs (and only then), and answers 404 again when switched off. A switch that is on without its key stays off. The open modules
 * (blog, listing reader) have no switch and always answer. The real flows of each module have their own tests; this one proves the doors.
 */
function ok_sw(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
function sw_code(callable $fn): int { http_response_code(200); ob_start(); try { $fn(); } catch (RedirectException $e) { ob_end_clean(); return 302; } ob_end_clean(); return http_response_code(); }

global $config;
$_SERVER['REMOTE_ADDR'] = '10.252.' . random_int(0, 255) . '.' . random_int(1, 254); $_SERVER['REQUEST_METHOD'] = 'GET'; $_SESSION = []; $_GET = []; $_POST = [];
$db = Database::connection($config['db']);
$slug = (string)$db->query('SELECT slug FROM products WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
$key = str_repeat('k', 40);
$saved = [$config['community_reports'], $config['seller_check'], $config['community_forum']];
try {
    // ---- community reports: flag AND key ----
    $door = static fn(): int => sw_code(fn() => (new CommunityReportController())->form($slug));
    $config['community_reports'] = ['enabled' => false, 'hmac_key' => $key];             ok_sw($door() === 404, 'Reports: off is 404 even with a key');
    $config['community_reports'] = ['enabled' => true, 'hmac_key' => ''];                ok_sw($door() === 404, 'Reports: on without a key stays 404');
    $config['community_reports'] = ['enabled' => true, 'hmac_key' => 'short'];           ok_sw($door() === 404, 'Reports: on with a short key stays 404');
    $config['community_reports'] = ['enabled' => true, 'hmac_key' => $key];              ok_sw($door() === 200, 'Reports: on with a real key opens the form');
    $config['community_reports'] = ['enabled' => false, 'hmac_key' => $key];             ok_sw($door() === 404, 'Reports: switched off again is 404');

    // ---- seller check (blacklist): flag AND key ----
    $door = static fn(): int => sw_code(fn() => (new SellerCheckController())->lookup());
    $config['seller_check'] = ['enabled' => false, 'hmac_key' => $key];                  ok_sw($door() === 404, 'Seller check: off is 404 even with a key');
    $config['seller_check'] = ['enabled' => true, 'hmac_key' => ''];                     ok_sw($door() === 404, 'Seller check: on without a key stays 404');
    $config['seller_check'] = ['enabled' => true, 'hmac_key' => $key];                   ok_sw($door() === 200, 'Seller check: on with a real key opens the lookup');
    $config['seller_check'] = ['enabled' => false, 'hmac_key' => $key];                  ok_sw($door() === 404, 'Seller check: switched off again is 404');
    foreach (['reportForm', 'disputeForm'] as $m) { ok_sw(sw_code(fn() => (new SellerCheckController())->$m()) === 404, "Seller check: $m is 404 while off"); }

    // ---- forum: flag ----
    $door = static fn(): int => sw_code(fn() => (new CommunityController())->index());
    $config['community_forum'] = ['enabled' => false];                                   ok_sw($door() === 404, 'Forum: off is 404');
    $config['community_forum'] = ['enabled' => true];                                    ok_sw($door() === 200, 'Forum: on opens the list');
    $config['community_forum'] = ['enabled' => false];                                   ok_sw($door() === 404, 'Forum: switched off again is 404');

    // ---- the open modules have no switch ----
    ok_sw(sw_code(fn() => (new BlogController())->index()) === 200 && sw_code(fn() => (new ListingReaderController())->form()) === 200, 'Blog and listing reader are open');
} finally {
    [$config['community_reports'], $config['seller_check'], $config['community_forum']] = $saved;
}
// the document says the same
$doc = (string)file_get_contents(ROOT_PATH . '/docs/MODULE_SWITCHES_TH.md');
foreach (['COMMUNITY_REPORTS_ENABLED', 'COMMUNITY_REPORTS_HMAC_KEY', 'COMMUNITY_FORUM_ENABLED', 'SELLER_CHECK_ENABLED', 'SELLER_CHECK_HMAC_KEY', 'SELLER_LISTINGS_PUBLIC', 'MEMBER_GOOGLE_CLIENT_ID', '026_community_reports.sql', '027_community_forum.sql', '025_seller_reports.sql'] as $k) { ok_sw(str_contains($doc, $k), "The switch document names $k"); }
echo "module_switch_rehearsal_test: PASS\n";
