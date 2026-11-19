<?php
use App\Core\Csrf;
use App\Core\Database;
use App\Services\Auth\AuthorizationService;
use App\Services\Auth\AuthService;

$base = $config['base_url'];
$authUser = $auth_user ?? (new AuthService())->currentUser();
$authz = new AuthorizationService();
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$basePath = rtrim(parse_url($base, PHP_URL_PATH) ?: '', '/');
if ($basePath !== '' && str_starts_with($requestPath, $basePath)) {
    $requestPath = substr($requestPath, strlen($basePath)) ?: '/';
}
$isActive = function (array $paths) use ($requestPath): string {
    foreach ($paths as $path) {
        $normalized = rtrim($path, '/') ?: '/';
        if ($requestPath === $normalized) return 'active';
        if ($normalized !== '/admin' && str_starts_with($requestPath, $normalized . '/')) return 'active';
    }
    return '';
};
$groups = [
    'DASHBOARD' => [
        ['label' => 'Dashboard', 'icon' => 'fa-tachometer-alt', 'href' => '/admin', 'permission' => 'admin.dashboard.view', 'paths' => ['/admin']],
    ],
    'CATALOG' => [
        ['label' => 'Products', 'icon' => 'fa-microchip', 'href' => '/admin/products', 'permission' => 'products.view', 'paths' => ['/admin/products']],
        ['label' => 'Aliases', 'icon' => 'fa-random', 'href' => '/admin/product-aliases', 'permission' => 'products.view', 'paths' => ['/admin/product-aliases']],
        ['label' => 'Unresolved', 'icon' => 'fa-question-circle', 'href' => '/admin/unresolved-listings', 'permission' => 'products.view', 'paths' => ['/admin/unresolved-listings']],
    ],
    'MARKET DATA' => [
        ['label' => 'Observations', 'icon' => 'fa-list-ul', 'href' => '/admin/price-observations', 'permission' => 'review.view', 'paths' => ['/admin/price-observations']],
        ['label' => 'Review Queue', 'icon' => 'fa-clipboard-check', 'href' => '/admin/review-queue', 'permission' => 'review.view', 'paths' => ['/admin/review-queue', '/admin/review']],
        ['label' => 'Review Analytics', 'icon' => 'fa-chart-bar', 'href' => '/admin/review-analytics', 'permission' => 'review.analytics.view', 'paths' => ['/admin/review-analytics']],
        ['label' => 'Trends & Locations', 'icon' => 'fa-map-marker-alt', 'href' => '/admin/market-trends', 'permission' => 'snapshots.view', 'paths' => ['/admin/market-trends']],
        ['label' => 'Beta readiness', 'icon' => 'fa-clipboard-list', 'href' => '/admin/beta-readiness', 'permission' => 'products.manage', 'paths' => ['/admin/beta-readiness']],
        ['label' => 'Data health', 'icon' => 'fa-heartbeat', 'href' => '/admin/data-health', 'permission' => 'snapshots.view', 'paths' => ['/admin/data-health']],
        ['label' => 'Price Index', 'icon' => 'fa-tags', 'href' => '/admin/price-indices', 'permission' => 'snapshots.view', 'paths' => ['/admin/price-indices']],
        ['label' => 'Seller reports', 'icon' => 'fa-flag', 'href' => '/admin/seller-reports', 'permission' => 'reports.moderate', 'paths' => ['/admin/seller-reports']],
        ['label' => 'Community reports', 'icon' => 'fa-comments', 'href' => '/admin/community-reports', 'permission' => 'reports.moderate', 'paths' => ['/admin/community-reports']],
        ['label' => 'Seller listings', 'icon' => 'fa-shopping-bag', 'href' => '/admin/seller-listings', 'permission' => 'listings.moderate', 'paths' => ['/admin/seller-listings']],
        ['label' => 'New-price refs', 'icon' => 'fa-tag', 'href' => '/admin/new-prices', 'permission' => 'products.manage', 'paths' => ['/admin/new-prices']],
    ],
    'COLLECTION' => [
        ['label' => 'Sources', 'icon' => 'fa-rss', 'href' => '/admin/sources', 'permission' => 'sources.view', 'paths' => ['/admin/sources']],
        ['label' => 'Owner intake', 'icon' => 'fa-upload', 'href' => '/admin/intake', 'permission' => 'products.manage', 'paths' => ['/admin/intake']],
        ['label' => 'Imports', 'icon' => 'fa-download', 'href' => '/admin/collector-jobs', 'permission' => 'collection.jobs.view', 'paths' => ['/admin/collector-jobs']],
    ],
    'CONTENT' => [
        ['label' => 'Articles', 'icon' => 'fa-newspaper', 'href' => '/admin/articles', 'permission' => 'products.view', 'paths' => ['/admin/articles']],
    ],
    'SYSTEM' => [
        ['label' => 'Users', 'icon' => 'fa-users', 'href' => '/admin/users', 'permission' => 'users.manage', 'paths' => ['/admin/users']],
        ['label' => 'Audit', 'icon' => 'fa-history', 'href' => '/admin/audit-logs', 'permission' => 'audit.view', 'paths' => ['/admin/audit-logs']],
    ],
];

if (!function_exists('mi_page_header')) {
    /** DropX-style page header: breadcrumb (home icon + chevrons), title, description, right-aligned actions. */
    function mi_page_header(string $base, array $crumbs, string $title, string $description = '', string $actions = ''): string {
        $html = '<div class="dx-page-head"><nav aria-label="Breadcrumb" class="dx-crumbs d-flex align-items-center text-muted"><a href="' . $base . '/admin" aria-label="Home"><i class="fas fa-home"></i></a>';
        $last = count($crumbs) - 1;
        foreach ($crumbs as $i => $c) {
            $html .= '<i class="fas fa-chevron-right mx-2" aria-hidden="true"></i>' . ($i === $last ? '<span aria-current="page" class="font-weight-bold dx-crumb-current">' . $c . '</span>' : '<span>' . $c . '</span>');
        }
        $html .= '</nav><div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-sm-between mt-2"><div><h1 class="dx-title">' . $title . '</h1>';
        if ($description !== '') { $html .= '<p class="dx-desc text-muted mb-0">' . $description . '</p>'; }
        $html .= '</div>';
        if ($actions !== '') { $html .= '<div class="d-flex align-items-center mt-2 mt-sm-0">' . $actions . '</div>'; }
        return $html . '</div></div>';
    }
}
$pendingReviews = 0;
try {
    $pendingReviews = (int) Database::connection($config['db'])->query("SELECT COUNT(*) FROM price_observations WHERE verified_status='pending'")->fetchColumn();
} catch (\Throwable $e) { $pendingReviews = 0; }
$userInitial = htmlspecialchars(strtoupper(substr((string) ($authUser['name'] ?? 'A'), 0, 1)));
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
<meta name="description" content="ModInspect Admin operations console">
<title><?= htmlspecialchars($title ?? 'Admin') ?> - <?= htmlspecialchars($config['name']) ?> Admin</title>
<link rel="stylesheet" href="<?= $base ?>/assets/admin/vendor/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= $base ?>/assets/admin/vendor/fontawesome/css/fontawesome-all.css?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/admin/vendor/fontawesome/css/fontawesome-all.css') ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/admin/vendor/datatables/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="<?= $base ?>/assets/admin/css/admin.css">
<link rel="stylesheet" href="<?= $base ?>/assets/ci-tokens.css?v=<?= (int) @filemtime(ROOT_PATH . "/public/assets/ci-tokens.css") ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/admin/vendor/select2/select2.min.css">
<link rel="stylesheet" href="<?= $base ?>/assets/mi-select2.css?v=<?= (int) @filemtime(ROOT_PATH . "/public/assets/mi-select2.css") ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/mi-admin.css?v=<?= (int) @filemtime(ROOT_PATH . "/public/assets/mi-admin.css") ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/mi-dropx.css?v=<?= (int) @filemtime(ROOT_PATH . "/public/assets/mi-dropx.css") ?>">
<script src="<?= $base ?>/assets/mi-dropx.js?v=<?= (int) @filemtime(ROOT_PATH . "/public/assets/mi-dropx.js") ?>"></script>
<link rel="icon" href="<?= $base ?>/favicon.ico?v=<?= (int) @filemtime(ROOT_PATH . "/public/favicon.ico") ?>" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="<?= $base ?>/assets/brand/favicon-32x32.png">
<link rel="apple-touch-icon" href="<?= $base ?>/assets/brand/apple-touch-icon.png">
</head>
<body class="mi-admin-body">
<div class="dx-app d-flex vh-100 overflow-hidden">
    <div class="dx-backdrop" aria-hidden="true"></div>
    <aside class="dx-sidebar d-flex flex-column" id="adminSidebarNav">
        <div class="dx-brand d-flex align-items-center justify-content-between">
            <a class="dx-logo d-flex align-items-center" href="<?= $base ?>/admin" aria-label="ModInspect Admin dashboard"><span class="dx-mark"><i class="fas fa-search"></i></span><span class="dx-wordmark">ModInspect</span></a>
            <button type="button" class="dx-close d-lg-none" aria-label="Close sidebar"><i class="fas fa-times"></i></button>
        </div>
        <nav class="dx-nav flex-grow-1" aria-label="Main navigation">
            <?php foreach($groups as $group => $links): ?>
                <?php $visibleLinks=array_values(array_filter($links, fn($link) => $authz->can($authUser, $link['permission']))); ?>
                <?php if(!$visibleLinks) continue; ?>
                <div class="dx-group">
                    <p class="dx-group-label"><?= htmlspecialchars($group) ?></p>
                    <?php foreach($visibleLinks as $link): ?>
                        <a class="dx-link <?= $isActive($link['paths']) ?>" href="<?= $base.htmlspecialchars($link['href']) ?>" title="<?= htmlspecialchars($link['label']) ?>">
                            <i class="fas fa-fw <?= htmlspecialchars($link['icon']) ?>"></i>
                            <span class="flex-grow-1"><?= htmlspecialchars($link['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>
    </aside>
    <div class="dx-column d-flex flex-column flex-grow-1 overflow-hidden">
        <header class="dx-header">
            <div class="d-flex align-items-center h-100">
                <button type="button" class="dx-icon-btn d-lg-none mr-2 js-nav-open" aria-label="Open sidebar"><i class="fas fa-bars"></i></button>
                <button type="button" class="dx-icon-btn d-none d-lg-flex mr-2 js-sidebar-toggle" aria-label="Collapse or expand sidebar"><i class="fas fa-columns"></i></button>
                <div class="flex-grow-1 dx-search-wrap">
                    <label class="dx-search mb-0">
                        <i class="fas fa-search"></i>
                        <input type="text" id="dxSearch" class="flex-grow-1" placeholder="Search admin pages..." autocomplete="off" aria-label="Search admin pages">
                        <kbd class="d-none d-sm-inline">Ctrl K</kbd>
                    </label>
                </div>
                <div class="d-flex align-items-center ml-3">
                    <button type="button" class="dx-icon-btn d-none d-md-flex mr-2" id="dxFullscreen" aria-label="Toggle fullscreen"><i class="fas fa-expand"></i></button>
                    <button type="button" id="dxThemeToggle" class="dx-icon-btn mr-2" aria-label="Toggle light or dark theme" aria-pressed="false"><i class="fas fa-moon"></i></button>
                    <a class="dx-icon-btn position-relative mr-2" href="<?= $base ?>/admin/review-queue" aria-label="Review queue"><i class="fas fa-bell"></i><?php if ($pendingReviews > 0): ?><span class="dx-dot" title="<?= $pendingReviews ?> observations pending review"></span><?php endif; ?></a>
                    <div class="dropdown">
                        <button class="dx-user dropdown-toggle-split" type="button" id="adminUserMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <span class="dx-avatar"><?= $userInitial ?></span>
                            <span class="d-none d-md-block text-left"><span class="dx-user-name"><?= htmlspecialchars($authUser['name'] ?? 'Admin') ?></span><span class="dx-user-role"><i class="fas fa-shield-alt"></i><?= htmlspecialchars($authUser['role'] ?? 'admin') ?></span></span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right dx-user-menu" aria-labelledby="adminUserMenu">
                            <div class="dx-user-head"><strong><?= htmlspecialchars($authUser['name'] ?? 'Admin') ?></strong><small><?= htmlspecialchars($authUser['email'] ?? '') ?></small></div>
                            <a class="dropdown-item" href="<?= $base ?>/" target="_blank" rel="noopener noreferrer"><i class="fas fa-external-link-alt fa-fw mr-2"></i>Public site</a>
                            <div class="dropdown-divider"></div>
                            <form method="post" action="<?= $base ?>/logout" class="m-0">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                <button type="submit" class="dropdown-item text-danger"><i class="fas fa-power-off fa-fw mr-2"></i>Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <main class="dx-content flex-grow-1">
            <?php if(!empty($_SESSION['flash']) && is_array($_SESSION['flash'])): ?>
                <?php $flash=$_SESSION['flash']; unset($_SESSION['flash']); ?>
                <div class="alert alert-<?= htmlspecialchars((string)($flash['type'] ?? 'info')) ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars((string)($flash['message'] ?? '')) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
            <?php endif; ?>
            <div class="dx-page">
