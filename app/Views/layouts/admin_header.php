<?php
use App\Core\Csrf;
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
    ],
    'MARKET DATA' => [
        ['label' => 'Observations', 'icon' => 'fa-list-ul', 'href' => '/admin/price-observations', 'permission' => 'review.view', 'paths' => ['/admin/price-observations']],
        ['label' => 'Review Queue', 'icon' => 'fa-clipboard-check', 'href' => '/admin/review-queue', 'permission' => 'review.view', 'paths' => ['/admin/review-queue', '/admin/review']],
        ['label' => 'Review Analytics', 'icon' => 'fa-chart-bar', 'href' => '/admin/review-analytics', 'permission' => 'review.analytics.view', 'paths' => ['/admin/review-analytics']],
        ['label' => 'Price Index', 'icon' => 'fa-tags', 'href' => '/admin/price-indices', 'permission' => 'snapshots.view', 'paths' => ['/admin/price-indices']],
    ],
    'COLLECTION' => [
        ['label' => 'Sources', 'icon' => 'fa-broadcast-tower', 'href' => '/admin/sources', 'permission' => 'sources.view', 'paths' => ['/admin/sources']],
        ['label' => 'Imports', 'icon' => 'fa-download', 'href' => '/admin/collector-jobs', 'permission' => 'collection.jobs.view', 'paths' => ['/admin/collector-jobs']],
    ],
    'CONTENT' => [
        ['label' => 'Articles', 'icon' => 'fa-newspaper', 'href' => '/admin/articles', 'permission' => 'products.view', 'paths' => ['/admin/articles']],
    ],
    'SYSTEM' => [
        ['label' => 'Audit', 'icon' => 'fa-history', 'href' => '/admin/audit-logs', 'permission' => 'audit.view', 'paths' => ['/admin/audit-logs']],
    ],
];
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
<meta name="description" content="ModInspect Admin operations console">
<title><?= htmlspecialchars($title ?? 'Admin') ?> - <?= htmlspecialchars($config['name']) ?> Admin</title>
<link rel="stylesheet" href="<?= $base ?>/assets/admin/vendor/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= $base ?>/assets/admin/vendor/fontawesome/css/fontawesome-all.css">
<link rel="stylesheet" href="<?= $base ?>/assets/admin/vendor/datatables/css/dataTables.bootstrap4.css">
<link rel="stylesheet" href="<?= $base ?>/assets/admin/css/admin.css">
</head>
<body class="mi-admin-body">
<div class="dashboard-main-wrapper mi-admin-shell">
    <div class="dashboard-header mi-admin-topbar">
        <nav class="navbar navbar-expand-lg bg-white fixed-top">
            <button class="btn btn-link mi-sidebar-toggle js-sidebar-toggle" type="button" aria-label="Toggle admin navigation">
                <i class="fas fa-bars"></i>
            </button>
            <a class="navbar-brand" href="<?= $base ?>/admin">ModInspect Admin</a>
            <span class="mi-topbar-context"><?= htmlspecialchars($title ?? 'Operations') ?></span>
            <ul class="navbar-nav ml-auto navbar-right-top">
                <li class="nav-item">
                    <a class="nav-link mi-public-link" href="<?= $base ?>/" target="_blank" rel="noopener noreferrer"><i class="fas fa-external-link-alt mr-1"></i> Public Site</a>
                </li>
                <li class="nav-item dropdown nav-user">
                    <a class="nav-link dropdown-toggle mi-user-toggle" href="#" id="adminUserMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="mi-user-avatar"><?= htmlspecialchars(strtoupper(substr((string)($authUser['name'] ?? 'A'),0,1))) ?></span>
                        <span><?= htmlspecialchars($authUser['name'] ?? 'Admin') ?></span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right nav-user-dropdown" aria-labelledby="adminUserMenu">
                        <div class="mi-user-card">
                            <strong><?= htmlspecialchars($authUser['name'] ?? 'Admin') ?></strong>
                            <small><?= htmlspecialchars($authUser['email'] ?? '') ?></small>
                            <span class="badge badge-light"><?= htmlspecialchars($authUser['role'] ?? 'admin') ?></span>
                        </div>
                        <form method="post" action="<?= $base ?>/logout" class="px-3 py-2">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                            <button type="submit" class="btn btn-outline-danger btn-block"><i class="fas fa-power-off mr-2"></i>Logout</button>
                        </form>
                    </div>
                </li>
            </ul>
        </nav>
    </div>
    <aside class="nav-left-sidebar sidebar-dark mi-admin-sidebar">
        <div class="menu-list">
            <nav class="navbar navbar-expand-lg navbar-light">
                <a class="d-xl-none d-lg-none mi-mobile-menu-title" href="<?= $base ?>/admin">Admin Menu</a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#adminSidebarNav" aria-controls="adminSidebarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="adminSidebarNav">
                    <ul class="navbar-nav flex-column">
                        <?php foreach($groups as $group => $links): ?>
                            <?php $visibleLinks=array_values(array_filter($links, fn($link) => $authz->can($authUser, $link['permission']))); ?>
                            <?php if(!$visibleLinks) continue; ?>
                            <li class="nav-divider"><?= htmlspecialchars($group) ?></li>
                            <?php foreach($visibleLinks as $link): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?= $isActive($link['paths']) ?>" href="<?= $base.htmlspecialchars($link['href']) ?>">
                                        <i class="fas fa-fw <?= htmlspecialchars($link['icon']) ?>"></i>
                                        <span><?= htmlspecialchars($link['label']) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </nav>
        </div>
    </aside>
    <main class="dashboard-wrapper mi-admin-main">
        <div class="dashboard-content mi-admin-content">
