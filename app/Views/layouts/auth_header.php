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
<link rel="stylesheet" href="<?= $base ?>/assets/ci-tokens.css?v=<?= (int) @filemtime(ROOT_PATH . "/public/assets/ci-tokens.css") ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/mi-dropx.css?v=<?= (int) @filemtime(ROOT_PATH . "/public/assets/mi-dropx.css") ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/mi-auth.css?v=<?= (int) @filemtime(ROOT_PATH . "/public/assets/mi-auth.css") ?>">
<script src="<?= $base ?>/assets/mi-dropx.js?v=<?= (int) @filemtime(ROOT_PATH . "/public/assets/mi-dropx.js") ?>"></script>
<link rel="icon" href="<?= $base ?>/favicon.ico?v=<?= (int) @filemtime(ROOT_PATH . "/public/favicon.ico") ?>" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="<?= $base ?>/assets/brand/favicon-32x32.png">
<link rel="apple-touch-icon" href="<?= $base ?>/assets/brand/apple-touch-icon.png">
</head>
<body class="mi-auth-body mi-admin-body">
