<?php
declare(strict_types=1);

/**
 * Rebuild monthly price-trend buckets from approved, single-item, non-defective asking observations (by observed_at).
 * A bucket needs at least 3 samples; quartiles use linear interpolation. Idempotent: rebuilds the table content per product.
 * Usage: php cli/price_trend_build.php [--min=3]
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

$min = 3;
foreach (array_slice($argv, 1) as $a) { if (str_starts_with($a, '--min=')) { $min = max(2, (int)substr($a, 6)); } }
global $config;
$db = Database::connection($config['db']);

function quantile(array $s, float $p): float {
    $n = count($s); if ($n === 1) { return $s[0]; }
    $pos = ($n - 1) * $p; $lo = (int)floor($pos); $hi = (int)ceil($pos);
    return $s[$lo] + ($s[$hi] - $s[$lo]) * ($pos - $lo);
}

$rows = $db->query("SELECT product_id, DATE_FORMAT(observed_at,'%Y-%m-01') period, COALESCE(price_value,asking_price) price
    FROM price_observations
    WHERE verified_status='approved' AND price_type='asking' AND listing_type='single_item'
      AND is_defective=0 AND is_duplicate=0 AND COALESCE(price_value,asking_price) > 0
    ORDER BY product_id, period")->fetchAll();
$groups = [];
foreach ($rows as $r) { $groups[(int)$r['product_id']][$r['period']][] = (float)$r['price']; }

$db->beginTransaction();
$db->exec('DELETE FROM price_trend_points');
$ins = $db->prepare('INSERT INTO price_trend_points (product_id,period_start,period_type,q1,median,q3,low_price,high_price,sample_size) VALUES (?,?,\'month\',?,?,?,?,?,?)');
$points = 0; $products = 0;
foreach ($groups as $pid => $periods) {
    $kept = 0;
    foreach ($periods as $period => $prices) {
        if (count($prices) < $min) { continue; }
        sort($prices);
        $ins->execute([$pid, $period, round(quantile($prices, .25), 2), round(quantile($prices, .5), 2), round(quantile($prices, .75), 2), min($prices), max($prices), count($prices)]);
        $points++; $kept++;
    }
    if ($kept >= 1) { $products++; }
}
$db->commit();
echo "trend points=$points products=$products (min samples per month=$min)\n";
