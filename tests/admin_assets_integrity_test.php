<?php
declare(strict_types=1);

/**
 * Guards the admin UI against the failures found in the Sprint 0 audit:
 *  - vendor assets only present on one developer machine (ignored by git, no pinned hashes)
 *  - official DataTables silently replaced by a hand-written adapter
 *  - DataTables init options overridden by <table data-*> attributes (data-ajax broke the filters)
 */

function assert_assets(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);

// 1. Manifest verification (hashes, layout references, official DataTables).
$output = [];
$exitCode = 0;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/cli/admin_vendor_manifest.php') . ' --verify 2>&1', $output, $exitCode);
assert_assets($exitCode === 0, "Vendor manifest verification failed:\n" . implode("\n", $output));

// 2. Server-side tables must not carry data-* attributes that DataTables reads as init options.
$conflicting = ['data-ajax', 'data-server-side', 'data-processing', 'data-ordering', 'data-searching', 'data-order'];
$tableCount = 0;
foreach (glob($root . '/app/Views/admin/*.php') ?: [] as $view) {
    $source = (string)file_get_contents($view);
    if (!preg_match_all('/<table\b[^>]*js-server-data-table[^>]*>/', $source, $tables)) {
        continue;
    }
    foreach ($tables[0] as $tag) {
        $tableCount++;
        assert_assets(str_contains($tag, 'data-source-url="'), basename($view) . ': server table needs data-source-url');
        foreach ($conflicting as $attribute) {
            assert_assets(!preg_match('/\s' . preg_quote($attribute, '/') . '=/', $tag), basename($view) . ": {$attribute} on a DataTables element overrides the init options");
        }
    }
}
assert_assets($tableCount >= 9, "Expected at least 9 server-side admin tables, found {$tableCount}");

// 2b. Every Font Awesome icon class used by a view must exist in the bundled FA version (FA 5.0.9 lacks e.g. fa-file-csv
// and fa-search-dollar, which render as blank boxes).
$faCss = (string)file_get_contents($root . '/public/assets/admin/vendor/fontawesome/css/fontawesome-all.css');
$missingIcons = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app/Views', FilesystemIterator::SKIP_DOTS)) as $viewFile) {
    if (!$viewFile->isFile() || $viewFile->getExtension() !== 'php') {
        continue;
    }
    // icons named as data (e.g. the admin sidebar array 'icon' => 'fa-rss') as well as in class attributes
    $source = (string)file_get_contents($viewFile->getPathname());
    preg_match_all('/\bfa[sbrl]?\s+(fa-[a-z0-9-]+)|\'icon\'\s*=>\s*\'(fa-[a-z0-9-]+)\'/', $source, $iconMatches, PREG_SET_ORDER);
    $icons = [1 => array_values(array_filter(array_map(static fn($m) => $m[2] ?? $m[1] ?? '', $iconMatches)))];
    if ($icons[1]) {
        foreach (array_unique($icons[1]) as $icon) {
            if (!preg_match('/\.' . preg_quote($icon, '/') . '[\s,{:]/', $faCss)) {
                $missingIcons[$icon] = basename($viewFile->getPathname());
            }
        }
    }
}
assert_assets($missingIcons === [], 'Icons missing from bundled Font Awesome: ' . json_encode($missingIcons));

// 3. admin.js must merge filters into DataTables' own request parameters instead of replacing them.
$adminJs = (string)file_get_contents($root . '/public/assets/admin/js/admin.js');
assert_assets(str_contains($adminJs, "data('source-url')"), 'admin.js must read the table source from data-source-url');
assert_assets(str_contains($adminJs, '$.extend(d, activeFilters($table))'), 'admin.js ajax.data must extend the DataTables request (a returned object replaces it)');
assert_assets(!str_contains($adminJs, 'miniDataTable'), 'admin.js must not depend on the removed custom adapter');

echo "Admin assets integrity test passed\n";
echo "Server-side admin tables checked: {$tableCount}\n";
