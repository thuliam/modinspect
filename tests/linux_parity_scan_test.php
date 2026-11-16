<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/**
 * Windows-vs-Linux parity scan (LS-2 H-12). The project is developed on Windows (case-insensitive file names, drive letters, backslashes)
 * and runs CI and production on Linux. This static scan catches the classes of mistake that only show up there:
 *   1. a `use App\...` class whose file name or folder differs in case from the namespace (autoload fails on Linux only);
 *   2. a literal project path in ROOT_PATH/__DIR__ concatenations or in require/include whose case differs from the file on disk;
 *   3. a required/included literal file that does not exist;
 *   4. hard-coded drive letters, `cmd /c`, `taskkill` and backslash-separated paths in shipped PHP (allowed only where listed below);
 *   5. tests that hand the importer a file from the system temp dir (the importer only reads inside the project root on Linux: the CI failure of 2026-11-16).
 */
function ok_lp(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$root = dirname(__DIR__);
$files = [];
foreach (['app', 'cli', 'config', 'public', 'tests', 'scripts'] as $dir) {
    if (!is_dir("$root/$dir")) { continue; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir", FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $p = str_replace('\\', '/', $f->getPathname());
        if ($f->isFile() && str_ends_with($p, '.php') && !str_contains($p, '/vendor/')) { $files[] = $p; }
    }
}
ok_lp(count($files) > 100, 'The scan sees the project files (' . count($files) . ')');

/** exact-case existence: compares every path segment against the real directory listing */
$existsExact = static function (string $path) use ($root): bool {
    $rel = ltrim(substr(str_replace('\\', '/', $path), strlen($root)), '/');
    $cur = $root;
    foreach (explode('/', $rel) as $seg) {
        if ($seg === '' || $seg === '.') { continue; }
        $names = @scandir($cur);
        if ($names === false || !in_array($seg, $names, true)) { return false; }
        $cur .= '/' . $seg;
    }
    return true;
};
$existsAnyCase = static fn(string $path): bool => file_exists($path);   // true on Windows for a wrong-case name; false on Linux

$problems = [];
$classUses = 0; $pathRefs = 0;
foreach ($files as $file) {
    $rel = substr($file, strlen($root) + 1);
    $src = (string)file_get_contents($file);

    // 1. namespace -> file (PSR-4: App\ maps to app/)
    if (preg_match_all('/^use\s+(App\\\\[A-Za-z0-9_\\\\]+?)(?:\s+as\s+\w+)?;/m', $src, $m)) {
        foreach ($m[1] as $fqcn) {
            $classUses++;
            $target = $root . '/app/' . str_replace('\\', '/', substr($fqcn, 4)) . '.php';
            if (!$existsExact($target)) { $problems[] = "$rel: class $fqcn has no file with that exact case (" . substr($target, strlen($root) + 1) . ')'; }
        }
    }

    // 2./3. literal paths glued to ROOT_PATH, dirname(__DIR__) or __DIR__
    if (preg_match_all("/(ROOT_PATH|dirname\(__DIR__\)|__DIR__)\s*\.\s*'(\/[A-Za-z0-9_\-\.\/]+)'/", $src, $pm, PREG_SET_ORDER)) {
        foreach ($pm as [, $base, $lit]) {
            $pathRefs++;
            $abs = $base === '__DIR__' ? dirname($file) . $lit : ($base === 'ROOT_PATH' ? $root . $lit : dirname(dirname($file)) . $lit);
            if (str_contains($abs, '/storage/') || str_contains($abs, '/.env') || str_contains($lit, 'storage/')) { continue; }   // created or provided at runtime
            if (file_exists($abs) && !$existsExact($abs)) { $problems[] = "$rel: path '$lit' differs in case from the file on disk"; }
        }
    }
    if (preg_match_all("/\b(?:require|require_once|include|include_once)\s+(ROOT_PATH|dirname\(__DIR__\)|__DIR__)\s*\.\s*'(\/[A-Za-z0-9_\-\.\/]+)'/", $src, $rm, PREG_SET_ORDER)) {
        foreach ($rm as [, $base, $lit]) {
            $abs = $base === '__DIR__' ? dirname($file) . $lit : ($base === 'ROOT_PATH' ? $root . $lit : dirname(dirname($file)) . $lit);
            if (!file_exists($abs)) { $problems[] = "$rel: required file '$lit' does not exist"; }
            elseif (!$existsExact($abs)) { $problems[] = "$rel: required file '$lit' differs in case from the file on disk"; }
        }
    }
    // 4. Windows-only constructs in shipped code (tests of this scanner itself and the binary lookup are listed)
    $allowed = ['tests/linux_parity_scan_test.php' => true, 'app/Services/Ops/DatabaseBackup.php' => true];
    if (!isset($allowed[$rel])) {
        if (preg_match('/[\'"][A-Za-z]:[\\\\\/](?!\/)[A-Za-z]/', $src, $mm) && !str_starts_with($rel, 'tests/')) { $problems[] = "$rel: hard-coded drive-letter path ({$mm[0]})"; }
        if (preg_match('/\b(cmd\s*\/c|taskkill|powershell(\.exe)?\s|\bwmic\b)/i', $src, $mm) && !str_starts_with($rel, 'tests/')) { $problems[] = "$rel: Windows-only command ({$mm[1]})"; }
        if (preg_match("/'\\\\\\\\(app|public|storage|config|cli)\\\\\\\\/", $src, $mm)) { $problems[] = "$rel: backslash-separated project path"; }
    }

    // 5. importer fixtures must live inside the project root
    if (str_starts_with($rel, 'tests/') && $rel !== 'tests/linux_parity_scan_test.php' && preg_match('/sys_get_temp_dir\(\)[^;]*\.(csv|json)/', $src) && preg_match('/->import\(/', $src)) {
        $problems[] = "$rel: hands the importer a file from the system temp dir (it only reads inside the project root on Linux)";
    }
}
ok_lp($classUses > 100, "Class uses were scanned ($classUses)");
ok_lp($pathRefs > 20, "Path references were scanned ($pathRefs)");
ok_lp($problems === [], "Linux parity problems:\n  " . implode("\n  ", $problems));

echo "linux_parity_scan_test: PASS (" . count($files) . " PHP files, $classUses class uses, $pathRefs literal path references)\n";
