<?php
declare(strict_types=1);

/**
 * LS-9 C-6: jQuery stays at a version that has the fixes for CVE-2019-11358 (3.4.0), CVE-2020-11022 and CVE-2020-11023 (3.5.0). Every layout that loads
 * jQuery must point at a file that exists, the file's own banner must name the version the manifest records, and the manifest hash must match the file.
 * The generic vendor guard (cli/admin_vendor_manifest.php --verify) checks hashes; this adds the minimum-version rule so a downgrade is a visible failure.
 */
function ok_jq(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

$root = dirname(__DIR__);
$manifest = json_decode((string)file_get_contents($root . '/public/assets/admin/vendor/MANIFEST.json'), true);
$jq = $manifest['components']['jquery'] ?? null;
ok_jq(is_array($jq) && isset($jq['version'], $jq['files']), 'The manifest has a jquery component');
ok_jq(version_compare((string)$jq['version'], '3.5.0', '>='), 'jQuery must be 3.5.0 or newer (advisory fixes); manifest says ' . $jq['version']);
ok_jq(count($jq['files']) === 1, 'Exactly one jQuery file is pinned');
$rel = (string)array_key_first($jq['files']);
$file = $root . '/public/assets/admin/vendor/' . $rel;
ok_jq(is_file($file), "The pinned jQuery file exists: $rel");
ok_jq(hash_file('sha256', $file) === $jq['files'][$rel], 'The pinned hash matches the file');
$head = (string)file_get_contents($file, false, null, 0, 200);
ok_jq(preg_match('#^/\*! jQuery v(\d+\.\d+\.\d+)#', $head, $m) === 1 && $m[1] === $jq['version'], 'The file banner names the manifest version: ' . substr($head, 0, 40));
ok_jq(str_contains($rel, $jq['version']), 'The file name carries the version, so a browser never serves a stale copy');

$layouts = glob($root . '/app/Views/layouts/*.php') ?: [];
$seen = 0;
foreach ($layouts as $l) {
    foreach (preg_split('/\R/', (string)file_get_contents($l)) as $line) {
        if (preg_match('#vendor/(jquery/[^"\']+\.js)#', $line, $mm) === 1) {
            $seen++;
            ok_jq($mm[1] === $rel, basename($l) . " loads $mm[1], not the pinned $rel");
        }
    }
}
ok_jq($seen >= 3, "Public, admin and sign-in layouts all load jQuery ($seen found)");
echo "jquery_version_guard_test: PASS\n";
