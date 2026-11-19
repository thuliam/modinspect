<?php
declare(strict_types=1);

/**
 * LS-20 C3/C4 (found in the PHP error log): application code must not raise warnings for ordinary input.
 *  - the "read the key again after testing its default" form: in_array($_GET['x'] ?? 'd', [...]) ? (string)$_GET['x'] : 'd' returns '' and warns when x is missing
 *    (it broke the default of the unresolved-listings page and saved articles without a status);
 *  - the 500 and 419 pages give the visitor a way on, and the 500 page stays standalone and shows no variable.
 */
function ok_wn(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
$root = dirname(__DIR__);

$bad = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!str_ends_with($f->getFilename(), '.php')) { continue; }
    foreach (file($f->getPathname()) ?: [] as $n => $line) {
        // in_array(<super-global read with a default>, [allowed], true) ? (string)<the same super-global key again> : ...
        // It only misbehaves when the DEFAULT is itself an allowed value: then a missing key passes the test and the second read returns '' (and warns).
        if (!preg_match('/in_array\((?:\(string\)\()?\$_(GET|POST)\[([^\]]+)\]\s*\?\?\s*\'([^\']*)\'\)?\s*,\s*\[([^\]]*)\]\s*,\s*true\)\s*\?\s*\(string\)\s*\$_\1\[\2\]/', $line, $m)) { continue; }
        if (str_contains($m[4], "'" . $m[3] . "'")) { $bad[] = substr(str_replace(chr(92), '/', $f->getPathname()), strlen($root) + 1) . ':' . ($n + 1); }
    }
}
ok_wn($bad === [], "A missing parameter would warn and give '' instead of its default:\n  " . implode("\n  ", $bad));

// the fixed lines behave: simulate the new form
$pick = static function (array $in, array $allowed, string $default): string { $s = (string)($in['status'] ?? $default); return in_array($s, $allowed, true) ? $s : $default; };
ok_wn($pick([], ['open', 'stale'], 'open') === 'open' && $pick(['status' => 'stale'], ['open', 'stale'], 'open') === 'stale' && $pick(['status' => 'x'], ['open', 'stale'], 'open') === 'open', 'A missing, valid, unknown value gives the default, the value, the default');
$ctl = (string)file_get_contents($root . '/app/Controllers/AdminController.php');
ok_wn(str_contains($ctl, "\$status=(string)(\$_GET['status'] ?? 'open'); if(!in_array(\$status,['open','stale','decided','all'],true)){ \$status='open'; }") && str_contains($ctl, "\$status=(string)(\$_POST['status'] ?? 'draft'); if(!in_array(\$status,['draft','published','archived'],true)){ \$status='draft'; }"), 'Both Admin controller lines use the fixed form');

$boot = (string)file_get_contents($root . '/app/bootstrap.php');
ok_wn(str_contains($boot, '<title>ระบบขัดข้อง</title>') && str_contains($boot, 'href="./">กลับหน้าแรก</a>') && str_contains($boot, 'name="viewport"'), 'The 500 page is a complete small page with a link home');
preg_match("/echo '<!doctype html><html lang=.*?;\r?\n/s", $boot, $m);
ok_wn(isset($m[0]) && !str_contains($m[0], '$'), 'The 500 page output contains no variable (nothing from the failure reaches the visitor)');
$e419 = (string)file_get_contents($root . '/app/Views/errors/419.php');
ok_wn(str_contains($e419, "str_starts_with(\$ref, rtrim((string) \$base, '/') . '/')") && str_contains($e419, 'htmlspecialchars($back)'), 'The 419 page goes back to the same-site referrer only, escaped');
foreach (['403', '404', '419', '429'] as $c) { ok_wn(preg_match('/<a [^>]*href=/', (string)file_get_contents($root . "/app/Views/errors/$c.php")) === 1, "The $c page has a link to go on"); }
echo "ls20_warnings_test: PASS\n";
