<?php
declare(strict_types=1);

/** LS-62/63: the admin views carry no inline styles (spacing and size come from classes in mi-dropx.css), the form pattern rules exist, and the status field is a checkbox (Bootstrap 4.1 has no custom-switch). */
function ok_ui(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
$root = dirname(__DIR__);
foreach (glob($root . '/app/Views/admin/*.php') ?: [] as $f) {
    ok_ui(!str_contains((string) file_get_contents($f), ' style="'), 'inline style in ' . basename($f));
    ok_ui(!str_contains((string) file_get_contents($f), 'custom-switch'), 'custom-switch (unsupported in Bootstrap 4.1) in ' . basename($f));
}
$css = (string) file_get_contents($root . '/public/assets/mi-dropx.css');
foreach (['LS-63: admin form pattern', 'LS-62 audit fixes', 'min-height: 38px', '.mi-th-wide', '.mi-chart-box'] as $needle) { ok_ui(str_contains($css, $needle), 'mi-dropx.css lacks: ' . $needle); }
ok_ui(is_file($root . '/app/Views/admin/ui-kit.php'), 'the UI kit page exists');
echo "admin_ui_pattern_test: PASS\n";
