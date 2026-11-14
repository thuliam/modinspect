<?php
use App\Services\Auth\AuthService;

$base = $config['base_url'];
$authUser = $auth_user ?? (new AuthService())->currentUser();
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="เช็กราคา เช็กสภาพ ก่อนซื้อขายคอมมือสอง">
<title><?= htmlspecialchars($title ?? $config['name']) ?> · <?= htmlspecialchars($config['name']) ?></title>
<link rel="stylesheet" href="<?= $base ?>/assets/app.css?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/app.css') ?>">
</head>
<body class="mb">
<header class="site-header">
    <div class="container nav public-nav">
        <a class="brand" href="<?= $base ?>/" aria-label="ModInspect home">
            <img class="brand-logo" src="<?= $base ?>/assets/images/home-1.jpg" alt="">
            <span>ModInspect</span>
        </a>
        <nav aria-label="Primary navigation">
            <a href="<?= $base ?>/price">เช็กราคา</a>
            <a href="<?= $base ?>/deal-checker">Deal Checker</a>
            <a href="<?= $base ?>/methodology">วิธีคำนวณ</a>
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
