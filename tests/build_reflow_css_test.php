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

// LS-14 4.4: the saved build's three totals (ต่ำ / คาดการณ์ / สูง) pushed the page to 404 px at 320 px; below 480 px they stack (measured in a browser)
$theme = (string)file_get_contents(dirname(__DIR__) . '/public/assets/mi-theme.css');
ok_br(str_contains($theme, '@media (max-width: 480px) { body.mi .build-totals { grid-template-columns: 1fr; } }'), 'The saved build totals stack on a phone');
// LS-14 4.2: no readable text under 12px on the public pages (measured in a browser on 9 pages; these are the rules that lifted it)
foreach (['body.mi .confidence-badge', 'body.mi .mb-range-scale', 'body.mi .spec-workspace small', 'body.mi .spec-workspace .brand-name'] as $sel) { ok_br(str_contains($theme, $sel), "A 12px text rule exists for $sel"); }
$pos = strpos($theme, 'LS-14 4.2');
ok_br($pos !== false, 'The 4.2 block exists');
$block = substr($theme, (int)$pos, (int)strpos($theme, 'LS-14 4.4', (int)$pos) - (int)$pos);
ok_br(preg_match('/font-size:\s*(?:[0-9]|1[01])px/', $block) === 0 && substr_count($block, 'font-size: 12px') >= 4, 'The 4.2 block sets 12px and nothing smaller');
echo "build_reflow_css_test: PASS\n";
