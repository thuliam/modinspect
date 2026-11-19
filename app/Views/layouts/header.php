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
<?php
$seoDescription = \App\Services\Seo\SeoService::description('เช็กราคา เช็กสภาพ ก่อนซื้อขายคอมมือสอง', $metaDescription ?? null);
$seoTitle = ($title ?? $config['name']) . ' · ' . $config['name'];
$seoUrl = \App\Services\Seo\SeoService::canonical((string) $config['base_url'], $relativePath);
?>
<meta name="description" content="<?= htmlspecialchars($seoDescription) ?>">
<link rel="canonical" href="<?= htmlspecialchars($seoUrl) ?>">
<?php if (!empty($config['pilot'])) { ?><meta name="robots" content="noindex,nofollow,noarchive">
<?php } else { foreach (\App\Services\Seo\SeoService::DISALLOW as $noindex) { if (str_starts_with($relativePath, $noindex)) { echo "<meta name=\"robots\" content=\"noindex,nofollow\">
"; break; } } } ?>
<meta property="og:type" content="website">
<meta property="og:locale" content="th_TH">
<meta property="og:site_name" content="<?= htmlspecialchars($config['name']) ?>">
<meta property="og:title" content="<?= htmlspecialchars($seoTitle) ?>">
<meta property="og:description" content="<?= htmlspecialchars($seoDescription) ?>">
<meta property="og:url" content="<?= htmlspecialchars($seoUrl) ?>">
<?php // LS-16 U-4: link previews in chat apps get the brand hero picture (an absolute URL, as Open Graph requires) ?>
<?php $ogDefault = rtrim((string) $config['base_url'], '/') . '/assets/brand/hero-hardware.png'; $ogImage = isset($ogImage) && is_string($ogImage) && $ogImage !== '' ? $ogImage : $ogDefault; // LS-19 C2: a product page passes its own photo ?>
<meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
<?php if (!empty($ogWidth) && !empty($ogHeight)): ?><meta property="og:image:width" content="<?= (int) $ogWidth ?>">
<meta property="og:image:height" content="<?= (int) $ogHeight ?>">
<?php elseif ($ogImage === $ogDefault): ?><meta property="og:image:width" content="1026">
<meta property="og:image:height" content="436">
<?php endif; ?>
<?php if (!empty($jsonLd) && is_string($jsonLd)): // LS-19 C1: schema.org data built by SeoService::productJsonLd (JSON_HEX_TAG, so it cannot close this tag) ?>
<script type="application/ld+json"><?= $jsonLd ?></script>
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<title><?= htmlspecialchars($title ?? $config['name']) ?> · <?= htmlspecialchars($config['name']) ?></title>
<link rel="icon" href="<?= $base ?>/favicon.ico?v=<?= (int) @filemtime(ROOT_PATH . '/public/favicon.ico') ?>" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="<?= $base ?>/assets/brand/favicon-32x32.png?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/brand/favicon-32x32.png') ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?= $base ?>/assets/brand/favicon-16x16.png?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/brand/favicon-16x16.png') ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?= $base ?>/assets/brand/apple-touch-icon.png?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/brand/apple-touch-icon.png') ?>">
<meta name="theme-color" content="#0a0d14">
<link rel="manifest" href="<?= $base ?>/manifest.webmanifest">
<link rel="preload" href="<?= $base ?>/assets/fonts/PlusJakartaSans-700.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= $base ?>/assets/fonts/PlusJakartaSans-800.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= $base ?>/assets/fonts/Prompt-Thai-600.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= $base ?>/assets/admin/vendor/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= $base ?>/assets/admin/vendor/fontawesome/css/fontawesome-all.css?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/admin/vendor/fontawesome/css/fontawesome-all.css') ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/app.css?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/app.css') ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/ci-tokens.css?v=<?= (int) @filemtime(ROOT_PATH . "/public/assets/ci-tokens.css") ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/admin/vendor/select2/select2.min.css">
<link rel="stylesheet" href="<?= $base ?>/assets/mi-select2.css?v=<?= (int) @filemtime(ROOT_PATH . "/public/assets/mi-select2.css") ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/mi-theme.css?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/mi-theme.css') ?>">
<script src="<?= $base ?>/assets/mi-motion-init.js?v=<?= (int) @filemtime(ROOT_PATH . "/public/assets/mi-motion-init.js") ?>"></script>
</head>
<body class="mb mi<?= $relativePath === '/' ? ' is-home' : '' ?>">
<a class="sr-only sr-only-focusable mi-skip" href="#main">ข้ามไปยังเนื้อหา</a>
<?php if (!empty($config['pilot'])) { require ROOT_PATH . '/app/Views/components/pilot-banner.php'; } ?>
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs>
    <linearGradient id="miLime" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#e6ff6a"/><stop offset="1" stop-color="#9dbd1a"/></linearGradient>
    <linearGradient id="miMetal" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3b4440"/><stop offset="1" stop-color="#171c1a"/></linearGradient>
    <radialGradient id="miScreen" cx=".3" cy=".25" r=".95"><stop offset="0" stop-color="#d4f139" stop-opacity=".85"/><stop offset=".5" stop-color="#3b5a12" stop-opacity=".8"/><stop offset="1" stop-color="#0b0e0d"/></radialGradient>
    <radialGradient id="miGlow" cx=".5" cy=".5" r=".6"><stop offset="0" stop-color="#d4f139" stop-opacity=".35"/><stop offset="1" stop-color="#d4f139" stop-opacity="0"/></radialGradient>
</defs></svg>
<header class="site-header">
    <nav class="navbar navbar-expand-xl navbar-dark container public-nav" aria-label="Primary navigation">
        <a class="navbar-brand brand" href="<?= $base ?>/" aria-label="modinpect home"><?= mi_logo($base) ?></a>
        <button class="navbar-toggler burger" type="button" data-toggle="collapse" data-target="#site-menu" aria-controls="site-menu" aria-expanded="false" aria-label="เมนู"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="site-menu">
            <ul class="navbar-nav mx-xl-auto text-nowrap">
                <li class="nav-item"><a class="nav-link<?= $navCurrent('/') ?>" href="<?= $base ?>/">หน้าแรก</a></li>
                <li class="nav-item"><a class="nav-link<?= $navCurrent('/price') ?>" href="<?= $base ?>/price">เช็กราคา</a></li>
                <li class="nav-item"><a class="nav-link<?= $navCurrent('/compare') ?>" href="<?= $base ?>/compare">เทียบรุ่น</a></li>
                <li class="nav-item"><a class="nav-link<?= $navCurrent('/condition-guide') ?>" href="<?= $base ?>/condition-guide">เช็กสภาพ</a></li>
                <li class="nav-item"><a class="nav-link<?= $navCurrent('/deal-checker') ?>" href="<?= $base ?>/deal-checker">Deal Checker</a></li>
                <li class="nav-item"><a class="nav-link<?= $navCurrent('/read-listing') ?>" href="<?= $base ?>/read-listing">วางประกาศ</a></li>
                <li class="nav-item"><a class="nav-link<?= $navCurrent('/blog') ?>" href="<?= $base ?>/blog">บทความ</a></li>
                <li class="nav-item"><a class="nav-link<?= $navCurrent('/build') ?>" href="<?= $base ?>/build">ประกอบคอม</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="cat-menu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">เพิ่มเติม</a>
                    <div class="dropdown-menu" aria-labelledby="cat-menu">
                        <a class="dropdown-item" href="<?= $base ?>/methodology">วิธีคำนวณ</a>
                        <div class="dropdown-divider"></div>
                        <h6 class="dropdown-header">หมวดหมู่</h6>
                        <?php foreach (['cpu' => 'CPU', 'gpu' => 'GPU', 'motherboard' => 'Motherboard', 'ram' => 'RAM', 'storage' => 'Storage', 'psu' => 'PSU', 'case' => 'Case', 'cooling' => 'Cooling'] as $navCat => $navLabel): ?>
                        <a class="dropdown-item" href="<?= $base ?>/price?category=<?= $navCat ?>"><?= $navLabel ?></a>
                        <?php endforeach; ?>
                    </div>
                </li>
            </ul>
            <div class="nav-tools">
                <a class="nav-icon" href="<?= $base ?>/price" aria-label="ค้นหารุ่นสินค้า" title="ค้นหารุ่น (กด / ได้ทุกหน้า)"><i class="fas fa-search" aria-hidden="true"></i></a>
                <?php if ($authUser): ?><a class="login-button" href="<?= $base ?>/admin">หลังบ้าน</a><?php endif; ?>
                <?php $memberOffered = array_filter($config['members'] ?? [], static fn($p) => is_array($p) && trim((string) ($p['client_id'] ?? '')) !== '' && trim((string) ($p['client_secret'] ?? '')) !== ''); $memberNow = !empty($_SESSION[\App\Services\Member\MemberAuth::SESSION_MEMBER_ID]); ?>
                <?php if ($memberNow): ?><a class="login-button" href="<?= $base ?>/account">บัญชีของฉัน</a><?php elseif ($memberOffered): ?><a class="login-button" href="<?= $base ?>/account/login">เข้าสู่ระบบ</a><?php endif; ?>
            </div>
        </div>
    </nav>
</header>
<main id="main" tabindex="-1">
