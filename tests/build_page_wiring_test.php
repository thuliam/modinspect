<?php
declare(strict_types=1);

/**
 * LS-15 guards for the Build page files (static, because node is not part of the toolchain):
 *  - the script only displays: no compatibility rule (socket, DDR, wattage, form factor, cooler) may appear in it, they live in PHP and are tested there;
 *  - animation respects the site's calm-motion setting; nothing is loaded from a CDN; the preview address comes from the server;
 *  - every value echoed into the page or the summary is escaped or cast (XSS), checked line by line against a short allow list.
 */
function ok_pw(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
$root = dirname(__DIR__);
$js = (string)file_get_contents($root . '/public/assets/spec-builder.js');
$view = (string)file_get_contents($root . '/app/Views/modules/build.php');
$sum = (string)file_get_contents($root . '/app/Views/components/build-summary.php');

// the script
ok_pw(preg_match('/socket|ddr\d?|watt|form[_ ]?factor|cooler|tdp/i', preg_replace('#/\*.*?\*/#s', '', preg_replace('#//[^\n]*#', '', $js)) ?? '') === 0, 'The script contains no compatibility rule (checked without comments)');
ok_pw(str_contains($js, "hasClass('motion-calm')") && str_contains($js, '$.fx.off = true'), 'Animations are switched off by the site calm-motion setting');
ok_pw(!preg_match('#https?://#i', $js), 'The script loads nothing from another host');
ok_pw(str_contains($js, 'cfg.previewUrl') && str_contains($js, "'_token'") === false && str_contains($js, 'input[name="_token"]'), 'The script posts to the server-given address and reads the CSRF token from the form');
ok_pw(substr_count($js, 'if (my !== seq)') === 2, 'An older preview answer arriving late is ignored, for a success and for a failure');
ok_pw(substr_count($js, '$.ajax') === 1, 'One request type only: the preview');

// the script is loaded once, by the footer, deferred after jQuery (found in the browser console: a second tag in the page ran before jQuery and threw
// "jQuery is not defined" on every load, while the footer's copy worked); and it does nothing on pages that do not set MI_BUILD
$footer = (string)file_get_contents($root . '/app/Views/layouts/footer.php');
ok_pw(preg_match('#jquery-3\.7\.1\.min\.js.*spec-builder\.js[^\n]*defer#s', $footer) === 1, 'The footer loads jQuery first and then spec-builder.js with defer');
ok_pw(!str_contains($view, 'spec-builder.js'), 'The Build page does not load spec-builder.js a second time (it would run before jQuery)');
ok_pw(preg_match('/var cfg = window\.MI_BUILD \|\| \{\};\s*if \(!cfg\.mode\) \{ return; \}/', $js) === 1, 'The script returns at once on pages that do not set MI_BUILD (before it touches $.fx)');

// the page
foreach (['cpu', 'motherboard', 'gpu', 'ram', 'storage', 'psu', 'case', 'cooling'] as $role) { ok_pw(str_contains($view, "BuildPreview::ROLES"), "The page iterates the eight categories ($role)"); }
ok_pw(substr_count($view, 'h-auto') >= 6, 'Form controls use h-auto (the theme padding clips the fixed Bootstrap height otherwise)');
ok_pw(!str_contains($view, '<main') && str_contains($view, 'id="picker"'), 'The page adds no second main landmark');
ok_pw(str_contains($view, 'name="_token"') && str_contains($view, 'id="selected-inputs"'), 'The form carries the CSRF token and the hidden part inputs');
ok_pw(!preg_match('#https?://#i', preg_replace('#<!--.*?-->#s', '', $view) ?? ''), 'The page loads nothing from another host');

// escaping: every echo tag in the page and the summary must be escaped, cast or built from a trusted constant
$allow = '/htmlspecialchars|number_format|\(int\)|\(float\)|rawurlencode|json_encode|\$base\b|\$mode\b|\$pr\[\'(expected|label|basis)\'\]|\$badge\b|\$basisBadge|\$statusText|\$statusIcon|\$role\b|\$roleIcon|mi_fa\(|\$thumb\(|\$result\[|\$result \? \'|\$count|\$i\b|\\\\App\\\\Core\\\\Csrf::token|\$l\b|\$v\b|\$t\[|\$data\[\'(blocked|chosen_count)|\$k\b|\$cat|\$label|\$data\[\'remaining|\$thumb/';
foreach (['build.php' => $view, 'build-summary.php' => $sum] as $name => $src) {
    preg_match_all('/<\?=\s*(.+?)\s*\?>/s', $src, $mm);
    foreach ($mm[1] as $expr) {
        if (preg_match($allow, $expr)) { continue; }
        throw new RuntimeException("Unescaped output in $name: <?= " . substr($expr, 0, 90) . ' ?>');
    }
}
echo "build_page_wiring_test: PASS\n";
