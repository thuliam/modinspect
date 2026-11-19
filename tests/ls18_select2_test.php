<?php
declare(strict_types=1);

/**
 * LS-18 (owner request 2026-11-19): every dropdown uses Select2 4.0.13, self-hosted and pinned; Compare only offers the category of the first chosen model.
 * Static checks (node is not part of the toolchain); the behaviour was checked in a browser (public 1440/375 px, Admin filters, /build filters).
 */
function ok_s2(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }
$root = dirname(__DIR__);
$read = static fn(string $p): string => (string)file_get_contents($root . '/' . $p);

// the library: official files only, pinned, licensed, no CDN
foreach (['select2.min.js', 'select2.min.css', 'th.js', 'LICENSE.md'] as $f) { ok_s2(is_file("$root/public/assets/admin/vendor/select2/$f"), "select2/$f is present"); }
$manifest = json_decode($read('public/assets/admin/vendor/MANIFEST.json'), true);
ok_s2(($manifest['components']['select2']['version'] ?? '') === '4.0.13' && ($manifest['components']['select2']['provenance'] ?? '') === 'official-npm-verified', 'Select2 is pinned in the vendor manifest');
ok_s2(str_contains($read('docs/THIRD_PARTY_LICENSES.md'), '## Select2 4.0.13'), 'The licence is recorded');

// both layouts load it after jQuery, in the right order, and the glue opts nothing in by hand
foreach (['app/Views/layouts/footer.php', 'app/Views/layouts/admin_footer.php'] as $f) {
    ok_s2(preg_match('#jquery-3\.7\.1\.min\.js.*select2/select2\.min\.js.*select2/th\.js.*mi-select\.js#s', $read($f)) === 1, "$f loads jQuery, Select2, the Thai strings, then the glue");
    ok_s2(!preg_match('#<(script|link)[^>]+(src|href)="https?://#i', $read($f)), "$f loads no script or style from another host");
}
foreach (['app/Views/layouts/header.php', 'app/Views/layouts/admin_header.php'] as $f) { ok_s2(str_contains($read($f), 'select2/select2.min.css') && str_contains($read($f), 'mi-select2.css'), "$f loads the Select2 styles"); }
ok_s2(preg_match('#mi-select\.js[^\n]*defer.*site\.js[^\n]*defer#s', $read('app/Views/layouts/footer.php')) === 1, 'On public pages the glue runs before site.js (Compare uses its options)');
$glue = $read('public/assets/mi-select.js');
ok_s2(str_contains($glue, "find('select').not('[data-native], .js-select2-manual, .dataTables_length select, .select2-hidden-accessible')"), 'Every select is enhanced except opt-outs, manual ones and the DataTables page-size control');
ok_s2(str_contains($glue, "language: 'th'") && !preg_match('#https?://#', $glue), 'Thai strings, no network');
ok_s2(!preg_match('/#[0-9a-f]{3,6}\b/i', preg_replace('#var\([^)]*\)#', '', $read('public/assets/mi-select2.css')) ?? ''), 'The Select2 theme uses the CI tokens, no hard-coded colours (fallbacks inside var() only)');

// Compare: grouped selects with the category on every option, locked by site.js; the server still accepts typed names
$cmp = $read('app/Views/modules/compare.php');
ok_s2(str_contains($cmp, 'js-select2-manual js-compare') && str_contains($cmp, 'data-cat="<?= htmlspecialchars($cat) ?>"') && str_contains($cmp, '<optgroup label="<?= htmlspecialchars($cat) ?>">'), 'Compare boxes are grouped selects with the category on each option');
$site = $read('public/assets/site.js');
ok_s2(str_contains($site, "if (locked && lockCat && data.children && data.text !== lockCat) { return null; }"), 'Boxes after the first chosen model only list its category');
ok_s2(str_contains($site, "\$s.val('').trigger('change.select2')") && str_contains($site, '$.inArray(c.id, taken) < 0'), 'A box of another category is cleared, and a model chosen elsewhere is not offered twice');
ok_s2(str_contains($read('public/assets/spec-builder.js'), ".trigger('change.select2');"), '/build redraws the brand box after it rebuilds the options');
echo "ls18_select2_test: PASS\n";
