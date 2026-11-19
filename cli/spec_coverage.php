<?php
declare(strict_types=1);

/**
 * READ-ONLY spec coverage report (LS-22 B1). Writes nothing. For each category that has price-index filters it shows how many active models each filter
 * can match and lists the models with no value (those never match a filter: nothing is guessed). Use it to see where the catalog needs a spec.
 *
 *   MODINSPECT_ENV_FILE=.env.testing php cli/spec_coverage.php          (text)
 *   MODINSPECT_ENV_FILE=.env.testing php cli/spec_coverage.php --json   (machine-readable)
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Models\Product;
use App\Services\Catalog\SpecFilter;

$json = in_array('--json', $argv, true);
$model = new Product();
$products = $model->search('', null);
$specMap = $model->specMap();

$report = [];
foreach (SpecFilter::BY_CATEGORY as $category => $keys) {
    $inCat = array_values(array_filter($products, static fn(array $p): bool => ($p['category_slug'] ?? '') === $category));
    $row = ['category' => $category, 'models' => count($inCat), 'filters' => []];
    foreach ($keys as $key) {
        $have = 0; $missing = [];
        foreach ($inCat as $p) {
            if (isset(SpecFilter::attrs($p, $specMap[(int)$p['id']] ?? [])[$key])) { $have++; } else { $missing[] = (string)$p['full_name']; }
        }
        $row['filters'][$key] = ['label' => SpecFilter::LABELS[$key] ?? $key, 'covered' => $have, 'missing' => $missing];
    }
    $report[] = $row;
}

if ($json) { echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n"; exit(0); }
foreach ($report as $r) {
    echo "== {$r['category']} ({$r['models']} models)\n";
    foreach ($r['filters'] as $key => $f) {
        $pct = $r['models'] > 0 ? (int)round($f['covered'] * 100 / $r['models']) : 0;
        echo sprintf("  %-8s %3d/%-3d %3d%%  %s\n", $key, $f['covered'], $r['models'], $pct, $f['label']);
        foreach (array_slice($f['missing'], 0, 8) as $name) { echo "      - no value: $name\n"; }
        if (count($f['missing']) > 8) { echo '      ... and ' . (count($f['missing']) - 8) . " more\n"; }
    }
}
exit(0);
