<?php
use App\Services\Auth\AuthService;

$base = $config['base_url'];
$authUser = $auth_user ?? (new AuthService())->currentUser();
$requestPath = '/' . ltrim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');
$basePath = rtrim((string) parse_url((string) $base, PHP_URL_PATH), '/');
$relativePath = ($basePath !== '' && str_starts_with($requestPath, $basePath)) ? substr($requestPath, strlen($basePath)) : $requestPath;
$relativePath = $relativePath === '' ? '/' : $relativePath;
$navCurrent = static fn(string $path): string => ($path === '/' ? $relativePath === '/' : ($relativePath === $path || str_starts_with($relativePath, $path . '/'))) ? ' active" aria-current="page' : '';
require_once ROOT_PATH . '/app/Views/components/hw-art.php';
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="เช็กราคา เช็กสภาพ ก่อนซื้อขายคอมมือสอง">
<title><?= htmlspecialchars($title ?? $config['name']) ?> · <?= htmlspecialchars($config['name']) ?></title>
<link rel="icon" href="<?= $base ?>/favicon.ico?v=<?= (int) @filemtime(ROOT_PATH . '/public/favicon.ico') ?>" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="<?= $base ?>/assets/brand/favicon-32x32.png?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/brand/favicon-32x32.png') ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?= $base ?>/assets/brand/favicon-16x16.png?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/brand/favicon-16x16.png') ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?= $base ?>/assets/brand/apple-touch-icon.png?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/brand/apple-touch-icon.png') ?>">
<meta name="theme-color" content="#0a0d14">
<link rel="preload" href="<?= $base ?>/assets/fonts/PlusJakartaSans-700.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= $base ?>/assets/fonts/PlusJakartaSans-800.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= $base ?>/assets/fonts/Prompt-Thai-600.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= $base ?>/assets/admin/vendor/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= $base ?>/assets/admin/vendor/fontawesome/css/fontawesome-all.css">
<link rel="stylesheet" href="<?= $base ?>/assets/app.css?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/app.css') ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/ci-tokens.css?v=<?= (int) @filemtime(ROOT_PATH . "/public/assets/ci-tokens.css") ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/mi-theme.css?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/mi-theme.css') ?>">
<script>(function(){var d=document.documentElement,q=matchMedia("(prefers-reduced-motion: reduce)").matches?"calm":"full",m=q;try{var s=localStorage.getItem("mi-motion");if(s==="full"||s==="calm"){m=s}}catch(e){}d.classList.add("js","motion-"+m)})()</script>
</head>
<body class="mb mi<?= $relativePath === '/' ? ' is-home' : '' ?>">
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs>
    <linearGradient id="miLime" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#e6ff6a"/><stop offset="1" stop-color="#9dbd1a"/></linearGradient>
    <linearGradient id="miMetal" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3b4440"/><stop offset="1" stop-color="#171c1a"/></linearGradient>
    <radialGradient id="miScreen" cx=".3" cy=".25" r=".95"><stop offset="0" stop-color="#d4f139" stop-opacity=".85"/><stop offset=".5" stop-color="#3b5a12" stop-opacity=".8"/><stop offset="1" stop-color="#0b0e0d"/></radialGradient>
    <radialGradient id="miGlow" cx=".5" cy=".5" r=".6"><stop offset="0" stop-color="#d4f139" stop-opacity=".35"/><stop offset="1" stop-color="#d4f139" stop-opacity="0"/></radialGradient>
</defs></svg>
<header class="site-header">
    <nav class="navbar navbar-expand-lg navbar-dark container public-nav" aria-label="Primary navigation">
        <a class="navbar-brand brand" href="<?= $base ?>/" aria-label="modinpect home"><?= mi_logo($base) ?></a>
        <button class="navbar-toggler burger" type="button" data-toggle="collapse" data-target="#site-menu" aria-controls="site-menu" aria-expanded="false" aria-label="เมนู"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="site-menu">
            <ul class="navbar-nav mx-lg-auto">
                <li class="nav-item"><a class="nav-link<?= $navCurrent('/') ?>" href="<?= $base ?>/">หน้าแรก</a></li>
                <li class="nav-item"><a class="nav-link<?= $navCurrent('/price') ?>" href="<?= $base ?>/price">เช็กราคา</a></li>
                <li class="nav-item"><a class="nav-link<?= $navCurrent('/deal-checker') ?>" href="<?= $base ?>/deal-checker">Deal Checker</a></li>
                <li class="nav-item"><a class="nav-link<?= $navCurrent('/methodology') ?>" href="<?= $base ?>/methodology">วิธีคำนวณ</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="cat-menu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">หมวดหมู่</a>
                    <div class="dropdown-menu" aria-labelledby="cat-menu">
                        <a class="dropdown-item" href="<?= $base ?>/price?category=cpu">CPU</a>
                        <a class="dropdown-item" href="<?= $base ?>/price?category=gpu">GPU</a>
                        <a class="dropdown-item" href="<?= $base ?>/price?category=ram">RAM</a>
                        <a class="dropdown-item" href="<?= $base ?>/price?category=storage">Storage</a>
                    </div>
                </li>
            </ul>
            <div class="nav-tools">
                <a class="nav-icon" href="<?= $base ?>/price" aria-label="ค้นหารุ่นสินค้า"><i class="fas fa-search" aria-hidden="true"></i></a>
                <?php if ($authUser): ?><a class="login-button" href="<?= $base ?>/admin">หลังบ้าน</a><?php endif; ?>
            </div>
        </div>
    </nav>
</header>
<main>
