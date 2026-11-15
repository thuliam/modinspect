<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function assert_prune(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/modinspect_prune_' . bin2hex(random_bytes(4));
mkdir($tmp);
$in = $tmp . '/in.css'; $out = $tmp . '/out.css';

// "container" is certainly a used class (Bootstrap grid in the views); the other names exist nowhere in app/ or public/assets JS.
file_put_contents($in, <<<'CSS'
:root { --x: 1; }
.container { margin: 0; }
.zz-dead-class-1 { color: red; }
.container .zz-dead-class-2, .zz-dead-class-3 { color: blue; }
.container, .zz-dead-class-4 { padding: 0; }
.container:not(.zz-dead-class-5) { border: 1px solid #000; }
@media (max-width: 600px) { .zz-dead-class-6 { display: none; } .container { margin: 1px; } }
@font-face { font-family: X; src: url(x.woff); }
@keyframes zz-dead-anim { from { opacity: 0; } to { opacity: 1; } }
CSS);
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/scripts/prune_dead_css.php') . ' --in=' . escapeshellarg($in) . ' --out=' . escapeshellarg($out) . ' 2>&1', $log, $code);
assert_prune($code === 0, 'pruner failed: ' . implode(' ', $log));
$css = (string)file_get_contents($out);

assert_prune(!str_contains($css, 'zz-dead-class-1'), 'A rule whose only class is unused must be removed');
assert_prune(!str_contains($css, 'zz-dead-class-2') && !str_contains($css, 'zz-dead-class-3'), 'Rules where every selector is dead must be removed');
assert_prune(str_contains($css, 'padding: 0') && !str_contains($css, 'zz-dead-class-4'), 'Only the dead selector of a mixed list is trimmed');
assert_prune(str_contains($css, 'border: 1px solid #000') && str_contains($css, 'zz-dead-class-5'), 'An unused class inside :not() must not kill the rule');
assert_prune(!str_contains($css, 'zz-dead-class-6') && str_contains($css, 'margin: 1px'), 'Dead rules inside @media are removed, live ones kept');
assert_prune(str_contains($css, ':root') && str_contains($css, '@font-face') && str_contains($css, '@keyframes'), ':root, @font-face and @keyframes are never touched');
assert_prune(str_contains((string)file_get_contents($in), 'zz-dead-class-1'), 'The input file must never be edited');

foreach ([$in, $out] as $f) { @unlink($f); }
@rmdir($tmp);
echo "ui_prune_css_test: PASS\n";
