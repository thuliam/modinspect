<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/**
 * F-6 (owner scope 2026-11-18): the unfinished modules (local match, seller dashboard, shop dashboard, shop inventory, market report) are not public.
 * No public page links to them, and a direct address answers 404 like any unknown page. The views and code stay for later. The seller price advisor
 * and everything on the price/condition path are unaffected. Source-level check (the controller needs a database to render).
 */
function ok_hm(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$root = dirname(__DIR__);
$ctl = (string)file_get_contents($root . '/app/Controllers/ModuleController.php');
foreach (['sellerDashboard', 'localMatch', 'marketReport', 'shop', 'inventory'] as $m) {
    ok_hm(preg_match('/function ' . $m . '\(\): void \{ \$this->notPublic\(\);/', $ctl) === 1, "$m answers 404 through notPublic() before doing anything");
}
ok_hm(preg_match('/function notPublic\(\): void \{[^}]*http_response_code\(404\)/', $ctl) === 1, 'notPublic() sets 404');
foreach (['sellerAdvisor', 'compare', 'conditionGuide', 'build', 'guide'] as $m) {
    ok_hm(preg_match('/function ' . $m . '\([^)]*\): void \{[^}]*notPublic/', $ctl) === 0, "$m stays public");
}

// no public view links to a hidden module (the hidden modules' own views may link among themselves)
// LS-14 OD-5: /seller is the members' seller page now (SellerController); the old ModuleController::sellerDashboard stays hidden
$hidden = ['/shop', '/local-match', '/market-report'];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app/Views', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || str_contains(str_replace('\\', '/', $f->getPathname()), '/Views/modules/') && preg_match('/(seller-dashboard|shop-dashboard|inventory|local-match|market-report)\.php$/', $f->getFilename())) { continue; }
    $src = (string)file_get_contents($f->getPathname());
    foreach ($hidden as $h) { ok_hm(!str_contains($src, '$base ?>' . $h), basename($f->getPathname()) . " does not link to hidden $h"); }
}
$sitemap = (string)file_get_contents($root . '/app/Services/Seo/SeoService.php');
ok_hm(!str_contains($sitemap, "'/shop") && !str_contains($sitemap, "'/local-match") && !str_contains($sitemap, "'/market-report"), 'The sitemap does not list hidden modules');
echo "hidden_modules_test: PASS\n";
