<?php
declare(strict_types=1);

/**
 * LS-89: every class named in app/, cli/ and public/ exists. PHP only notices a missing import when the line runs (or, in a catch, never),
 * so a method moved between files can keep a name that no longer resolves and still pass the syntax check and most tests.
 */
require __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$problems = [];
$checked = 0;
foreach (['app', 'cli', 'public', 'config'] as $dir) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') { continue; }
        $tokens = array_values(array_filter(PhpToken::tokenize((string)file_get_contents($file->getPathname())), static fn(PhpToken $t): bool => !$t->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])));
        $namespace = '';
        $uses = [];
        $declared = [];
        $depth = 0;
        foreach ($tokens as $i => $t) {
            if ($t->text === '{') { $depth++; } elseif ($t->text === '}') { $depth--; }
            if ($t->is(T_NAMESPACE)) { $namespace = $tokens[$i + 1]->text; }
            if ($t->is(T_USE) && $depth === 0 && $tokens[$i + 1]->text !== '(') {
                $name = ltrim($tokens[$i + 1]->text, '\\');
                $alias = isset($tokens[$i + 2]) && $tokens[$i + 2]->is(T_AS) ? $tokens[$i + 3]->text : substr('\\' . $name, strrpos('\\' . $name, '\\') + 1);
                $uses[strtolower($alias)] = $name;
            }
            if ($t->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM]) && isset($tokens[$i + 1]) && $tokens[$i + 1]->is(T_STRING)) { $declared[strtolower($tokens[$i + 1]->text)] = true; }
        }
        foreach ($tokens as $i => $t) {
            if (!$t->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) { continue; }
            $before = $tokens[$i - 1] ?? null;
            $after = $tokens[$i + 1] ?? null;
            $isClass = ($before !== null && $before->is([T_NEW, T_INSTANCEOF, T_EXTENDS, T_IMPLEMENTS]))
                || ($after !== null && $after->is(T_DOUBLE_COLON))
                || ($before !== null && $before->text === '(' && isset($tokens[$i - 2]) && $tokens[$i - 2]->is(T_CATCH))
                || ($before !== null && $before->text === '|' && $after !== null && ($after->is(T_VARIABLE) || $after->text === '|' || $after->text === ')') && !($tokens[$i - 2]->is(T_VARIABLE)));
            if (!$isClass || in_array(strtolower($t->text), ['self', 'static', 'parent', 'int', 'string', 'float', 'bool', 'array', 'null', 'false', 'true', 'mixed', 'void', 'never', 'object', 'iterable', 'callable'], true) || ($before !== null && $before->is([T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NULLSAFE_OBJECT_OPERATOR]))) { continue; }
            if ($t->is(T_NAME_FULLY_QUALIFIED)) {
                $full = ltrim($t->text, '\\');
            } else {
                $parts = explode('\\', $t->text, 2);
                if (isset($uses[strtolower($parts[0])])) { $full = $uses[strtolower($parts[0])] . (isset($parts[1]) ? '\\' . $parts[1] : ''); }
                elseif (isset($declared[strtolower($t->text)])) { continue; }
                else { $full = ($namespace === '' ? '' : $namespace . '\\') . $t->text; }
            }
            // names that are constants, not classes, in a union such as FLAG_A | FLAG_B
            if (preg_match('/^[A-Z0-9_]+$/', $t->text) && $before !== null && $before->text === '|') { continue; }
            $checked++;
            if ($full === 'ZipArchive') { continue; }   // optional extension; cli/intake_validate.php checks for it
            if (!class_exists($full) && !interface_exists($full) && !trait_exists($full) && !isset($declared[strtolower($t->text)])) {
                $problems[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1)) . ':' . $t->line . ' ' . $full;
            }
        }
    }
}
if ($checked < 500) { throw new RuntimeException("too few class names were read ($checked)"); }
if ($problems !== []) { throw new RuntimeException("Class names that do not resolve:\n  " . implode("\n  ", array_values(array_unique($problems)))); }
echo "class_reference_test: PASS ($checked references)\n";
