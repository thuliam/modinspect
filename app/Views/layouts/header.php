<?php
use App\Core\Csrf;
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
<link rel="stylesheet" href="<?= $base ?>/assets/app.css">
</head>
<body>
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
        <div class="nav-actions">
            <?php if ($authUser): ?>
                <span class="login-button"><?= htmlspecialchars($authUser['name']) ?> · <?= htmlspecialchars($authUser['role']) ?></span>
                <form method="post" action="<?= $base ?>/logout" class="inline-form">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                    <button type="submit" class="ghost-button">ออกจากระบบ</button>
                </form>
            <?php else: ?>
                <a class="login-button" href="<?= $base ?>/login">Admin</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<main>
