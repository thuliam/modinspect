<?php
$base = $config['base_url'];
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
<meta name="description" content="ModInspect Admin login">
<title><?= htmlspecialchars($title ?? 'Admin Login') ?> - <?= htmlspecialchars($config['name']) ?></title>
<link rel="stylesheet" href="<?= $base ?>/assets/admin/vendor/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= $base ?>/assets/admin/vendor/fontawesome/css/fontawesome-all.css">
<link rel="stylesheet" href="<?= $base ?>/assets/admin/css/admin.css">
</head>
<body class="mi-auth-body">
