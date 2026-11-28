<?php
declare(strict_types=1);

/**
 * Rebuild monthly price-trend buckets by observed_at from the same rows a price snapshot accepts (LS-89: App\Services\Pricing\CohortRules).
 * A bucket needs at least 3 samples; quartiles use linear interpolation. Idempotent: rebuilds the table content.
 * Usage: php cli/price_trend_build.php [--min=3] [--dry-run]
 * --dry-run writes nothing and counts how the stored months would change.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Pricing\TrendBuilder;

$min = TrendBuilder::MIN_SAMPLE;
foreach (array_slice($argv, 1) as $a) { if (str_starts_with($a, '--min=')) { $min = max(2, (int)substr($a, 6)); } }
global $config;
$trend = new TrendBuilder(Database::connection($config['db']));

if (in_array('--dry-run', $argv, true)) {
    $d = $trend->diff($min);
    foreach ($d['changed'] as $k => $v) { echo "changed product_period=$k median {$v['from'][0]} -> {$v['to'][0]} samples {$v['from'][1]} -> {$v['to'][1]}\n"; }
    foreach ($d['removed'] as $k => $v) { echo "removed product_period=$k median {$v[0]} samples {$v[1]}\n"; }
    echo "DRY RUN (nothing written): stored={$d['stored']} rebuilt={$d['rebuilt']} changed=" . count($d['changed']) . ' added=' . count($d['added']) . ' removed=' . count($d['removed']) . "\n";
    exit(0);
}

$result = $trend->rebuild(null, $min);
echo "trend points={$result['points']} products={$result['products']} (min samples per month=$min)\n";
