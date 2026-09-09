<?php use App\Core\Csrf; use App\Services\Auth\AuthService; $base=$config['base_url']; $authUser=$auth_user ?? (new AuthService())->currentUser(); ?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="เช็กราคาคอมพิวเตอร์มือสองจากหลายตัวอย่าง พร้อมความสดและความมั่นใจ">
<title><?= htmlspecialchars($title??$config['name']) ?> · <?= htmlspecialchars($config['name']) ?></title>
<link rel="stylesheet" href="<?= $base ?>/assets/app.css">
</head><body>
<header class="site-header"><div class="container nav">
<a class="brand" href="<?= $base ?>/"><img class="brand-logo" src="<?= $base ?>/assets/images/home-1.jpg" alt="ModInspect"><span>ModInspect</span></a>
<nav><a href="<?= $base ?>/price">เช็กราคา</a><a href="<?= $base ?>/deal-checker">เช็กดีล</a><a href="<?= $base ?>/build">จัดสเปกมือสอง</a><a href="<?= $base ?>/shop">สำหรับร้านค้า</a></nav>
<div class="nav-actions"><span class="material-symbols-outlined globe">language</span><?php if($authUser): ?><span class="login-button"><?= htmlspecialchars($authUser['name']) ?> · <?= htmlspecialchars($authUser['role']) ?></span><form method="post" action="<?= $base ?>/logout" class="inline-form"><input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token()) ?>"><button type="submit" class="ghost-button">ออกจากระบบ</button></form><?php else: ?><a class="login-button" href="<?= $base ?>/login">เข้าสู่ระบบ</a><?php endif; ?></div>
</div></header><main>
