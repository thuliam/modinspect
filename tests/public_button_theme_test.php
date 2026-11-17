<?php
declare(strict_types=1);

/**
 * LS-6 M-4: public pages must not use Bootstrap's btn-outline-secondary. On hover the theme paints it lime while Bootstrap keeps the text white
 * (measured in the browser on /price/<slug>: white on lime, contrast 1.32, unreadable). The theme's own secondary button is btn-ghost
 * (mi-theme.css), which sets text and background together. Admin views are styled by their own sheets (mi-admin.css) and are not covered here.
 */
function ok_pb(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$root = dirname(__DIR__) . '/app/Views';
$bad = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    $path = str_replace(chr(92), '/', $file->getPathname());
    if (!str_ends_with($path, '.php') || str_contains($path, '/Views/admin/')) { continue; }
    if (str_contains((string)file_get_contents($path), 'btn-outline-secondary')) { $bad[] = substr($path, strlen(str_replace(chr(92), '/', $root)) + 1); }
}
ok_pb($bad === [], 'Public views use btn-outline-secondary (unreadable on hover); use btn-ghost: ' . implode(', ', $bad));
$theme = (string)file_get_contents(dirname(__DIR__) . '/public/assets/mi-theme.css');
ok_pb(str_contains($theme, 'body.mi .btn-ghost:hover'), 'The theme still defines the btn-ghost hover state the public views rely on');
echo "public_button_theme_test: PASS\n";
