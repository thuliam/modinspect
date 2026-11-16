<?php
declare(strict_types=1);

/**
 * Removes CSS rules that a browser run proved redundant (removing the rule changed no computed style of any element it matched,
 * on every measured page and width). Rules are addressed by their CSSOM index path plus a hash of the normalised selector, so a
 * misaligned file aborts instead of deleting the wrong rule.
 *
 *   php scripts/remove_shadowed_css.php --in=public/assets/mi-theme.css --out=public/assets/mi-theme.new.css --total=474 --ids="2:320bb896,10.0:945486f3"
 *
 * Pseudo-state rules (:hover, :focus, ::before ...) and rules that matched no element were never measured and are never listed.
 */
$o = getopt('', ['in:', 'out:', 'total:', 'ids:']);
$in = (string)($o['in'] ?? ''); $out = (string)($o['out'] ?? ''); $total = (int)($o['total'] ?? 0); $idsArg = (string)($o['ids'] ?? '');
if ($in === '' || $out === '' || $total <= 0 || $idsArg === '') { fwrite(STDERR, "Usage: --in --out --total --ids\n"); exit(2); }

$hash = static function (string $selector): string {
    $s = strtolower((string)preg_replace('/[\s"\']/u', '', $selector));
    $x = 5381;
    foreach (unpack('n*', mb_convert_encoding($s, 'UTF-16BE', 'UTF-8')) ?: [] as $c) { $x = (($x * 33) & 0xFFFFFFFF) ^ $c; }
    return dechex($x);
};
$chunkify = static function (string $css): array {
    $len = strlen($css); $chunks = []; $buf = ''; $depth = 0; $str = ''; $com = false;
    for ($i = 0; $i < $len; $i++) {
        $c = $css[$i]; $n = $css[$i + 1] ?? '';
        if ($com) { $buf .= $c; if ($c === '*' && $n === '/') { $buf .= '/'; $i++; $com = false; if ($depth === 0) { $chunks[] = $buf; $buf = ''; } } continue; }
        if ($str !== '') { $buf .= $c; if ($c === '\\') { $buf .= $n; $i++; } elseif ($c === $str) { $str = ''; } continue; }
        if ($c === '/' && $n === '*') { if ($depth === 0 && trim($buf) !== '') { $chunks[] = $buf; $buf = ''; } $buf .= '/*'; $i++; $com = true; continue; }
        if ($c === '"' || $c === "'") { $str = $c; $buf .= $c; continue; }
        $buf .= $c;
        if ($c === '{') { $depth++; } elseif ($c === '}') { $depth--; if ($depth === 0) { $chunks[] = $buf; $buf = ''; } } elseif ($c === ';' && $depth === 0) { $chunks[] = $buf; $buf = ''; }
    }
    if (trim($buf) !== '') { $chunks[] = $buf; }
    return $chunks;
};
$isComment = static fn(string $c): bool => str_starts_with(ltrim($c), '/*');
$head = static function (string $chunk): string {
    $h = substr($chunk, 0, (int)strpos($chunk, '{'));
    return trim((string)preg_replace('#/\*.*?\*/#s', '', $h));
};

$css = (string)file_get_contents($in);
$css = (string)preg_replace('/^\xEF\xBB\xBF/', '', $css);
$chunks = $chunkify($css);
// rule chunks in CSSOM order (comments are not rules; @import/@charset statements without a block are rules too but absent here)
$ruleIdx = [];
foreach ($chunks as $i => $c) { if (!$isComment($c) && strpos($c, '{') !== false) { $ruleIdx[] = $i; } }
if (count($ruleIdx) !== $total) { fwrite(STDERR, "Alignment check failed: file has " . count($ruleIdx) . " rules, browser reported $total\n"); exit(1); }

$remove = [];   // top-level chunk index => true, or [chunk index => [nested index => true]]
$nested = [];
foreach (explode(',', $idsArg) as $pair) {
    [$id, $h] = explode(':', $pair);
    $path = array_map('intval', explode('.', $id));
    $chunk = $chunks[$ruleIdx[$path[0]]];
    if (count($path) === 1) {
        if ($hash($head($chunk)) !== $h && !str_starts_with($head($chunk), '@')) { fwrite(STDERR, "Selector hash mismatch for rule $id\n"); exit(1); }
        if (!str_starts_with($head($chunk), '@')) { $remove[$ruleIdx[$path[0]]] = true; }
    } else {
        $nested[$ruleIdx[$path[0]]][$path[1]] = $h;
    }
}
$removedTop = count($remove); $removedNested = 0;
foreach ($nested as $ci => $inner) {
    $chunk = $chunks[$ci]; $open = strpos($chunk, '{'); $close = strrpos($chunk, '}');
    $body = substr($chunk, $open + 1, $close - $open - 1);
    $parts = $chunkify($body);
    $rules = [];
    foreach ($parts as $pi => $p) { if (!$isComment($p) && strpos($p, '{') !== false) { $rules[] = $pi; } }
    $keep = '';
    foreach ($parts as $pi => $p) {
        $ri = array_search($pi, $rules, true);
        if ($ri !== false && isset($inner[$ri])) {
            if ($hash($head($p)) !== $inner[$ri]) { fwrite(STDERR, "Selector hash mismatch for nested rule $ci.$ri\n"); exit(1); }
            $removedNested++; continue;
        }
        $keep .= $p;
    }
    $chunks[$ci] = substr($chunk, 0, $open + 1) . $keep . '}';
    if (trim($keep) === '') { $remove[$ci] = true; }
}
$result = '';
foreach ($chunks as $i => $c) { if (!isset($remove[$i])) { $result .= $c; } }
file_put_contents($out, preg_replace("/\n{3,}/", "\n\n", $result));
printf("rules in file: %d | removed top-level: %d, nested: %d | %d -> %d bytes\n", $total, $removedTop, $removedNested, strlen($css), strlen($result));
