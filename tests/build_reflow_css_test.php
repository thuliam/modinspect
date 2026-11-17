<?php
declare(strict_types=1);

/**
 * LS-7 N-1: /build overflowed by 5 px at 320 px wide (WCAG reflow). A grid column written as a bare 1fr cannot shrink below the width of an unbreakable
 * part name; minmax(0,1fr) can. The other .part-option rules already use minmax(0,1fr); every rule that sets its columns must.
 */
function ok_br(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
$css = (string)file_get_contents(dirname(__DIR__) . '/public/assets/app.css');
preg_match_all('/\.part-option\{[^}]*grid-template-columns:([^;}]*)/', $css, $m);
ok_br(count($m[1]) >= 2, 'The .part-option grid rules are found (' . count($m[1]) . ')');
foreach ($m[1] as $cols) { ok_br(preg_match('/(^|\s)1fr(\s|$)/', trim($cols)) !== 1, 'A bare 1fr column cannot shrink on a 320 px screen: ' . trim($cols)); }
echo "build_reflow_css_test: PASS\n";
