<?php
declare(strict_types=1);

/**
 * Static dead-rule pruner for the public CSS (UI rule: less hand-written CSS).
 *
 *   php scripts/prune_dead_css.php --in=public/assets/app.css --out=public/assets/app.pruned.css [--report]
 *
 * A selector is DEAD when it names a class or id that appears nowhere in app/Views/**, public/assets/*.js or app/**\/*.php helpers
 * (conservative: any word anywhere counts as used; names built in PHP such as "confidence-<?= ... ?>" are covered by their prefix).
 * A rule is dropped only when every selector in its list is dead; otherwise only the dead selectors are removed.
 * @font-face, @keyframes, @import, :root and selectors without class/id tokens are never touched. Writes a new file; never edits the input.
 */
$opts = getopt('', ['in:', 'out:', 'report']);
$in = (string)($opts['in'] ?? ''); $out = (string)($opts['out'] ?? '');
if ($in === '' || !is_file($in) || $out === '') { fwrite(STDERR, "Usage: php scripts/prune_dead_css.php --in=file.css --out=file.css [--report]\n"); exit(2); }
$root = dirname(__DIR__);

// 1. words used anywhere in markup/scripts
$used = []; $prefixes = [];
$sources = [];
foreach ([$root . '/app', $root . '/public/assets'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) { continue; }
        $ext = strtolower($f->getExtension());
        if ($ext === 'php' || ($ext === 'js' && !str_contains($f->getPathname(), 'vendor'))) { $sources[] = $f->getPathname(); }
    }
}
foreach ($sources as $path) {
    if (preg_match_all('/[A-Za-z_][\w-]*/', (string)file_get_contents($path), $m)) {
        foreach ($m[0] as $w) { $used[$w] = true; if (str_ends_with($w, '-') || str_ends_with($w, '_')) { $prefixes[$w] = true; } }
    }
}
$isUsed = static function (string $token) use ($used, $prefixes): bool {
    if (isset($used[$token])) { return true; }
    foreach ($prefixes as $p => $_) { if (str_starts_with($token, (string)$p)) { return true; } }
    return false;
};

// 2. split into top-level chunks (rules and at-rules) keeping braces balanced
$css = (string)file_get_contents($in);
$css = preg_replace('/^\xEF\xBB\xBF/', '', $css);
$len = strlen($css);
$chunks = []; $buf = ''; $depth = 0; $inStr = ''; $inComment = false;
for ($i = 0; $i < $len; $i++) {
    $c = $css[$i]; $n = $css[$i + 1] ?? '';
    if ($inComment) { $buf .= $c; if ($c === '*' && $n === '/') { $buf .= '/'; $i++; $inComment = false; if ($depth === 0) { $chunks[] = $buf; $buf = ''; } } continue; }
    if ($inStr !== '') { $buf .= $c; if ($c === '\\') { $buf .= $n; $i++; } elseif ($c === $inStr) { $inStr = ''; } continue; }
    if ($c === '/' && $n === '*') { if ($depth === 0 && trim($buf) !== '') { $chunks[] = $buf; $buf = ''; } $buf .= '/*'; $i++; $inComment = true; continue; }
    if ($c === '"' || $c === "'") { $inStr = $c; $buf .= $c; continue; }
    $buf .= $c;
    if ($c === '{') { $depth++; }
    elseif ($c === '}') { $depth--; if ($depth === 0) { $chunks[] = $buf; $buf = ''; } }
    elseif ($c === ';' && $depth === 0) { $chunks[] = $buf; $buf = ''; }
}
if (trim($buf) !== '') { $chunks[] = $buf; }

$splitSelectors = static function (string $list): array {
    $parts = []; $cur = ''; $paren = 0; $br = 0;
    for ($i = 0, $l = strlen($list); $i < $l; $i++) {
        $c = $list[$i];
        if ($c === '(') { $paren++; } elseif ($c === ')') { $paren--; } elseif ($c === '[') { $br++; } elseif ($c === ']') { $br--; }
        if ($c === ',' && $paren === 0 && $br === 0) { $parts[] = $cur; $cur = ''; continue; }
        $cur .= $c;
    }
    $parts[] = $cur;
    return $parts;
};
$stats = ['rules' => 0, 'removed_rules' => 0, 'trimmed_rules' => 0];
$prune = function (string $chunk, callable $self) use ($splitSelectors, $isUsed, &$stats): string {
    $trim = ltrim($chunk);
    if ($trim === '' || str_starts_with($trim, '/*')) { return $chunk; }
    $open = strpos($chunk, '{');
    if ($open === false) { return $chunk; }
    $head = substr($chunk, 0, $open); $body = substr($chunk, $open + 1, strrpos($chunk, '}') - $open - 1);
    $h = trim($head);
    if (str_starts_with($h, '@')) {
        if (preg_match('/^@(media|supports)\b/i', $h)) {
            // nested rules: re-parse the body with the same logic
            $inner = ''; $depth = 0; $b = ''; $inStr = '';
            for ($i = 0, $l = strlen($body); $i < $l; $i++) {
                $c = $body[$i]; $b .= $c;
                if ($inStr !== '') { if ($c === $inStr) { $inStr = ''; } continue; }
                if ($c === '"' || $c === "'") { $inStr = $c; continue; }
                if ($c === '{') { $depth++; } elseif ($c === '}') { $depth--; if ($depth === 0) { $inner .= $self($b, $self); $b = ''; } }
            }
            $inner .= $b;
            return trim($inner) === '' ? '' : $head . '{' . $inner . '}';
        }
        return $chunk; // @font-face, @keyframes, ...
    }
    if (str_starts_with($h, ':root')) { return $chunk; }
    $stats['rules']++;
    $keep = [];
    foreach ($splitSelectors($h) as $sel) {
        $dead = false;
        // ignore comments, attribute selectors, strings and negations: an unused class inside :not() does not make a selector dead
        $clean = preg_replace('/\/\*.*?\*\//s', '', $sel);
        $clean = preg_replace('/:not\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)/', '', (string)$clean);
        $clean = preg_replace('/\[[^\]]*\]|"[^"]*"|\'[^\']*\'/', '', (string)$clean);
        if (preg_match_all('/[.#]([A-Za-z_][\w-]*)/', (string)$clean, $m)) {
            foreach ($m[1] as $token) { if (!$isUsed($token)) { $dead = true; break; } }
        }
        if (!$dead) { $keep[] = $sel; }
    }
    $total = count($splitSelectors($h));
    if (!$keep) { $stats['removed_rules']++; return ''; }
    if (count($keep) < $total) { $stats['trimmed_rules']++; return implode(',', array_map('trim', $keep)) . ' {' . $body . '}'; }
    return $chunk;
};
$result = '';
foreach ($chunks as $ch) { $result .= $prune($ch, $prune); }
file_put_contents($out, preg_replace("/\n{3,}/", "\n\n", $result));
printf("in: %d bytes, %d lines | out: %d bytes, %d lines | rules %d, removed %d, trimmed %d\n", strlen($css), substr_count($css, "\n") + 1, strlen($result), substr_count($result, "\n") + 1, $stats['rules'], $stats['removed_rules'], $stats['trimmed_rules']);
