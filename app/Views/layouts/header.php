<?php
use App\Services\Auth\AuthService;

$base = $config['base_url'];
$authUser = $auth_user ?? (new AuthService())->currentUser();
$requestPath = '/' . ltrim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');
$basePath = rtrim((string) parse_url((string) $base, PHP_URL_PATH), '/');
$relativePath = ($basePath !== '' && str_starts_with($requestPath, $basePath)) ? substr($requestPath, strlen($basePath)) : $requestPath;
$relativePath = $relativePath === '' ? '/' : $relativePath;
$navCurrent = static fn(string $path): string => ($relativePath === $path || str_starts_with($relativePath, $path . '/')) ? ' aria-current="page"' : '';
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="เช็กราคา เช็กสภาพ ก่อนซื้อขายคอมมือสอง">
<title><?= htmlspecialchars($title ?? $config['name']) ?> · <?= htmlspecialchars($config['name']) ?></title>
<link rel="stylesheet" href="<?= $base ?>/assets/app.css?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/app.css') ?>">
<script>(function(){var d=document.documentElement,q=matchMedia("(prefers-reduced-motion: reduce)").matches?"calm":"full",m=q;try{var s=localStorage.getItem("mi-motion");if(s==="full"||s==="calm"){m=s}}catch(e){}d.classList.add("js","motion-"+m)})()</script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&display=swap">
</head>
<body class="mb<?= $relativePath === '/' ? ' is-home' : '' ?>">
<header class="site-header">
    <div class="container nav public-nav">
        <a class="brand" href="<?= $base ?>/" aria-label="ModInspect home">
            <svg class="brand-mark" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M3 6h12M9 12h12M3 18h12"/><circle cx="19" cy="6" r="1.6" fill="currentColor" stroke="none"/><circle cx="5" cy="12" r="1.6" fill="currentColor" stroke="none"/><circle cx="19" cy="18" r="1.6" fill="currentColor" stroke="none"/></svg>
            <span>ModInspect</span>
        </a>
        <button class="burger" id="burger" type="button" aria-label="เมนู" aria-expanded="false" aria-controls="site-menu">&#9776;</button>
        <nav class="menu" id="site-menu" aria-label="Primary navigation">
            <a href="<?= $base ?>/price"<?= $navCurrent('/price') ?>>เช็กราคา</a>
            <a href="<?= $base ?>/deal-checker"<?= $navCurrent('/deal-checker') ?>>Deal Checker</a>
            <a href="<?= $base ?>/methodology"<?= $navCurrent('/methodology') ?>>วิธีคำนวณ</a>
            <a class="gs" href="<?= $base ?>/price">เริ่มเช็กราคา</a>
        </nav>
        <?php if ($authUser): ?>
            <?php // Staff convenience only: no name/role chip and no logout form on the public site. Visitors never see an Admin entry. ?>
            <div class="nav-actions">
                <a class="login-button" href="<?= $base ?>/admin">หลังบ้าน</a>
            </div>
        <?php endif; ?>
    </div>
</header>
<main>
