<section class="container page narrow"><div class="empty"><h1>แบบฟอร์มหมดอายุ</h1><p>โปรดกลับไปกรอกใหม่เพื่อความปลอดภัย</p><?php // LS-20 C3: the form that expired may be any of them, so go back to the page the visitor came from (same site only), else the home page
$ref = (string) ($_SERVER['HTTP_REFERER'] ?? ''); $back = ($ref !== '' && str_starts_with($ref, rtrim((string) $base, '/') . '/') && !str_contains($ref, "
")) ? $ref : $base . '/'; ?>
<a class="button" href="<?= htmlspecialchars($back) ?>">กลับไปกรอกใหม่</a> <a class="button ghost" href="<?= $base ?>/">หน้าแรก</a></div></section>

