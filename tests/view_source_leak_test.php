<?php
declare(strict_types=1);

/**
 * LS-89: no view prints PHP source to the visitor. The admin review detail page showed about sixty lines of its own code for two weeks
 * because an opening tag was lost during a layout change; the file still passed the syntax check. This reads the text outside PHP tags
 * (scripts and styles left out) of every view and refuses anything that looks like PHP.
 */
function ok_vsl(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$root = dirname(__DIR__);
$looksLikePhp = '/\$[A-Za-z_]\w*\s*(?:=[^=]|->|\?\?|\[\'|\)\s*\{)|new\s+\\\\?App\\\\|->\w+\(|<\?(?!xml)/';
$findings = [];
$scanned = 0;
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app/Views', FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if ($file->getExtension() !== 'php') { continue; }
    $scanned++;
    $html = '';
    foreach (PhpToken::tokenize((string)file_get_contents($file->getPathname())) as $token) {
        $html .= $token->is(T_INLINE_HTML) ? $token->text : ' ';
    }
    $html = (string)preg_replace('~<(script|style)\b.*?</\1>~is', ' ', $html);
    if (preg_match($looksLikePhp, $html, $m, PREG_OFFSET_CAPTURE)) {
        $findings[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1)) . ': ' . trim(substr($html, max(0, $m[0][1] - 20), 70));
    }
}
ok_vsl($scanned > 50, "views were scanned ($scanned)");
ok_vsl((bool)preg_match($looksLikePhp, "= new App\\Services\\Auth\\AuthorizationService();\n\$canCorrect = 1;"), 'the pattern catches the case that happened');
ok_vsl($findings === [], "PHP source outside PHP tags:\n  " . implode("\n  ", $findings));
echo "view_source_leak_test: PASS ($scanned views)\n";
